<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index()
    {
        return view('warehouse.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = Warehouse::query();

        $recordsTotal = Warehouse::count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%")
                    ->orWhere('code', 'like', "%{$searchValue}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $warehouses = $query->orderBy('name')->offset($start)->limit($length)->get();

        $data = $warehouses->map(fn ($warehouse, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'id' => $warehouse->id,
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'type' => $warehouse->type,
            'type_label' => $warehouse->type_label,
            'status' => $warehouse->status,
        ])->values();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $warehouse = Warehouse::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Gudang '.$warehouse->name.' berhasil ditambahkan.',
        ]);
    }

    public function edit($id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Warehouse::findOrFail($id)->toArray(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate($this->rules($warehouse->id));
        $warehouse->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Gudang '.$warehouse->name.' berhasil diupdate.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($id);

        if (GoodsReceipt::where('warehouse_id', $id)->exists() || StockMovement::where('warehouse_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Gudang masih dipakai transaksi stok dan tidak dapat dihapus.',
            ], 422);
        }

        $name = $warehouse->name;
        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Gudang '.$name.' berhasil dihapus.',
        ]);
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'code' => 'required|string|max:50|unique:warehouses,code'.($ignoreId ? ','.$ignoreId : ''),
            'name' => 'required|string|max:150',
            'type' => 'required|in:'.implode(',', array_keys(Warehouse::TYPE_LABELS)),
            'status' => 'required|in:Active,Inactive',
        ];
    }
}
