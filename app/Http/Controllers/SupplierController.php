<?php

namespace App\Http\Controllers;

use App\Models\DirectPurchase;
use App\Models\Log;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
                'payment_term_days' => $supplier->payment_term_days,
                'payment_term_label' => Supplier::termLabel($supplier->payment_term_days),
                'max_advance_percent' => $supplier->max_advance_percent,
                'is_pkp' => $supplier->is_pkp,
                'has_bank' => (bool) $supplier->bank_account_no,
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
            'payment_term_days' => $s->payment_term_days,
            'max_advance_percent' => $s->max_advance_percent,
        ]);

        return response()->json(['results' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->withoutEmptyTerm($request->validate($this->rules()));

        $supplier = Supplier::create($validated + ['is_pkp' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Supplier '.$supplier->name.' berhasil ditambahkan.',
            'id' => $supplier->id,
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

        $validated = $this->withoutEmptyTerm($request->validate($this->rules($supplier->id)));

        $supplier->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Supplier '.$supplier->name.' berhasil diupdate.',
        ]);
    }

    /**
     * Batas uang muka per PO (% nilai PO). Dipisah dari update biasa karena
     * menyangkut risiko dana keluar sebelum barang datang: route
     * supplier.advance.approve -> middleware mewajibkan can_approve Supplier.
     */
    public function updateAdvanceLimit(Request $request, $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validate([
            'max_advance_percent' => 'required|numeric|min:0|max:100',
        ], ['max_advance_percent.max' => 'Batas uang muka maksimal 100%.']);

        $before = $supplier->max_advance_percent;
        $supplier->update(['max_advance_percent' => round((float) $validated['max_advance_percent'], 2)]);

        Log::record(
            'update_supplier_advance_limit',
            'Batas uang muka supplier '.$supplier->name.' diubah dari '.$before.'% menjadi '.$supplier->max_advance_percent.'% oleh '.Auth::user()->display_name,
            'MOD_SUPPLIER',
            $supplier
        );

        return response()->json([
            'success' => true,
            'message' => 'Batas uang muka '.$supplier->name.' menjadi '.rtrim(rtrim(number_format($supplier->max_advance_percent, 2), '0'), '.').'%.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        if (PurchaseOrder::where('supplier_id', $id)->exists() || DirectPurchase::where('supplier_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier masih dipakai oleh Purchase Order / Direct Purchase dan tidak dapat dihapus. Ubah statusnya menjadi Inactive.',
            ], 422);
        }

        $name = $supplier->name;
        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Supplier '.$name.' berhasil dihapus.',
        ]);
    }

    /**
     * Termin kosong tidak menimpa nilai yang ada (default kolom: 30 hari).
     */
    private function withoutEmptyTerm(array $validated): array
    {
        if (($validated['payment_term_days'] ?? null) === null) {
            unset($validated['payment_term_days']);
        }

        return $validated;
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
            'payment_term_days' => 'nullable|integer|min:0|max:365',
            'npwp' => 'nullable|string|max:30',
            'is_pkp' => 'nullable|boolean',
            'default_pph_percent' => 'nullable|numeric|min:0|max:100',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_no' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:150',
            'status' => 'required|in:Active,Inactive',
        ];
    }
}
