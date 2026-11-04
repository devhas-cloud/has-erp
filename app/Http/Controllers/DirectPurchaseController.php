<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPurchaseDocuments;
use App\Models\Currency;
use App\Models\DirectPurchase;
use App\Models\DirectPurchaseItem;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\SupplierBill;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Modul "Direct Purchase": pembelian langsung tanpa Permintaan Barang / PO formal.
 * Purpose menentukan tujuan barang saat diterima lewat Penerimaan Barang:
 *   - stock   : masuk gudang stok (stok bebas);
 *   - project : masuk gudang transit, dialokasikan ke project;
 *   - direct  : langsung pakai (konfirmasi terima saja, tidak masuk stok).
 * Item wajib master product. Approval satu approver (can_approve). Satu dokumen
 * satu mata uang, tanpa kurs. Tanpa terms/delivery/your ref/diskon.
 */
class DirectPurchaseController extends Controller
{
    use ManagesPurchaseDocuments;

    public function index()
    {
        return view('direct-purchase.index', [
            'suppliers' => DirectPurchase::whereNotNull('supplier_name')->distinct()->orderBy('supplier_name')->pluck('supplier_name'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = DirectPurchase::withCount('items')->with('creator');

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('supplier_name', 'like', "%{$searchValue}%")
                    ->orWhere('dp_number', 'like', "%{$searchValue}%")
                    ->orWhereHas('items', fn ($i) => $i->where('part_number', 'like', "%{$searchValue}%"))
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%")->orWhere('full_name', 'like', "%{$searchValue}%"));
            });
        }

        // Filter bar: status, supplier, tanggal (tanggal dokumen, fallback tanggal dibuat), milik saya.
        $query->when($request->input('supplier'), fn ($q, $supplier) => $q->where('supplier_name', $supplier))
            ->when($request->input('date_from'), fn ($q, $from) => $q->whereRaw('DATE(COALESCE(date, created_at)) >= ?', [$from]))
            ->when($request->input('date_to'), fn ($q, $to) => $q->whereRaw('DATE(COALESCE(date, created_at)) <= ?', [$to]))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()))
            ->when($request->input('purpose'), fn ($q, $purpose) => $q->where('purpose', $purpose));

        $statusCounts = (clone $query)->reorder()->toBase()
            ->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $orders = $query->orderBy('id', 'desc')->offset($start)->limit($length)->get();

        $data = [];
        foreach ($orders as $i => $dp) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $dp->id,
                'supplier_name' => $dp->supplier_name,
                'dp_number' => $dp->dp_number ?: '—',
                'date' => $dp->date?->format('d/m/Y') ?? '—',
                'item_count' => $dp->items_count,
                'purpose' => $dp->purpose,
                'purpose_label' => $dp->purpose_label,
                'grand_total_label' => $dp->currencyCode().' '.number_format($dp->netTotal(), 2, '.', ','),
                'creator_name' => $dp->creator?->display_name ?? '—',
                'status' => $dp->status,
                'status_label' => $dp->status_label,
                'status_badge' => $dp->statusBadgeHtml(),
                'is_creator' => (int) $dp->created_by === (int) Auth::id(),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'status_counts' => collect(DirectPurchase::STATUS_LABELS)->map(fn ($label, $key) => (int) ($statusCounts[$key] ?? 0)),
            'data' => $data,
        ]);
    }

    public function create()
    {
        return view('direct-purchase.form', [
            'directPurchase' => null,
            'projects' => $this->projectOptions(),
            'items' => [],
            'currencies' => Currency::formOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateDirectPurchase($request);

        $directPurchase = $this->createWithNumber(fn () => DB::transaction(function () use ($validated) {
            $dp = DirectPurchase::create([
                'supplier_name' => $validated['supplier_name'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'supplier_phone' => $validated['supplier_phone'] ?? null,
                'dp_number' => DirectPurchase::generateNumber(),
                'date' => $validated['date'] ?? null,
                'payment_term_days' => $validated['payment_term_days'],
                'ppn_percent' => $validated['ppn_percent'] ?? null,
                'purpose' => $validated['purpose'],
                'project_id' => $validated['purpose'] === DirectPurchase::PURPOSE_PROJECT ? $validated['project_id'] : null,
                'status' => DirectPurchase::STATUS_DRAFT,
                'notes' => $validated['notes'] ?? null,
                'request_by_name' => $validated['request_by_name'] ?? null,
                'finance_name' => $validated['finance_name'] ?? null,
                'accounting_name' => $validated['accounting_name'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($dp, $validated['items'] ?? []);

            return $dp;
        }));

        return response()->json([
            'success' => true,
            'message' => 'Direct purchase '.$directPurchase->dp_number.' berhasil dibuat.',
            'id' => $directPurchase->id,
            'dp_number' => $directPurchase->dp_number,
        ]);
    }

    public function show($id)
    {
        $directPurchase = DirectPurchase::with([
            'items.masterProduct',
            'items.goodsReceiptItems.goodsReceipt',
            'goodsReceipts.warehouse',
            'supplierBills.allocations.payment',
            'project',
            'supplier',
            'creator',
            'finalChecker',
        ])->findOrFail($id);

        return view('direct-purchase.show', [
            'directPurchase' => $directPurchase,
            'documents' => $this->documentList($directPurchase),
        ]);
    }

    public function edit($id)
    {
        $directPurchase = DirectPurchase::with(['items.masterProduct', 'project'])->findOrFail($id);

        if ($directPurchase->status !== DirectPurchase::STATUS_DRAFT) {
            return redirect()->route('direct-purchase.index')
                ->with('error', 'Hanya direct purchase berstatus Draft yang bisa diedit.');
        }

        return view('direct-purchase.form', [
            'directPurchase' => $directPurchase,
            'projects' => $this->projectOptions(),
            'items' => $directPurchase->items->map(fn ($item) => [
                'id' => $item->id,
                'master_product_id' => $item->master_product_id,
                'product_name' => $item->masterProduct?->name,
                'part_number' => $item->part_number,
                'description' => Quotation::renderDescription($item->description),
                'qty' => $item->qty,
                'unit' => $item->unit,
                'price' => $item->price,
                'currency' => $item->currency,
            ])->all(),
            'currencies' => Currency::formOptions(),
        ]);
    }

    public function pdf($id)
    {
        $directPurchase = DirectPurchase::with([
            'items.masterProduct',
            'project',
            'supplier',
            'creator',
        ])->findOrFail($id);

        $currencyCode = $directPurchase->currencyCode();
        $currencySymbol = Currency::where('name', $currencyCode)->value('symbol') ?: $currencyCode;

        $pdf = Pdf::loadView('direct-purchase.pdf', compact('directPurchase', 'currencySymbol'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('DirectPurchase-'.($directPurchase->dp_number ?: $directPurchase->id).'.pdf');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $directPurchase = DirectPurchase::findOrFail($id);

        if ($directPurchase->status !== DirectPurchase::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya direct purchase berstatus Draft yang bisa diubah.'], 422);
        }

        $validated = $this->validateDirectPurchase($request);

        DB::transaction(function () use ($directPurchase, $validated) {
            $directPurchase->update([
                'supplier_name' => $validated['supplier_name'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'supplier_phone' => $validated['supplier_phone'] ?? null,
                'date' => $validated['date'] ?? null,
                'payment_term_days' => $validated['payment_term_days'],
                'ppn_percent' => $validated['ppn_percent'] ?? null,
                'purpose' => $validated['purpose'],
                'project_id' => $validated['purpose'] === DirectPurchase::PURPOSE_PROJECT ? $validated['project_id'] : null,
                'notes' => $validated['notes'] ?? null,
                'request_by_name' => $validated['request_by_name'] ?? null,
                'finance_name' => $validated['finance_name'] ?? null,
                'accounting_name' => $validated['accounting_name'] ?? null,
            ]);

            $this->syncItems($directPurchase, $validated['items'] ?? []);
        });

        return response()->json([
            'success' => true,
            'message' => 'Direct purchase berhasil diperbarui.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $directPurchase = DirectPurchase::findOrFail($id);

        if ($directPurchase->status !== DirectPurchase::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya direct purchase berstatus Draft yang bisa dihapus.'], 422);
        }

        $directPurchase->delete();

        return response()->json(['success' => true, 'message' => 'Direct purchase dihapus.']);
    }

    public function submit($id): JsonResponse
    {
        $directPurchase = DirectPurchase::findOrFail($id);

        if ($directPurchase->status !== DirectPurchase::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya direct purchase berstatus Draft yang bisa di-submit.'], 422);
        }

        if ($directPurchase->items()->count() === 0) {
            return response()->json(['success' => false, 'message' => 'Tambahkan minimal satu item sebelum submit.'], 422);
        }

        $directPurchase->update(['status' => DirectPurchase::STATUS_WAITING_APPROVAL]);

        return response()->json(['success' => true, 'message' => 'Direct purchase dikirim untuk approval.']);
    }

    public function approve($id): JsonResponse
    {
        $directPurchase = DirectPurchase::findOrFail($id);

        if ($directPurchase->status !== DirectPurchase::STATUS_WAITING_APPROVAL) {
            return response()->json(['success' => false, 'message' => 'Direct purchase ini tidak sedang menunggu approval.'], 422);
        }

        $bill = DB::transaction(function () use ($directPurchase) {
            $directPurchase->update([
                'status' => DirectPurchase::STATUS_APPROVED,
                'final_checked_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return SupplierBill::createFromDirectPurchase($directPurchase, Auth::id());
        });

        return response()->json([
            'success' => true,
            'message' => 'Direct purchase disetujui.'.($bill ? ' Tagihan '.$bill->bill_number.' dibuat otomatis untuk pembayaran.' : ''),
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $directPurchase = DirectPurchase::findOrFail($id);

        if ($directPurchase->status !== DirectPurchase::STATUS_WAITING_APPROVAL) {
            return response()->json(['success' => false, 'message' => 'Direct purchase ini tidak sedang menunggu approval.'], 422);
        }

        $validated = $request->validate([
            'approval_note' => 'required|string',
        ]);

        $directPurchase->update([
            'status' => DirectPurchase::STATUS_REJECTED,
            'final_checked_by' => Auth::id(),
            'approval_note' => $validated['approval_note'],
            'rejected_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Direct purchase ditolak.']);
    }

    /**
     * Katalog Master Product untuk picker "Tambah dari Katalog": pencarian,
     * filter divisi/kategori, paging (per_page maks 50), harga beli + mata uang.
     * Daftar pilihan filter ikut dikirim bila with_filters=1.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $category = trim((string) $request->input('category', ''));
        $divisionId = $request->integer('division_id');
        $perPage = min(max($request->integer('per_page', 15), 5), 50);
        $base = Currency::baseName();

        $products = MasterProduct::with(['currency', 'division'])
            ->active()
            ->when($q !== '', fn ($query) => $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            }))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($divisionId, fn ($query) => $query->where('division_id', $divisionId))
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'code', 'brand', 'category', 'description', 'purchase_price', 'currency_id', 'division_id']);

        $response = [
            'success' => true,
            'data' => collect($products->items())->map(fn (MasterProduct $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'brand' => $product->brand,
                'category' => $product->category,
                'description' => Quotation::renderDescription($product->description),
                'division_name' => $product->division?->division_name,
                'price' => (float) $product->purchase_price,
                'currency' => $product->currency && ! $product->currency->isBase() ? strtoupper($product->currency->name) : $base,
            ])->values(),
            'meta' => [
                'page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ];

        if ($request->boolean('with_filters')) {
            $active = MasterProduct::active();
            $response['filters'] = [
                'categories' => (clone $active)->whereNotNull('category')->where('category', '!=', '')
                    ->distinct()->orderBy('category')->pluck('category')->values(),
                'divisions' => Division::whereIn('id', (clone $active)->whereNotNull('division_id')->distinct()->pluck('division_id'))
                    ->orderBy('division_name')->get(['id', 'division_name'])
                    ->map(fn ($d) => ['id' => $d->id, 'name' => $d->division_name])->values(),
            ];
        }

        return response()->json($response);
    }

    private function validateDirectPurchase(Request $request): array
    {
        $validated = $request->validate([
            'supplier_name' => 'required|string|max:200',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'supplier_phone' => 'nullable|string|max:50',
            'date' => 'nullable|date',
            'payment_term_days' => 'nullable|integer|min:0|max:365',
            'ppn_percent' => 'nullable|numeric|min:0|max:100',
            'purpose' => 'required|in:'.implode(',', array_keys(DirectPurchase::PURPOSE_LABELS)),
            'project_id' => 'required_if:purpose,'.DirectPurchase::PURPOSE_PROJECT.'|nullable|integer|exists:projects,id',
            'notes' => 'nullable|string',
            'request_by_name' => 'nullable|string|max:150',
            'finance_name' => 'nullable|string|max:150',
            'accounting_name' => 'nullable|string|max:150',
            'items' => 'nullable|array',
            'items.*.master_product_id' => 'required|integer|exists:master_products,id',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.description' => 'nullable|string',
            'items.*.qty' => 'nullable|integer|min:0',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.currency' => 'nullable|string|max:10',
        ], [
            'project_id.required_if' => 'Pilih project untuk pembelian bertujuan project.',
            'items.*.master_product_id.required' => 'Setiap item wajib terhubung master produk. Pilih produk dari katalog.',
        ]);

        // Termin bayar (hari) = dasar jatuh tempo tagihan; kosong -> termin supplier (DP umumnya 0 = tunai).
        $validated['payment_term_days'] ??= isset($validated['supplier_id'])
            ? Supplier::whereKey($validated['supplier_id'])->value('payment_term_days')
            : null;

        return $validated;
    }

    /**
     * Project aktif untuk pilihan tujuan "Untuk Project".
     */
    private function projectOptions()
    {
        return Project::where('status', Project::STATUS_ACTIVE)->orderByDesc('id')
            ->get(['id', 'project_number', 'project_name'])
            ->map(fn (Project $p) => ['id' => $p->id, 'text' => $p->project_number.' — '.$p->project_name])
            ->values();
    }

    /**
     * Buat dokumen dengan nomor otomatis; unique dp_number menolak duplikat
     * race, dicoba ulang dengan nomor berikutnya.
     */
    private function createWithNumber(callable $create, int $attempts = 3): DirectPurchase
    {
        for ($i = 1; ; $i++) {
            try {
                return $create();
            } catch (QueryException $e) {
                $duplicateNumber = str_contains($e->getMessage(), 'dp_number');
                if (! $duplicateNumber || $i >= $attempts) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Simpan item flat (tanpa hierarki). Item berdiri sendiri, tidak menunjuk
     * Permintaan Barang. Harga: bila ada master_product_id dan harga belum
     * dikirim, diisi dari harga beli master. Satu dokumen satu mata uang.
     */
    private function syncItems(DirectPurchase $directPurchase, array $items): void
    {
        $items = array_values($items);

        if (empty($items)) {
            $directPurchase->items()->delete();

            return;
        }

        $productIds = collect($items)->pluck('master_product_id')->filter()->unique()->values();
        $products = MasterProduct::with('currency')
            ->whereIn('id', $productIds)
            ->get(['id', 'purchase_price', 'currency_id'])
            ->keyBy('id');
        $baseCurrency = Currency::baseName();

        $now = now();
        $payload = [];

        foreach ($items as $i => $item) {
            $productId = $item['master_product_id'] ?? null;
            $product = $productId ? $products->get($productId) : null;

            $rawPrice = $item['price'] ?? null;
            $hasPrice = $rawPrice !== null && $rawPrice !== '';
            $price = $hasPrice ? round((float) $rawPrice, 2) : ($product ? round((float) $product->purchase_price, 2) : null);

            $currency = ($item['currency'] ?? '') !== '' ? strtoupper($item['currency']) : null;
            if (! $currency && $product) {
                $currency = $product->currency && ! $product->currency->isBase()
                    ? strtoupper($product->currency->name)
                    : $baseCurrency;
            }
            if (! $currency) {
                $currency = $baseCurrency;
            }

            $payload[] = [
                'direct_purchase_id' => $directPurchase->id,
                'master_product_id' => $item['master_product_id'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'description' => Quotation::sanitizeDescription($item['description'] ?? ''),
                'qty' => isset($item['qty']) && $item['qty'] !== '' ? (int) $item['qty'] : null,
                'unit' => $item['unit'] ?? null,
                'currency' => $currency,
                'price' => $price,
                'sort_order' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $currencies = collect($payload)->pluck('currency')->filter()->unique()->values();
        if ($currencies->count() > 1) {
            abort(response()->json([
                'success' => false,
                'message' => 'Semua item dalam satu direct purchase harus memakai mata uang yang sama. Ditemukan: '.$currencies->implode(', ').'. Pisahkan ke dokumen berbeda.',
            ], 422));
        }

        $directPurchase->items()->delete();

        DirectPurchaseItem::insert($payload);
    }

    protected function documentOwner($id): Model
    {
        return DirectPurchase::findOrFail($id);
    }

    protected function documentRoutePrefix(): string
    {
        return 'direct-purchase';
    }

    protected function documentModuleCode(): string
    {
        return 'MOD_DIRECT_PURCHASE';
    }
}
