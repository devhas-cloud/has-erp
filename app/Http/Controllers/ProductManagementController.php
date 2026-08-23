<?php

namespace App\Http\Controllers;

use App\Models\Division;
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
        $divisions = Division::where('status', 'Active')->orderBy('division_name')->get();

        return view('product-management.index', compact('divisions'));
    }

    public function data(Request $request): JsonResponse
    {
        $query = MasterProduct::with(['division']);

        $recordsTotal = MasterProduct::count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%")
                    ->orWhere('code', 'like', "%{$searchValue}%")
                    ->orWhere('brand', 'like', "%{$searchValue}%")
                    ->orWhere('category', 'like', "%{$searchValue}%")
                    ->orWhereHas('division', function ($q) use ($searchValue) {
                        $q->where('division_name', 'like', "%{$searchValue}%");
                    });
            });
        }

        $recordsFiltered = $query->count();

        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'asc');

        $columnOrderMap = [
            1 => 'name',
            2 => 'code',
            3 => 'brand',
            4 => 'category',
            6 => 'price',
            7 => 'status',
        ];

        if (isset($columnOrderMap[$orderColumnIndex])) {
            $query->orderBy($columnOrderMap[$orderColumnIndex], $orderDirection);
        }
        $query->orderBy('id', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $products = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($products as $i => $product) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $product->id,
                'name' => $product->name ?? '—',
                'initials' => strtoupper(substr($product->name ?? '?', 0, 2)),
                'name_display' => $product->name ?? '—',
                'code' => $product->code ?? '—',
                'brand' => $product->brand ?? '—',
                'category' => $product->category ?? '—',
                'division_name' => $product->division?->division_name ?? '—',
                'price' => $product->price,
                'price_formatted' => number_format((float) $product->price, 0, '.', ','),
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:150|unique:master_products,name',
            'code' => 'required|string|max:50|unique:master_products,code',
            'brand' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'division_id' => 'nullable|exists:divisions,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:Active,Inactive',
        ]);

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
        $product = MasterProduct::with('division')->findOrFail($id);

        $data = $product->toArray();
        $data['image_url'] = $product->image_url;
        $data['division_name'] = $product->division?->division_name;

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
            'division_id' => 'nullable|exists:divisions,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:Active,Inactive',
        ]);

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

    public function export()
    {
        $products = MasterProduct::with('division')
            ->orderBy('id', 'desc')
            ->get();

        $headers = [
            'Name', 'Code', 'Brand', 'Category', 'Division',
            'Description', 'Price', 'Status',
        ];

        $service = new ProductExportService;
        $filePath = $service->export($products, $headers);

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
}
