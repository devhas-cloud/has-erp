<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\GoodsRequest;
use App\Models\GoodsRequestItem;
use App\Models\MasterProduct;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
 *
 * Nomor PO dibuat otomatis saat PO pertama kali disimpan (YYMM + 4 digit,
 * urutan bersama lintas jenis, acuan tanggal dibuat) dengan suffix jenis:
 * manufacture tanpa suffix, local "-L", calibration "-C". Ganti jenis selama
 * Draft hanya mengganti suffix.
 *
 * Mata uang: satu PO satu mata uang, mengikuti mata uang item Permintaan
 * Barang (dikunci) atau master product, TANPA konversi kurs — semua harga dan
 * total dalam mata uang PO. Harga otomatis dari harga beli (purchase_price)
 * master product.
 */
class PurchaseOrderController extends Controller
{
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
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%")->orWhere('full_name', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $orders = $query->orderBy('id', 'desc')->offset($start)->limit($length)->get();

        $data = [];
        foreach ($orders as $i => $po) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $po->id,
                'supplier_name' => $po->supplier_name,
                'po_number' => $po->po_number ?: '—',
                'po_type' => $po->po_type,
                'type_badge' => $po->typeBadgeHtml(),
                'date' => $po->date?->format('d/m/Y') ?? '—',
                'item_count' => $po->items_count,
                'grand_total_label' => $po->currencyCode().' '.number_format($po->netTotal(), 2, '.', ','),
                'creator_name' => $po->creator?->display_name ?? '—',
                'status' => $po->status,
                'status_label' => $po->status_label,
                'status_badge' => $po->statusBadgeHtml(),
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

