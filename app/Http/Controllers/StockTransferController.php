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
 * dokumen. Asal & tujuan = (gudang, project|bebas). User memilih baris stok
 * asal; material boleh sebagian (baris dipecah), barang ber-SN pindah utuh dan
 * boleh belum ber-SN. Pindah kepemilikan dicatat sebagai alokasi (biaya project).
 * Disimpan langsung terposting, tidak bisa diubah/dihapus — koreksi dengan transfer balik.
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
                'limit' => 300,
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
            'items.required' => 'Pilih minimal satu baris stok yang ditransfer.',
            'items.*.stock_id.distinct' => 'Baris stok yang sama tidak boleh dipilih dua kali.',
        ]);

        $fromProjectId = $validated['from_project_id'] ?? null;
        $toProjectId = $validated['to_project_id'] ?? null;

        if ((int) $validated['from_warehouse_id'] === (int) $validated['to_warehouse_id'] && $fromProjectId == $toProjectId) {
            return response()->json(['success' => false, 'message' => 'Asal dan tujuan sama. Ubah gudang tujuan atau kepemilikan tujuan.'], 422);
        }
        if (! Warehouse::active()->whereKey($validated['to_warehouse_id'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Gudang tujuan tidak aktif.'], 422);
        }
        // Transfer Stok hanya dalam satu branch; antar branch wajib lewat Branch Transfer (surat jalan + penerimaan).
        $branches = Warehouse::whereIn('id', [$validated['from_warehouse_id'], $validated['to_warehouse_id']])->pluck('branch_id', 'id');
        if ((int) ($branches[$validated['from_warehouse_id']] ?? 0) !== (int) ($branches[$validated['to_warehouse_id']] ?? 0)) {
            return response()->json(['success' => false, 'message' => 'Gudang asal dan tujuan berbeda branch. Gunakan Branch Transfer untuk pengiriman antar branch.'], 422);
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
                        throw new StockException('Baris '.($i + 1).': baris stok tidak ditemukan.');
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
            'message' => 'Transfer '.$transfer->transfer_number.' diposting.',
            'id' => $transfer->id,
        ]);
    }

    public function show($id)
    {
        $transfer = StockTransfer::with(['items.stock.masterProduct', 'fromWarehouse', 'toWarehouse', 'fromProject', 'toProject', 'creator'])->findOrFail($id);

        return view('stock-transfer.show', compact('transfer'));
    }
}
