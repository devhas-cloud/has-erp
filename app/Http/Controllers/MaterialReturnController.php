<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Log;
use App\Models\MaterialHandoverItem;
use App\Models\MaterialReturn;
use App\Models\Stock;
use App\Models\StockLog;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\ModuleAccess;
use App\Support\StockCost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Retur Material Produksi — produksi mengajukan pengembalian material sisa
 * (belum terpakai) ke gudang asal sebagai stok bebas. Disimpan = Menunggu
 * Approval (stok belum bergerak); approver (can_approve) menyetujui -> stok
 * masuk, atau menolak.
 *
 *  - Sisa = qty serah terima − pemakaian produksi posted − retur disetujui.
 *  - Material boleh retur sebagian; barang ber-SN retur utuh per SN.
 *  - Stok kembali ke gudang asal (warehouse_id baris stok saat diserahkan)
 *    sebagai stok bebas, harga modal saat diserahkan (unit_cost frozen).
 *  - Bila material berasal dari stok bebas (ada log allocate saat serah terima),
 *    alokasinya dilepas: tulis allocate −qty ke project asal. Material yang dulu
 *    milik project tidak dilepas alokasinya (biaya pembelian tidak tersentuh).
 */
class MaterialReturnController extends Controller
{
    public function index()
    {
        $visible = MaterialReturn::visibleTo(Auth::user());

        return view('material-return.index', [
            'branches' => Branch::whereIn('id', (clone $visible)->select('branch_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = MaterialReturn::visibleTo(Auth::user())
            ->with(['branch:id,name', 'creator', 'items.masterProduct:id,code,name', 'items.handoverItem.stock']);

        $recordsTotal = (clone $query)->count();

        $query
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('branch'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('return_date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('return_date', '<=', $date))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()));

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('return_number', 'like', "%{$search}%")
                ->orWhereHas('items.masterProduct', fn ($p) => $p->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                ->orWhereHas('items.handoverItem.stock', fn ($s) => $s->where('serial_number', 'like', "%{$search}%"))
                ->orWhereHas('items.handoverItem.materialHandover.materialRequest.project', fn ($p) => $p->where('project_number', 'like', "%{$search}%")->orWhere('project_name', 'like', "%{$search}%")));
        }

        $recordsFiltered = (clone $query)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $rows = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->map(fn (MaterialReturn $r, $i) => [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $r->id,
                'return_number' => $r->return_number,
                'return_date' => $r->return_date?->format('d/m/Y'),
                'branch' => $r->branch?->name ?? '—',
                'item_count' => $r->items->count(),
                'total_value' => StockCost::format($r->totalValue()),
                'creator' => $r->creator?->display_name ?? '—',
                'status' => $r->status,
                'status_badge' => $r->statusBadgeHtml(),
            ]),
        ]);
    }

    public function create()
    {
        $groups = $this->openPool()
            ->groupBy(fn (MaterialHandoverItem $item) => $item->materialHandover->materialRequest->branch_id)
            ->map(function (Collection $items) {
                $branch = $items->first()->materialHandover->materialRequest->branch;

                return [
                    'branch_id' => $branch->id,
                    'label' => $branch->name,
                    'lines' => $items->count(),
                ];
            })
            ->sortBy('label')
            ->values();

        return view('material-return.form', [
            'groups' => $groups,
        ]);
    }

    /** Material di Produksi (sisa) satu branch (semua project). */
    public function pool(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
        ]);
        abort_unless(Auth::user()->canAccessBranch((int) $validated['branch_id']), 403, 'Branch bukan milik Anda.');

        $rows = MaterialHandoverItem::withUsedQty()
            ->withReturnedQty()
            ->whereHas('materialHandover.materialRequest', fn (Builder $q) => $q->where('branch_id', (int) $validated['branch_id']))
            ->with(['stock.masterProduct', 'stock.warehouse', 'materialHandover.materialRequest.project:id,project_number,project_name', 'outLog'])
            ->orderBy('id')
            ->get()
            ->filter(fn (MaterialHandoverItem $item) => $item->remainingQty() > 0)
            ->map(fn (MaterialHandoverItem $item) => [
                'id' => $item->id,
                'product_code' => $item->stock?->masterProduct?->code,
                'product_name' => $item->stock?->masterProduct?->name,
                'identity' => $item->stock?->identity_label,
                'serialized' => (bool) $item->stock?->isSerialized(),
                'remaining' => $item->remainingQty(),
                'remaining_label' => StockCost::qty($item->remainingQty()),
                'unit' => $item->stock?->unit,
                'unit_cost' => $item->unitCost(),
                'handover_number' => $item->materialHandover?->handover_number,
                'project_id' => $item->materialHandover?->materialRequest?->project_id,
                'project_label' => $item->materialHandover?->materialRequest?->project
                    ? $item->materialHandover->materialRequest->project->project_number.' — '.$item->materialHandover->materialRequest->project->project_name
                    : '—',
                'warehouse' => $item->stock?->warehouse?->code.' — '.$item->stock?->warehouse?->name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'return_date' => 'required|date',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.material_handover_item_id' => 'required|integer|distinct',
            'items.*.qty' => 'required|numeric|gt:0',
        ], [
            'items.required' => 'Pilih minimal satu material yang dikembalikan.',
            'items.*.material_handover_item_id.distinct' => 'Baris material yang sama tidak boleh dipilih dua kali.',
        ]);

        if (! Auth::user()->canAccessBranch((int) $validated['branch_id'])) {
            return response()->json(['success' => false, 'message' => 'Branch bukan milik Anda.'], 403);
        }

        $date = $validated['return_date'];

        try {
            $return = DB::transaction(function () use ($validated, $date) {
                $return = MaterialReturn::create([
                    'return_number' => MaterialReturn::generateNumber($date),
                    'branch_id' => (int) $validated['branch_id'],
                    'return_date' => $date,
                    'status' => MaterialReturn::STATUS_WAITING_APPROVAL,
                    'note' => $validated['note'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                foreach ($validated['items'] as $row) {
                    $this->saveItem($return, (int) $row['material_handover_item_id'], StockService::round((float) $row['qty']));
                }

                return $return;
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Log::record('create_material_return', 'Retur Material '.$return->return_number.' diajukan oleh '.Auth::user()->display_name, MaterialReturn::MODULE_CODE, $return);

        return response()->json(['success' => true, 'message' => 'Retur '.$return->return_number.' diajukan untuk approval.', 'id' => $return->id]);
    }

    public function approve($id): JsonResponse
    {
        $return = MaterialReturn::visibleTo(Auth::user())->with('items')->findOrFail($id);
        if (! $return->isWaitingApproval()) {
            return response()->json(['success' => false, 'message' => 'Retur ini tidak sedang menunggu approval.'], 422);
        }

        $service = app(StockService::class);
        $date = $return->return_date->toDateString();

        try {
            DB::transaction(function () use ($return, $service, $date) {
                $this->postItems($return, $service, $date);
                $return->update(['status' => MaterialReturn::STATUS_APPROVED, 'approved_by' => Auth::id(), 'approved_at' => now()]);
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Log::record('approve_material_return', 'Retur Material '.$return->return_number.' disetujui oleh '.Auth::user()->display_name, MaterialReturn::MODULE_CODE, $return);

        return response()->json(['success' => true, 'message' => 'Retur '.$return->return_number.' disetujui. Material kembali menjadi stok bebas.']);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:255'], ['reason.required' => 'Alasan penolakan wajib diisi.']);
        $return = MaterialReturn::visibleTo(Auth::user())->findOrFail($id);
        if (! $return->isWaitingApproval()) {
            return response()->json(['success' => false, 'message' => 'Retur ini tidak sedang menunggu approval.'], 422);
        }

        $return->update([
            'status' => MaterialReturn::STATUS_REJECTED,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'reject_reason' => $validated['reason'],
        ]);
        Log::record('reject_material_return', 'Retur Material '.$return->return_number.' ditolak oleh '.Auth::user()->display_name.': '.$validated['reason'], MaterialReturn::MODULE_CODE, $return);

        return response()->json(['success' => true, 'message' => 'Retur '.$return->return_number.' ditolak.']);
    }

    public function show($id)
    {
        $return = MaterialReturn::visibleTo(Auth::user())
            ->with([
                'branch', 'creator', 'approver', 'rejecter',
                'items.masterProduct', 'items.warehouse', 'items.stock', 'items.project',
                'items.handoverItem.materialHandover.materialRequest.project',
            ])
            ->findOrFail($id);

        return view('material-return.show', [
            'return' => $return,
            'canDecide' => $return->isWaitingApproval() && ModuleAccess::for(Auth::user())->module(MaterialReturn::MODULE_CODE)->canApprove(),
        ]);
    }

    /** Simpan baris retur (draft) dengan harga & posisi asal yang dibekukan. */
    private function saveItem(MaterialReturn $return, int $handoverItemId, float $qty): void
    {
        $source = MaterialHandoverItem::withUsedQty()
            ->withReturnedQty()
            ->with(['stock.masterProduct', 'materialHandover.materialRequest.project'])
            ->find($handoverItemId);
        $this->assertReturnable($return, $source, $qty);

        $return->items()->create([
            'material_handover_item_id' => $source->id,
            'stock_id' => $source->stock_id,
            'master_product_id' => $source->stock->master_product_id,
            'warehouse_id' => (int) $source->stock->warehouse_id,
            'project_id' => $source->materialHandover->materialRequest->project_id,
            'qty' => $qty,
            'unit' => $source->stock->unit,
            'unit_cost' => $source->unitCost(),
        ]);
    }

    /** Validasi ulang sisa (dalam lock) lalu pindahkan stok + lepas alokasi. */
    private function postItems(MaterialReturn $return, StockService $service, string $date): void
    {
        $ids = $return->items->pluck('material_handover_item_id')->map(fn ($id) => (int) $id)->sort()->values();
        $sources = MaterialHandoverItem::withUsedQty()
            ->withReturnedQty()
            ->with(['stock.masterProduct', 'materialHandover.materialRequest.project'])
            ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($return->items as $item) {
            $source = $sources->get((int) $item->material_handover_item_id);
            $this->assertReturnable($return, $source, (float) $item->qty);

            $warehouseId = (int) $item->warehouse_id;
            $unitCost = (float) $item->unit_cost;
            $log = [
                'date' => $date,
                'ref_type' => StockLog::REF_MATERIAL_RETURN_ITEM,
                'ref_id' => $item->id,
            ];

            if ($source->stock->isSerialized()) {
                $service->reactivate($source->stock, $warehouseId, null, StockLog::TYPE_IN, $log + [
                    'note' => 'Retur material ke gudang '.$return->return_number,
                ]);
            } else {
                $service->receive($source->stock->masterProduct, (float) $item->qty, [
                    'warehouse_id' => $warehouseId,
                    'project_id' => null,
                    'received_date' => $date,
                    'unit' => $item->unit,
                    'unit_cost' => $unitCost,
                    'source_type' => Stock::SOURCE_PRODUCTION_RETURN,
                    'source_id' => $return->id,
                ], [], $log + [
                    'note' => 'Retur material ke gudang '.$return->return_number,
                ]);
            }

            if ($source->allocateLog()->exists()) {
                StockLog::create([
                    'stock_id' => $item->stock_id,
                    'master_product_id' => $item->master_product_id,
                    'warehouse_id' => $warehouseId,
                    'project_id' => $item->project_id,
                    'is_material' => (bool) ($source->stock->is_material ?? true),
                    'log_date' => $date,
                    'type' => StockLog::TYPE_ALLOCATE,
                    'qty' => StockService::round(-(float) $item->qty),
                    'qty_after' => 0,
                    'unit_cost' => $unitCost,
                    'ref_type' => StockLog::REF_MATERIAL_RETURN_ITEM,
                    'ref_id' => $item->id,
                    'note' => 'Retur material ke gudang: lepas alokasi project '.$return->return_number,
                    'created_by' => Auth::id(),
                ]);
            }
        }
    }

    private function assertReturnable(MaterialReturn $return, ?MaterialHandoverItem $source, float $qty): void
    {
        $mr = $source?->materialHandover?->materialRequest;
        if (! $source || (int) $mr->branch_id !== (int) $return->branch_id) {
            throw new StockException('Material yang dipilih bukan Material di Produksi branch ini.');
        }
        $label = app(StockService::class)->label($source->stock);
        $remaining = $source->remainingQty();
        if ($qty - $remaining > Stock::EPSILON) {
            throw new StockException('Sisa '.$label.' tinggal '.StockCost::qty($remaining).' (diretur '.StockCost::qty($qty).').');
        }
        if ($source->stock->isSerialized() && abs($qty - $remaining) >= Stock::EPSILON) {
            throw new StockException($label.' adalah barang ber-SN dan diretur utuh.');
        }
    }

    /** Material di Produksi yang masih bersisa, sesuai branch user. */
    private function openPool(): Collection
    {
        $user = Auth::user();

        return MaterialHandoverItem::withUsedQty()
            ->withReturnedQty()
            ->with(['materialHandover.materialRequest.project:id,project_number,project_name', 'materialHandover.materialRequest.branch:id,name'])
            ->when($user->branch_id, fn ($q) => $q->whereHas('materialHandover.materialRequest', fn ($m) => $m->where('branch_id', $user->branch_id)))
            ->get()
            ->filter(fn (MaterialHandoverItem $item) => $item->remainingQty() > 0)
            ->values();
    }
}
