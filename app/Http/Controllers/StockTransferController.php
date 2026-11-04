<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Transfer Stok — pindah gudang dan/atau pindah kepemilikan dalam satu
 * dokumen. Asal & tujuan = (gudang, project|bebas). Qty per produk tidak boleh
 * melebihi saldo asal. Disimpan langsung terposting (transfer_out +
 * transfer_in), tidak bisa diubah/dihapus — koreksi dengan transfer balik.
 */
class StockTransferController extends Controller
{
    public function index()
    {
        return view('stock-transfer.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = StockTransfer::withCount('items')->with(['fromWarehouse', 'toWarehouse', 'fromProject', 'toProject', 'creator']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('transfer_number', 'like', "%{$searchValue}%")
                    ->orWhere('note', 'like', "%{$searchValue}%")
                    ->orWhereHas('fromWarehouse', fn ($w) => $w->where('name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('toWarehouse', fn ($w) => $w->where('name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('fromProject', fn ($p) => $p->where('project_number', 'like', "%{$searchValue}%")->orWhere('project_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('toProject', fn ($p) => $p->where('project_number', 'like', "%{$searchValue}%")->orWhere('project_name', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $transfers = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        $data = $transfers->map(fn (StockTransfer $transfer, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'id' => $transfer->id,
            'transfer_number' => $transfer->transfer_number,
            'transfer_date' => $transfer->transfer_date?->format('d/m/Y') ?? '—',
            'kind' => $transfer->kindLabel(),
            'from' => $transfer->fromWarehouse?->name.' · '.StockTransfer::ownershipLabel($transfer->fromProject),
            'to' => $transfer->toWarehouse?->name.' · '.StockTransfer::ownershipLabel($transfer->toProject),
            'item_count' => $transfer->items_count,
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
            'warehouses' => Warehouse::active()->orderBy('type')->orderBy('name')->get(),
            'projects' => Project::where('status', Project::STATUS_ACTIVE)->orderByDesc('id')->get(['id', 'project_number', 'project_name']),
        ]);
    }

    /**
     * Stok tersedia di (gudang, project|bebas) asal — dipanggil form saat asal dipilih.
     */
    public function availableStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'project_id' => 'nullable|integer|exists:projects,id',
        ]);

        return response()->json([
            'success' => true,
            'items' => $this->available((int) $validated['warehouse_id'], $validated['project_id'] ?? null)->values(),
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
            'items.*.master_product_id' => 'required|integer|distinct',
            'items.*.qty' => 'required|integer|min:1',
        ], [
            'items.required' => 'Isi qty minimal satu barang yang ditransfer.',
            'items.*.master_product_id.distinct' => 'Produk yang sama tidak boleh muncul dua kali.',
        ]);

        $fromProjectId = $validated['from_project_id'] ?? null;
        $toProjectId = $validated['to_project_id'] ?? null;

        if ((int) $validated['from_warehouse_id'] === (int) $validated['to_warehouse_id'] && $fromProjectId == $toProjectId) {
            return response()->json(['success' => false, 'message' => 'Asal dan tujuan sama. Ubah gudang tujuan atau kepemilikan tujuan.'], 422);
        }
        if (! Warehouse::active()->whereKey($validated['to_warehouse_id'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Gudang tujuan tidak aktif.'], 422);
        }

        $available = $this->available((int) $validated['from_warehouse_id'], $fromProjectId)->keyBy('master_product_id');

        foreach (array_values($validated['items']) as $i => $row) {
            $stock = $available->get((int) $row['master_product_id']);
            if (! $stock) {
                return response()->json(['success' => false, 'message' => 'Baris '.($i + 1).': produk tidak punya stok di asal transfer.'], 422);
            }
            if ((int) $row['qty'] > $stock['balance']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Qty '.$stock['code'].' melebihi stok asal (tersedia '.$stock['balance'].').',
                ], 422);
            }
        }

        $transfer = DB::transaction(function () use ($validated, $fromProjectId, $toProjectId, $available) {
            $date = $validated['transfer_date'] ?? now()->toDateString();

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
                $unit = $available[(int) $row['master_product_id']]['unit'];
                $item = $transfer->items()->create([
                    'master_product_id' => $row['master_product_id'],
                    'qty' => (int) $row['qty'],
                    'unit' => $unit,
                    'sort_order' => $i + 1,
                ]);

                $base = [
                    'master_product_id' => $item->master_product_id,
                    'qty' => $item->qty,
                    'unit' => $unit,
                    'source_type' => StockMovement::SOURCE_STOCK_TRANSFER,
                    'source_id' => $transfer->id,
                    'source_line_id' => $item->id,
                    'moved_at' => $date,
                    'note' => 'Transfer '.$transfer->transfer_number,
                ];

                StockMovement::record($base + [
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'project_id' => $fromProjectId,
                    'type' => StockMovement::TYPE_TRANSFER_OUT,
                ]);
                StockMovement::record($base + [
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'project_id' => $toProjectId,
                    'type' => StockMovement::TYPE_TRANSFER_IN,
                ]);
            }

            return $transfer;
        });

        Log::record(
            'stock_transfer',
            'Transfer stok '.$transfer->transfer_number.' ('.$transfer->kindLabel().') diposting oleh '.Auth::user()->display_name,
            'MOD_STOCK_TRANSFER',
            $transfer
        );

        return response()->json([
            'success' => true,
            'message' => 'Transfer '.$transfer->transfer_number.' diposting.',
            'id' => $transfer->id,
        ]);
    }

    public function show($id)
    {
        $transfer = StockTransfer::with(['items.masterProduct', 'fromWarehouse', 'toWarehouse', 'fromProject', 'toProject', 'creator'])->findOrFail($id);

        return view('stock-transfer.show', compact('transfer'));
    }

    /**
     * Saldo > 0 per produk di (gudang, project|bebas).
     */
    private function available(int $warehouseId, $projectId)
    {
        $rows = StockMovement::balancesQuery()
            ->where('warehouse_id', $warehouseId)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId), fn ($q) => $q->whereNull('project_id'))
            ->havingRaw('balance > 0')
            ->get();

        $products = MasterProduct::whereIn('id', $rows->pluck('master_product_id'))->get(['id', 'code', 'name'])->keyBy('id');

        return $rows->map(fn ($row) => [
            'master_product_id' => (int) $row->master_product_id,
            'code' => $products[$row->master_product_id]->code ?? '—',
            'name' => $products[$row->master_product_id]->name ?? '—',
            'balance' => (int) $row->balance,
            'unit' => $row->unit,
        ])->sortBy('code')->values();
    }
}
