<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Project;
use App\Models\StockLog;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\StockPicker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Transfer Stok — pindah gudang dan/atau pindah kepemilikan dalam satu
 * dokumen (satu branch). Asal & tujuan = (gudang, project|bebas).
 *   - Material: saldo asal berkurang, saldo tujuan bertambah (harga rata-rata
 *     di tujuan); boleh sebagian.
 *   - Barang ber-SN: unit pindah utuh, boleh belum ber-SN.
 * Pindah kepemilikan dicatat sebagai alokasi (biaya project). Disimpan langsung
 * terposting, tidak bisa diubah/dihapus — koreksi dengan transfer balik.
 */
class StockTransferController extends Controller
{
    public function index()
    {
        $warehouseIds = StockTransfer::pluck('from_warehouse_id')->merge(StockTransfer::pluck('to_warehouse_id'))->unique();
        $projectIds = StockTransfer::pluck('from_project_id')->merge(StockTransfer::pluck('to_project_id'))->filter()->unique();

        return view('stock-transfer.index', [
            'warehouses' => Warehouse::whereIn('id', $warehouseIds->all() ?: [0])->orderBy('name')->get(['id', 'code', 'name']),
            'projects' => Project::whereIn('id', $projectIds->all() ?: [0])->orderByDesc('id')->get(['id', 'project_number', 'project_name']),
        ]);
    }

    /** Jenis transfer dari kombinasi kepemilikan asal & tujuan. */
    public const KIND_FILTERS = [
        'move' => 'Move Warehouse',
        'allocate' => 'Allocate to Project',
        'release' => 'Release Allocation',
        'project' => 'Move Between Projects',
    ];

