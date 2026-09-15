<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        return view('supplier.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = Supplier::query();

        $recordsTotal = Supplier::count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%")
                    ->orWhere('phone', 'like', "%{$searchValue}%")
                    ->orWhere('email', 'like', "%{$searchValue}%")
                    ->orWhere('attn_name', 'like', "%{$searchValue}%");
            });
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $suppliers = $query->orderBy('name')->offset($start)->limit($length)->get();

        $data = [];
        foreach ($suppliers as $i => $supplier) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $supplier->id,
                'name' => $supplier->name,
                'address' => $supplier->address,
                'phone' => $supplier->phone,
                'fax' => $supplier->fax,
                'email' => $supplier->email,
                'attn_name' => $supplier->attn_name,
                'status' => $supplier->status,
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Pencarian ringkas untuk picker Supplier (Select2) di form Purchase
     * Order — mengembalikan format {results: [...]} sesuai kontrak Select2
     * (lihat pola yang sama di OpportunityManagementController::searchCompanies()).
     * Setiap hasil membawa alamat/telepon/fax/attn supaya form bisa
     * langsung mengisi field snapshot tanpa AJAX tambahan saat dipilih.
     */
    public function search(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $suppliers = Supplier::where('status', 'Active')
            ->where('name', 'like', "%{$q}%")
            ->orderBy('name')
            ->limit(20)
            ->get();

        $data = $suppliers->map(fn (Supplier $s) => [
            'id' => $s->id,
            'text' => $s->name,
            'address' => $s->address,
            'phone' => $s->phone,
            'fax' => $s->fax,
            'attn_name' => $s->attn_name,
        ]);

        return response()->json(['results' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $supplier = Supplier::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Supplier '.$supplier->name.' berhasil ditambahkan.',
        ]);
    }

    public function edit($id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $supplier->toArray(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validate($this->rules($supplier->id));

        $supplier->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Supplier '.$supplier->name.' berhasil diupdate.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        if (PurchaseOrder::where('supplier_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier masih dipakai oleh Purchase Order dan tidak dapat dihapus.',
            ], 422);
        }

        $name = $supplier->name;
        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Supplier '.$name.' berhasil dihapus.',
        ]);
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'name' => 'required|string|max:200|unique:suppliers,name'.($ignoreId ? ','.$ignoreId : ''),
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'attn_name' => 'nullable|string|max:150',
            'status' => 'required|in:Active,Inactive',
        ];
    }
}
