<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceiptItem;
use App\Models\Log;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\StockPicker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Retur ke Supplier (grup Purchasing, Tahap 4). Hanya qty yang sudah diterima
 * dan BELUM ditagih yang bisa diretur — barang yang sudah ditagih harus di-void
 * dulu tagihannya (koreksi lewat dokumen, bukan edit). Disimpan langsung
 * terposting: barang yang dulu masuk gudang diretur dengan memilih baris stok
 * yang berasal dari penerimaan PO ini (unit ber-SN wajib sudah ber-SN);
 * barang langsung pakai cukup dicatat qty-nya.
 */
class SupplierReturnController extends Controller
{
    private const RETURNABLE_STATUSES = [
        PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        PurchaseOrder::STATUS_RECEIVED,
        PurchaseOrder::STATUS_CANCELLED,
    ];

    public function index()
    {
        return view('supplier-return.index', [
            // Hanya supplier & gudang yang pernah dipakai retur.
            'suppliers' => Supplier::whereIn('id', SupplierReturn::select('supplier_id'))->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::whereIn('id', SupplierReturn::whereNotNull('warehouse_id')->select('warehouse_id'))->orderBy('name')->get(['id', 'name']),
            'hasDirect' => SupplierReturn::whereNull('warehouse_id')->exists(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = SupplierReturn::with(['supplier', 'purchaseOrder', 'warehouse', 'creator'])->withSum('items', 'qty');

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('return_number', 'like', "%{$searchValue}%")
                    ->orWhere('reason', 'like', "%{$searchValue}%")
                    ->orWhereHas('purchaseOrder', fn ($po) => $po->where('po_number', 'like', "%{$searchValue}%"))
                    ->orWhereHas('supplier', fn ($sup) => $sup->where('name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('items.purchaseOrderItem', fn ($i) => $i->where('part_number', 'like', "%{$searchValue}%"));
            });
        }

        // Filter bar: supplier, gudang ("direct" = langsung pakai), tanggal retur, milik saya.
        $query->when($request->input('supplier'), fn ($q, $supplier) => $q->where('supplier_id', $supplier))
            ->when($request->input('warehouse'), fn ($q, $warehouse) => $warehouse === 'direct'
                ? $q->whereNull('warehouse_id')
                : $q->where('warehouse_id', $warehouse))
            ->when($request->input('date_from'), fn ($q, $from) => $q->whereDate('return_date', '>=', $from))
            ->when($request->input('date_to'), fn ($q, $to) => $q->whereDate('return_date', '<=', $to))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()));

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $returns = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        $data = [];
        foreach ($returns as $i => $return) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $return->id,
                'return_number' => $return->return_number,
                'date' => $return->return_date?->format('d/m/Y') ?? '—',
                'purchase_order_id' => $return->purchase_order_id,
                'po_number' => $return->purchaseOrder?->po_number ?? '—',
                'supplier_name' => $return->supplier?->name ?? '—',
                'warehouse_name' => $return->warehouse?->name ?? 'Langsung pakai',
                'qty' => (int) $return->items_sum_qty,
                'reason' => Str::limit((string) $return->reason, 90),
                'creator_name' => $return->creator?->display_name ?? '—',
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        return view('supplier-return.form', [
            'purchaseOrders' => $this->returnablePurchaseOrders(),
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'preselectPoId' => $request->integer('purchase_order_id') ?: null,
        ]);
    }

    public function returnableLines(Request $request): JsonResponse
    {
        $validated = $request->validate(['purchase_order_id' => 'required|integer|exists:purchase_orders,id']);
        $po = $this->loadPurchaseOrder((int) $validated['purchase_order_id']);

        return response()->json([
            'success' => true,
            'supplier_name' => $po->supplier?->name ?? $po->supplier_name,
            'lines' => $po->items->map(fn (PurchaseOrderItem $item) => [
                'purchase_order_item_id' => $item->id,
                'part_number' => $item->part_number,
                'description' => Quotation::renderDescription($item->description),
                'unit' => $item->unit,
                'received' => $item->receivedQty(),
                'billed' => $item->billedQty(),
                'returnable' => $this->returnableQty($item),
                'stock_returnable' => $this->receivedToWarehouse($item),
                'direct_returnable' => $this->receivedDirect($item),
            ])->values(),
        ]);
    }

    /**
     * Baris stok terbuka yang berasal dari penerimaan satu baris PO (termasuk pecahannya).
     */
    public function stockOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_item_id' => 'required|integer|exists:purchase_order_items,id',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);
        $item = PurchaseOrderItem::with('goodsReceiptItems.goodsReceipt')->findOrFail($validated['purchase_order_item_id']);