    public function data(Request $request): JsonResponse
    {
        $query = StockTransfer::withCount('items')->with(['fromWarehouse', 'toWarehouse', 'fromProject', 'toProject', 'creator', 'items:id,stock_transfer_id,stock_id,qty', 'items.stock:id,master_product_id', 'items.stock.masterProduct:id,is_material']);

        $recordsTotal = (clone $query)->count();

        $ownership = (string) $request->input('ownership', '');
        $query
            ->when($request->input('kind') === 'move', fn ($q) => $q->where(fn ($w) => $w
                ->where(fn ($n) => $n->whereNull('from_project_id')->whereNull('to_project_id'))
                ->orWhereColumn('from_project_id', 'to_project_id')))
            ->when($request->input('kind') === 'allocate', fn ($q) => $q->whereNull('from_project_id')->whereNotNull('to_project_id'))
            ->when($request->input('kind') === 'release', fn ($q) => $q->whereNotNull('from_project_id')->whereNull('to_project_id'))
            ->when($request->input('kind') === 'project', fn ($q) => $q->whereNotNull('from_project_id')->whereNotNull('to_project_id')->whereColumn('from_project_id', '!=', 'to_project_id'))
            ->when($request->integer('warehouse'), fn ($q, $id) => $q->where(fn ($w) => $w->where('from_warehouse_id', $id)->orWhere('to_warehouse_id', $id)))
            ->when($ownership === 'free', fn ($q) => $q->where(fn ($w) => $w->whereNull('from_project_id')->orWhereNull('to_project_id')))
            ->when(ctype_digit($ownership), fn ($q) => $q->where(fn ($w) => $w->where('from_project_id', (int) $ownership)->orWhere('to_project_id', (int) $ownership)))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('transfer_date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('transfer_date', '<=', $date))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()));

        $searchValue = trim((string) $request->input('search.value', ''));
        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('transfer_number', 'like', "%{$searchValue}%")
                    ->orWhere('note', 'like', "%{$searchValue}%")
                    ->orWhereHas('fromWarehouse', fn ($w) => $w->where('name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('toWarehouse', fn ($w) => $w->where('name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('fromProject', fn ($p) => $p->where('project_number', 'like', "%{$searchValue}%")->orWhere('project_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('toProject', fn ($p) => $p->where('project_number', 'like', "%{$searchValue}%")->orWhere('project_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('items.stock', fn ($st) => $st->where('serial_number', 'like', "%{$searchValue}%")
                        ->orWhereHas('masterProduct', fn ($p) => $p->where('code', 'like', "%{$searchValue}%")->orWhere('name', 'like', "%{$searchValue}%")));
            });
        }

        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $transfers = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        // Nilai = qty x harga saat dipindah (log keluar di baris asal).
        $itemToTransfer = collect();
        foreach ($transfers as $t) {
            foreach ($t->items as $item) {
                $itemToTransfer[$item->id] = $t->id;
            }
        }
        $values = $itemToTransfer->isEmpty() ? collect() : StockLog::where('ref_type', StockLog::REF_STOCK_TRANSFER_ITEM)
            ->whereIn('ref_id', $itemToTransfer->keys())
            ->where('qty', '<', 0)
            ->get(['ref_id', 'qty', 'unit_cost'])
            ->groupBy(fn ($log) => $itemToTransfer[$log->ref_id])
            ->map(fn ($logs) => round($logs->sum(fn ($log) => abs($log->qty) * $log->unit_cost), 2));

        $data = $transfers->map(fn (StockTransfer $transfer, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'id' => $transfer->id,
            'transfer_number' => $transfer->transfer_number,
            'transfer_date' => $transfer->transfer_date?->format('d/m/Y') ?? '—',
            'kind' => $transfer->kindLabel(),
            'from' => $transfer->fromWarehouse?->name.' · '.StockTransfer::ownershipLabel($transfer->fromProject),
            'to' => $transfer->toWarehouse?->name.' · '.StockTransfer::ownershipLabel($transfer->toProject),
            'from_warehouse' => $transfer->fromWarehouse ? $transfer->fromWarehouse->code.' — '.$transfer->fromWarehouse->name : '—',
            'to_warehouse' => $transfer->toWarehouse ? $transfer->toWarehouse->code.' — '.$transfer->toWarehouse->name : '—',
            'from_owner' => StockTransfer::ownershipLabel($transfer->fromProject),
            'to_owner' => StockTransfer::ownershipLabel($transfer->toProject),
            'item_count' => $transfer->items_count,
            'material_lines' => $transfer->items->filter(fn ($i) => $i->stock?->masterProduct?->isMaterial())->count(),
            'serial_units' => $transfer->items->filter(fn ($i) => $i->stock?->masterProduct && ! $i->stock->masterProduct->isMaterial())->count(),
            'value' => (float) ($values[$transfer->id] ?? 0),
            'creator' => $transfer->creator?->display_name ?? '—',
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create()
    {
        return view('stock-transfer.form', [
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'projects' => Project::where('status', Project::STATUS_ACTIVE)->orderByDesc('id')->get(['id', 'project_number', 'project_name']),
        ]);
    }

    /**
     * Baris stok di (gudang, project|bebas) asal — dipanggil form saat asal dipilih.
     */
    public function availableStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'q' => 'nullable|string|max:100',
        ]);

        return response()->json([
            'success' => true,
            'data' => StockPicker::options([
                'warehouse_id' => $validated['warehouse_id'],
                'project' => $validated['project_id'] ?? 'free',
                'q' => $validated['q'] ?? null,
                // Semua baris dimuat sekali; pencarian & filter di browser.
                'limit' => 2000,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => 'required|integer|exists:warehouses,id',
            'from_project_id' => 'nullable|integer|exists:projects,id',
            'to_warehouse_id' => 'required|integer|exists:warehouses,id',
            'to_project_id' => 'nullable|integer|exists:projects,id',
            'transfer_date' => 'nullable|date',
            'note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.stock_id' => 'required|integer|distinct',
            'items.*.qty' => 'required|numeric|gt:0',
        ], [
            'items.required' => 'Select at least one stock line to transfer.',
            'items.*.stock_id.distinct' => 'The same stock line cannot be selected twice.',
        ]);

        $fromProjectId = $validated['from_project_id'] ?? null;
        $toProjectId = $validated['to_project_id'] ?? null;

        if ((int) $validated['from_warehouse_id'] === (int) $validated['to_warehouse_id'] && $fromProjectId == $toProjectId) {
            return response()->json(['success' => false, 'message' => 'Source and destination are the same. Change the destination warehouse or ownership.'], 422);
        }
        if (! Warehouse::active()->whereKey($validated['to_warehouse_id'])->exists()) {
            return response()->json(['success' => false, 'message' => 'The destination warehouse is not active.'], 422);
        }
        // Transfer Stok hanya dalam satu branch; antar branch wajib lewat Branch Transfer (surat jalan + penerimaan).
        $branches = Warehouse::whereIn('id', [$validated['from_warehouse_id'], $validated['to_warehouse_id']])->pluck('branch_id', 'id');
        if ((int) ($branches[$validated['from_warehouse_id']] ?? 0) !== (int) ($branches[$validated['to_warehouse_id']] ?? 0)) {
            return response()->json(['success' => false, 'message' => 'Source and destination warehouses are in different branches. Use Branch Transfer to ship between branches.'], 422);
        }

        // Pindah kepemilikan (bebas <-> project, antar project) = alokasi -> biaya project.
        $kind = (int) $fromProjectId !== (int) $toProjectId ? StockLog::TYPE_ALLOCATE : StockLog::TYPE_TRANSFER_OUT;
        $service = app(StockService::class);

        try {
            $transfer = DB::transaction(function () use ($validated, $fromProjectId, $toProjectId, $kind, $service) {
                $date = $validated['transfer_date'] ?? now()->toDateString();
                $stocks = $service->lock(collect($validated['items'])->pluck('stock_id'));

                $transfer = StockTransfer::create([
                    'transfer_number' => StockTransfer::generateNumber($date),
                    'transfer_date' => $date,
                    'from_warehouse_id' => $validated['from_warehouse_id'],
                    'from_project_id' => $fromProjectId,
                    'to_warehouse_id' => $validated['to_warehouse_id'],
                    'to_project_id' => $toProjectId,
                    'note' => $validated['note'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                foreach (array_values($validated['items']) as $i => $row) {
                    $stock = $stocks->get((int) $row['stock_id']);
                    if (! $stock) {
                        throw new StockException('Line '.($i + 1).': stock line not found.');
                    }
                    $service->assertPosition($stock, (int) $validated['from_warehouse_id'], $fromProjectId ? (int) $fromProjectId : null);

                    $item = $transfer->items()->create([
                        'stock_id' => $stock->id,
                        'qty' => StockService::round((float) $row['qty']),
                        'unit' => $stock->unit,
                        'sort_order' => $i + 1,
                    ]);

                    $moved = $service->move($stock, $item->qty, (int) $transfer->to_warehouse_id, $toProjectId ? (int) $toProjectId : null, $kind, [
                        'date' => $date,
                        'ref_type' => StockLog::REF_STOCK_TRANSFER_ITEM,
                        'ref_id' => $item->id,
                        'note' => 'Transfer '.$transfer->transfer_number,
                    ]);
                    // Simpan baris tujuan bila baris asal dipecah (riwayat & tampilan dokumen).
                    if ($moved->id !== $stock->id) {
                        $item->update(['stock_id' => $moved->id]);
                    }
                }

                return $transfer;
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Log::record(
            'stock_transfer',
            'Transfer stok '.$transfer->transfer_number.' ('.$transfer->kindLabel().') diposting oleh '.Auth::user()->display_name,
            'MOD_STOCK_TRANSFER',
            $transfer
        );

        return response()->json([
            'success' => true,
            'message' => 'Transfer '.$transfer->transfer_number.' posted.',
            'id' => $transfer->id,
        ]);
    }

    public function show($id)
    {
        $transfer = StockTransfer::with(['items.stock.masterProduct', 'fromWarehouse', 'toWarehouse', 'fromProject', 'toProject', 'creator'])->findOrFail($id);

        // Log per baris: keluar (asal, harga saat dipindah) & masuk (tujuan, saldo sesudahnya).
        $logs = StockLog::where('ref_type', StockLog::REF_STOCK_TRANSFER_ITEM)
            ->whereIn('ref_id', $transfer->items->pluck('id')->all() ?: [0])
            ->orderBy('id')
            ->get()
            ->groupBy('ref_id');

        return view('stock-transfer.show', compact('transfer', 'logs'));
    }
}
