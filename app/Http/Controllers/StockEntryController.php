<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\StockEntry;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\StockValuation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Masuk Stok Lain-lain — barang masuk tanpa PO / Direct Purchase (saldo awal,
 * retur, hibah/sampel, dll). Produk wajib dari master. Disimpan langsung
 * terposting (misc_in), tidak bisa diubah/dihapus.
 */
class StockEntryController extends Controller
{
    public function index()
    {
        return view('stock-entry.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = StockEntry::withCount('items')->with(['warehouse', 'project', 'creator']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('entry_number', 'like', "%{$searchValue}%")
                    ->orWhere('note', 'like', "%{$searchValue}%")
                    ->orWhereHas('warehouse', fn ($w) => $w->where('name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('project', fn ($p) => $p->where('project_number', 'like', "%{$searchValue}%")->orWhere('project_name', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $entries = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        $data = $entries->map(fn (StockEntry $entry, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'id' => $entry->id,
            'entry_number' => $entry->entry_number,
            'entry_date' => $entry->entry_date?->format('d/m/Y') ?? '—',
            'reason_label' => $entry->reason_label,
            'warehouse_name' => $entry->warehouse?->name ?? '—',
            'ownership' => $entry->project ? $entry->project->project_number.' — '.$entry->project->project_name : 'Stok bebas',
            'item_count' => $entry->items_count,
            'creator' => $entry->creator?->display_name ?? '—',
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create()
    {
        return view('stock-entry.form', [
            'warehouses' => Warehouse::active()->orderBy('type')->orderBy('name')->get(),
            'projects' => Project::where('status', Project::STATUS_ACTIVE)->orderByDesc('id')->get(['id', 'project_number', 'project_name']),
        ]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $products = MasterProduct::active()
            ->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'code', 'brand', 'category']);

        $data = $products->map(fn (MasterProduct $product) => $product->only(['id', 'name', 'code', 'brand', 'category']) + [
            'cost' => StockValuation::averageCost($product->id),
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'entry_date' => 'nullable|date',
            'reason' => 'required|in:'.implode(',', array_keys(StockEntry::REASON_LABELS)),
            'note' => 'nullable|string|max:1000|required_if:reason,'.StockEntry::REASON_OTHER,
            'items' => 'required|array|min:1',
            'items.*.master_product_id' => 'required|integer|distinct|exists:master_products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ], [
            'items.required' => 'Tambahkan minimal satu barang.',
            'items.*.master_product_id.distinct' => 'Produk yang sama tidak boleh muncul dua kali.',
            'note.required_if' => 'Keterangan wajib diisi untuk alasan Lain-lain.',
        ]);

        if (! Warehouse::active()->whereKey($validated['warehouse_id'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Gudang tidak aktif.'], 422);
        }

        $entry = DB::transaction(function () use ($validated) {
            $date = $validated['entry_date'] ?? now()->toDateString();

            $entry = StockEntry::create([
                'entry_number' => StockEntry::generateNumber($date),
                'entry_date' => $date,
                'warehouse_id' => $validated['warehouse_id'],
                'project_id' => $validated['project_id'] ?? null,
                'reason' => $validated['reason'],
                'note' => $validated['note'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach (array_values($validated['items']) as $i => $row) {
                $item = $entry->items()->create([
                    'master_product_id' => $row['master_product_id'],
                    'qty' => (int) $row['qty'],
                    'unit' => $row['unit'] ?? null,
                    'unit_cost' => isset($row['unit_cost']) ? (float) $row['unit_cost'] : StockValuation::standardCost((int) $row['master_product_id']),
                    'sort_order' => $i + 1,
                ]);

                StockMovement::record([
                    'warehouse_id' => $entry->warehouse_id,
                    'master_product_id' => $item->master_product_id,
                    'project_id' => $entry->project_id,
                    'type' => StockMovement::TYPE_MISC_IN,
                    'qty' => $item->qty,
                    'unit' => $item->unit,
                    'unit_cost' => $item->unit_cost,
                    'source_type' => StockMovement::SOURCE_STOCK_ENTRY,
                    'source_id' => $entry->id,
                    'source_line_id' => $item->id,
                    'moved_at' => $date,
                    'note' => $entry->reason_label.' '.$entry->entry_number,
                ]);
            }

            return $entry;
        });

        Log::record(
            'stock_entry',
            'Masuk stok lain-lain '.$entry->entry_number.' ('.$entry->reason_label.') diposting oleh '.Auth::user()->display_name,
            'MOD_STOCK_ENTRY',
            $entry
        );

        return response()->json([
            'success' => true,
            'message' => 'Masuk stok '.$entry->entry_number.' diposting.',
            'id' => $entry->id,
        ]);
    }

    public function show($id)
    {
        $entry = StockEntry::with(['items.masterProduct', 'warehouse', 'project', 'creator'])->findOrFail($id);

        return view('stock-entry.show', compact('entry'));
    }
}
