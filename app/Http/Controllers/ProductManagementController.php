<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Division;
use Illuminate\Support\Facades\Auth;
use App\Models\MasterProduct;
use App\Services\ProductExportService;
use App\Services\ProductImportService;
use App\Services\ProductXlsxTemplateGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return view('product-management.index', compact('divisions', 'currencies', 'categories'));
    }

    public function data(Request $request): JsonResponse
    {
        $query = MasterProduct::with(['division', 'currency']);

        $recordsTotal = MasterProduct::count();

        $this->applyProductFilters($query, $request);

        $recordsFiltered = $query->count();

        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'asc');

        $columnOrderMap = [
            1 => 'name',
            2 => 'brand',
            3 => 'category',
            4 => 'type',
            6 => 'selling_price',
            7 => 'status',
        ];

        if (isset($columnOrderMap[$orderColumnIndex])) {
            $query->orderBy($columnOrderMap[$orderColumnIndex], $orderDirection);
        }
        $query->orderBy('id', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $products = $query->offset($start)->limit($length)->get();

        if ($this->isAdmin()) {
            $baseSymbol = Currency::where('is_base', true)->first()?->symbol ?: 'Rp';
            $withPrices = true;
        } else {
            $baseSymbol = null;
            $withPrices = false;
        }

        $data = [];
        foreach ($products as $i => $product) {
            $currency = $product->currency;
            if ($withPrices) {
                $selling = $this->formula_selling_price($product->currency_id, $product->purchase_price);
                $purchase = round((float) $product->purchase_price, 2);
                $purchaseFormatted = number_format($purchase, 2, '.', ',');
                $sellingFormatted = number_format($selling);
            } else {
                $selling = null;
                $purchase = null;
                $purchaseFormatted = null;
                $sellingFormatted = null;
            }
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $product->id,
                'name' => $product->name ?? '—',
                'initials' => strtoupper(substr($product->name ?? '?', 0, 2)),
                'name_display' => $product->name ?? '—',
                'code' => $product->code ?? '—',
                'brand' => $product->brand ?? '—',
                'category' => $product->category ?? '—',
                'type' => $product->type ?? '—',
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
        $currency = Currency::where('id', $currencyId)->first();
        $isBase   = ! $currency || $currency->is_base;           // 1. tanpa currency = base
        $purchase = (float) $purchasePrice;
        if ($purchase <= 0) return 0.0;

        $rate = $isBase ? 1.0 : (float) $currency->rate;
        // satuan: /0.43 (non-base) / x2 (base)
        $priceCurrency = $isBase ? $purchase * 2 : $purchase / 0.43;
        $raw = $priceCurrency * $rate;

        if (! $isBase) {                                          // ceil hanya non-base
            $step = $raw < 10000000 ? 500000 : 1000000;
            return (int) (ceil($raw / $step) * $step);
        }
        return round($raw, 2);
    }


    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:150|unique:master_products,name',
            'code' => 'required|string|max:50|unique:master_products,code',
            'brand' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'type' => 'nullable|in:Acc,Main,Service',
            'parameter' => 'nullable|string|max:255',
            'division_id' => 'nullable|exists:divisions,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'selling_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'currency_id' => 'nullable|exists:currencies,id',
            'status' => 'required|in:Active,Inactive',
        ]);

        $validated['currency_id'] = $this->resolveCurrencyId($request->input('currency_id'));
        $validated['selling_price'] = $this->formula_selling_price($validated['currency_id'], $validated['purchase_price']);

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

        if (! $this->isAdmin()) {
            // Harga hanya untuk role Admin divisi Admin.
            $data['selling_price'] = null;
            $data['purchase_price'] = null;
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

        $validated = $request->validate([
            'name' => 'nullable|string|max:150|unique:master_products,name,'.$product->id,
            'code' => 'required|string|max:50|unique:master_products,code,'.$product->id,
            'brand' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'type' => 'nullable|in:Acc,Main,Service',
            'parameter' => 'nullable|string|max:255',
            'division_id' => 'nullable|exists:divisions,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'selling_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'currency_id' => 'nullable|exists:currencies,id',
            'status' => 'required|in:Active,Inactive',
        ]);

        $validated['currency_id'] = $this->resolveCurrencyId($request->input('currency_id'));
        // Harga hanya bisa diubah role Admin divisi Admin. Non-Admin tidak
        // mengirim harga -> harga DB tetap.
        if ($this->isAdmin()) {
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

        $withPrices = $this->isAdmin();

        // Selling Price pada export = hasil formulasi dari harga beli.
        if ($withPrices) {
            foreach ($products as $product) {
                $product->selling_price = $this->formula_selling_price($product->currency_id, $product->purchase_price);
            }
        }

        if ($withPrices) {
            $headers = [
                'Name', 'Code', 'Brand', 'Category', 'Type', 'Parameter', 'Division',
                'Description', 'Currency', 'Purchase Price', 'Selling Price', 'Status',
            ];
        } else {
            $headers = [
                'Name', 'Code', 'Brand', 'Category', 'Type', 'Parameter', 'Division',
                'Description', 'Currency', 'Status',
            ];
        }

        $service = new ProductExportService;
        $filePath = $service->export($products, $headers, $withPrices);

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
            $service = new ProductImportService;
            $result = $service->import($fullPath);

            return response()->json([
                'success' => $result['failed'] === 0,
                'message' => "Import selesai. {$result['success']} baru, {$result['updated']} diupdate, {$result['failed']} gagal.",
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
     * Harga beli/jual hanya dilihat role Admin yang berdivisi Admin.
     */
    private function isAdmin(): bool
    {
        $user = Auth::user();

        return $user !== null
            && $user->role === 'Admin'
            && strcasecmp($user->division?->division_name ?? '', 'Admin') === 0;
    }

    /**
     * Terapkan filter pencarian (search.value dari DataTables / search flat dari export)
     * dan filter dropdown (category, type, division_id, status).
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
                    ->orWhere('code', 'like', "%{$searchValue}%")
                    ->orWhere('brand', 'like', "%{$searchValue}%")
                    ->orWhere('category', 'like', "%{$searchValue}%")
                    ->orWhere('type', 'like', "%{$searchValue}%")
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
