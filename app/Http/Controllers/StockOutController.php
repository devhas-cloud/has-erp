<?php

namespace App\Http\Controllers;

use App\Models\GoodsRequest;
use App\Models\GoodsRequestItem;
use App\Models\Log;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\StockCost;
use App\Support\StockPicker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pemenuhan dari Stok — gudang memenuhi item Permintaan Barang Approved dari
 * STOK BEBAS (tetap boleh dibeli lewat PO). User memilih baris stok yang dipakai;
 * partial diperbolehkan, material boleh diambil sebagian dari satu baris.
 *   - Permintaan internal : barang keluar (log 'out'); unit ber-SN wajib sudah ber-SN.
 *   - Permintaan project  : alokasi — baris stok menjadi milik project (log 'allocate'),
 *     boleh tetap di gudang yang sama; belum keluar sampai dikirim lewat Delivery Order.
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

        $warehouses = Warehouse::active()->orderBy('name')->get();
        $warehouseNames = $warehouses->keyBy('id');

        // Saldo stok bebas per (produk, gudang) dalam satu query.
        $productIds = $requests->flatMap(fn ($gr) => $gr->items->pluck('master_product_id'))->unique()->values();
        $free = Stock::open()->whereNull('project_id')->whereIn('master_product_id', $productIds->all() ?: [0])
            ->whereIn('warehouse_id', $warehouses->pluck('id'))
            ->groupBy('master_product_id', 'warehouse_id')
            ->selectRaw('master_product_id, warehouse_id, SUM(qty) AS balance')
            ->get()->groupBy('master_product_id');

        $pending = [];
        foreach ($requests as $gr) {
            foreach ($gr->items as $item) {
                $remaining = $item->remainingQty();
                if ($remaining <= 0) {
                    continue;
                }

                $stock = ($free[$item->master_product_id] ?? collect())->map(fn ($row) => [
                    'id' => (int) $row->warehouse_id,
                    'code' => $warehouseNames[$row->warehouse_id]->code,
                    'name' => $warehouseNames[$row->warehouse_id]->name,
                    'balance' => (float) $row->balance,
                    'balance_label' => StockCost::qty((float) $row->balance),
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
                    'from_stock_qty' => $item->stockQty(),
                    'ordered_qty' => $item->orderedQty(),
                    'closed_qty' => (int) $item->closed_qty,
                    'free_qty' => (float) array_sum(array_column($stock, 'balance')),
                    'request_date' => $gr->created_at?->format('d M Y'),
                    'request_url' => route('goods-request.show', $gr->id),
                    'division_id' => $gr->division_id,
                    'division_name' => $gr->division?->division_name,
                    'for_project' => (bool) $gr->isForProject(),
                    'project_name' => $gr->destinationLabel(),
                    'stock' => $stock,
                ];
            }
        }

        return view('stock-out.index', [
            'pending' => $pending,
            'allWarehouses' => $warehouses,
        ]);
    }

    /**
     * Baris stok bebas yang bisa dipilih untuk satu item permintaan di satu gudang.
     */
    public function stockOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goods_request_item_id' => 'required|integer|exists:goods_request_items,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'q' => 'nullable|string|max:100',
        ]);
        $item = GoodsRequestItem::findOrFail($validated['goods_request_item_id']);

        return response()->json(['success' => true, 'data' => StockPicker::options([
            'warehouse_id' => $validated['warehouse_id'],
            'project' => 'free',
            'product_id' => $item->master_product_id,
            'q' => $validated['q'] ?? null,
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goods_request_item_id' => 'required|integer|exists:goods_request_items,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'destination_warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'picks' => 'required|array|min:1',
            'picks.*.stock_id' => 'required|integer|distinct',
            'picks.*.qty' => 'required|numeric|gt:0',
        ], ['picks.required' => 'Pilih minimal satu baris stok.']);

        $item = GoodsRequestItem::with(array_merge(['goodsRequest.project', 'masterProduct'], GoodsRequestItem::FULFILLMENT_RELATIONS))
            ->findOrFail($validated['goods_request_item_id']);
        $goodsRequest = $item->goodsRequest;

        if ($goodsRequest?->status !== GoodsRequest::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Permintaan barang belum Approved.'], 422);
        }
        if (! $item->master_product_id) {
            return response()->json(['success' => false, 'message' => 'Item belum terhubung master produk. Petakan produknya dulu di Permintaan Barang.'], 422);
        }

        $total = StockService::round(collect($validated['picks'])->sum(fn ($p) => (float) $p['qty']));
        $remaining = $item->remainingQty();
        if (abs($total - round($total)) >= Stock::EPSILON) {
            return response()->json(['success' => false, 'message' => 'Total qty harus bilangan bulat sesuai satuan Permintaan Barang (dipilih '.$total.').'], 422);
        }
        if ($total > $remaining) {
            return response()->json(['success' => false, 'message' => 'Qty melebihi sisa kebutuhan ('.$remaining.').'], 422);
        }

        $forProject = (bool) $goodsRequest->isForProject();
        $destination = null;
        if ($forProject) {
            // Gudang tujuan boleh sama dengan gudang asal (cukup berpindah kepemilikan).
            $destination = Warehouse::active()->find($validated['destination_warehouse_id'] ?? $validated['warehouse_id']);
            if (! $destination) {
                return response()->json(['success' => false, 'message' => 'Gudang tujuan alokasi tidak aktif.'], 422);
            }
        }

        $service = app(StockService::class);
        $date = now()->toDateString();
        $log = [
            'date' => $date,
            'ref_type' => StockLog::REF_GOODS_REQUEST_ITEM,
            'ref_id' => $item->id,
            'goods_request_item_id' => $item->id,
        ];

        try {
            DB::transaction(function () use ($service, $validated, $item, $goodsRequest, $forProject, $destination, $log) {
                $stocks = $service->lock(collect($validated['picks'])->pluck('stock_id'));
                foreach ($validated['picks'] as $pick) {
                    $stock = $stocks->get((int) $pick['stock_id']);
                    if (! $stock || (int) $stock->master_product_id !== (int) $item->master_product_id) {
                        throw new StockException('Baris stok yang dipilih bukan produk '.($item->masterProduct?->code ?? 'item ini').'.');
                    }
                    $service->assertPosition($stock, (int) $validated['warehouse_id'], null);

                    if ($forProject) {
                        $service->move($stock, (float) $pick['qty'], $destination->id, $goodsRequest->project_id, StockLog::TYPE_ALLOCATE, $log + [
                            'note' => 'Alokasi ke project '.$goodsRequest->project?->project_number.' (Permintaan '.$goodsRequest->number_label.')',
                        ]);
                    } else {
                        $service->take($stock, (float) $pick['qty'], StockLog::TYPE_OUT, $log + [
                            'note' => 'Keluar stok untuk Permintaan '.$goodsRequest->number_label,
                        ]);
                    }
                }
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $qtyLabel = StockCost::qty($total).' '.($item->unit ?: 'unit');
        Log::record(
            $forProject ? 'allocate_stock' : 'issue_stock',
            $qtyLabel.' '.($item->part_number ?: 'item').' '.($forProject ? 'dialokasikan ke project' : 'dikeluarkan dari stok').' untuk Permintaan '.$goodsRequest->number_label.' oleh '.Auth::user()->display_name,
            'MOD_STOCK_OUT',
            $goodsRequest
        );

        return response()->json([
            'success' => true,
            'message' => $forProject
                ? $qtyLabel.' dialokasikan ke project di '.$destination->name.'.'
                : $qtyLabel.' dikeluarkan dari gudang.',
        ]);
    }
}
