<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\StockCost;
use App\Support\StockPicker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Stock Opname — hitung fisik per gudang, per BARIS STOK (batch/SN/harga tepat);
 * boleh sebagian (cycle count). Barang ber-SN cukup dicentang ada/tidak.
 * Barang temuan (belum ada di sistem) ditambahkan sebagai baris baru.
 *
 * Angka sistem dicatat saat qty fisik diisi/disimpan. Posting
 * (stock-opname.approve -> can_approve) menerapkan selisih (fisik - angka saat
 * dihitung) ke qty sekarang, sehingga barang yang keluar/masuk setelah dihitung
 * tetap benar. Hasil minus ditolak.
 */
class StockOpnameController extends Controller
{
    public function index()
    {
        return view('stock-opname.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = StockOpname::withCount('items')->with(['warehouse', 'creator']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('opname_number', 'like', "%{$searchValue}%")
                    ->orWhereHas('warehouse', fn ($w) => $w->where('name', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $opnames = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        $data = $opnames->map(fn (StockOpname $opname, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'id' => $opname->id,
            'opname_number' => $opname->opname_number,
            'opname_date' => $opname->opname_date?->format('d/m/Y') ?? '—',
            'warehouse_name' => $opname->warehouse?->name ?? '—',
            'item_count' => $opname->items_count,
            'creator' => $opname->creator?->display_name ?? '—',
            'status' => $opname->status,
            'status_badge' => $opname->statusBadgeHtml(),
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
        return view('stock-opname.form', $this->formData(null));
    }

    /**
     * Baris stok terbuka di gudang (opsional disaring) untuk dihitung.
     */
    public function systemStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'q' => 'nullable|string|max:100',
            'include' => 'nullable|array',
            'include.*' => 'integer',
        ]);

        $rows = StockPicker::options([
            'warehouse_id' => $validated['warehouse_id'],
            'q' => $validated['q'] ?? null,
            'limit' => 1000,
        ]);

        // Baris yang sudah ada di draft tetap tampil walau stoknya sudah habis.
        $missing = collect($validated['include'] ?? [])->diff($rows->pluck('id'));
        if ($missing->isNotEmpty()) {
            $extra = Stock::with(['masterProduct:id,code,name,is_material', 'warehouse:id,code,name', 'project:id,project_number,project_name'])
                ->whereIn('id', $missing)->where('warehouse_id', $validated['warehouse_id'])->get()
                ->map(fn (Stock $stock) => StockPicker::row($stock));
            $rows = $rows->concat($extra);
        }

        return response()->json(['success' => true, 'data' => $rows->values()]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $products = MasterProduct::active()
            ->where(fn ($b) => $b->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")->orWhere('brand', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'code', 'brand', 'category', 'is_material']);

        return response()->json(['success' => true, 'data' => $products->map(fn (MasterProduct $p) => $p->only(['id', 'name', 'code', 'brand', 'category']) + [
            'serialized' => ! $p->isMaterial(),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateOpname($request);

        $opname = DB::transaction(function () use ($validated) {
            $opname = StockOpname::create([
                'opname_number' => StockOpname::generateNumber($validated['opname_date']),
                'warehouse_id' => $validated['warehouse_id'],
                'opname_date' => $validated['opname_date'],
                'note' => $validated['note'] ?? null,
                'status' => StockOpname::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);
            $this->syncItems($opname, $validated['items']);

            return $opname;
        });

        Log::record('create_stock_opname', 'Stock opname '.$opname->opname_number.' dibuat oleh '.Auth::user()->display_name, 'MOD_STOCK_OPNAME', $opname);

        return response()->json(['success' => true, 'message' => 'Stock opname '.$opname->opname_number.' disimpan sebagai draft.', 'id' => $opname->id]);
    }

    public function show($id)
    {
        $opname = StockOpname::with(['items.masterProduct', 'items.project', 'items.stock', 'warehouse', 'creator', 'postedBy'])->findOrFail($id);

        // Draft: tandai baris yang stoknya berubah sejak dihitung (selisih tetap diterapkan ke qty sekarang).
        $changed = [];
        if ($opname->isDraft()) {
            foreach ($opname->items->whereNotNull('stock_id') as $item) {
                if ($item->stock && abs($item->stock->qty - $item->system_qty) >= Stock::EPSILON) {
                    $changed[$item->id] = $item->stock->qty;
                }
            }
        }

        return view('stock-opname.show', compact('opname', 'changed'));
    }

    public function edit($id)
    {
        $opname = StockOpname::with(['items.masterProduct', 'items.project'])->findOrFail($id);
        abort_unless($opname->isDraft(), 403, 'Stock opname yang sudah diposting tidak bisa diubah.');

        return view('stock-opname.form', $this->formData($opname));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $opname = StockOpname::findOrFail($id);
        if (! $opname->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Stock opname yang sudah diposting tidak bisa diubah.'], 422);
        }

        $validated = $this->validateOpname($request, $opname->warehouse_id);

        DB::transaction(function () use ($opname, $validated) {
            $opname->update(['opname_date' => $validated['opname_date'], 'note' => $validated['note'] ?? null]);
            $this->syncItems($opname, $validated['items']);
        });

        return response()->json(['success' => true, 'message' => 'Stock opname '.$opname->opname_number.' diperbarui.', 'id' => $opname->id]);
    }

    public function destroy($id): JsonResponse
    {
        $opname = StockOpname::findOrFail($id);
        if (! $opname->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Stock opname yang sudah diposting tidak bisa dihapus.'], 422);
        }

        $opname->delete();

        return response()->json(['success' => true, 'message' => 'Stock opname dihapus.']);
    }

    /**
     * Posting (can_approve): selisih (fisik - angka saat dihitung) diterapkan ke
     * qty sekarang; barang temuan menjadi baris stok baru.
     */
    public function approve($id): JsonResponse
    {
        $opname = StockOpname::with(['items.masterProduct'])->findOrFail($id);
        if (! $opname->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Stock opname sudah diposting.'], 422);
        }

        $service = app(StockService::class);
        $date = $opname->opname_date?->toDateString() ?? now()->toDateString();

        try {
            $adjusted = DB::transaction(function () use ($opname, $service, $date) {
                $adjusted = 0;
                $stocks = $service->lock($opname->items->pluck('stock_id')->filter());

                foreach ($opname->items as $item) {
                    $log = [
                        'date' => $date,
                        'ref_type' => StockLog::REF_STOCK_OPNAME_ITEM,
                        'ref_id' => $item->id,
                        'note' => 'Stock opname '.$opname->opname_number,
                    ];

                    if (! $item->isFound()) {
                        $stock = $stocks->get($item->stock_id);
                        if (! $stock) {
                            throw new StockException('Baris stok item '.$item->sort_order.' tidak ditemukan.');
                        }
                        $diff = $item->difference();
                        if (abs($diff) >= Stock::EPSILON) {
                            $service->adjust($stock, $diff, $log);
                            $adjusted++;
                        }

                        continue;
                    }

                    if ((float) $item->counted_qty <= 0) {
                        continue;
                    }
                    $adjusted++;
                    $product = $item->masterProduct;

                    // SN yang pernah keluar ditemukan lagi: aktifkan baris lamanya.
                    $existing = $item->serial_number
                        ? Stock::where('master_product_id', $product->id)->where('serial_number', $item->serial_number)->lockForUpdate()->first()
                        : null;
                    if ($existing) {
                        $service->reactivate($existing, (int) $opname->warehouse_id, $item->project_id, StockLog::TYPE_ADJUST, $log);

                        continue;
                    }

                    $service->receive($product, (float) $item->counted_qty, [
                        'warehouse_id' => $opname->warehouse_id,
                        'project_id' => $item->project_id,
                        'received_date' => $date,
                        'expiry_date' => $item->expiry_date,
                        'batch_number' => $item->batch_number,
                        'unit' => $item->unit,
                        'unit_cost' => $item->unit_cost ?? StockCost::standardCost($product->id),
                        'source_type' => Stock::SOURCE_STOCK_OPNAME_ITEM,
                        'source_id' => $item->id,
                        'note' => $item->remark,
                    ], $item->serial_number ? [$item->serial_number] : [], $log, StockLog::TYPE_ADJUST);
                }

                $opname->update([
                    'status' => StockOpname::STATUS_POSTED,
                    'posted_by' => Auth::id(),
                    'posted_at' => now(),
                ]);

                return $adjusted;
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Log::record('post_stock_opname', 'Stock opname '.$opname->opname_number.' diposting ('.$adjusted.' penyesuaian) oleh '.Auth::user()->display_name, 'MOD_STOCK_OPNAME', $opname);

        return response()->json([
            'success' => true,
            'message' => 'Stock opname diposting — '.($adjusted ? $adjusted.' baris disesuaikan.' : 'tidak ada selisih.'),
        ]);
    }

    private function formData(?StockOpname $opname): array
    {
        return [
            'opname' => $opname,
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'projects' => Project::where('status', Project::STATUS_ACTIVE)->orderByDesc('id')->get(['id', 'project_number', 'project_name']),
            'items' => $opname?->items->map(fn (StockOpnameItem $item) => [
                'stock_id' => $item->stock_id,
                'master_product_id' => $item->master_product_id,
                'project_id' => $item->project_id,
                'counted_qty' => $item->counted_qty,
                'system_qty' => $item->system_qty,
                'remark' => $item->remark,
                'batch_number' => $item->batch_number,
                'serial_number' => $item->serial_number,
                'expiry_date' => $item->expiry_date?->toDateString(),
                'unit' => $item->unit,
                'code' => $item->masterProduct?->code,
                'name' => $item->masterProduct?->name,
                'serialized' => $item->masterProduct ? ! $item->masterProduct->isMaterial() : false,
            ])->values() ?? [],
        ];
    }

    private function validateOpname(Request $request, ?int $warehouseId = null): array
    {
        $validated = $request->validate([
            'warehouse_id' => $warehouseId ? 'nullable' : 'required|integer|exists:warehouses,id',
            'opname_date' => 'required|date',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.stock_id' => 'nullable|integer|exists:stocks,id',
            'items.*.master_product_id' => 'required_without:items.*.stock_id|nullable|integer|exists:master_products,id',
            'items.*.project_id' => 'nullable|integer|exists:projects,id',
            'items.*.counted_qty' => 'required|numeric|min:0',
            'items.*.remark' => 'nullable|string|max:255',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.serial_number' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.unit' => 'nullable|string|max:50',
        ], ['items.required' => 'Isi qty hitung minimal satu baris.']);

        $validated['warehouse_id'] = $warehouseId ?? (int) $validated['warehouse_id'];

        $stockIds = collect($validated['items'])->pluck('stock_id')->filter();
        if ($stockIds->duplicates()->isNotEmpty()) {
            abort(response()->json(['success' => false, 'message' => 'Baris stok yang sama tidak boleh muncul dua kali.'], 422));
        }
        if (Stock::whereIn('id', $stockIds)->where('warehouse_id', '!=', $validated['warehouse_id'])->exists()) {
            abort(response()->json(['success' => false, 'message' => 'Ada baris stok yang tidak berada di gudang opname.'], 422));
        }

        return $validated;
    }

    /**
     * Simpan baris draft. Angka sistem dicatat ulang hanya untuk baris yang qty
     * fisiknya baru diisi/berubah (saat itulah barang dihitung).
     */
    private function syncItems(StockOpname $opname, array $items): void
    {
        $existing = $opname->items()->get()->keyBy(fn (StockOpnameItem $i) => $i->stock_id ? 's'.$i->stock_id : 'f'.$i->id);
        $stocks = Stock::with('masterProduct')->whereIn('id', collect($items)->pluck('stock_id')->filter())->get()->keyBy('id');
        $products = MasterProduct::whereIn('id', collect($items)->pluck('master_product_id')->filter())->get()->keyBy('id');
        $keep = [];

        foreach (array_values($items) as $i => $row) {
            $counted = StockService::round((float) $row['counted_qty']);
            $stock = ! empty($row['stock_id']) ? $stocks->get((int) $row['stock_id']) : null;

            if ($stock) {
                if ($stock->isSerialized() && ! in_array($counted, [0.0, 1.0], true)) {
                    abort(response()->json(['success' => false, 'message' => $stock->masterProduct?->code.' '.$stock->identity_label.': barang ber-SN dihitung 0 atau 1.'], 422));
                }
                $old = $existing->get('s'.$stock->id);
                $recount = ! $old || abs((float) $old->counted_qty - $counted) >= Stock::EPSILON;
                $attrs = [
                    'stock_id' => $stock->id,
                    'master_product_id' => $stock->master_product_id,
                    'project_id' => $stock->project_id,
                    'counted_qty' => $counted,
                    'unit' => $stock->unit,
                    'unit_cost' => $stock->unit_cost,
                    'remark' => $row['remark'] ?? null,
                    'sort_order' => $i + 1,
                ] + ($recount ? ['system_qty' => $stock->qty, 'counted_at' => now()] : []);

                if ($old) {
                    $old->update($attrs);
                    $keep[] = $old->id;
                } else {
                    $keep[] = $opname->items()->create($attrs + ['system_qty' => $stock->qty, 'counted_at' => now()])->id;
                }

                continue;
            }

            // Barang temuan: belum ada di sistem.
            $product = $products->get((int) $row['master_product_id']);
            if ($product && ! $product->isMaterial() && $counted > 1) {
                abort(response()->json(['success' => false, 'message' => $product->code.': barang ber-SN ditambahkan per unit (qty 1, SN boleh menyusul).'], 422));
            }
            $item = $opname->items()->create([
                'master_product_id' => $product->id,
                'project_id' => $row['project_id'] ?? null,
                'batch_number' => $row['batch_number'] ?? null,
                'serial_number' => $product->isMaterial() ? null : (($row['serial_number'] ?? '') !== '' ? trim($row['serial_number']) : null),
                'expiry_date' => $row['expiry_date'] ?? null,
                'system_qty' => 0,
                'counted_qty' => $counted,
                'counted_at' => now(),
                'unit' => $row['unit'] ?? null,
                'unit_cost' => StockCost::standardCost($product->id),
                'remark' => $row['remark'] ?? null,
                'sort_order' => $i + 1,
            ]);
            $keep[] = $item->id;
        }

        // Baris yang tidak dikirim lagi dihapus; baris temuan lama selalu diganti yang baru.
        $opname->items()->whereNotIn('id', array_filter($keep))->delete();
    }
}
