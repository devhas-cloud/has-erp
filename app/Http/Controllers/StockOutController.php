<?php

namespace App\Http\Controllers;

use App\Models\GoodsRequest;
use App\Models\GoodsRequestItem;
use App\Models\Log;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pemenuhan dari Stok — gudang memenuhi item Permintaan Barang Approved dari
 * STOK BEBAS (pilihan user; tetap boleh dibeli lewat PO). Partial diperbolehkan.
 *   - Permintaan internal : keluar stok (issue) dari gudang asal.
 *   - Permintaan project  : alokasi — stok bebas dipindah ke gudang tujuan
 *     (umumnya gudang transit) sebagai stok milik project (allocate_out + allocate_in),
 *     belum keluar perusahaan sampai dikirim lewat Delivery Order.
 */
class StockOutController extends Controller
{
    public function index()
    {
        $requests = GoodsRequest::with([
            'items' => fn ($q) => $q->with(array_merge(['masterProduct'], GoodsRequestItem::FULFILLMENT_RELATIONS))
                ->whereNotNull('master_product_id'),
            'division',
            'project',
        ])
            ->where('status', GoodsRequest::STATUS_APPROVED)
            ->orderByDesc('id')
            ->get();

        $warehouses = Warehouse::active()->orderBy('type')->orderBy('name')->get();
        $warehouseNames = $warehouses->keyBy('id');

        $pending = [];
        foreach ($requests as $gr) {
            foreach ($gr->items as $item) {
                $remaining = $item->remainingQty();
                if ($remaining <= 0) {
                    continue;
                }

                $stock = collect(StockMovement::freeBalancesByWarehouse($item->master_product_id))
                    ->filter(fn ($qty, $warehouseId) => $warehouseNames->has($warehouseId))
                    ->map(fn ($qty, $warehouseId) => [
                        'id' => $warehouseId,
                        'code' => $warehouseNames[$warehouseId]->code,
                        'name' => $warehouseNames[$warehouseId]->name,
                        'balance' => $qty,
                    ])->values()->all();

                $pending[] = [
                    'goods_request_item_id' => $item->id,
                    'goods_request_id' => $gr->id,
                    'request_number' => $gr->number_label,
                    'master_product_id' => $item->master_product_id,
                    'part_number' => $item->part_number,
                    'description' => $item->description,
                    'product_name' => $item->masterProduct?->name,
                    'unit' => $item->unit,
                    'requested_qty' => (int) $item->qty,
                    'remaining_qty' => $remaining,
                    'division_name' => $gr->division?->division_name,
                    'for_project' => (bool) $gr->isForProject(),
                    'project_name' => $gr->destinationLabel(),
                    'stock' => $stock,
                ];
            }
        }

        return view('stock-out.index', [
            'pending' => $pending,
            'transitWarehouses' => $warehouses->where('type', Warehouse::TYPE_TRANSIT)->values(),
            'allWarehouses' => $warehouses,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goods_request_item_id' => 'required|integer|exists:goods_request_items,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'destination_warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'qty' => 'required|integer|min:1',
        ]);

        $item = GoodsRequestItem::with(array_merge(['goodsRequest', 'masterProduct'], GoodsRequestItem::FULFILLMENT_RELATIONS))
            ->findOrFail($validated['goods_request_item_id']);
        $goodsRequest = $item->goodsRequest;

        if ($goodsRequest?->status !== GoodsRequest::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Permintaan barang belum Approved.'], 422);
        }
        if (! $item->master_product_id) {
            return response()->json(['success' => false, 'message' => 'Item belum terhubung master produk. Petakan produknya dulu di Permintaan Barang.'], 422);
        }

        $qty = (int) $validated['qty'];
        $remaining = $item->remainingQty();
        $balance = StockMovement::balance((int) $validated['warehouse_id'], (int) $item->master_product_id);

        if ($qty > $remaining) {
            return response()->json(['success' => false, 'message' => 'Qty melebihi sisa kebutuhan ('.$remaining.').'], 422);
        }
        if ($qty > $balance) {
            return response()->json(['success' => false, 'message' => 'Stok bebas gudang tidak cukup (tersedia '.$balance.').'], 422);
        }

        $forProject = (bool) $goodsRequest->isForProject();
        $destination = null;
        if ($forProject) {
            $destination = Warehouse::active()->find($validated['destination_warehouse_id'] ?? 0);
            if (! $destination) {
                return response()->json(['success' => false, 'message' => 'Pilih gudang tujuan alokasi (umumnya gudang transit) untuk barang project.'], 422);
            }
        }

        $base = [
            'master_product_id' => $item->master_product_id,
            'qty' => $qty,
            'unit' => $item->unit,
            'source_type' => StockMovement::SOURCE_STOCK_FULFILLMENT,
            'source_id' => $goodsRequest->id,
            'source_line_id' => $item->id,
            'goods_request_item_id' => $item->id,
        ];

        DB::transaction(function () use ($validated, $base, $forProject, $destination, $goodsRequest) {
            if (! $forProject) {
                StockMovement::record($base + [
                    'warehouse_id' => $validated['warehouse_id'],
                    'type' => StockMovement::TYPE_ISSUE,
                    'note' => 'Keluar stok untuk Permintaan '.$goodsRequest->number_label,
                ]);

                return;
            }

            StockMovement::record($base + [
                'warehouse_id' => $validated['warehouse_id'],
                'type' => StockMovement::TYPE_ALLOCATE_OUT,
                'note' => 'Alokasi ke project '.$goodsRequest->project?->project_number.' (Permintaan '.$goodsRequest->number_label.')',
            ]);
            // Sisi masuk tidak dihitung sebagai pemenuhan (lihat FULFILLMENT_TYPES).
            StockMovement::record(array_merge($base, ['goods_request_item_id' => null]) + [
                'warehouse_id' => $destination->id,
                'project_id' => $goodsRequest->project_id,
                'type' => StockMovement::TYPE_ALLOCATE_IN,
                'note' => 'Alokasi dari stok bebas (Permintaan '.$goodsRequest->number_label.')',
            ]);
        });

        Log::record(
            $forProject ? 'allocate_stock' : 'issue_stock',
            $qty.' '.($item->unit ?: 'unit').' '.($item->part_number ?: 'item').' '.($forProject ? 'dialokasikan ke project' : 'dikeluarkan dari stok').' untuk Permintaan '.$goodsRequest->number_label.' oleh '.Auth::user()->display_name,
            'MOD_STOCK_OUT',
            $goodsRequest
        );

        return response()->json([
            'success' => true,
            'message' => $forProject
                ? $qty.' '.($item->unit ?: 'unit').' dialokasikan ke project di '.$destination->name.'.'
                : $qty.' '.($item->unit ?: 'unit').' dikeluarkan dari gudang.',
        ]);
    }
}
