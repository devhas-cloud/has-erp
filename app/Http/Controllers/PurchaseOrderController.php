<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\GoodsRequest;
use App\Models\GoodsRequestItem;
use App\Models\MasterProduct;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Support\ModuleAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Modul "Purchase Order": PO barang ke supplier, disusun dari item Permintaan
 * Barang yang sudah Approved. Satu PO boleh menggabungkan item dari beberapa
 * Permintaan Barang berbeda — termasuk lintas divisi — selama tujuannya
 * supplier yang sama. Satu item Permintaan Barang hanya boleh masuk ke SATU
 * PO manapun (dijaga unique constraint di goods_request_items.id pada
 * purchase_order_items, plus dicek ulang di controller). Harga baru muncul
 * di modul ini — sesuai keputusan bahwa Permintaan Barang tidak perlu tahu
 * harga, harga ditentukan saat PO ke supplier.
 */
class PurchaseOrderController extends Controller
{
    private const MODULE_CODE = 'MOD_PURCHASE_ORDER';

    public function index()
    {
        return view('purchase-order.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = PurchaseOrder::withCount(['items' => fn ($q) => $q->whereNull('category')])->with('creator');

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('supplier_name', 'like', "%{$searchValue}%")
                    ->orWhere('po_number', 'like', "%{$searchValue}%")
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $orders = $query->orderBy('id', 'desc')->offset($start)->limit($length)->get();

        $canApprove = ModuleAccess::for()->module(self::MODULE_CODE)->canApprove();

        $data = [];
        foreach ($orders as $i => $po) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $po->id,
                'supplier_name' => $po->supplier_name,
                'po_number' => $po->po_number ?: '—',
                'date' => $po->date?->format('d/m/Y') ?? '—',
                'item_count' => $po->items_count,
                'grand_total_label' => number_format($po->grandTotal(), 0, '.', ','),
                'creator_name' => $po->creator?->username ?? '—',
                'status' => $po->status,
                'status_label' => $po->status_label,
                'status_badge' => $po->statusBadgeHtml(),
                'can_approve' => $canApprove && $po->status === PurchaseOrder::STATUS_WAITING_APPROVAL,
                'is_creator' => (int) $po->created_by === (int) Auth::id(),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create()
    {
        return view('purchase-order.form', [
            'purchaseOrder' => null,
            'items' => [],
            'currencies' => Currency::formOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePurchaseOrder($request);

        $purchaseOrder = DB::transaction(function () use ($validated) {
            $po = PurchaseOrder::create([
                'supplier_name' => $validated['supplier_name'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'supplier_address' => $validated['supplier_address'] ?? null,
                'supplier_phone' => $validated['supplier_phone'] ?? null,
                'supplier_fax' => $validated['supplier_fax'] ?? null,
                'supplier_attn' => $validated['supplier_attn'] ?? null,
                'po_number' => $validated['po_number'] ?? null,
                'date' => $validated['date'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'notes' => $validated['notes'] ?? null,
                'request_by_name' => $validated['request_by_name'] ?? null,
                'finance_name' => $validated['finance_name'] ?? null,
                'accounting_name' => $validated['accounting_name'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($po, $validated['items'] ?? []);

            return $po;
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase order berhasil dibuat.',
            'id' => $purchaseOrder->id,
        ]);
    }

    public function show($id)
    {
        $purchaseOrder = PurchaseOrder::with(['items.goodsRequest.division', 'items.goodsRequest.opportunity', 'items.goodsRequest.quotation', 'supplier', 'creator', 'finalChecker'])->findOrFail($id);

        return view('purchase-order.show', [
            'purchaseOrder' => $purchaseOrder,
            'poGroups' => $purchaseOrder->hierarchyGroups(),
            'canApprove' => ModuleAccess::for()->module(self::MODULE_CODE)->canApprove(),
            'canUpdate' => ModuleAccess::for()->module(self::MODULE_CODE)->canManage(),
        ]);
    }

    public function edit($id)
    {
        $purchaseOrder = PurchaseOrder::with(['items'])->findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return redirect()->route('purchase-order.index')
                ->with('error', 'Hanya purchase order berstatus Draft yang bisa diedit.');
        }

        return view('purchase-order.form', [
            'purchaseOrder' => $purchaseOrder,
            'items' => $purchaseOrder->items->map(fn ($item) => [
                '_key' => 'db-'.$item->id,
                'parent_key' => $item->parent_id ? 'db-'.$item->parent_id : null,
                'category' => $item->category,
                'id' => $item->id,
                'goods_request_item_id' => $item->goods_request_item_id,
                'goods_request_id' => $item->goods_request_id,
                'master_product_id' => $item->master_product_id,
                'part_number' => $item->part_number,
                'description' => Quotation::renderDescription($item->description),
                'qty' => $item->qty,
                'unit' => $item->unit,
                'price' => $item->price,
                'price_currency' => $item->price_currency,
                'currency' => $item->currency,
            ])->all(),
            'currencies' => Currency::formOptions(),
        ]);
    }

    public function pdf($id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'items.goodsRequest.division',
            'items.goodsRequest.opportunity',
            'supplier',
            'creator',
        ])->findOrFail($id);

        $printCurrency = $purchaseOrder->printCurrency();
        $currencySymbol = $printCurrency
            ? (Currency::where('name', $printCurrency)->value('symbol') ?: $printCurrency)
            : 'Rp';

        $pdf = Pdf::loadView('purchase-order.pdf', compact('purchaseOrder', 'currencySymbol'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('PO-'.($purchaseOrder->po_number ?: $purchaseOrder->id).'.pdf');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya purchase order berstatus Draft yang bisa diubah.'], 422);
        }

        $validated = $this->validatePurchaseOrder($request);

        DB::transaction(function () use ($purchaseOrder, $validated) {
            $purchaseOrder->update([
                'supplier_name' => $validated['supplier_name'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'supplier_address' => $validated['supplier_address'] ?? null,
                'supplier_phone' => $validated['supplier_phone'] ?? null,
                'supplier_fax' => $validated['supplier_fax'] ?? null,
                'supplier_attn' => $validated['supplier_attn'] ?? null,
                'po_number' => $validated['po_number'] ?? null,
                'date' => $validated['date'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'request_by_name' => $validated['request_by_name'] ?? null,
                'finance_name' => $validated['finance_name'] ?? null,
                'accounting_name' => $validated['accounting_name'] ?? null,
            ]);

            $this->syncItems($purchaseOrder, $validated['items'] ?? []);
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase order berhasil diperbarui.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya purchase order berstatus Draft yang bisa dihapus.'], 422);
        }

        // Middleware (DELETE -> can_delete) sudah menjaga izin hapus; tidak perlu
        // dicek ulang di sini — itu justru salah menolak user yang hanya diberi can_delete.

        $purchaseOrder->delete();

        return response()->json(['success' => true, 'message' => 'Purchase order dihapus.']);
    }

    public function submit($id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya purchase order berstatus Draft yang bisa di-submit.'], 422);
        }

        if ($purchaseOrder->items()->count() === 0) {
            return response()->json(['success' => false, 'message' => 'Tambahkan minimal satu item sebelum submit.'], 422);
        }

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_WAITING_APPROVAL]);

        return response()->json(['success' => true, 'message' => 'Purchase order dikirim untuk approval.']);
    }

    public function approve($id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_WAITING_APPROVAL) {
            return response()->json(['success' => false, 'message' => 'Purchase order ini tidak sedang menunggu approval.'], 422);
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_APPROVED,
            'final_checked_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Purchase order disetujui.']);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_WAITING_APPROVAL) {
            return response()->json(['success' => false, 'message' => 'Purchase order ini tidak sedang menunggu approval.'], 422);
        }

        $validated = $request->validate([
            'approval_note' => 'required|string',
        ]);

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_REJECTED,
            'final_checked_by' => Auth::id(),
            'approval_note' => $validated['approval_note'],
            'rejected_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Purchase order ditolak.']);
    }

    /**
     * Item dari SEMUA Permintaan Barang berstatus Approved yang belum pernah
     * dipakai di Purchase Order manapun — lintas divisi/opportunity, untuk
     * modal "Ambil dari Permintaan Barang". Dikelompokkan per Permintaan
     * Barang saat dikirim balik supaya jelas asalnya.
     */
    public function fetchAvailableItems(Request $request): JsonResponse
    {
        $excludeGoodsRequestItemIds = [];
        if ($request->filled('exclude_goods_request_item_ids')) {
            $excludeGoodsRequestItemIds = array_filter((array) $request->input('exclude_goods_request_item_ids'));
        }

        $requests = GoodsRequest::with(['items' => function ($q) use ($excludeGoodsRequestItemIds) {
            $q->whereDoesntHave('purchaseOrderItem');
            if (! empty($excludeGoodsRequestItemIds)) {
                $q->whereNotIn('id', $excludeGoodsRequestItemIds);
            }
        }, 'division', 'opportunity', 'quotation'])
            ->where('status', GoodsRequest::STATUS_APPROVED)
            ->get()
            ->filter(fn (GoodsRequest $gr) => $gr->items->isNotEmpty())
            ->values();

        $data = $requests->map(fn (GoodsRequest $gr) => [
            'goods_request_id' => $gr->id,
            'division_name' => $gr->division?->division_name,
            'opportunity_name' => $gr->opportunity?->opportunity_name,
            'quotation_number' => $gr->quotation?->quotation_number,
            'items' => $gr->items->map(fn (GoodsRequestItem $item) => [
                'goods_request_item_id' => $item->id,
                'quote_configuration_item_id' => $item->quote_configuration_item_id,
                'master_product_id' => $item->master_product_id,
                'project_name' => $gr->opportunity?->opportunity_name,
                'part_number' => $item->part_number,
                'description' => Quotation::renderDescription($item->description),
                'qty' => $item->qty,
                'unit' => $item->unit,
            ])->values(),
        ])->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Katalog Master Product (aktif, lintas divisi — PO bisa menggabungkan
     * item lintas divisi) untuk picker produk di kolom Part Number pada form
     * PO. Mengembalikan harga + mata uang agar saat produk dipilih, currency
     * dan harga satuan langsung terisi (masih bisa diedit / negosiasi supplier).
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        $base = Currency::baseName();

        $products = MasterProduct::with('currency')
            ->active()
            ->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'code', 'brand', 'category', 'description', 'price', 'currency_id']);

        $data = $products->map(fn (MasterProduct $product) => [
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'brand' => $product->brand,
            'category' => $product->category,
            'description' => $product->description,
            'price' => (float) $product->price,
            'currency' => $product->currency && ! $product->currency->isBase() ? strtoupper($product->currency->name) : $base,
            'rate' => $product->currency && ! $product->currency->isBase() ? (float) $product->currency->rate : 1,
        ])->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    private function validatePurchaseOrder(Request $request): array
    {
        return $request->validate([
            'supplier_name' => 'required|string|max:200',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'supplier_address' => 'nullable|string',
            'supplier_phone' => 'nullable|string|max:50',
            'supplier_fax' => 'nullable|string|max:50',
            'supplier_attn' => 'nullable|string|max:150',
            'po_number' => 'nullable|string|max:50',
            'date' => 'nullable|date',
            'terms' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'request_by_name' => 'nullable|string|max:150',
            'finance_name' => 'nullable|string|max:150',
            'accounting_name' => 'nullable|string|max:150',
            'items' => 'nullable|array',
            'items.*._key' => 'nullable|string',
            'items.*.parent_key' => 'nullable|string',
            'items.*.category' => 'nullable|string|max:200',
            'items.*.goods_request_item_id' => 'nullable|integer|exists:goods_request_items,id',
            'items.*.goods_request_id' => 'nullable|integer|exists:goods_requests,id',
            'items.*.master_product_id' => 'nullable|integer|exists:master_products,id',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.description' => 'nullable|string',
            'items.*.qty' => 'nullable|integer|min:0',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.price_currency' => 'nullable|numeric|min:0',
            'items.*.currency' => 'nullable|string|max:10',
        ]);
    }

    /**
     * Simpan item PO dengan hirarki parent-child (baris kategori sebagai parent).
     * goods_request_item_id divalidasi ulang di sini (selain unique constraint di
     * DB) supaya pesan error jelas kalau ternyata sudah dipakai PO lain sejak
     * form dibuka (race condition).
     *
     * Flow dua-pass (pola water/ims syncItems):
     *   1. insert semua baris (parent & item) tanpa parent_id
     *   2. ambil id hasil insert (urutan sama dengan payload)
     *   3. pasang parent_id berdasar parent_key
     *
     * Harga: bila baris punya master_product_id dan user belum mengirim
     * price_currency, diisi otomatis dari master product (Q2=A: tetap bisa
     * diedit/negosiasi supplier).
     */
    private function syncItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $items = array_values($items);

        $goodsRequestItemIds = collect($items)->pluck('goods_request_item_id')->filter()->values();

        if ($goodsRequestItemIds->isNotEmpty()) {
            $alreadyOrdered = PurchaseOrderItem::whereIn('goods_request_item_id', $goodsRequestItemIds)
                ->where('purchase_order_id', '!=', $purchaseOrder->id)
                ->exists();

            if ($alreadyOrdered) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Salah satu item sudah dipakai di Purchase Order lain. Muat ulang halaman dan coba lagi.',
                ], 422));
            }
        }

        $purchaseOrder->items()->delete();

        if (empty($items)) {
            return;
        }

        $productIds = collect($items)->pluck('master_product_id')->filter()->unique()->values();
        $products = MasterProduct::with('currency')
            ->whereIn('id', $productIds)
            ->get(['id', 'price', 'currency_id'])
            ->keyBy('id');
        $baseCurrency = strtoupper((string) (Currency::where('is_base', true)->value('name') ?: 'IDR'));

        $now = now();
        $keyMap = [];
        $payload = [];

        foreach ($items as $i => $item) {
            $key = $item['_key'] ?? 'row-'.$i;
            $keyMap[$key] = $i;

            $pricing = $this->pricingForItem($item, $products, $baseCurrency);

            $payload[] = [
                'purchase_order_id' => $purchaseOrder->id,
                'parent_id' => null,
                'category' => ($item['category'] ?? '') !== '' ? $item['category'] : null,
                'goods_request_item_id' => $item['goods_request_item_id'] ?? null,
                'goods_request_id' => $item['goods_request_id'] ?? null,
                'master_product_id' => $item['master_product_id'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'description' => Quotation::sanitizeDescription($item['description'] ?? ''),
                'qty' => isset($item['qty']) && $item['qty'] !== '' ? (int) $item['qty'] : null,
                'unit' => $item['unit'] ?? null,
                'price' => $pricing['price'],
                'price_currency' => $pricing['price_currency'],
                'currency' => $pricing['currency'],
                'sort_order' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        try {
            PurchaseOrderItem::insert($payload);
        } catch (QueryException $e) {
            abort(response()->json([
                'success' => false,
                'message' => 'Salah satu item sudah dipakai di Purchase Order lain. Muat ulang halaman dan coba lagi.',
            ], 422));
        }

        // Id hasil insert berurutan sama dengan urutan payload.
        $ids = PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $parentByChildId = [];
        foreach ($items as $i => $item) {
            $parentKey = $item['parent_key'] ?? null;
            if (! $parentKey || ! isset($keyMap[$parentKey]) || $keyMap[$parentKey] === $i) {
                continue;
            }
            if (isset($ids[$i], $ids[$keyMap[$parentKey]])) {
                $parentByChildId[$ids[$i]] = $ids[$keyMap[$parentKey]];
            }
        }

        $this->assignParents($parentByChildId);
    }

    /**
     * Harga item: bila ada master_product_id dan user belum mengirim
     * price_currency/currency, diisi dari master product (currency + harga asli
     * sebelum kurs + harga IDR sesudah kurs). Nilai yang dikirim user (hasil
     * edit/negosiasi) tetap dipakai.
     *
     * @return array{currency: ?string, price_currency: ?float, price: ?float}
     */
    private function pricingForItem(array $item, \Illuminate\Support\Collection $products, string $baseCurrency): array
    {
        $productId = $item['master_product_id'] ?? null;
        $product = $productId ? $products->get($productId) : null;

        $rawPriceCurrency = $item['price_currency'] ?? null;
        $hasPriceCurrency = $rawPriceCurrency !== null && $rawPriceCurrency !== '';

        if ($product && ! $hasPriceCurrency) {
            $priceCurrency = round((float) $product->price, 2);
            $currency = $product->currency && ! $product->currency->isBase()
                ? strtoupper($product->currency->name)
                : $baseCurrency;
        } else {
            $priceCurrency = $hasPriceCurrency ? (float) $rawPriceCurrency : null;
            $currency = ($item['currency'] ?? '') !== '' ? $item['currency'] : ($product && $product->currency && ! $product->currency->isBase() ? strtoupper($product->currency->name) : $baseCurrency);
        }

        $rate = 1.0;
        if ($currency && $currency !== $baseCurrency) {
            $rate = (float) (Currency::where('name', $currency)->value('rate') ?? 1);
        }

        $price = $item['price'] ?? null;
        if (($price === null || $price === '') && $priceCurrency !== null) {
            $price = round($priceCurrency * $rate, 2);
        }

        return [
            'currency' => $currency,
            'price_currency' => $priceCurrency,
            'price' => $price !== null && $price !== '' ? round((float) $price, 2) : null,
        ];
    }

    /**
     * Pasang parent_id banyak baris dalam satu query:
     * UPDATE ... SET parent_id = CASE id WHEN ? THEN ? ... END WHERE id IN (...).
     *
     * @param  array<int,int>  $parentByChildId  [child_id => parent_id]
     */
    private function assignParents(array $parentByChildId): void
    {
        if (empty($parentByChildId)) {
            return;
        }

        $cases = '';
        $bindings = [];
        foreach ($parentByChildId as $childId => $parentId) {
            $cases .= ' WHEN ? THEN ?';
            $bindings[] = $childId;
            $bindings[] = $parentId;
        }

        $childIds = array_keys($parentByChildId);
        $placeholders = implode(',', array_fill(0, count($childIds), '?'));

        DB::update(
            "UPDATE purchase_order_items SET parent_id = CASE id{$cases} END WHERE id IN ({$placeholders})",
            array_merge($bindings, $childIds)
        );
    }
}
