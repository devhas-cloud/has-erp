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
 * retur, hibah/sampel, dll). Produk wajib dari master; satuan selalu dari master.
 * Disimpan langsung terposting (log 'in'), tidak bisa diubah/dihapus.
 *   - Material: menambah saldo produk + gudang + kepemilikan (harga rata-rata
 *     tertimbang); batch / kedaluwarsa / garansi tidak dipakai.
 *   - Barang ber-SN: 1 baris stok per unit; SN yang pernah keluar kembali ke barisnya.
 */
class StockEntryController extends Controller
{
    public function index()
    {
        return view('stock-entry.index', [
            'warehouses' => Warehouse::whereIn('id', StockEntry::select('warehouse_id'))->orderBy('name')->get(['id', 'code', 'name']),
            'projects' => Project::whereIn('id', StockEntry::whereNotNull('project_id')->select('project_id'))->orderByDesc('id')->get(['id', 'project_number', 'project_name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = StockEntry::withCount('items')->with(['warehouse.branch', 'project', 'creator', 'items:id,stock_entry_id,master_product_id,qty', 'items.masterProduct:id,is_material']);

        $recordsTotal = (clone $query)->count();

        $searchValue = trim((string) $request->input('search.value', ''));
        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('entry_number', 'like', "%{$searchValue}%")
                    ->orWhere('note', 'like', "%{$searchValue}%")
                    ->orWhereHas('warehouse', fn ($w) => $w->where('name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('project', fn ($p) => $p->where('project_number', 'like', "%{$searchValue}%")->orWhere('project_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('items.masterProduct', fn ($p) => $p->where('code', 'like', "%{$searchValue}%")->orWhere('name', 'like', "%{$searchValue}%"));
            });
        }

        $ownership = (string) $request->input('ownership', '');
        $query
            ->when($request->input('reason'), fn ($q, $reason) => $q->where('reason', $reason))
            ->when($request->integer('warehouse'), fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($ownership === 'free', fn ($q) => $q->whereNull('project_id'))
            ->when(ctype_digit($ownership), fn ($q) => $q->where('project_id', (int) $ownership))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('entry_date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('entry_date', '<=', $date))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()));

        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $entries = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        // Nilai masuk = log masuk (harga modal saat masuk).
        // (Bukan flatMap: kunci id item akan diurut ulang.)
        $itemToEntry = collect();
        foreach ($entries as $e) {
            foreach ($e->items as $i) {
                $itemToEntry[$i->id] = $e->id;
            }
        }
        $values = $itemToEntry->isEmpty() ? collect() : StockLog::where('ref_type', StockLog::REF_STOCK_ENTRY_ITEM)
            ->whereIn('ref_id', $itemToEntry->keys())
            ->where('type', StockLog::TYPE_IN)
            ->get(['ref_id', 'qty', 'unit_cost'])
            ->groupBy(fn ($log) => $itemToEntry[$log->ref_id])
            ->map(fn ($logs) => round($logs->sum(fn ($log) => $log->qty * $log->unit_cost), 2));

        $data = $entries->map(fn (StockEntry $entry, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'id' => $entry->id,
            'entry_number' => $entry->entry_number,
            'entry_date' => $entry->entry_date?->format('d/m/Y') ?? '—',
            'reason' => $entry->reason,
            'reason_label' => $entry->reason_label,
            'warehouse_name' => $entry->warehouse ? $entry->warehouse->code.' — '.$entry->warehouse->name : '—',
            'warehouse_branch' => $entry->warehouse?->branch?->name,
            'project_id' => $entry->project_id,
            'ownership' => $entry->project ? $entry->project->project_number.' — '.$entry->project->project_name : 'Free stock',
            'item_count' => $entry->items_count,
            'material_lines' => $entry->items->filter(fn ($i) => $i->masterProduct?->isMaterial())->count(),
            'serial_units' => (int) $entry->items->filter(fn ($i) => $i->masterProduct && ! $i->masterProduct->isMaterial())->sum('qty'),
            'value' => (float) ($values[$entry->id] ?? 0),
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
            // Harga modal disimpan dalam mata uang dasar.
            'baseSymbol' => \App\Models\Currency::where('is_base', true)->value('symbol') ?: \App\Models\Currency::baseName(),
        ]);
    }

    /**
     * Katalog produk untuk popup "Add from Catalog": per halaman, filter jenis & kategori.
     * Stock Entry tidak terikat aturan harga master (Admin/IMS): harga modal standar
     * selalu tampil sebagai nilai awal harga satuan.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));

        $products = MasterProduct::active()
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhere('brand', 'like', "%{$q}%")
                ->orWhere('category', 'like', "%{$q}%")))
            ->when($request->input('kind') === 'material', fn ($b) => $b->where('is_material', true))
            ->when($request->input('kind') === 'serial', fn ($b) => $b->where('is_material', false))
            ->when($request->filled('category'), fn ($b) => $b->where('category', $request->input('category')))
            ->orderBy('code')
            ->paginate($perPage, ['id', 'name', 'code', 'brand', 'category', 'division_id', 'is_material', 'unit', 'description']);

        $categories = $request->boolean('with_categories')
            ? MasterProduct::active()->whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category')
            : null;

        return response()->json([
            'success' => true,
            'data' => collect($products->items())->map(fn (MasterProduct $product) => $product->only(['id', 'name', 'code', 'brand', 'category', 'unit']) + [
                'description' => $product->description ? \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($product->description))), 160) : null,
                'serialized' => ! $product->isMaterial(),
                'cost' => StockCost::standardCost($product->id),
            ]),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
            'categories' => $categories,
        ]);
    }

    /**
     * Saldo material saat ini (qty & harga rata-rata) di gudang + kepemilikan,
     * untuk ditampilkan di form sebelum posting.
     */
    public function balances(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'product_ids' => 'required|array|max:500',
            'product_ids.*' => 'integer',
        ]);

        $rows = Stock::where('warehouse_id', $validated['warehouse_id'])
            ->when($validated['project_id'] ?? null, fn ($q, $id) => $q->where('project_id', $id), fn ($q) => $q->whereNull('project_id'))
            ->whereIn('master_product_id', $validated['product_ids'])
            ->whereNull('serial_number')
            ->whereHas('masterProduct', fn ($p) => $p->where('is_material', true))
            ->orderBy('id')
            ->get(['id', 'master_product_id', 'qty', 'unit_cost', 'unit'])
            ->unique('master_product_id');

        return response()->json(['success' => true, 'data' => $rows->mapWithKeys(fn (Stock $s) => [$s->master_product_id => [
            'qty' => round((float) $s->qty, 4),
            'qty_label' => StockCost::qty((float) $s->qty),
            'unit_cost' => round((float) $s->unit_cost, 4),
            'unit' => $s->unit,
        ]])]);
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
            'items.required' => 'Add at least one item.',
            'note.required_if' => 'A description is required for the reason "Other".',
        ]);

        if (! Warehouse::active()->whereKey($validated['warehouse_id'])->exists()) {
            return response()->json(['success' => false, 'message' => 'The warehouse is not active.'], 422);
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
            'message' => 'Stock entry '.$entry->entry_number.' posted.',
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
            // Material: satu saldo, tanpa batch / kedaluwarsa / garansi.
            $tracked = ! $product->isMaterial();

            $item = $entry->items()->create([
                'master_product_id' => $product->id,
                'qty' => StockService::round((float) $row['qty']),
                // Satuan selalu dari master produk (saldo material tidak boleh tertimpa satuan ketikan).
                'unit' => $product->unit,
                'unit_cost' => isset($row['unit_cost']) ? (float) $row['unit_cost'] : StockCost::standardCost($product->id),
                'batch_number' => $tracked ? ($row['batch_number'] ?? null) : null,
                'expiry_date' => $tracked ? ($row['expiry_date'] ?? null) : null,
                'supplier_id' => $tracked ? ($row['supplier_id'] ?? null) : null,
                'warranty_until' => $tracked ? ($row['warranty_until'] ?? null) : null,
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

        // Log masuk per baris: harga modal & saldo material sesudahnya.
        $inLogs = StockLog::where('ref_type', StockLog::REF_STOCK_ENTRY_ITEM)
            ->whereIn('ref_id', $entry->items->pluck('id')->all() ?: [0])
            ->where('type', StockLog::TYPE_IN)
            ->orderBy('id')
            ->get()
            ->groupBy('ref_id');

        return view('stock-entry.show', compact('entry', 'inLogs'));
    }
}
