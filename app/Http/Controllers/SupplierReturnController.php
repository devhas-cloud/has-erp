<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceiptItem;
use App\Models\Log;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\StockMovement;
use App\Models\SupplierReturn;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Retur ke Supplier (grup Purchasing, Tahap 4). Hanya qty yang sudah diterima
 * dan BELUM ditagih yang bisa diretur — barang yang sudah ditagih harus di-void
 * dulu tagihannya (koreksi lewat dokumen, bukan edit). Disimpan langsung
 * terposting: stok keluar dari gudang (kepemilikan project mengikuti baris PO)
 * untuk barang yang dulu masuk gudang; barang langsung pakai cukup dicatat.
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
            'returns' => SupplierReturn::with(['supplier', 'purchaseOrder', 'warehouse'])->withSum('items', 'qty')->orderByDesc('id')->limit(200)->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('supplier-return.form', [
            'purchaseOrders' => $this->returnablePurchaseOrders(),
            'warehouses' => Warehouse::active()->orderBy('type')->orderBy('name')->get(),
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
                'to_warehouse' => $this->receivedToWarehouse($item) > 0,
            ])->values(),
        ]);
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
            'items.*.qty' => 'required|integer|min:1',
        ], [
            'reason.required' => 'Alasan retur wajib diisi.',
            'items.required' => 'Isi qty minimal satu barang yang diretur.',
        ]);

        $po = $this->loadPurchaseOrder((int) $validated['purchase_order_id']);
        $fail = fn (string $message) => abort(response()->json(['success' => false, 'message' => $message], 422));
        $poItems = $po->items->keyBy('id');
        $warehouse = $validated['warehouse_id'] ? Warehouse::find($validated['warehouse_id']) : null;

        $rows = [];
        foreach (array_values($validated['items']) as $i => $row) {
            $item = $poItems->get((int) $row['purchase_order_item_id']);
            if (! $item) {
                $fail('Baris '.($i + 1).' bukan bagian dari PO ini.');
            }
            $qty = (int) $row['qty'];
            $label = $item->part_number ?: 'baris '.($i + 1);
            if ($qty > $this->returnableQty($item)) {
                $fail($label.': qty retur melebihi yang diterima dan belum ditagih ('.$this->returnableQty($item).'). Barang yang sudah ditagih: void tagihannya dulu.');
            }

            // Barang yang dulu masuk gudang keluar dari stok; sisanya (langsung pakai) cukup dicatat.
            $fromWarehouse = min($qty, $this->receivedToWarehouse($item));
            if ($fromWarehouse > 0) {
                if (! $warehouse) {
                    $fail('Pilih gudang asal barang yang diretur.');
                }
                $balance = StockMovement::balance($warehouse->id, (int) $item->master_product_id, $item->projectId());
                if ($fromWarehouse > $balance) {
                    $fail($label.': stok di '.$warehouse->name.' tidak cukup ('.$balance.').');
                }
            }
            $rows[] = ['item' => $item, 'qty' => $qty, 'from_warehouse' => $fromWarehouse];
        }

        $return = DB::transaction(function () use ($validated, $po, $rows, $warehouse) {
            $return = SupplierReturn::create([
                'return_number' => SupplierReturn::generateNumber($validated['return_date']),
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'warehouse_id' => $warehouse?->id,
                'return_date' => $validated['return_date'],
                'reason' => $validated['reason'],
                'status' => SupplierReturn::STATUS_POSTED,
                'created_by' => Auth::id(),
            ]);

            foreach ($rows as $i => $row) {
                $item = $row['item'];
                $line = $return->items()->create([
                    'purchase_order_item_id' => $item->id,
                    'master_product_id' => $item->master_product_id,
                    'project_id' => $item->projectId(),
                    'qty' => $row['qty'],
                    'unit' => $item->unit,
                    'destination' => $row['from_warehouse'] > 0 ? GoodsReceiptItem::DESTINATION_WAREHOUSE : GoodsReceiptItem::DESTINATION_DIRECT,
                    'sort_order' => $i + 1,
                ]);

                if ($row['from_warehouse'] > 0) {
                    StockMovement::record([
                        'warehouse_id' => $warehouse->id,
                        'master_product_id' => $item->master_product_id,
                        'project_id' => $item->projectId(),
                        'type' => StockMovement::TYPE_RETURN_OUT,
                        'qty' => $row['from_warehouse'],
                        'unit' => $item->unit,
                        'source_type' => StockMovement::SOURCE_SUPPLIER_RETURN,
                        'source_id' => $return->id,
                        'source_line_id' => $line->id,
                        'moved_at' => $validated['return_date'],
                        'note' => 'Retur ke supplier '.$return->return_number,
                    ]);
                }
            }

            $po->refreshReceiptStatus();

            return $return;
        });

        Log::record('supplier_return', 'Retur ke supplier '.$return->return_number.' (PO '.$po->po_number.') diposting oleh '.Auth::user()->display_name, 'MOD_SUPPLIER_RETURN', $return);

        return response()->json(['success' => true, 'message' => 'Retur '.$return->return_number.' diposting. Barang retur tidak bisa ditagih dan PO kembali menunggu pengganti.', 'id' => $return->id]);
    }

    public function show($id)
    {
        $return = SupplierReturn::with(['items.purchaseOrderItem', 'items.project', 'supplier', 'purchaseOrder', 'warehouse', 'creator'])->findOrFail($id);

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
        $toWarehouse = (int) $item->goodsReceiptItems
            ->filter(fn (GoodsReceiptItem $r) => $r->goodsReceipt?->status === 'posted' && $r->destination === GoodsReceiptItem::DESTINATION_WAREHOUSE)
            ->sum('qty');
        $returnedFromStock = (int) $item->returnItems->where('destination', GoodsReceiptItem::DESTINATION_WAREHOUSE)->sum('qty');

        return max(0, $toWarehouse - $returnedFromStock);
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
