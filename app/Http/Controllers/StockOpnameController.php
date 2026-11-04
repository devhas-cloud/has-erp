<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\Warehouse;
use App\Support\StockValuation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Stock Opname — hitung fisik per gudang. Baris = (produk, kepemilikan);
 * boleh sebagian produk (cycle count). Draft oleh gudang; Posting
 * (stock-opname.approve -> can_approve) menghitung ulang qty sistem lalu
 * mencatat selisih sebagai adjustment_in / adjustment_out.
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
     * Qty sistem per (produk, kepemilikan) di gudang — termasuk saldo 0 yang pernah ada.
     */
    public function systemStock(Request $request): JsonResponse
    {
        $validated = $request->validate(['warehouse_id' => 'required|integer|exists:warehouses,id']);

        return response()->json(['success' => true, 'items' => $this->systemLines((int) $validated['warehouse_id'])->values()]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $products = MasterProduct::active()
            ->where(fn ($b) => $b->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")->orWhere('brand', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'code', 'brand', 'category']);

        return response()->json(['success' => true, 'data' => $products]);
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
        $opname = StockOpname::with(['items.masterProduct', 'items.project', 'warehouse', 'creator', 'postedBy'])->findOrFail($id);

        // Draft: tampilkan qty sistem & harga terkini (yang akan dipakai saat posting).
        if ($opname->isDraft()) {
            $system = $this->systemLines($opname->warehouse_id)->keyBy(fn ($line) => $line['master_product_id'].':'.($line['project_id'] ?? ''));
            foreach ($opname->items as $item) {
                $item->system_qty = $system[$item->master_product_id.':'.($item->project_id ?? '')]['system_qty'] ?? 0;
                $item->unit_cost = StockValuation::averageCost($item->master_product_id);
            }
        }

        return view('stock-opname.show', compact('opname'));
    }

    public function edit($id)
    {
        $opname = StockOpname::with('items.masterProduct')->findOrFail($id);
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
            $opname->items()->delete();
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
     * Posting (can_approve): qty sistem dihitung ulang, selisih dicatat sebagai penyesuaian.
     */
    public function approve($id): JsonResponse
    {
        $opname = StockOpname::with('items.masterProduct')->findOrFail($id);
        if (! $opname->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Stock opname sudah diposting.'], 422);
        }

        $adjusted = DB::transaction(function () use ($opname) {
            $adjusted = 0;
            foreach ($opname->items as $item) {
                $systemQty = StockMovement::balance($opname->warehouse_id, $item->master_product_id, $item->project_id);
                $cost = StockValuation::averageCost($item->master_product_id);
                $item->update(['system_qty' => $systemQty, 'unit_cost' => $cost]);

                $difference = $item->difference();
                if ($difference === 0) {
                    continue;
                }

                StockMovement::record([
                    'warehouse_id' => $opname->warehouse_id,
                    'master_product_id' => $item->master_product_id,
                    'project_id' => $item->project_id,
                    'type' => $difference > 0 ? StockMovement::TYPE_ADJUSTMENT_IN : StockMovement::TYPE_ADJUSTMENT_OUT,
                    'qty' => abs($difference),
                    'unit' => $item->unit,
                    'unit_cost' => $cost,
                    'source_type' => StockMovement::SOURCE_STOCK_OPNAME,
                    'source_id' => $opname->id,
                    'source_line_id' => $item->id,
                    'moved_at' => $opname->opname_date?->toDateString() ?? now()->toDateString(),
                    'note' => 'Stock opname '.$opname->opname_number,
                ]);
                $adjusted++;
            }

            $opname->update([
                'status' => StockOpname::STATUS_POSTED,
                'posted_by' => Auth::id(),
                'posted_at' => now(),
            ]);

            return $adjusted;
        });

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
            'warehouses' => Warehouse::active()->orderBy('type')->orderBy('name')->get(),
            'items' => $opname?->items->map(fn ($item) => [
                'master_product_id' => $item->master_product_id,
                'project_id' => $item->project_id,
                'counted_qty' => $item->counted_qty,
                'remark' => $item->remark,
                'code' => $item->masterProduct?->code,
                'name' => $item->masterProduct?->name,
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
            'items.*.master_product_id' => 'required|integer|exists:master_products,id',
            'items.*.project_id' => 'nullable|integer|exists:projects,id',
            'items.*.counted_qty' => 'required|integer|min:0',
            'items.*.remark' => 'nullable|string|max:255',
        ], ['items.required' => 'Isi qty hitung minimal satu baris.']);

        $validated['warehouse_id'] = $warehouseId ?? (int) $validated['warehouse_id'];

        $keys = collect($validated['items'])->map(fn ($row) => $row['master_product_id'].':'.($row['project_id'] ?? ''));
        if ($keys->duplicates()->isNotEmpty()) {
            abort(response()->json(['success' => false, 'message' => 'Produk dengan kepemilikan yang sama tidak boleh muncul dua kali.'], 422));
        }

        return $validated;
    }

    private function syncItems(StockOpname $opname, array $items): void
    {
        $system = $this->systemLines($opname->warehouse_id)->keyBy(fn ($line) => $line['master_product_id'].':'.($line['project_id'] ?? ''));

        foreach (array_values($items) as $i => $row) {
            $line = $system->get($row['master_product_id'].':'.($row['project_id'] ?? ''));
            $opname->items()->create([
                'master_product_id' => $row['master_product_id'],
                'project_id' => $row['project_id'] ?? null,
                'system_qty' => $line['system_qty'] ?? 0,
                'counted_qty' => (int) $row['counted_qty'],
                'unit' => $line['unit'] ?? null,
                'remark' => $row['remark'] ?? null,
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function systemLines(int $warehouseId): Collection
    {
        $rows = StockMovement::balancesQuery()->where('warehouse_id', $warehouseId)->get();

        $products = MasterProduct::whereIn('id', $rows->pluck('master_product_id'))->get(['id', 'code', 'name'])->keyBy('id');
        $projects = Project::whereIn('id', $rows->pluck('project_id')->filter())->get(['id', 'project_number', 'project_name'])->keyBy('id');

        return $rows->map(fn ($row) => [
            'master_product_id' => (int) $row->master_product_id,
            'code' => $products[$row->master_product_id]->code ?? '—',
            'name' => $products[$row->master_product_id]->name ?? '—',
            'project_id' => $row->project_id ? (int) $row->project_id : null,
            'ownership' => $row->project_id && isset($projects[$row->project_id])
                ? $projects[$row->project_id]->project_number.' — '.$projects[$row->project_id]->project_name
                : 'Stok bebas',
            'system_qty' => (int) $row->balance,
            'unit' => $row->unit,
        ])->sortBy(fn ($line) => $line['code'].'|'.($line['project_id'] ?? 0))->values();
    }
}
