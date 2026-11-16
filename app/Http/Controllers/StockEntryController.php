<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\Stock;
use App\Models\StockEntry;
use App\Models\StockLog;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\StockCost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Masuk Stok Lain-lain — barang masuk tanpa PO / Direct Purchase (saldo awal,
 * retur, hibah/sampel, dll). Produk wajib dari master. Disimpan langsung
 * terposting (baris stocks baru + log 'in'), tidak bisa diubah/dihapus.
 * Produk sama boleh lebih dari satu baris (beda batch/harga/kedaluwarsa).
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
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'projects' => Project::where('status', Project::STATUS_ACTIVE)->orderByDesc('id')->get(['id', 'project_number', 'project_name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        $user = Auth::user();

        $products = MasterProduct::active()
            ->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'code', 'brand', 'category', 'division_id', 'is_material']);

        // Harga beli default hanya untuk user yang boleh melihatnya; lainnya kosong
        // (server tetap memakai harga beli master saat posting).
        $data = $products->map(fn (MasterProduct $product) => $product->only(['id', 'name', 'code', 'brand', 'category']) + [
            'serialized' => ! $product->isMaterial(),
            'cost' => $user->canViewPurchasePriceOf($product) ? StockCost::standardCost($product->id) : null,
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
            'items.*.master_product_id' => 'required|integer|exists:master_products,id',
            'items.*.qty' => 'required|numeric|gt:0',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.supplier_id' => 'nullable|integer|exists:suppliers,id',
            'items.*.warranty_until' => 'nullable|date',
            'items.*.serial_numbers' => 'nullable',
            'items.*.note' => 'nullable|string|max:255',
        ], [
            'items.required' => 'Tambahkan minimal satu barang.',
            'note.required_if' => 'Keterangan wajib diisi untuk alasan Lain-lain.',
        ]);

        if (! Warehouse::active()->whereKey($validated['warehouse_id'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Gudang tidak aktif.'], 422);
        }

        try {
            $entry = DB::transaction(fn () => $this->persist($validated));
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

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

    private function persist(array $validated): StockEntry
    {
        $service = app(StockService::class);
        $date = $validated['entry_date'] ?? now()->toDateString();
        $products = MasterProduct::whereIn('id', collect($validated['items'])->pluck('master_product_id'))->get()->keyBy('id');

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
            $product = $products[(int) $row['master_product_id']];
            $serials = StockService::parseSerials($row['serial_numbers'] ?? null);

            $item = $entry->items()->create([
                'master_product_id' => $product->id,
                'qty' => StockService::round((float) $row['qty']),
                'unit' => $row['unit'] ?? null,
                'unit_cost' => isset($row['unit_cost']) ? (float) $row['unit_cost'] : StockCost::standardCost($product->id),
                'batch_number' => $row['batch_number'] ?? null,
                'expiry_date' => $row['expiry_date'] ?? null,
                'supplier_id' => $row['supplier_id'] ?? null,
                'warranty_until' => $row['warranty_until'] ?? null,
                'serial_numbers' => $serials ?: null,
                'note' => $row['note'] ?? null,
                'sort_order' => $i + 1,
            ]);

            $log = [
                'date' => $date,
                'ref_type' => StockLog::REF_STOCK_ENTRY_ITEM,
                'ref_id' => $item->id,
                'note' => $entry->reason_label.' '.$entry->entry_number,
            ];

            // SN yang pernah keluar (mis. retur dari customer) kembali ke baris stok lamanya.
            $returning = $product->isMaterial() || ! $serials ? collect() : Stock::where('master_product_id', $product->id)
                ->whereIn('serial_number', $serials)->where('is_open', false)->lockForUpdate()->get();
            foreach ($returning as $stock) {
                $service->reactivate($stock, $entry->warehouse_id, $entry->project_id, StockLog::TYPE_IN, $log);
            }

            $newQty = $item->qty - $returning->count();
            if ($newQty > Stock::EPSILON) {
                $service->receive($product, $newQty, [
                    'warehouse_id' => $entry->warehouse_id,
                    'project_id' => $entry->project_id,
                    'received_date' => $date,
                    'expiry_date' => $item->expiry_date,
                    'batch_number' => $item->batch_number,
                    'supplier_id' => $item->supplier_id,
                    'warranty_until' => $item->warranty_until,
                    'unit' => $item->unit,
                    'unit_cost' => $item->unit_cost,
                    'source_type' => Stock::SOURCE_STOCK_ENTRY_ITEM,
                    'source_id' => $item->id,
                    'note' => $item->note,
                ], array_values(array_diff($serials, $returning->pluck('serial_number')->all())), $log);
            }
        }

        return $entry;
    }

    public function show($id)
    {
        $entry = StockEntry::with(['items.masterProduct', 'items.supplier', 'items.stocks.masterProduct', 'warehouse', 'project', 'creator'])->findOrFail($id);

        return view('stock-entry.show', compact('entry'));
    }
}