        return response()->json(['success' => true, 'data' => StockPicker::options([
            'warehouse_id' => $validated['warehouse_id'] ?? null,
            'source_ids' => $this->receiptItemIds($item),
            'limit' => 300,
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|integer|exists:purchase_orders,id',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'return_date' => 'required|date|before_or_equal:today',
            'reason' => 'required|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|integer|distinct',
            'items.*.direct_qty' => 'nullable|integer|min:0',
            'items.*.picks' => 'nullable|array',
            'items.*.picks.*.stock_id' => 'required|integer',
            'items.*.picks.*.qty' => 'required|numeric|gt:0',
        ], [
            'reason.required' => 'Alasan retur wajib diisi.',
            'items.required' => 'Pilih minimal satu barang yang diretur.',
        ]);

        $po = $this->loadPurchaseOrder((int) $validated['purchase_order_id']);
        $fail = fn (string $message) => abort(response()->json(['success' => false, 'message' => $message], 422));
        $poItems = $po->items->keyBy('id');

        $rows = [];
        foreach (array_values($validated['items']) as $i => $row) {
            $item = $poItems->get((int) $row['purchase_order_item_id']);
            if (! $item) {
                $fail('Baris '.($i + 1).' bukan bagian dari PO ini.');
            }
            $label = $item->part_number ?: 'baris '.($i + 1);
            $picks = $row['picks'] ?? [];
            $fromStock = StockService::round(collect($picks)->sum(fn ($p) => (float) $p['qty']));
            $direct = (int) ($row['direct_qty'] ?? 0);
            $qty = $fromStock + $direct;

            if ($qty <= 0) {
                continue;
            }
            if (abs($fromStock - round($fromStock)) >= Stock::EPSILON) {
                $fail($label.': total qty retur dari stok harus bilangan bulat sesuai satuan PO (dipilih '.$fromStock.').');
            }
            if ($qty > $this->returnableQty($item)) {
                $fail($label.': qty retur melebihi yang diterima dan belum ditagih ('.$this->returnableQty($item).'). Barang yang sudah ditagih: void tagihannya dulu.');
            }
            if ($fromStock > $this->receivedToWarehouse($item)) {
                $fail($label.': qty dari stok melebihi barang yang dulu masuk gudang ('.$this->receivedToWarehouse($item).').');
            }
            if ($direct > $this->receivedDirect($item)) {
                $fail($label.': qty langsung pakai melebihi yang diterima langsung pakai ('.$this->receivedDirect($item).').');
            }
            $rows[] = ['item' => $item, 'picks' => $picks, 'direct' => $direct, 'source_ids' => $this->receiptItemIds($item)];
        }
        if (! $rows) {
            $fail('Isi qty minimal satu barang yang diretur.');
        }

        $service = app(StockService::class);
        $warehouseId = $validated['warehouse_id'] ?? null;

        try {
            $return = DB::transaction(function () use ($validated, $po, $rows, $warehouseId, $service) {
                $stocks = $service->lock(collect($rows)->flatMap(fn ($r) => collect($r['picks'])->pluck('stock_id')));

                $return = SupplierReturn::create([
                    'return_number' => SupplierReturn::generateNumber($validated['return_date']),
                    'supplier_id' => $po->supplier_id,
                    'purchase_order_id' => $po->id,
                    'warehouse_id' => $warehouseId,
                    'return_date' => $validated['return_date'],
                    'reason' => $validated['reason'],
                    'status' => SupplierReturn::STATUS_POSTED,
                    'created_by' => Auth::id(),
                ]);

                $sort = 0;
                foreach ($rows as $row) {
                    $item = $row['item'];
                    foreach ($row['picks'] as $pick) {
                        $stock = $stocks->get((int) $pick['stock_id']);
                        if (! $stock || $stock->source_type !== Stock::SOURCE_GOODS_RECEIPT_ITEM || ! in_array((int) $stock->source_id, $row['source_ids'], true)) {
                            throw new StockException(($item->part_number ?: 'Barang').': baris stok yang dipilih bukan dari penerimaan PO ini.');
                        }
                        if ($warehouseId) {
                            $service->assertPosition($stock, (int) $warehouseId, false);
                        }

                        $line = $return->items()->create([
                            'purchase_order_item_id' => $item->id,
                            'stock_id' => $stock->id,
                            'master_product_id' => $stock->master_product_id,
                            'project_id' => $stock->project_id,
                            'qty' => StockService::round((float) $pick['qty']),
                            'unit' => $stock->unit ?? $item->unit,
                            'destination' => GoodsReceiptItem::DESTINATION_WAREHOUSE,
                            'sort_order' => ++$sort,
                        ]);
                        $service->take($stock, $line->qty, StockLog::TYPE_RETURN_SUPPLIER, [
                            'date' => $validated['return_date'],
                            'ref_type' => StockLog::REF_SUPPLIER_RETURN_ITEM,
                            'ref_id' => $line->id,
                            'note' => 'Retur ke supplier '.$return->return_number,
                        ]);
                    }

                    if ($row['direct'] > 0) {
                        $return->items()->create([
                            'purchase_order_item_id' => $item->id,
                            'master_product_id' => $item->master_product_id,
                            'project_id' => $item->projectId(),
                            'qty' => $row['direct'],
                            'unit' => $item->unit,
                            'destination' => GoodsReceiptItem::DESTINATION_DIRECT,
                            'sort_order' => ++$sort,
                        ]);
                    }
                }

                $po->refreshReceiptStatus();

                return $return;
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Log::record('supplier_return', 'Retur ke supplier '.$return->return_number.' (PO '.$po->po_number.') diposting oleh '.Auth::user()->display_name, 'MOD_SUPPLIER_RETURN', $return);

        return response()->json(['success' => true, 'message' => 'Retur '.$return->return_number.' diposting. Barang retur tidak bisa ditagih dan PO kembali menunggu pengganti.', 'id' => $return->id]);
    }

    public function show($id)
    {
        $return = SupplierReturn::with(['items.purchaseOrderItem', 'items.project', 'items.stock.masterProduct', 'supplier', 'purchaseOrder', 'warehouse', 'creator'])->findOrFail($id);

        return view('supplier-return.show', compact('return'));
    }

    /** Diterima bersih - sudah ditagih. */
    private function returnableQty(PurchaseOrderItem $item): int
    {
        return max(0, $item->receivedQty() - $item->billedQty());
    }

    /** Qty yang dulu masuk gudang (bukan langsung pakai) dan belum diretur dari stok. */
    private function receivedToWarehouse(PurchaseOrderItem $item): int
    {
        return $this->receivedRemaining($item, GoodsReceiptItem::DESTINATION_WAREHOUSE);
    }

    /** Qty yang dulu diterima langsung pakai dan belum diretur. */
    private function receivedDirect(PurchaseOrderItem $item): int
    {
        return $this->receivedRemaining($item, GoodsReceiptItem::DESTINATION_DIRECT);
    }

    private function receivedRemaining(PurchaseOrderItem $item, string $destination): int
    {
        $received = (int) round($item->goodsReceiptItems
            ->filter(fn (GoodsReceiptItem $r) => $r->goodsReceipt?->status === 'posted' && $r->destination === $destination)
            ->sum('qty'));
        $returned = (int) round($item->returnItems->where('destination', $destination)->sum('qty'));

        return max(0, $received - $returned);
    }

    /**
     * Id item GRN (posted, masuk gudang) dari satu baris PO — sumber baris stok yang boleh diretur.
     *
     * @return array<int, int>
     */
    private function receiptItemIds(PurchaseOrderItem $item): array
    {
        return $item->goodsReceiptItems
            ->filter(fn (GoodsReceiptItem $r) => $r->goodsReceipt?->status === 'posted' && $r->destination === GoodsReceiptItem::DESTINATION_WAREHOUSE)
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    private function loadPurchaseOrder(int $id): PurchaseOrder
    {
        $po = PurchaseOrder::with(['supplier', 'items.goodsReceiptItems.goodsReceipt', 'items.billItems.bill', 'items.returnItems', 'items.goodsRequestItem.goodsRequest'])->findOrFail($id);
        if (! in_array($po->status, self::RETURNABLE_STATUSES, true) || ! $po->supplier_id) {
            abort(response()->json(['success' => false, 'message' => 'PO belum punya barang diterima atau belum tertaut master supplier.'], 422));
        }

        return $po;
    }

    private function returnablePurchaseOrders(): Collection
    {
        return PurchaseOrder::with(['items.goodsReceiptItems.goodsReceipt', 'items.billItems.bill', 'items.returnItems'])
            ->whereIn('status', self::RETURNABLE_STATUSES)
            ->whereNotNull('supplier_id')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PurchaseOrder $po) => $po->items->contains(fn (PurchaseOrderItem $item) => $this->returnableQty($item) > 0))
            ->values();
    }
}
