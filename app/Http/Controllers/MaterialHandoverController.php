<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Log;
use App\Models\MaterialHandover;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\Project;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\StockCost;
use App\Support\StockPicker;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Serah Terima Material — gudang menyerahkan baris stok untuk Material Request
 * Disetujui. Satu langkah: disimpan = stok langsung keluar (tanpa gudang
 * produksi) dan tidak bisa dibatalkan.
 *
 *  - Hanya gudang di branch MR; stok bebas atau milik project MR.
 *  - Stok bebas: dialokasikan ke project dulu (log allocate = biaya project),
 *    lalu keluar (log out). Stok milik project: langsung keluar (biaya sudah
 *    tercatat sebelumnya, tidak dihitung dua kali).
 *  - Barang ber-SN wajib sudah ber-SN; qty per item tidak melebihi sisa MR.
 */
class MaterialHandoverController extends Controller
{
    public function index()
    {
        $visible = MaterialHandover::visibleTo(Auth::user());
        $requestIds = (clone $visible)->select('material_request_id');

        return view('material-handover.index', [
            'projects' => Project::whereIn('id', MaterialRequest::whereIn('id', $requestIds)->select('project_id'))->orderBy('project_number')->get(['id', 'project_number', 'project_name']),
            'branches' => Branch::whereIn('id', MaterialRequest::whereIn('id', $requestIds)->select('branch_id'))->orderBy('name')->get(['id', 'name']),
            'pendingCount' => $this->pendingRequests()->count(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = MaterialHandover::visibleTo(Auth::user())
            ->with(['materialRequest.project:id,project_number,project_name', 'materialRequest.branch:id,name', 'creator', 'items.stock']);

        $recordsTotal = (clone $query)->count();

        $query
            ->when($request->integer('project'), fn ($q, $id) => $q->whereHas('materialRequest', fn ($m) => $m->where('project_id', $id)))
            ->when($request->integer('branch'), fn ($q, $id) => $q->whereHas('materialRequest', fn ($m) => $m->where('branch_id', $id)))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('handover_date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('handover_date', '<=', $date))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()));

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('handover_number', 'like', "%{$search}%")
                ->orWhereHas('materialRequest', fn ($m) => $m->where('request_number', 'like', "%{$search}%"))
                ->orWhereHas('materialRequest.project', fn ($p) => $p->where('project_number', 'like', "%{$search}%")->orWhere('project_name', 'like', "%{$search}%"))
                ->orWhereHas('items.stock', fn ($s) => $s->where('serial_number', 'like', "%{$search}%")));
        }

        $recordsFiltered = (clone $query)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $rows = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->map(fn (MaterialHandover $h, $i) => [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $h->id,
                'handover_number' => $h->handover_number,
                'handover_date' => $h->handover_date?->format('d/m/Y'),
                'request_number' => $h->materialRequest?->request_number,
                'project' => $h->materialRequest?->project ? $h->materialRequest->project->project_number.' — '.$h->materialRequest->project->project_name : '—',
                'branch' => $h->materialRequest?->branch?->name ?? '—',
                'item_count' => $h->items->count(),
                'total_value' => StockCost::format($h->totalValue()),
                'creator' => $h->creator?->display_name ?? '—',
            ]),
        ]);
    }

