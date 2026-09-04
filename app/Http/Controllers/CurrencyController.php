<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\MasterProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index()
    {
        return view('currency.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = Currency::query();

        $recordsTotal = Currency::count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%")
                    ->orWhere('symbol', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%");
            });
        }

        $recordsFiltered = $query->count();

        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'asc');

        $columnOrderMap = [
            1 => 'name',
            2 => 'symbol',
            3 => 'rate',
            4 => 'is_base',
            5 => 'status',
        ];

        if (isset($columnOrderMap[$orderColumnIndex])) {
            $query->orderBy($columnOrderMap[$orderColumnIndex], $orderDirection);
        }
        $query->orderBy('is_base', 'desc')->orderBy('name');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $currencies = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($currencies as $i => $currency) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $currency->id,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'rate' => $currency->rate,
                'rate_formatted' => number_format((float) $currency->rate, 2, '.', ','),
                'is_base' => (bool) $currency->is_base,
                'is_base_label' => $currency->isBase() ? 'Ya' : 'Tidak',
                'status' => $currency->status,
                'description' => $currency->description,
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
        $validated = $request->validate($this->rules());

        if ($request->boolean('is_base') && Currency::where('is_base', true)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Currency base sudah ada. Ubah currency base yang ada terlebih dahulu.',
            ], 422);
        }

        if ($request->boolean('is_base')) {
            $validated['rate'] = 1;
        }

        $currency = Currency::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Currency '.$currency->name.' berhasil ditambahkan.',
        ]);
    }

    public function edit($id): JsonResponse
    {
        $currency = Currency::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $currency->toArray(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $currency = Currency::findOrFail($id);

        $validated = $request->validate($this->rules($currency->id));

        if ($currency->isBase()) {
            // Mata uang base tidak bisa dinonaktifkan/diubah jadi non-base.
            if (! $request->boolean('is_base') || $request->input('status') !== 'Active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Currency base wajib aktif dan tidak dapat diubah menjadi non-base.',
                ], 422);
            }

            // Base tidak boleh nonaktif; rate base selalu 1.
            $validated['is_base'] = true;
            $validated['rate'] = 1;
        } else {
            // Currency non-base tidak bisa dijadikan base jika base sudah ada.
            if ($request->boolean('is_base')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Currency base sudah ada. Ubah currency base yang ada terlebih dahulu.',
                ], 422);
            }
        }

        $currency->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Currency '.$currency->name.' berhasil diupdate.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $currency = Currency::findOrFail($id);

        if ($currency->isBase()) {
            return response()->json([
                'success' => false,
                'message' => 'Currency base tidak dapat dihapus.',
            ], 422);
        }

        if (MasterProduct::where('currency_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Currency masih dipakai oleh produk dan tidak dapat dihapus.',
            ], 422);
        }

        $name = $currency->name;
        $currency->delete();

        return response()->json([
            'success' => true,
            'message' => 'Currency '.$name.' berhasil dihapus.',
        ]);
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'name' => 'required|string|max:10|unique:currencies,name'.($ignoreId ? ','.$ignoreId : ''),
            'symbol' => 'nullable|string|max:10',
            'rate' => 'required|numeric|min:0.0001',
            'is_base' => 'nullable|boolean',
            'status' => 'required|in:Active,Inactive',
            'description' => 'nullable|string|max:255',
        ];
    }
}
