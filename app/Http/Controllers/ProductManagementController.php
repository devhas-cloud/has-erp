<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Division;
use Illuminate\Support\Facades\Auth;
use App\Models\MasterProduct;
use App\Services\ProductExportService;
use App\Services\ProductImportService;
use App\Services\ProductXlsxTemplateGenerator;
use App\Support\ProductPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductManagementController extends Controller
{
    public function index()
    {
        $divisions = Division::where('status', 'Active')->where('type', 'Internal')->orderBy('division_name')->get();
        $currencies = Currency::where('status', 'Active')->orderBy('is_base', 'desc')->orderBy('name')->get();
        $categories = MasterProduct::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $types = MasterProduct::typeOptions();
        $units = MasterProduct::unitOptions();
        // IMS: divisi produk terkunci ke divisi user.
        $scopedDivisionId = $this->scopedDivisionId();

        return view('product-management.index', compact('divisions', 'currencies', 'categories', 'types', 'units', 'scopedDivisionId'));
    }

    public function data(Request $request): JsonResponse
    {
        $query = MasterProduct::with(['division', 'currency']);

        $recordsTotal = MasterProduct::count();

        $this->applyProductFilters($query, $request);

        $recordsFiltered = $query->count();

        // Urutan berdasarkan nama data kolom (bukan index), karena kolom Harga Jual
        // bisa disembunyikan sehingga index kolom berikutnya bergeser.
        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderColumnData = $request->input("columns.{$orderColumnIndex}.data");
        $orderDirection = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $columnOrderMap = [
            // Nama kosong -> urut berdasarkan deskripsi (sama dengan nama tampilan).
            'name_display' => DB::raw("COALESCE(NULLIF(TRIM(name), ''), description)"),
            'brand' => 'brand',
            'category' => 'category',
            'type' => 'type',
            'unit' => 'unit',
            'status' => 'status',
        ];
        // Urut harga beli hanya untuk Admin: IMS hanya melihat sebagian harga, urutan
        // lintas semua produk akan membocorkan posisi harga yang disembunyikan.
        if ($this->canManagePrice()) {
            $columnOrderMap['purchase_price_formatted'] = 'purchase_price';
        }
        if ($this->canViewSellingPrice()) {
            $columnOrderMap['selling_price_formatted'] = 'selling_price';
        }

        if ($orderColumnData === 'name_display') {
            // Tanpa nama & deskripsi selalu di bawah, apa pun arah urutnya.
            $query->orderByRaw("COALESCE(NULLIF(TRIM(name), ''), NULLIF(TRIM(description), '')) IS NULL")
                ->orderBy($columnOrderMap['name_display'], $orderDirection);
        } elseif (is_string($orderColumnData) && isset($columnOrderMap[$orderColumnData])) {
            $query->orderBy($columnOrderMap[$orderColumnData], $orderDirection);
        }
        $query->orderBy('id', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $products = $query->offset($start)->limit($length)->get();

        $withPurchase = $this->canViewPurchasePrice();
        $withSelling = $this->canViewSellingPrice();
        $baseSymbol = $withSelling
            ? (Currency::where('is_base', true)->first()?->symbol ?: 'Rp')
            : null;

        $data = [];
        foreach ($products as $i => $product) {
            $currency = $product->currency;
            $purchase = null;
            $purchaseFormatted = null;
            $selling = null;
            $sellingFormatted = null;
            if ($withPurchase && $this->canViewPurchasePriceOf($product)) {
                $purchase = round((float) $product->purchase_price, 2);
                $purchaseFormatted = number_format($purchase, 2, '.', ',');
            }
            if ($withSelling) {
                $selling = $this->formula_selling_price($product->currency_id, $product->purchase_price);
                $sellingFormatted = number_format($selling);
            }
            $displayName = $product->display_name;
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $product->id,
                'name' => $displayName ?? $product->code ?? '—',
                'initials' => strtoupper(mb_substr($displayName ?? $product->code ?? '?', 0, 2)),
                'name_display' => $displayName ?? '—',
                'name_from_description' => $product->nameFromDescription(),
                'code' => $product->code ?? '—',
                'brand' => $product->brand ?? '—',
                'category' => $product->category ?? '—',
                'type' => $product->type ?? '—',
                'unit' => $product->unit,
                'is_material' => $product->isMaterial(),
                'parameter' => $product->parameter ?? '—',
                'division_name' => $product->division?->division_name ?? '—',
                'purchase_price' => $purchase,
                'purchase_price_formatted' => $purchaseFormatted,
                'selling_price' => $selling,
                'selling_price_formatted' => $sellingFormatted,
                'base_currency_symbol' => $baseSymbol,
                'currency_id' => $product->currency_id,
                'currency_name' => $currency?->name ?? '—',
                'currency_symbol' => '',
                'status' => $product->status ?? 'Active',
                'image_url' => $product->image_url,
                'can_manage' => $this->canManageProduct($product),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function formula_selling_price($currencyId, $purchasePrice): float
    {
        return ProductPricing::price($currencyId, $purchasePrice)['price_idr'];
    }


    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:150|unique:master_products,name',
            'code' => 'required|string|max:50|unique:master_products,code',
            'brand' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'type' => 'nullable|string|max:50',
            'unit' => 'nullable|string|max:50',
            'is_material' => 'nullable|boolean',
            'parameter' => 'nullable|string|max:255',
            'division_id' => 'nullable|exists:divisions,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'selling_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'currency_id' => 'nullable|exists:currencies,id',
            'status' => 'required|in:Active,Inactive',
        ]);

        $validated['type'] = MasterProduct::normalizeType($validated['type'] ?? null);
        $validated['unit'] = trim((string) ($validated['unit'] ?? '')) ?: null;
        // Tidak diisi = bukan Material (barang ber-SN); Material = stok per qty.
        $validated['is_material'] = $request->boolean('is_material');

        // IMS: produk baru selalu milik divisinya sendiri.
        if ($scopedDivisionId = $this->scopedDivisionId()) {
            $validated['division_id'] = $scopedDivisionId;
        }

        // Harga beli & currency hanya boleh diisi Admin, atau IMS untuk produk divisinya;
        // selain itu produk baru memakai currency base.
        $canInputPrice = $this->canInputPrice();
        $validated['currency_id'] = $this->resolveCurrencyId($canInputPrice ? $request->input('currency_id') : null);
        if (! $canInputPrice) {
            unset($validated['purchase_price']);
        }
        // Harga jual tidak pernah diterima dari input: selalu hasil formulasi harga beli.
        $validated['selling_price'] = $this->formula_selling_price($validated['currency_id'], $validated['purchase_price'] ?? null);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('product-images', 'public');
        }

        MasterProduct::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product berhasil ditambahkan.',
        ]);
    }

    public function edit($id): JsonResponse
    {
        $product = MasterProduct::with(['division', 'currency'])->findOrFail($id);

        $currency = $product->currency;
        $baseCurrency = Currency::where('is_base', true)->where('status', 'Active')->first();

        $data = $product->toArray();
        $data['display_name'] = $product->display_name;
        $data['name_from_description'] = $product->nameFromDescription();
        $data['image_url'] = $product->image_url;
        $data['division_name'] = $product->division?->division_name;
        $data['currency_name'] = $currency?->name;
        $data['currency_symbol'] = $currency?->symbol ?: ($currency?->name ?? '—');
        $data['currency_rate'] = $currency ? (float) $currency->rate : 1.0;
        $data['currency_is_base'] = $currency ? (bool) $currency->is_base : true;
        $data['base_currency_name'] = $baseCurrency?->name ?? 'IDR';
        $data['base_currency_symbol'] = $baseCurrency?->symbol ?: ($baseCurrency?->name ?? 'IDR');

        // Harga jual selalu ditampilkan hasil formulasi dari harga beli (IDR).
        $data['selling_price_formula'] = $this->formula_selling_price($product->currency_id, $product->purchase_price);
        $data['selling_price_formula_formatted'] = number_format($data['selling_price_formula'], 2, '.', ',');

        if (! $this->canViewPurchasePriceOf($product)) {
            // Harga beli: Admin semua produk; IMS hanya produk divisinya.
            $data['purchase_price'] = null;
        }
        $data['can_manage'] = $this->canManageProduct($product);
        $data['can_edit_price'] = (bool) Auth::user()?->canEditPurchasePriceOf($product);
        if (! $this->canViewSellingPrice()) {
            // Harga jual hanya untuk role Admin / divisi Admin.
            $data['selling_price'] = null;
            $data['selling_price_formula'] = null;
            $data['selling_price_formula_formatted'] = null;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Detail produk ditampilkan via modal di halaman index (bukan view terpisah).
     */
    public function show($id): JsonResponse
    {
        return $this->edit($id);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $product = MasterProduct::findOrFail($id);
        if (! $this->canManageProduct($product)) {
            return $this->notOwnDivision();
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:150|unique:master_products,name,'.$product->id,
            'code' => 'required|string|max:50|unique:master_products,code,'.$product->id,
            'brand' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'type' => 'nullable|string|max:50',
            'unit' => 'nullable|string|max:50',
            'is_material' => 'nullable|boolean',
            'parameter' => 'nullable|string|max:255',
            'division_id' => 'nullable|exists:divisions,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'selling_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'currency_id' => 'nullable|exists:currencies,id',
            'status' => 'required|in:Active,Inactive',
        ]);

        $validated['type'] = MasterProduct::normalizeType($validated['type'] ?? null);
        $validated['unit'] = trim((string) ($validated['unit'] ?? '')) ?: null;
        // IMS tidak bisa memindahkan produk keluar dari divisinya.
        if ($scopedDivisionId = $this->scopedDivisionId()) {
            $validated['division_id'] = $scopedDivisionId;
        }
        // Form tanpa field is_material (klien lama) -> nilai lama tetap.
        if ($request->has('is_material')) {
            $validated['is_material'] = $request->boolean('is_material');
            if ($validated['is_material'] !== $product->isMaterial() && $product->hasStock()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis stok (Material / Barang ber-SN) tidak bisa diubah karena produk ini masih punya stok. Kosongkan stoknya dulu.',
                ], 422);
            }
        } else {
            unset($validated['is_material']);
        }

        // Harga beli & currency hanya bisa diubah Admin (semua produk) atau IMS (produk
        // divisinya); harga jual ikut dihitung ulang dari formulasi. Lainnya -> harga &
        // currency DB tetap. Form tanpa field purchase_price -> harga DB tetap.
        $canEditPrice = (bool) Auth::user()?->canEditPurchasePriceOf($product);
        if ($canEditPrice && $request->has('currency_id')) {
            $validated['currency_id'] = $this->resolveCurrencyId($request->input('currency_id'));
        } else {
            unset($validated['currency_id']);
        }
        if ($canEditPrice && array_key_exists('purchase_price', $validated)) {
            $validated['currency_id'] ??= $product->currency_id;
            $validated['selling_price'] = $this->formula_selling_price($validated['currency_id'], $validated['purchase_price']);
        } else {
            unset($validated['selling_price'], $validated['purchase_price']);
        }

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('product-images', 'public');
        }

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product berhasil diupdate.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $product = MasterProduct::findOrFail($id);
        if (! $this->canManageProduct($product)) {
            return $this->notOwnDivision();
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product berhasil dihapus.',
        ]);
    }

    public function export(Request $request)
    {
        $query = MasterProduct::with(['division', 'currency']);

        $this->applyProductFilters($query, $request);

        $products = $query->orderBy('id', 'desc')->get();

        $withPurchase = $this->canViewPurchasePrice();
        $withSelling = $this->canViewSellingPrice();

        foreach ($products as $product) {
            // Selling Price pada export = hasil formulasi dari harga beli.
            if ($withSelling) {
                $product->selling_price = $this->formula_selling_price($product->currency_id, $product->purchase_price);
            }
            // IMS: harga beli produk divisi lain dikosongkan.
            if ($withPurchase && ! $this->canViewPurchasePriceOf($product)) {
                $product->purchase_price = null;
                $product->purchase_price_hidden = true;
            }
        }

        $service = new ProductExportService;
        $filePath = $service->export($products, $withPurchase, $withSelling);

        return response()->download($filePath, 'products-export-'.date('Y-m-d').'.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function downloadTemplate()
    {
        $path = storage_path('app/private/templates/product_import_template.xlsx');

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $references = ProductImportService::getReferenceData();

        $generator = new ProductXlsxTemplateGenerator;
        $generator->generate($references, $path);

        return response()->download($path, 'Product_Import_Template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $filePath = $file->storeAs('imports', 'product-import-'.uniqid().'.'.$ext);

        $fullPath = Storage::path($filePath);

        try {
            // Harga dari file hanya dipakai bila importer Admin; selain itu harga diabaikan
            // (produk baru tanpa harga, produk lama harganya tetap).
            // IMS: hanya produk divisinya — produk baru otomatis berdivisi IMS, baris produk
            // divisi lain ditolak; harga & currency dipakai untuk produk divisinya.
            $canInputPrice = $this->canInputPrice();
            $scopedDivisionId = $this->scopedDivisionId();
            $service = new ProductImportService($canInputPrice, $scopedDivisionId);
            $result = $service->import($fullPath);

            $message = "Import selesai. {$result['success']} baru, {$result['updated']} diupdate, {$result['failed']} gagal.";
            if (! $canInputPrice) {
                $message .= ' Kolom harga & currency diabaikan (hanya Admin / IMS untuk produk divisinya yang boleh mengisi).';
            }
            if ($scopedDivisionId) {
                $message .= ' Kolom divisi diabaikan: semua produk dicatat sebagai divisi Anda.';
            }

            return response()->json([
                'success' => $result['failed'] === 0,
                'message' => $message,
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor: '.$e->getMessage(),
            ], 422);
        } finally {
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    /**
     * Kolom harga beli: role Admin, divisi Admin, atau divisi IMS (User::canViewProductPrice).
     */
    private function canViewPurchasePrice(): bool
    {
        return (bool) Auth::user()?->canViewProductPrice();
    }

    /**
     * Nilai harga beli satu produk: Admin semua, IMS hanya produk divisinya.
     */
    private function canViewPurchasePriceOf(MasterProduct $product): bool
    {
        return (bool) Auth::user()?->canViewPurchasePriceOf($product);
    }

    /**
     * Pengelola harga penuh (semua produk, urut harga beli): role Admin atau divisi Admin.
     */
    private function canManagePrice(): bool
    {
        return (bool) Auth::user()?->canManageProductPrice();
    }

    /**
     * Input harga & currency di form/import: Admin, atau IMS (produk divisinya).
     */
    private function canInputPrice(): bool
    {
        return (bool) Auth::user()?->canInputProductPrice();
    }

    /**
     * Divisi user bila user hanya boleh mengelola produk divisinya (IMS); null = tanpa batas.
     */
    private function scopedDivisionId(): ?int
    {
        $user = Auth::user();

        return $user?->isDivisionScopedProductManager() ? (int) $user->division_id : null;
    }

    /**
     * Edit/hapus produk: IMS hanya produk divisinya.
     */
    private function canManageProduct(MasterProduct $product): bool
    {
        return (bool) Auth::user()?->canManageProduct($product);
    }

    private function notOwnDivision(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Produk ini milik divisi lain. Anda hanya bisa mengubah produk divisi Anda sendiri.',
        ], 403);
    }

    /**
     * Harga jual: hanya role Admin atau divisi Admin (User::canViewSellingPrice).
     */
    private function canViewSellingPrice(): bool
    {
        return (bool) Auth::user()?->canViewSellingPrice();
    }

    /**
     * Terapkan filter pencarian (search.value dari DataTables / search flat dari export;
     * termasuk deskripsi karena nama kosong ditampilkan dari deskripsi)
     * dan filter dropdown (category, type, stock_kind, division_id, status).
     */
    private function applyProductFilters($query, Request $request)
    {
        // DataTables mengirim search.value (bisa null/''), export mengirim 'search' flat string.
        $searchValue = $request->input('search.value');
        if (! $searchValue) {
            $flatSearch = $request->input('search');
            if (is_string($flatSearch)) {
                $searchValue = $flatSearch;
            }
        }

        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhere('code', 'like', "%{$searchValue}%")
                    ->orWhere('brand', 'like', "%{$searchValue}%")
                    ->orWhere('category', 'like', "%{$searchValue}%")
                    ->orWhere('type', 'like', "%{$searchValue}%")
                    ->orWhere('unit', 'like', "%{$searchValue}%")
                    ->orWhere('parameter', 'like', "%{$searchValue}%")
                    ->orWhereHas('division', function ($q) use ($searchValue) {
                        $q->where('division_name', 'like', "%{$searchValue}%");
                    });
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter jenis stok: material (qty) / serial (ber-SN).
        if ($request->input('stock_kind') === 'material') {
            $query->where('is_material', true);
        } elseif ($request->input('stock_kind') === 'serial') {
            $query->where('is_material', false);
        }

        if ($request->filled('division_id')) {
            $query->where('division_id', $request->input('division_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query;
    }

    /**
     * Currency tidak wajib dipilih. Jika kosong, produk memakai currency base
     * (biasanya IDR) sebagai acuan default.
     */
    private function resolveCurrencyId($currencyId): ?int
    {
        if ($currencyId) {
            return (int) $currencyId;
        }

        return Currency::where('is_base', true)->where('status', 'Active')->value('id');
    }
}
