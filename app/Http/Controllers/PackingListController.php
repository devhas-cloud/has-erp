<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\PackingList;
use App\Models\Project;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\ShipmentLines;
use App\Support\StockPicker;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Packing List — gudang mengemas barang milik project per koli.
 *
 * Satu baris = satu koli: deskripsi (dari item Quotation / bebas), qty order,
 * qty kirim, dimensi, berat; isinya baris stok milik project di gudang PKL
 * (wajib minimal satu). Draft -> Siap Kirim (stok dipesan, terkunci) ->
 * Terkirim (oleh posting Delivery Order). Siap Kirim boleh dibuka kembali
 * selama belum dimuat DO.
 */
class PackingListController extends Controller
{
    public function index()
    {
        return view('packing-list.index', [
            'projects' => Project::whereIn('id', PackingList::select('project_id'))->orderBy('project_number')->get(['id', 'project_number', 'project_name']),
            'warehouses' => Warehouse::whereIn('id', PackingList::select('warehouse_id'))->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = PackingList::with(['project:id,project_number,project_name', 'warehouse:id,code,name', 'deliveryOrder:id,do_number', 'creator', 'boxes']);

        $recordsTotal = (clone $query)->count();

        $query
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('project'), fn ($q, $id) => $q->where('project_id', $id))
            ->when($request->integer('warehouse'), fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('packing_date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('packing_date', '<=', $date))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()));

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('packing_number', 'like', "%{$search}%")
                ->orWhere('recipient_name', 'like', "%{$search}%")
                ->orWhere('customer_ref', 'like', "%{$search}%")
                ->orWhereHas('project', fn ($p) => $p->where('project_number', 'like', "%{$search}%")->orWhere('project_name', 'like', "%{$search}%"))
                ->orWhereHas('boxes', fn ($b) => $b->where('description', 'like', "%{$search}%"))
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
            'data' => $rows->map(fn (PackingList $pl, $i) => [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $pl->id,
                'packing_number' => $pl->packing_number,
                'packing_date' => $pl->packing_date?->format('d/m/Y'),
                'project' => $pl->project ? $pl->project->project_number.' — '.$pl->project->project_name : '—',
                'recipient' => $pl->recipient_name,
                'warehouse' => $pl->warehouse?->code ?? '—',
                'boxes' => $pl->boxes->count(),
                'weight' => \App\Support\StockCost::qty($pl->totalWeight()),
                'do_number' => $pl->deliveryOrder?->do_number,
                'creator' => $pl->creator?->display_name ?? '—',
                'status' => $pl->status,
                'status_badge' => $pl->statusBadgeHtml(),
            ]),
        ]);
    }

    public function create(Request $request)
    {
        return view('packing-list.form', $this->formData(null, $request->integer('project_id') ?: null));
    }

    /**
     * Gudang ber-stok project, default penerima & PO customer, item Quotation.
     */
    public function projectData(Request $request): JsonResponse
    {
        $validated = $request->validate(['project_id' => 'required|integer|exists:projects,id']);
        $project = Project::with('company')->findOrFail($validated['project_id']);

        return response()->json([
            'success' => true,
            'warehouses' => ShipmentLines::projectWarehouses($project->id),
            'defaults' => ShipmentLines::projectDefaults($project),
            'quotation_items' => ShipmentLines::quotationItems($project),
        ]);
    }

    /** Baris stok milik project di satu gudang (isi koli). */
    public function stockOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'q' => 'nullable|string|max:100',
        ]);

        return response()->json(['success' => true, 'data' => StockPicker::options([
            'warehouse_id' => $validated['warehouse_id'],
            'project' => (int) $validated['project_id'],
            'q' => $validated['q'] ?? null,
            'limit' => 300,
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePacking($request);

        $pl = DB::transaction(function () use ($validated) {
            $pl = PackingList::create($this->headerAttributes($validated) + [
                'packing_number' => PackingList::generateNumber($validated['packing_date']),
                'status' => PackingList::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);
            $this->syncBoxes($pl, $validated['boxes'], $validated['units']);

            return $pl;
        });

        Log::record('create_packing_list', 'Packing List '.$pl->packing_number.' dibuat oleh '.Auth::user()->display_name, PackingList::MODULE_CODE, $pl);

        return response()->json(['success' => true, 'message' => 'Packing List '.$pl->packing_number.' disimpan sebagai draft.', 'id' => $pl->id]);
    }

    public function show($id)
    {
        $pl = PackingList::with([
            'project.company', 'warehouse', 'deliveryOrder', 'creator', 'readyBy',
            'boxes.quotationItem', 'boxes.items.stock.masterProduct',
        ])->findOrFail($id);

        return view('packing-list.show', ['pl' => $pl]);
    }

    public function edit($id)
    {
        $pl = PackingList::with(['boxes.items.stock.masterProduct', 'boxes.items.stock.warehouse', 'boxes.items.stock.project'])->findOrFail($id);
        abort_unless($pl->isDraft(), 403, 'Hanya Packing List Draft yang bisa diubah.');

        return view('packing-list.form', $this->formData($pl, $pl->project_id));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $pl = PackingList::findOrFail($id);
        if (! $pl->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Hanya Packing List Draft yang bisa diubah.'], 422);
        }

        $validated = $this->validatePacking($request, $pl->id);

        DB::transaction(function () use ($pl, $validated) {
            $pl->update($this->headerAttributes($validated));
            $pl->boxes()->delete();
            $this->syncBoxes($pl, $validated['boxes'], $validated['units']);
        });

        return response()->json(['success' => true, 'message' => 'Packing List '.$pl->packing_number.' diperbarui.', 'id' => $pl->id]);
    }

    public function destroy($id): JsonResponse
    {
        $pl = PackingList::findOrFail($id);
        if (! $pl->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Hanya Packing List Draft yang bisa dihapus.'], 422);
        }

        $number = $pl->packing_number;
        $pl->delete();

        return response()->json(['success' => true, 'message' => 'Packing List '.$number.' dihapus.']);
    }

    /** Siap Kirim: cek ulang isi koli terhadap stok & pesanan PKL lain, lalu kunci. */
    public function ready($id): JsonResponse
    {
        $pl = PackingList::with('boxes.items')->findOrFail($id);
        if (! $pl->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Packing List ini bukan Draft.'], 422);
        }

        try {
            DB::transaction(function () use ($pl) {
                app(StockService::class)->lock($pl->boxes->flatMap->items->pluck('stock_id'));
                ShipmentLines::assertStocks($this->linesOf($pl), (int) $pl->project_id, (int) $pl->warehouse_id, $pl->id);
                $pl->update(['status' => PackingList::STATUS_READY, 'ready_by' => Auth::id(), 'ready_at' => now()]);
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Log::record('ready_packing_list', 'Packing List '.$pl->packing_number.' Siap Kirim oleh '.Auth::user()->display_name, PackingList::MODULE_CODE, $pl);

        return response()->json(['success' => true, 'message' => 'Packing List Siap Kirim. Barangnya dipesan untuk pengiriman.']);
    }

    /** Buka kembali ke Draft (melepas pesanan) selama belum dimuat DO. */
    public function reopen($id): JsonResponse
    {
        $pl = PackingList::findOrFail($id);
        if (! $pl->isReady()) {
            return response()->json(['success' => false, 'message' => 'Hanya Packing List Siap Kirim yang bisa dibuka kembali.'], 422);
        }
        if ($pl->delivery_order_id) {
            return response()->json(['success' => false, 'message' => 'Packing List sudah dimuat Delivery Order. Keluarkan dari DO dulu.'], 422);
        }

        $pl->update(['status' => PackingList::STATUS_DRAFT, 'ready_by' => null, 'ready_at' => null]);

        return response()->json(['success' => true, 'message' => 'Packing List dibuka kembali menjadi Draft. Pesanan stok dilepas.']);
    }

    public function pdf(Request $request, $id)
    {
        $pl = PackingList::with(['project', 'warehouse', 'boxes.items.stock.masterProduct'])->findOrFail($id);

        return Pdf::loadView('packing-list.pdf', ['pl' => $pl, 'withDetail' => $request->boolean('detail')])
            ->setPaper('a4', 'portrait')
            ->stream('PackingList-'.$pl->packing_number.'.pdf');
    }

    private function formData(?PackingList $pl, ?int $projectId): array
    {
        $projectIds = Stock::open()->whereNotNull('project_id')->distinct()->pluck('project_id');

        return [
            'pl' => $pl,
            'preselectProjectId' => $projectId,
            'projects' => Project::where(fn ($q) => $q->where('status', Project::STATUS_ACTIVE)->whereIn('id', $projectIds))
                ->when($projectId, fn ($q) => $q->orWhere('id', $projectId))
                ->orderByDesc('id')
                ->get(['id', 'project_number', 'project_name']),
            'initialBoxes' => $pl ? $pl->boxes->map(fn ($box) => [
                'quotation_item_id' => $box->quotation_item_id,
                'description' => $box->description,
                'qty_order' => $box->qty_order,
                'qty' => $box->qty,
                'unit' => $box->unit,
                'length_cm' => $box->length_cm,
                'width_cm' => $box->width_cm,
                'height_cm' => $box->height_cm,
                'weight_kg' => $box->weight_kg,
                'stocks' => $box->items->map(fn ($item) => ['stock_id' => $item->stock_id, 'qty' => $item->qty])->values(),
            ])->values() : collect(),
        ];
    }

    private function validatePacking(Request $request, ?int $packingListId = null): array
    {
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'packing_date' => 'required|date',
            'recipient_name' => 'required|string|max:150',
            'recipient_address' => 'nullable|string|max:2000',
            'recipient_phone' => 'nullable|string|max:100',
            'recipient_attn' => 'nullable|string|max:150',
            'customer_ref' => 'nullable|string|max:100',
            'customer_ref_date' => 'nullable|date',
            'marking' => 'nullable|string|max:150',
            'signer_name' => 'nullable|string|max:150',
            'signer_position' => 'nullable|string|max:150',
            'note' => 'nullable|string|max:2000',
            'boxes' => 'required|array|min:1',
            'boxes.*.quotation_item_id' => 'nullable|integer|exists:quotation_items,id',
            'boxes.*.description' => 'required|string|max:5000',
            'boxes.*.qty_order' => 'nullable|numeric|min:0',
            'boxes.*.qty' => 'required|numeric|gt:0',
            'boxes.*.unit' => 'nullable|string|max:50',
            'boxes.*.length_cm' => 'nullable|numeric|min:0',
            'boxes.*.width_cm' => 'nullable|numeric|min:0',
            'boxes.*.height_cm' => 'nullable|numeric|min:0',
            'boxes.*.weight_kg' => 'nullable|numeric|min:0',
            'boxes.*.stocks' => 'required|array|min:1',
            'boxes.*.stocks.*.stock_id' => 'required|integer',
            'boxes.*.stocks.*.qty' => 'required|numeric|gt:0',
        ], [
            'boxes.required' => 'Tambahkan minimal satu koli.',
            'boxes.*.description.required' => 'Deskripsi setiap koli wajib diisi.',
            'boxes.*.stocks.required' => 'Setiap koli wajib berisi minimal satu barang dari stok.',
            'boxes.*.stocks.min' => 'Setiap koli wajib berisi minimal satu barang dari stok.',
        ]);

        try {
            $validated['units'] = ShipmentLines::assertStocks($validated['boxes'], (int) $validated['project_id'], (int) $validated['warehouse_id'], $packingListId);
        } catch (StockException $e) {
            abort(response()->json(['success' => false, 'message' => $e->getMessage()], 422));
        }

        return $validated;
    }

    private function headerAttributes(array $validated): array
    {
        return collect($validated)->only([
            'project_id', 'warehouse_id', 'packing_date', 'recipient_name', 'recipient_address', 'recipient_phone', 'recipient_attn',
            'customer_ref', 'customer_ref_date', 'marking', 'signer_name', 'signer_position', 'note',
        ])->all();
    }

    private function syncBoxes(PackingList $pl, array $boxes, array $units): void
    {
        foreach (array_values($boxes) as $i => $row) {
            $box = $pl->boxes()->create([
                'quotation_item_id' => $row['quotation_item_id'] ?? null,
                'description' => trim($row['description']),
                'qty_order' => isset($row['qty_order']) && $row['qty_order'] !== '' ? StockService::round((float) $row['qty_order']) : null,
                'qty' => StockService::round((float) $row['qty']),
                'unit' => $row['unit'] ?? null,
                'length_cm' => $row['length_cm'] ?? null,
                'width_cm' => $row['width_cm'] ?? null,
                'height_cm' => $row['height_cm'] ?? null,
                'weight_kg' => $row['weight_kg'] ?? null,
                'sort_order' => $i + 1,
            ]);
            foreach ($row['stocks'] as $pick) {
                $box->items()->create([
                    'packing_list_id' => $pl->id,
                    'stock_id' => (int) $pick['stock_id'],
                    'qty' => StockService::round((float) $pick['qty']),
                    'unit' => $units[(int) $pick['stock_id']] ?? null,
                ]);
            }
        }
    }

    /** Koli -> format baris untuk ShipmentLines::assertStocks. */
    private function linesOf(PackingList $pl): array
    {
        return $pl->boxes->map(fn ($box) => [
            'stocks' => $box->items->map(fn ($item) => ['stock_id' => $item->stock_id, 'qty' => $item->qty])->all(),
        ])->all();
    }
}
