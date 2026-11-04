<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPurchaseDocuments;
use App\Models\DirectPurchase;
use App\Models\DirectPurchaseItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Log;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\StockValuation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Penerimaan Barang (GRN) dari Purchase Order (Approved / Diterima Sebagian)
 * atau Direct Purchase (Approved). Penerimaan boleh bertahap, tidak boleh
 * melebihi sisa per baris. Disimpan langsung terposting:
 *   - baris "Masuk Gudang"   -> stok masuk (receipt) ke gudang terpilih,
 *     beralokasi project bila barang untuk project;
 *   - baris "Langsung Pakai" -> hanya konfirmasi terima, tidak masuk kartu stok.
 * Satu penerimaan = satu gudang; PO yang mencampur barang project & internal
 * bisa diterima dalam beberapa penerimaan. Dokumen posted tidak bisa diubah.
 */
class GoodsReceiptController extends Controller
{
    use ManagesPurchaseDocuments;

    public const SOURCE_PO = 'po';

    public const SOURCE_DP = 'dp';

    public function index()
    {
        return view('goods-receipt.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = GoodsReceipt::withCount('items')->with(['purchaseOrder', 'directPurchase', 'warehouse', 'creator']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('receipt_number', 'like', "%{$searchValue}%")
                    ->orWhereHas('purchaseOrder', fn ($po) => $po->where('po_number', 'like', "%{$searchValue}%")->orWhere('supplier_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('directPurchase', fn ($dp) => $dp->where('dp_number', 'like', "%{$searchValue}%")->orWhere('supplier_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('warehouse', fn ($w) => $w->where('name', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $receipts = $query->orderBy('id', 'desc')->offset($start)->limit($length)->get();

        $data = $receipts->map(fn (GoodsReceipt $receipt, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number ?: '—',
            'source' => $receipt->sourceNumber(),
            'supplier_name' => $receipt->supplierName() ?? '—',
            'warehouse_name' => $receipt->warehouse?->name ?? 'Langsung pakai',
            'received_date' => $receipt->received_date?->format('d/m/Y') ?? '—',
            'item_count' => $receipt->items_count,
            'status' => $receipt->status,
            'status_label' => $receipt->status_label,
            'status_badge' => $receipt->statusBadgeHtml(),
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        $purchaseOrders = PurchaseOrder::with('items.goodsReceiptItems.goodsReceipt')
            ->whereIn('status', PurchaseOrder::RECEIVABLE_STATUSES)
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PurchaseOrder $po) => $po->items->contains(fn (PurchaseOrderItem $i) => $i->remainingReceiptQty() > 0))
            ->values();

        $directPurchases = DirectPurchase::with('items.goodsReceiptItems.goodsReceipt')
            ->where('status', DirectPurchase::STATUS_APPROVED)
            ->orderByDesc('id')
            ->get()
            ->filter(fn (DirectPurchase $dp) => $dp->items->contains(fn (DirectPurchaseItem $i) => $i->remainingReceiptQty() > 0))
            ->values();

        return view('goods-receipt.form', [
            'warehouses' => Warehouse::active()->orderBy('type')->orderBy('name')->get(),
            'purchaseOrders' => $purchaseOrders,
            'directPurchases' => $directPurchases,
            'preselect' => [
                'type' => $request->input('source_type'),
                'id' => $request->integer('source_id') ?: null,
            ],
        ]);
    }

    /**
     * Baris sumber (PO / DP) yang masih bisa diterima — dipanggil form setelah memilih sumber.
     */
    public function fetchSourceItems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type' => 'required|in:'.self::SOURCE_PO.','.self::SOURCE_DP,
            'source_id' => 'required|integer',
        ]);

        [$header, $lines] = $this->resolveSource($validated['source_type'], (int) $validated['source_id']);

        return response()->json([
            'success' => true,
            'source' => $header,
            'items' => $lines->filter(fn ($line) => $line['remaining'] > 0)->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type' => 'required|in:'.self::SOURCE_PO.','.self::SOURCE_DP,
            'source_id' => 'required|integer',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'received_date' => 'nullable|date',
            'note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.source_line_id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.destination' => 'required|in:'.GoodsReceiptItem::DESTINATION_WAREHOUSE.','.GoodsReceiptItem::DESTINATION_DIRECT,
        ], ['items.required' => 'Pilih minimal satu baris yang diterima.']);

        [, $lines] = $this->resolveSource($validated['source_type'], (int) $validated['source_id']);
        $lines = $lines->keyBy('source_line_id');

        $toWarehouse = collect($validated['items'])->contains('destination', GoodsReceiptItem::DESTINATION_WAREHOUSE);
        $warehouse = $toWarehouse ? Warehouse::active()->find($validated['warehouse_id'] ?? 0) : null;
        if ($toWarehouse && ! $warehouse) {
            return response()->json(['success' => false, 'message' => 'Pilih gudang tujuan (aktif) untuk barang yang masuk gudang.'], 422);
        }

        $rows = [];
        foreach (array_values($validated['items']) as $i => $row) {
            $line = $lines->get((int) $row['source_line_id']);
            if (! $line) {
                return response()->json(['success' => false, 'message' => 'Baris '.($i + 1).' tidak valid untuk sumber ini.'], 422);
            }
            if ((int) $row['qty'] > $line['remaining']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Qty baris '.($i + 1).' ('.($line['part_number'] ?: 'item').') melebihi sisa yang belum diterima ('.$line['remaining'].').',
                ], 422);
            }
            $rows[] = array_merge($line, ['qty' => (int) $row['qty'], 'destination' => $row['destination']]);
        }

        $receipt = DB::transaction(function () use ($validated, $warehouse, $rows) {
            $receipt = GoodsReceipt::create([
                'receipt_number' => GoodsReceipt::generateNumber(),
                'purchase_order_id' => $validated['source_type'] === self::SOURCE_PO ? $validated['source_id'] : null,
                'direct_purchase_id' => $validated['source_type'] === self::SOURCE_DP ? $validated['source_id'] : null,
                'warehouse_id' => $warehouse?->id,
                'received_date' => $validated['received_date'] ?? now()->toDateString(),
                'status' => GoodsReceipt::STATUS_DRAFT,
                'note' => $validated['note'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($rows as $i => $row) {
                $receipt->items()->create([
                    'purchase_order_item_id' => $validated['source_type'] === self::SOURCE_PO ? $row['source_line_id'] : null,
                    'direct_purchase_item_id' => $validated['source_type'] === self::SOURCE_DP ? $row['source_line_id'] : null,
                    'master_product_id' => $row['master_product_id'],
                    'qty' => $row['qty'],
                    'unit' => $row['unit'],
                    'currency' => $row['currency'],
                    'price' => $row['price'],
                    'destination' => $row['destination'],
                    'project_id' => $row['project_id'],
                    'sort_order' => $i + 1,
                ]);
            }

            $this->post($receipt->load('items'));

            return $receipt;
        });

        return response()->json([
            'success' => true,
            'message' => 'Penerimaan '.$receipt->receipt_number.' diposting.'.($warehouse ? ' Stok '.$warehouse->name.' bertambah.' : ''),
            'id' => $receipt->id,
        ]);
    }

    public function show($id)
    {
        $receipt = GoodsReceipt::with([
            'items.masterProduct',
            'items.project',
            'purchaseOrder',
            'directPurchase',
            'warehouse',
            'creator',
            'postedBy',
        ])->findOrFail($id);

        return view('goods-receipt.show', [
            'receipt' => $receipt,
            'documents' => $this->documentList($receipt),
        ]);
    }

    /**
     * Penerimaan yang sudah posted tidak bisa dihapus (koreksi lewat dokumen pembalik).
     */
    public function destroy($id): JsonResponse
    {
        $receipt = GoodsReceipt::findOrFail($id);

        if ($receipt->status !== GoodsReceipt::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Penerimaan yang sudah diposting tidak bisa dihapus.'], 422);
        }

        $receipt->delete();

        return response()->json(['success' => true, 'message' => 'Penerimaan dihapus.']);
    }

    /**
     * Posting: stok masuk untuk baris "Masuk Gudang" (beralokasi project bila ada),
     * lalu perbarui status penerimaan PO.
     */
    private function post(GoodsReceipt $receipt): void
    {
        foreach ($receipt->items as $item) {
            if ($item->destination !== GoodsReceiptItem::DESTINATION_WAREHOUSE) {
                continue;
            }

            StockMovement::record([
                'warehouse_id' => $receipt->warehouse_id,
                'master_product_id' => $item->master_product_id,
                'project_id' => $item->project_id,
                'type' => StockMovement::TYPE_RECEIPT,
                'qty' => $item->qty,
                'unit' => $item->unit,
                'unit_cost' => $item->price !== null
                    ? StockValuation::toBase($item->price, $item->currency, (float) ($receipt->purchaseOrder?->discount_percent ?? 0))
                    : null,
                'source_type' => StockMovement::SOURCE_GOODS_RECEIPT,
                'source_id' => $receipt->id,
                'source_line_id' => $item->id,
                'moved_at' => $receipt->received_date?->toDateString() ?? now()->toDateString(),
                'note' => 'Penerimaan '.$receipt->receipt_number,
            ]);
        }

        $receipt->update([
            'status' => GoodsReceipt::STATUS_POSTED,
            'posted_by' => Auth::id(),
            'posted_at' => now(),
        ]);

        $receipt->purchaseOrder?->refreshReceiptStatus();

        Log::record(
            'post_goods_receipt',
            'Penerimaan '.$receipt->receipt_number.' ('.$receipt->sourceNumber().') diposting oleh '.Auth::user()->display_name,
            'MOD_GOODS_RECEIPT',
            $receipt
        );
    }

    /**
     * Header & baris sumber penerimaan dalam format seragam (PO / DP).
     *
     * @return array{0: array, 1: Collection}
     */
    private function resolveSource(string $type, int $id): array
    {
        $fail = fn (string $message) => abort(response()->json(['success' => false, 'message' => $message], 422));

        if ($type === self::SOURCE_PO) {
            $po = PurchaseOrder::with(['items.masterProduct', 'items.goodsReceiptItems.goodsReceipt', 'items.goodsRequestItem.goodsRequest.project'])->findOrFail($id);
            if (! $po->isReceivable()) {
                $fail('Purchase order harus berstatus Approved / Diterima Sebagian untuk diterima.');
            }

            $lines = $po->items->map(fn (PurchaseOrderItem $item) => $this->line(
                $item->id, $item, $item->remainingReceiptQty(), $item->projectId(),
                GoodsReceiptItem::DESTINATION_WAREHOUSE, $item->goodsRequest()?->project
            ));

            return [[
                'type' => self::SOURCE_PO,
                'id' => $po->id,
                'number' => $po->po_number,
                'supplier_name' => $po->supplier_name,
            ], $lines];
        }

        $dp = DirectPurchase::with(['items.masterProduct', 'items.goodsReceiptItems.goodsReceipt', 'project'])->findOrFail($id);
        if ($dp->status !== DirectPurchase::STATUS_APPROVED) {
            $fail('Direct purchase harus berstatus Approved untuk diterima.');
        }

        $lines = $dp->items->map(fn (DirectPurchaseItem $item) => $this->line(
            $item->id, $item, $item->remainingReceiptQty(), $dp->allocationProjectId(),
            $dp->defaultReceiptDestination(), $dp->allocationProjectId() ? $dp->project : null
        ));

        return [[
            'type' => self::SOURCE_DP,
            'id' => $dp->id,
            'number' => $dp->dp_number,
            'supplier_name' => $dp->supplier_name,
            'purpose' => $dp->purpose,
        ], $lines];
    }

    private function line(int $lineId, PurchaseOrderItem|DirectPurchaseItem $item, int $remaining, ?int $projectId, string $destination, $project): array
    {
        return [
            'source_line_id' => $lineId,
            'master_product_id' => $item->master_product_id,
            'product_name' => $item->masterProduct?->name,
            'part_number' => $item->part_number,
            'description' => Quotation::renderDescription($item->description),
            'ordered' => (int) $item->qty,
            'remaining' => $remaining,
            'unit' => $item->unit,
            'currency' => $item->currency,
            'price' => $item->price,
            'project_id' => $projectId,
            'project_label' => $project ? $project->project_number.' — '.$project->project_name : null,
            'destination' => $destination,
        ];
    }

    protected function documentOwner($id): Model
    {
        return GoodsReceipt::findOrFail($id);
    }

    protected function documentRoutePrefix(): string
    {
        return 'goods-receipt';
    }

    protected function documentModuleCode(): string
    {
        return 'MOD_GOODS_RECEIPT';
    }
}