    public function create(Request $request)
    {
        $requests = $this->pendingRequests();
        $mr = null;
        if ($request->integer('material_request_id')) {
            $mr = $requests->firstWhere('id', $request->integer('material_request_id'));
            abort_unless($mr, 404, 'Material Request tidak ditemukan, belum disetujui, atau sudah diserahkan semua.');
        }

        return view('material-handover.form', [
            'requests' => $requests,
            'mr' => $mr,
            'lines' => $mr ? $this->handoverLines($mr) : collect(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'material_request_id' => 'required|integer|exists:material_requests,id',
            'handover_date' => 'required|date',
            'received_by_name' => 'required|string|max:150',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.material_request_item_id' => 'required|integer',
            'items.*.stock_id' => 'required|integer|distinct',
            'items.*.qty' => 'required|numeric|gt:0',
        ], [
            'items.required' => 'Isi qty minimal satu material yang diserahkan.',
            'received_by_name.required' => 'Isi nama penerima barang.',
            'items.*.stock_id.distinct' => 'Baris stok yang sama tidak boleh dipilih dua kali.',
        ]);

        $mr = MaterialRequest::visibleTo(Auth::user())->with(['project', 'items.masterProduct', 'items.handoverItems'])->findOrFail($validated['material_request_id']);
        if (! $mr->isApproved()) {
            return response()->json(['success' => false, 'message' => 'Material Request belum disetujui atau sudah selesai.'], 422);
        }
        if (! Auth::user()->canAccessBranch($mr->branch_id)) {
            return response()->json(['success' => false, 'message' => 'Material Request bukan milik branch Anda.'], 403);
        }

        $requestItems = $mr->items->keyBy('id');
        foreach (collect($validated['items'])->groupBy('material_request_item_id') as $itemId => $rows) {
            $item = $requestItems->get((int) $itemId);
            if (! $item) {
                return response()->json(['success' => false, 'message' => 'Item bukan bagian dari Material Request ini.'], 422);
            }
            $total = StockService::round($rows->sum(fn ($r) => (float) $r['qty']));
            if ($total - $item->remainingQty() > Stock::EPSILON) {
                return response()->json(['success' => false, 'message' => 'Qty '.$item->masterProduct?->code.' ('.StockCost::qty($total).') melebihi sisa permintaan ('.StockCost::qty($item->remainingQty()).').'], 422);
            }
        }

        $service = app(StockService::class);
        $date = $validated['handover_date'];

        try {
            $handover = DB::transaction(function () use ($validated, $mr, $requestItems, $service, $date) {
                $handover = MaterialHandover::create([
                    'handover_number' => MaterialHandover::generateNumber($date),
                    'material_request_id' => $mr->id,
                    'handover_date' => $date,
                    'received_by_name' => trim($validated['received_by_name']),
                    'note' => $validated['note'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                $stocks = $service->lock(collect($validated['items'])->pluck('stock_id'));
                foreach ($validated['items'] as $row) {
                    $requestItem = $requestItems->get((int) $row['material_request_item_id']);
                    $stock = $stocks->get((int) $row['stock_id']);
                    if (! $stock || (int) $stock->master_product_id !== (int) $requestItem->master_product_id) {
                        throw new StockException('Baris stok yang dipilih bukan produk '.$requestItem->masterProduct?->code.'.');
                    }
                    if ($stock->project_id && (int) $stock->project_id !== (int) $mr->project_id) {
                        throw new StockException($service->label($stock).' milik project lain.');
                    }
                    if ((int) Warehouse::whereKey($stock->warehouse_id)->value('branch_id') !== (int) $mr->branch_id) {
                        throw new StockException($service->label($stock).' tidak berada di gudang branch '.$mr->branch?->name.'.');
                    }
                    if (! $stock->canLeave()) {
                        throw new StockException('Unit '.$service->label($stock).' belum punya SN. Lengkapi SN sebelum diserahkan ke produksi.');
                    }

                    $item = $handover->items()->create([
                        'material_request_item_id' => $requestItem->id,
                        'stock_id' => $stock->id,
                        'qty' => (float) $row['qty'],
                    ]);
                    $log = [
                        'date' => $date,
                        'ref_type' => StockLog::REF_MATERIAL_HANDOVER_ITEM,
                        'ref_id' => $item->id,
                    ];

                    if (! $stock->project_id) {
                        // Stok bebas -> milik project (biaya project), di gudang yang sama.
                        $stock = $service->move($stock, (float) $row['qty'], (int) $stock->warehouse_id, $mr->project_id, StockLog::TYPE_ALLOCATE, $log + [
                            'note' => 'Alokasi ke project '.$mr->project?->project_number.' untuk produksi ('.$handover->handover_number.')',
                        ]);
                        $item->update(['stock_id' => $stock->id]);
                    }
                    $service->take($stock, (float) $row['qty'], StockLog::TYPE_OUT, $log + [
                        'note' => 'Serah terima ke produksi '.$handover->handover_number.' ('.$mr->request_number.')',
                    ]);
                }

                $mr->completeIfFulfilled();

                return $handover;
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Log::record('create_material_handover', 'Serah Terima '.$handover->handover_number.' untuk '.$mr->request_number.' oleh '.Auth::user()->display_name, 'MOD_MATERIAL_HANDOVER', $handover);

        return response()->json(['success' => true, 'message' => 'Serah Terima '.$handover->handover_number.' tersimpan. Material menjadi Material di Produksi.', 'id' => $handover->id]);
    }

    public function show($id)
    {
        $handover = MaterialHandover::visibleTo(Auth::user())
            ->with([
                'materialRequest.project', 'materialRequest.branch', 'materialRequest.creator',
                'materialRequest.items.masterProduct', 'materialRequest.items.handoverItems',
                'creator', 'items.stock.masterProduct', 'items.stock.warehouse', 'items.productionItems.production', 'items.outLog',
            ])
            ->findOrFail($id);
        $mr = $handover->materialRequest;

        return view('material-handover.show', [
            'handover' => $handover,
            // Serah terima lanjutan: MR masih Disetujui & bersisa, user boleh membuat serah terima.
            'canContinue' => $mr && $mr->isApproved() && $mr->remainingQty() > 0
                && \App\Support\ModuleAccess::for(Auth::user())->module('MOD_MATERIAL_HANDOVER')->canCreate(),
        ]);
    }

    /** Bukti serah terima untuk ditandatangani gudang & penerima. */
    public function pdf($id)
    {
        $handover = MaterialHandover::visibleTo(Auth::user())
            ->with(['materialRequest.project', 'materialRequest.branch', 'materialRequest.creator', 'creator', 'items.stock.masterProduct', 'items.stock.warehouse', 'items.requestItem', 'items.outLog'])
            ->findOrFail($id);

        return Pdf::loadView('material-handover.pdf', compact('handover'))
            ->setPaper('a4', 'portrait')
            ->stream('SerahTerima-'.$handover->handover_number.'.pdf');
    }

    /**
     * Kebutuhan per item MR (diminta / sudah diserahkan / sisa) beserta baris stok
     * yang bisa diserahkan dari gudang branch MR (stok bebas atau milik project MR).
     * Urutan stok = urutan ambil otomatis: milik project dulu (tanpa biaya baru),
     * lalu yang paling lama masuk (FIFO). Unit ber-SN tanpa SN tidak ikut.
     */
    private function handoverLines(MaterialRequest $mr)
    {
        $warehouseIds = Warehouse::active()->where('branch_id', $mr->branch_id)->pluck('id')->all();

        return $mr->items->map(function (MaterialRequestItem $item) use ($mr, $warehouseIds) {
            $remaining = $item->remainingQty();
            $rows = $remaining > 0
                ? StockPicker::query(['product_id' => $item->master_product_id, 'free_or_project' => $mr->project_id])
                    ->whereIn('warehouse_id', $warehouseIds ?: [0])
                    ->reorder()
                    ->orderByRaw('CASE WHEN stocks.project_id IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('received_date')
                    ->orderBy('stocks.id')
                    ->get()
                : collect();
            $usable = $rows->filter(fn (Stock $s) => $s->canLeave());

            return [
                'id' => $item->id,
                'code' => $item->masterProduct?->code,
                'name' => $item->masterProduct?->name,
                'unit' => $item->unit,
                'serialized' => $item->masterProduct ? ! $item->masterProduct->isMaterial() : false,
                'requested' => (float) $item->qty,
                'handed' => $item->handedQty(),
                'remaining' => $remaining,
                'note' => $item->note,
                'missing_sn' => $rows->count() - $usable->count(),
                'stocks' => $usable->values()->map(fn (Stock $s) => [
                    'id' => $s->id,
                    'qty' => round((float) ($s->available ?? $s->qty), 4),
                    'warehouse' => $s->warehouse?->code,
                    'serial' => $s->serial_number,
                    'batch' => $s->batch_number,
                    'own' => (bool) $s->project_id,
                ]),
            ];
        })->values();
    }

    /** MR Disetujui yang masih punya sisa, sesuai branch user. */
    private function pendingRequests()
    {
        return MaterialRequest::visibleTo(Auth::user())
            ->where('status', MaterialRequest::STATUS_APPROVED)
            ->with(['project:id,project_number,project_name', 'branch:id,name', 'creator', 'items.masterProduct', 'items.handoverItems'])
            ->orderBy('id')
            ->get()
            ->filter(fn (MaterialRequest $mr) => $mr->remainingQty() > 0)
            ->values();
    }
}