        $purchaseOrder = $this->createWithNumber(fn () => DB::transaction(function () use ($validated) {
            $po = PurchaseOrder::create([
                'supplier_name' => $validated['supplier_name'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'supplier_address' => $validated['supplier_address'] ?? null,
                'supplier_phone' => $validated['supplier_phone'] ?? null,
                'supplier_fax' => $validated['supplier_fax'] ?? null,
                'supplier_attn' => $validated['supplier_attn'] ?? null,
                'po_number' => PurchaseOrder::generatePoNumber($validated['po_type']),
                'po_type' => $validated['po_type'],
                'date' => $validated['date'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'delivery' => $validated['delivery'] ?? null,
                'your_ref' => $validated['your_ref'] ?? null,
                'discount_percent' => $validated['discount_percent'] ?? null,
                'ppn_percent' => $validated['ppn_percent'] ?? null,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'notes' => $validated['notes'] ?? null,
                'request_by_name' => $validated['request_by_name'] ?? null,
                'finance_name' => $validated['finance_name'] ?? null,
                'accounting_name' => $validated['accounting_name'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($po, $validated['items'] ?? []);

            return $po;
        }));

        return response()->json([
            'success' => true,
            'message' => 'Purchase order '.$purchaseOrder->po_number.' berhasil dibuat.',
            'id' => $purchaseOrder->id,
            'po_number' => $purchaseOrder->po_number,
        ]);
    }

    public function show($id)
    {
        $purchaseOrder = PurchaseOrder::with(['items.goodsRequest.division', 'items.goodsRequest.opportunity', 'items.goodsRequest.project', 'items.goodsRequest.quotation', 'supplier', 'creator', 'finalChecker'])->findOrFail($id);

        return view('purchase-order.show', [
            'purchaseOrder' => $purchaseOrder,
            'poGroups' => $purchaseOrder->hierarchyGroups(),
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
                'selling_price' => $item->selling_price,
                'selling_price_currency' => $item->selling_price_currency,
                'purchase_price_currency' => $item->purchase_price_currency,
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
            'items.goodsRequest.project',
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
                'po_number' => $purchaseOrder->po_number
                    ? PurchaseOrder::numberWithType($purchaseOrder->po_number, $validated['po_type'])
                    : PurchaseOrder::generatePoNumber($validated['po_type'], $purchaseOrder->created_at?->toDateString()),
                'po_type' => $validated['po_type'],
                'date' => $validated['date'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'delivery' => $validated['delivery'] ?? null,
                'your_ref' => $validated['your_ref'] ?? null,
                'discount_percent' => $validated['discount_percent'] ?? null,
                'ppn_percent' => $validated['ppn_percent'] ?? null,
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
     * dipakai di Purchase Order manapun — lintas project/divisi, untuk modal
     * "Ambil dari Permintaan Barang". Dikelompokkan per Permintaan Barang dan
     * diberi identitas project (acuan grup item & label PROJECT di PDF).
     * Filter opsional project_id; daftar project yang tersedia ikut dikirim
     * untuk dropdown filter.
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
        }, 'division', 'opportunity', 'project'])
            ->where('status', GoodsRequest::STATUS_APPROVED)
            ->orderByDesc('id')
            ->get()
            ->filter(fn (GoodsRequest $gr) => $gr->items->isNotEmpty())
            ->values();

        $projects = $requests->map(fn (GoodsRequest $gr) => $gr->project)->filter()->unique('id')
            ->sortBy('project_number')
            ->map(fn ($project) => [
                'id' => $project->id,
                'text' => $project->project_number.' — '.$project->project_name,
            ])->values();

        if ($request->filled('project_id')) {
            $requests = $requests->where('project_id', (int) $request->input('project_id'))->values();
        }

        $data = $requests->map(function (GoodsRequest $gr) {
            $projectName = $gr->project?->project_name ?: ($gr->opportunity?->opportunity_name ?: 'Stock');

            return [
                'goods_request_id' => $gr->id,
                'project_id' => $gr->project_id,
                'project_number' => $gr->project?->project_number,
                'project_name' => $projectName,
                'division_name' => $gr->division?->division_name,
                'opportunity_name' => $gr->opportunity?->opportunity_name,
                'items' => $gr->items->map(fn (GoodsRequestItem $item) => [
                    'goods_request_item_id' => $item->id,
                    'quote_configuration_item_id' => $item->quote_configuration_item_id,
                    'master_product_id' => $item->master_product_id,
                    'project_name' => $projectName,
                    'part_number' => $item->part_number,
                    'description' => Quotation::renderDescription($item->description),
                    'qty' => $item->qty,
                    'unit' => $item->unit,
                    'currency' => GoodsRequestItem::normalizeCurrency($item->currency),
                ])->values(),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data, 'projects' => $projects]);
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
            ->get(['id', 'name', 'code', 'brand', 'category', 'description', 'selling_price', 'purchase_price', 'currency_id']);

        $data = $products->map(fn (MasterProduct $product) => [
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'brand' => $product->brand,
            'category' => $product->category,
            'description' => $product->description,
            // Harga PO ke supplier = harga beli master product (mata uang produk, tanpa kurs).
            'price' => (float) $product->purchase_price,
            'selling_price' => (float) $product->selling_price,
            'purchase_price_currency' => (float) $product->purchase_price,
            'currency' => $product->currency && ! $product->currency->isBase() ? strtoupper($product->currency->name) : $base,
            'rate' => $product->currency && ! $product->currency->isBase() ? (float) $product->currency->rate : 1,
        ])->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    private function validatePurchaseOrder(Request $request): array
    {
        $validated = $request->validate([
            'supplier_name' => 'required|string|max:200',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'supplier_address' => 'nullable|string',
            'supplier_phone' => 'nullable|string|max:50',
            'supplier_fax' => 'nullable|string|max:50',
            'supplier_attn' => 'nullable|string|max:150',
            'po_type' => 'nullable|in:'.implode(',', array_keys(PurchaseOrder::TYPE_LABELS)),
            'date' => 'nullable|date',
            'terms' => 'nullable|string|max:100',
            'delivery' => 'nullable|string|max:150',
            'your_ref' => 'nullable|string|max:100',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'ppn_percent' => 'nullable|numeric|min:0|max:100',
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
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'items.*.selling_price_currency' => 'nullable|numeric|min:0',
            'items.*.purchase_price_currency' => 'nullable|numeric|min:0',
            'items.*.currency' => 'nullable|string|max:10',
        ]);

        $validated['po_type'] ??= PurchaseOrder::TYPE_MANUFACTURE;

        return $validated;
    }

    /**
     * Buat PO dengan nomor otomatis. Bila dua request mendapat nomor yang sama
     * bersamaan, unique index po_number menolak salah satunya — dicoba ulang
     * dengan nomor berikutnya.
     */
    private function createWithNumber(callable $create, int $attempts = 3): PurchaseOrder
    {
        for ($i = 1; ; $i++) {
            try {
                return $create();
            } catch (QueryException $e) {
                $duplicateNumber = str_contains($e->getMessage(), 'po_number');
                if (! $duplicateNumber || $i >= $attempts) {
                    throw $e;
                }
            }
        }
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
     * selling_price_currency, diisi otomatis dari master product (Q2=A: tetap bisa
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

        if (empty($items)) {
            $purchaseOrder->items()->delete();

            return;
        }

        $productIds = collect($items)->pluck('master_product_id')->filter()->unique()->values();
        $products = MasterProduct::with('currency')
            ->whereIn('id', $productIds)
            ->get(['id', 'selling_price', 'purchase_price', 'currency_id'])
            ->keyBy('id');
        $baseCurrency = strtoupper((string) (Currency::where('is_base', true)->value('name') ?: 'IDR'));
        // Item dari Permintaan Barang wajib memakai mata uang item Permintaan Barang-nya.
        $goodsRequestCurrencies = GoodsRequestItem::whereIn('id', collect($items)->pluck('goods_request_item_id')->filter())
            ->pluck('currency', 'id');

        $now = now();
        $keyMap = [];
        $payload = [];

        foreach ($items as $i => $item) {
            $key = $item['_key'] ?? 'row-'.$i;
            $keyMap[$key] = $i;

            $isHeader = ($item['category'] ?? '') !== '';
            // Baris group (kategori) tidak punya harga/mata uang — supaya tidak
            // terhitung sebagai mata uang lain saat cek satu mata uang per PO.
            $pricing = $isHeader
                ? ['currency' => null, 'selling_price_currency' => null, 'selling_price' => null, 'purchase_price_currency' => null]
                : $this->pricingForItem($item, $products, $baseCurrency);

            $goodsRequestItemId = $item['goods_request_item_id'] ?? null;
            if (! $isHeader && $goodsRequestItemId && isset($goodsRequestCurrencies[$goodsRequestItemId])) {
                $pricing['currency'] = GoodsRequestItem::normalizeCurrency($goodsRequestCurrencies[$goodsRequestItemId]);
            }

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
                'selling_price' => $pricing['selling_price'],
                'selling_price_currency' => $pricing['selling_price_currency'],
                'purchase_price_currency' => $pricing['purchase_price_currency'],
                'currency' => $pricing['currency'],
                'sort_order' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Satu PO hanya boleh satu mata uang (mis. tidak boleh campur IDR dan USD).
        $currencies = collect($payload)->pluck('currency')->filter()->unique()->values();
        if ($currencies->count() > 1) {
            abort(response()->json([
                'success' => false,
                'message' => 'Semua item dalam satu PO harus memakai mata uang yang sama. Ditemukan: '.$currencies->implode(', ').'. Pisahkan ke PO berbeda.',
            ], 422));
        }

        $purchaseOrder->items()->delete();

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
     * Harga item dalam mata uang item, TANPA konversi kurs. Bila ada
     * master_product_id dan user belum mengirim harga, harga diisi dari harga
     * beli (purchase_price) master product beserta mata uangnya — tetap bisa
     * diedit/negosiasi supplier. selling_price disamakan dengan harga satuan
     * (bukan lagi hasil kurs ke Rupiah) karena total PO dihitung dalam mata uang PO.
     *
     * @return array{currency: ?string, selling_price_currency: ?float, selling_price: ?float, purchase_price_currency: float}
     */
    private function pricingForItem(array $item, Collection $products, string $baseCurrency): array
    {
        $productId = $item['master_product_id'] ?? null;
        $product = $productId ? $products->get($productId) : null;
        $productCurrency = $product && $product->currency && ! $product->currency->isBase()
            ? strtoupper($product->currency->name)
            : $baseCurrency;

        $rawPrice = $item['selling_price_currency'] ?? null;
        $hasPrice = $rawPrice !== null && $rawPrice !== '';

        if ($product && ! $hasPrice) {
            $price = round((float) $product->purchase_price, 2);
            $currency = $productCurrency;
        } else {
            $price = $hasPrice ? round((float) $rawPrice, 2) : null;
            $currency = ($item['currency'] ?? '') !== '' ? strtoupper($item['currency']) : ($product ? $productCurrency : $baseCurrency);
        }

        $purchasePrice = $item['purchase_price_currency'] ?? null;
        if ($purchasePrice === null || $purchasePrice === '') {
            $purchasePrice = $product ? (float) $product->purchase_price : 0.0;
        }

        return [
            'currency' => $currency,
            'selling_price_currency' => $price,
            'selling_price' => $price,
            'purchase_price_currency' => round((float) $purchasePrice, 2),
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
