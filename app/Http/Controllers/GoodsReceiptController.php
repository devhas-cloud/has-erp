<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPurchaseDocuments;
use App\Models\Currency;
use App\Models\Document;
use App\Models\DirectPurchase;
use App\Models\DirectPurchaseItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Log;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\StockCost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Penerimaan Barang (GRN) dari Purchase Order (Approved / Diterima Sebagian)
 * atau Direct Purchase (Approved). Penerimaan boleh bertahap, tidak boleh
 * melebihi sisa per baris. Disimpan langsung terposting:
 *   - baris "Masuk Gudang"   -> baris stok baru (stocks) di gudang terpilih,
 *     milik project bila barang untuk project. Material = 1 baris; barang ber-SN
 *     = 1 baris per unit (SN, batch, kedaluwarsa, garansi boleh menyusul);
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
        $suppliers = PurchaseOrder::whereHas('goodsReceipts')->whereNotNull('supplier_name')->pluck('supplier_name')
            ->merge(DirectPurchase::whereHas('goodsReceipts')->whereNotNull('supplier_name')->pluck('supplier_name'))
            ->unique()->sort()->values();

        return view('goods-receipt.index', [
            'suppliers' => $suppliers,
            'warehouses' => Warehouse::with('branch')->whereIn('id', GoodsReceipt::whereNotNull('warehouse_id')->select('warehouse_id'))->orderBy('name')->get(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = GoodsReceipt::withCount('items')->with(['purchaseOrder', 'directPurchase', 'warehouse.branch', 'creator', 'items:id,goods_receipt_id,master_product_id,qty,unit,destination', 'items.masterProduct:id,is_material']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('receipt_number', 'like', "%{$searchValue}%")
                    ->orWhere('note', 'like', "%{$searchValue}%")
                    ->orWhereHas('purchaseOrder', fn ($po) => $po->where('po_number', 'like', "%{$searchValue}%")->orWhere('supplier_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('directPurchase', fn ($dp) => $dp->where('dp_number', 'like', "%{$searchValue}%")->orWhere('supplier_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('warehouse', fn ($w) => $w->where('name', 'like', "%{$searchValue}%"));
            });
        }

        // Filter bar: jenis dokumen, gudang (direct = langsung pakai), supplier, tanggal terima, SN belum lengkap, milik saya.
        $supplier = $request->input('supplier');
        $query->when($request->input('source_type') === self::SOURCE_PO, fn ($q) => $q->whereNotNull('purchase_order_id'))
            ->when($request->input('source_type') === self::SOURCE_DP, fn ($q) => $q->whereNotNull('direct_purchase_id'))
            ->when($request->input('warehouse') === 'direct', fn ($q) => $q->whereNull('warehouse_id'))
            ->when(ctype_digit((string) $request->input('warehouse')), fn ($q) => $q->where('warehouse_id', (int) $request->input('warehouse')))
            ->when($supplier, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('purchaseOrder', fn ($po) => $po->where('supplier_name', $supplier))
                ->orWhereHas('directPurchase', fn ($dp) => $dp->where('supplier_name', $supplier))))
            ->when($request->input('date_from'), fn ($q, $from) => $q->whereDate('received_date', '>=', $from))
            ->when($request->input('date_to'), fn ($q, $to) => $q->whereDate('received_date', '<=', $to))
            ->when($request->boolean('missing_sn') || $request->input('kind') === 'missing_sn', fn ($q) => $q->whereHas('items.stocks', fn ($s) => $s->missingSerial()))
            // Jenis barang yang masuk gudang: material (menambah saldo) / barang ber-SN (per unit).
            ->when(in_array($request->input('kind'), ['material', 'serial'], true), fn ($q) => $q->whereHas('items', fn ($i) => $i
                ->where('destination', GoodsReceiptItem::DESTINATION_WAREHOUSE)
                ->whereHas('masterProduct', fn ($p) => $p->where('is_material', $request->input('kind') === 'material'))))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()));

        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $receipts = $query->orderBy('id', 'desc')->offset($start)->limit($length)->get();

        // Unit barang ber-SN yang SN-nya belum diisi, per penerimaan (satu query).
        $itemToReceipt = collect();
        foreach ($receipts as $r) {
            foreach ($r->items as $item) {
                $itemToReceipt[$item->id] = $r->id;
            }
        }
        $missing = $itemToReceipt->isEmpty() ? collect() : Stock::missingSerial()
            ->where('source_type', Stock::SOURCE_GOODS_RECEIPT_ITEM)
            ->whereIn('source_id', $itemToReceipt->keys())
            ->pluck('source_id')
            ->countBy(fn ($itemId) => $itemToReceipt[$itemId]);

        // Nilai masuk gudang = log masuk (harga modal saat diterima, mata uang dasar).
        $values = $itemToReceipt->isEmpty() ? collect() : StockLog::where('ref_type', StockLog::REF_GOODS_RECEIPT_ITEM)
            ->whereIn('ref_id', $itemToReceipt->keys())
            ->where('type', StockLog::TYPE_IN)
            ->get(['ref_id', 'qty', 'unit_cost'])
            ->groupBy(fn ($log) => $itemToReceipt[$log->ref_id])
            ->map(fn ($logs) => round($logs->sum(fn ($log) => $log->qty * $log->unit_cost), 2));

        $data = $receipts->map(fn (GoodsReceipt $receipt, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number ?: '—',
            'source' => $receipt->sourceNumber(),
            'source_kind' => $receipt->purchase_order_id ? 'PO' : 'DPO',
            'supplier_name' => $receipt->supplierName() ?? '—',
            'warehouse_name' => $receipt->warehouse?->name,
            'warehouse_branch' => $receipt->warehouse?->branch?->name,
            'received_date' => $receipt->received_date?->format('d/m/Y') ?? '—',
            'item_count' => $receipt->items_count,
            'material_lines' => $receipt->items->filter(fn ($i) => $i->destination === GoodsReceiptItem::DESTINATION_WAREHOUSE && $i->masterProduct?->isMaterial())->count(),
            'serial_units' => (int) $receipt->items->filter(fn ($i) => $i->destination === GoodsReceiptItem::DESTINATION_WAREHOUSE && $i->masterProduct && ! $i->masterProduct->isMaterial())->sum('qty'),
            'direct_lines' => $receipt->items->where('destination', GoodsReceiptItem::DESTINATION_DIRECT)->count(),
            'stock_value' => (float) ($values[$receipt->id] ?? 0),
            'creator' => $receipt->creator?->display_name,
            'source_url' => $receipt->purchase_order_id ? route('purchase-order.show', $receipt->purchase_order_id) : ($receipt->direct_purchase_id ? route('direct-purchase.show', $receipt->direct_purchase_id) : null),
            'missing_serial' => (int) ($missing[$receipt->id] ?? 0),
            'status' => $receipt->status,
            'status_label' => $receipt->status_label,
            'status_badge' => $receipt->statusBadgeHtml(),
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            // Penerimaan dengan unit ber-SN yang SN-nya belum diisi (semua data, untuk pengingat di atas tabel).
            'missing_total' => GoodsReceipt::whereHas('items.stocks', fn ($s) => $s->missingSerial())->count(),
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
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'baseSymbol' => Currency::where('is_base', true)->value('symbol') ?: Currency::baseName(),
            'purchaseOrders' => $purchaseOrders,
            'directPurchases' => $directPurchases,
            'uploadLimits' => self::uploadLimits(),
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
        $lines = $lines->filter(fn ($line) => $line['remaining'] > 0)->values();

        return response()->json([
            'success' => true,
            'source' => $header,
            'items' => $lines,
            'base_currency' => Currency::baseName(),
            'currencies' => $this->foreignCurrencies($lines, $header),
        ]);
    }

    /**
     * Mata uang asing pada baris sumber beserta kurs yang dipakai:
     * PO / Direct Purchase -> kurs yang ditetapkan di dokumen (locked); dokumen lama tanpa kurs ->
     * kurs master (boleh diubah).
     * known=false: kode tidak ada di master Currency -> penerimaan ditolak.
     */
    private function foreignCurrencies(Collection $lines, array $header): array
    {
        $poRate = $header['exchange_rate'] ?? null;

        return $lines->pluck('currency')
            ->map(fn ($c) => strtoupper(trim((string) $c)))
            ->reject(fn ($c) => Currency::isBaseCode($c))
            ->unique()
            ->values()
            ->map(function (string $code) use ($poRate) {
                $currency = Currency::findByCode($code);

                return [
                    'code' => $code,
                    'symbol' => $currency?->symbol ?: $code,
                    'known' => (bool) $currency,
                    'locked' => $poRate !== null,
                    'rate' => $poRate ?? ($currency ? (float) $currency->rate : null),
                    'updated_at' => $currency?->updated_at?->format('d/m/Y'),
                    'stale' => $currency ? $currency->isStale() : false,
                ];
            })
            ->all();
    }

    /** Batas upload PHP agar form bisa memperingatkan sebelum file terbuang diam-diam. */
    private static function uploadLimits(): array
    {
        $bytes = function (string $value): int {
            $value = trim($value);
            $unit = strtolower(substr($value, -1));
            $number = (float) $value;

            return (int) match ($unit) {
                'g' => $number * 1024 ** 3,
                'm' => $number * 1024 ** 2,
                'k' => $number * 1024,
                default => $number,
            };
        };

        return [
            'file' => \Illuminate\Http\UploadedFile::getMaxFilesize(),
            'post' => $bytes((string) ini_get('post_max_size')) ?: PHP_INT_MAX,
            'files' => (int) ini_get('max_file_uploads') ?: 20,
            'per_item' => Document::MAX_FILES,
        ];
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
            'items.*.serial_numbers' => 'nullable',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.warranty_until' => 'nullable|date',
            'items.*.note' => 'nullable|string|max:255',
            'items.*.photos' => 'nullable|array|max:'.Document::MAX_FILES,
            'items.*.photos.*' => Document::IMAGE_RULE,
            'rates' => 'nullable|array',
            'rates.*' => 'nullable|numeric|gt:0',
        ], [
            'items.required' => 'Pilih minimal satu baris yang diterima.',
            'rates.*.gt' => 'Exchange rate must be greater than 0.',
            'items.*.photos.max' => 'Up to '.Document::MAX_FILES.' photos per item.',
            'items.*.photos.*.mimes' => 'Photos must be JPG, PNG or WEBP.',
            'items.*.photos.*.max' => 'Each photo must not exceed 10MB.',
            'items.*.photos.*.uploaded' => 'A photo failed to upload (file too large for the server).',
        ]);

        [$header, $lines] = $this->resolveSource($validated['source_type'], (int) $validated['source_id']);
        $lines = $lines->keyBy('source_line_id');

        $toWarehouse = collect($validated['items'])->contains('destination', GoodsReceiptItem::DESTINATION_WAREHOUSE);
        $warehouse = $toWarehouse ? Warehouse::active()->find($validated['warehouse_id'] ?? 0) : null;
        if ($toWarehouse && ! $warehouse) {
            return response()->json(['success' => false, 'message' => 'Select an active destination warehouse for goods going into stock.'], 422);
        }

        $rows = [];
        foreach (array_values($validated['items']) as $i => $row) {
            $line = $lines->get((int) $row['source_line_id']);
            if (! $line) {
                return response()->json(['success' => false, 'message' => 'Line '.($i + 1).' is not valid for this source.'], 422);
            }
            if ((int) $row['qty'] > $line['remaining']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Qty on line '.($i + 1).' ('.($line['part_number'] ?: 'item').') exceeds the outstanding qty ('.$line['remaining'].').',
                ], 422);
            }
            $toStock = $row['destination'] === GoodsReceiptItem::DESTINATION_WAREHOUSE;
            if ($toStock && ! $line['master_product_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Line '.($i + 1).' ('.($line['part_number'] ?: 'item').') is not linked to a master product, so it cannot go into stock. Map the product first.',
                ], 422);
            }
            $rate = $this->receiptRate($line['currency'], $validated['rates'] ?? [], $header['exchange_rate'] ?? null);
            if (is_string($rate)) {
                return response()->json(['success' => false, 'message' => 'Line '.($i + 1).' ('.($line['part_number'] ?: 'item').'): '.$rate], 422);
            }
            // Material masuk ke satu saldo: batch / kedaluwarsa / garansi tidak dipakai (sementara disembunyikan).
            $tracked = $toStock && $line['serialized'];
            if (! empty($row['photos']) && ! $tracked) {
                return response()->json([
                    'success' => false,
                    'message' => 'Line '.($i + 1).' ('.($line['part_number'] ?: 'item').'): photos can only be attached to serial items going into the warehouse.',
                ], 422);
            }
            $rows[] = array_merge($line, [
                'qty' => (int) $row['qty'],
                'destination' => $row['destination'],
                'serial_numbers' => $toStock ? StockService::parseSerials($row['serial_numbers'] ?? null) : [],
                'batch_number' => $tracked ? ($row['batch_number'] ?? null) : null,
                'expiry_date' => $tracked ? ($row['expiry_date'] ?? null) : null,
                'warranty_until' => $tracked ? ($row['warranty_until'] ?? null) : null,
                'note' => $row['note'] ?? null,
                'exchange_rate' => $rate,
                'photos' => $tracked ? array_values($row['photos'] ?? []) : [],
            ]);
        }

        try {
            $receipt = $this->persist($validated, $warehouse, $rows);
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Receipt '.$receipt->receipt_number.' posted.'.($warehouse ? ' Stock in '.$warehouse->name.' increased.' : ''),
            'id' => $receipt->id,
        ]);
    }

    /**
     * Kurs penerimaan untuk satu mata uang: base = 1; PO = kurs yang ditetapkan di PO
     * (isian form diabaikan); Direct Purchase = isian form, default kurs master.
     * Mengembalikan pesan error (string) bila mata uang tidak dikenal / kurs tidak valid.
     */
    private function receiptRate(?string $currency, array $rates, ?float $poRate = null): float|string
    {
        if (Currency::isBaseCode($currency)) {
            return 1.0;
        }

        $code = strtoupper(trim((string) $currency));
        $master = Currency::findByCode($code);
        if (! $master) {
            return 'currency '.$code.' is not registered in the Currency master. Add it (with its rate) first.';
        }

        $rate = (float) ($poRate ?? $rates[$code] ?? $master->rate);

        return $rate > 0 ? $rate : 'enter a valid exchange rate for '.$code.'.';
    }

    private function persist(array $validated, ?Warehouse $warehouse, array $rows): GoodsReceipt
    {
        // File foto tersimpan di disk sebelum commit: bila transaksi gagal, file dihapus lagi.
        $storedPaths = [];
        try {
            return $this->persistRows($validated, $warehouse, $rows, $storedPaths);
        } catch (\Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk(Document::DISK)->delete($path);
            }
            throw $e;
        }
    }

    private function persistRows(array $validated, ?Warehouse $warehouse, array $rows, array &$storedPaths): GoodsReceipt
    {
        return DB::transaction(function () use ($validated, $warehouse, $rows, &$storedPaths) {
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
                $item = $receipt->items()->create([
                    'purchase_order_item_id' => $validated['source_type'] === self::SOURCE_PO ? $row['source_line_id'] : null,
                    'direct_purchase_item_id' => $validated['source_type'] === self::SOURCE_DP ? $row['source_line_id'] : null,
                    'master_product_id' => $row['master_product_id'],
                    'qty' => $row['qty'],
                    'unit' => $row['unit'],
                    'currency' => $row['currency'],
                    'price' => $row['price'],
                    'exchange_rate' => $row['exchange_rate'],
                    'destination' => $row['destination'],
                    'project_id' => $row['project_id'],
                    'batch_number' => $row['batch_number'],
                    'expiry_date' => $row['expiry_date'],
                    'warranty_until' => $row['warranty_until'],
                    'serial_numbers' => $row['serial_numbers'] ?: null,
                    'note' => $row['note'],
                    'sort_order' => $i + 1,
                ]);
                foreach ($row['photos'] ?? [] as $file) {
                    $storedPaths[] = Document::storeFor($item, $file, Document::TYPE_RECEIPT_PHOTO)->file_path;
                }
            }

            $this->post($receipt->load(['items.masterProduct', 'purchaseOrder', 'directPurchase']));

            return $receipt;
        });
    }

    public function show($id)
    {
        $receipt = GoodsReceipt::with([
            'items.masterProduct',
            'items.project',
            'items.stocks.masterProduct',
            'items.photos.uploader',
            'purchaseOrder',
            'directPurchase',
            'warehouse',
            'creator',
            'postedBy',
        ])->findOrFail($id);

        // Log masuk per baris: harga modal saat diterima & saldo material sesudahnya.
        $inLogs = StockLog::where('ref_type', StockLog::REF_GOODS_RECEIPT_ITEM)
            ->whereIn('ref_id', $receipt->items->pluck('id')->all() ?: [0])
            ->where('type', StockLog::TYPE_IN)
            ->orderBy('id')
            ->get()
            ->groupBy('ref_id');

        return view('goods-receipt.show', [
            'receipt' => $receipt,
            'photoLists' => $receipt->items->filter->acceptsPhotos()->mapWithKeys(fn (GoodsReceiptItem $item) => [$item->id => $this->photoList($item)]),
            'inLogs' => $inLogs,
            'documents' => $this->documentList($receipt),
        ]);
    }

    /**
     * Foto susulan untuk barang ber-SN (juga setelah posting). Hak akses lewat
     * middleware modul: POST -> can_create, GET -> can_read, DELETE -> can_delete.
     */
    public function uploadItemPhotos(Request $request, $id, $itemId): JsonResponse
    {
        $item = $this->photoItem($id, $itemId);
        if (! $item->acceptsPhotos()) {
            return response()->json(['success' => false, 'message' => 'Photos can only be attached to serial items going into the warehouse.'], 422);
        }

        $room = Document::MAX_FILES - $item->photos()->count();
        $request->validate([
            'files' => 'required|array|min:1|max:'.max($room, 0),
            'files.*' => Document::IMAGE_RULE,
        ], [
            'files.required' => 'Select at least one photo.',
            'files.max' => $room > 0 ? 'This item can take '.$room.' more photo(s) (max '.Document::MAX_FILES.').' : 'This item already has '.Document::MAX_FILES.' photos.',
            'files.*.mimes' => 'Photos must be JPG, PNG or WEBP.',
            'files.*.max' => 'Each photo must not exceed 10MB.',
            'files.*.uploaded' => 'A photo failed to upload (file too large for the server).',
        ]);

        $stored = [];
        try {
            DB::transaction(function () use ($request, $item, &$stored) {
                foreach ($request->file('files') as $file) {
                    $stored[] = Document::storeFor($item, $file, Document::TYPE_RECEIPT_PHOTO)->file_path;
                }
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                Storage::disk(Document::DISK)->delete($path);
            }
            throw $e;
        }

        Log::record('upload_goods_receipt_photo', count($stored).' foto barang diunggah ke '.$item->goodsReceipt->receipt_number.' oleh '.Auth::user()->display_name, $this->documentModuleCode(), $item->goodsReceipt);

        return response()->json([
            'success' => true,
            'message' => count($stored).' photo(s) uploaded.',
            'photos' => $this->photoList($item),
        ]);
    }

    public function viewItemPhoto($id, $itemId, $documentId)
    {
        return $this->photoItem($id, $itemId)->photos()->findOrFail($documentId)->inlineResponse();
    }

    public function deleteItemPhoto($id, $itemId, $documentId): JsonResponse
    {
        $item = $this->photoItem($id, $itemId);
        $document = $item->photos()->findOrFail($documentId);

        if (! $this->canDeletePhoto($document)) {
            return response()->json(['success' => false, 'message' => 'Only the uploader can delete this photo.'], 422);
        }

        $document->delete();

        return response()->json(['success' => true, 'message' => 'Photo deleted.', 'photos' => $this->photoList($item)]);
    }

    private function photoItem($id, $itemId): GoodsReceiptItem
    {
        return GoodsReceiptItem::with(['masterProduct', 'goodsReceipt'])
            ->where('goods_receipt_id', $id)
            ->findOrFail($itemId);
    }

    private function canDeletePhoto(Document $document): bool
    {
        return Auth::user()?->role === 'Admin' || (int) $document->uploaded_by === (int) Auth::id();
    }

    /** Daftar foto untuk UI halaman detail. */
    private function photoList(GoodsReceiptItem $item): array
    {
        return $item->photos()->with('uploader')->get()->map(fn (Document $doc) => [
            'id' => $doc->id,
            'url' => route('goods-receipt.photos.show', [$item->goods_receipt_id, $item->id, $doc->id]),
            'delete_url' => $this->canDeletePhoto($doc) ? route('goods-receipt.photos.destroy', [$item->goods_receipt_id, $item->id, $doc->id]) : null,
            'title' => ($doc->uploader?->display_name ?? '—').' · '.$doc->created_at?->format('d/m/Y H:i'),
        ])->values()->all();
    }

    /**
     * Penerimaan yang sudah posted tidak bisa dihapus (koreksi lewat dokumen pembalik).
     */
    public function destroy($id): JsonResponse
    {
        $receipt = GoodsReceipt::findOrFail($id);

        if ($receipt->status !== GoodsReceipt::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'A posted receipt cannot be deleted.'], 422);
        }

        $receipt->delete();

        return response()->json(['success' => true, 'message' => 'Receipt deleted.']);
    }

    /**
     * Posting: stok masuk untuk baris "Masuk Gudang" (beralokasi project bila ada),
     * lalu perbarui status penerimaan PO.
     */
    private function post(GoodsReceipt $receipt): void
    {
        $service = app(StockService::class);
        $date = $receipt->received_date?->toDateString() ?? now()->toDateString();
        $supplierId = $receipt->purchaseOrder?->supplier_id ?? $receipt->directPurchase?->supplier_id;

        foreach ($receipt->items as $item) {
            if ($item->destination !== GoodsReceiptItem::DESTINATION_WAREHOUSE) {
                continue;
            }

            $service->receive($item->masterProduct, (float) $item->qty, [
                'warehouse_id' => $receipt->warehouse_id,
                'project_id' => $item->project_id,
                'received_date' => $date,
                'expiry_date' => $item->expiry_date,
                'batch_number' => $item->batch_number,
                'supplier_id' => $supplierId,
                'warranty_until' => $item->warranty_until,
                'unit' => $item->unit,
                'unit_cost' => $item->price !== null
                    ? StockCost::toBase((float) $item->price, $item->currency, (float) ($receipt->purchaseOrder?->discount_percent ?? 0), $item->exchange_rate)
                    : StockCost::standardCost((int) $item->master_product_id),
                'source_type' => Stock::SOURCE_GOODS_RECEIPT_ITEM,
                'source_id' => $item->id,
                'note' => $item->note,
            ], $item->serial_numbers ?? [], [
                'date' => $date,
                'ref_type' => StockLog::REF_GOODS_RECEIPT_ITEM,
                'ref_id' => $item->id,
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
                $fail('The purchase order must be Approved / Partially Received to receive goods.');
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
                'discount_percent' => (float) ($po->discount_percent ?? 0),
                // Kurs ditetapkan di PO (terkunci saat terima); PO valas lama tanpa kurs -> isian form, default kurs master.
                'exchange_rate' => $po->currencyCode() !== Currency::baseName() && $po->exchange_rate ? (float) $po->exchange_rate : null,
            ], $lines];
        }

        $dp = DirectPurchase::with(['items.masterProduct', 'items.goodsReceiptItems.goodsReceipt', 'project'])->findOrFail($id);
        if ($dp->status !== DirectPurchase::STATUS_APPROVED) {
            $fail('The direct purchase must be Approved to receive goods.');
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
            'purpose' => $dp->purpose_label,
            // Kurs ditetapkan di DP (terkunci saat terima); DP valas lama tanpa kurs -> isian form, default kurs master.
            'exchange_rate' => $dp->currencyCode() !== Currency::baseName() && $dp->exchange_rate ? (float) $dp->exchange_rate : null,
        ], $lines];
    }

    private function line(int $lineId, PurchaseOrderItem|DirectPurchaseItem $item, int $remaining, ?int $projectId, string $destination, $project): array
    {
        return [
            'source_line_id' => $lineId,
            'master_product_id' => $item->master_product_id,
            'product_code' => $item->masterProduct?->code,
            'product_name' => $item->masterProduct?->name,
            // Barang ber-SN: 1 baris stok per unit, SN boleh menyusul.
            'serialized' => $item->masterProduct ? ! $item->masterProduct->isMaterial() : false,
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
