<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\MaterialHandoverItem;
use App\Models\MaterialRequest;
use App\Models\Production;
use App\Models\Project;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\StockCost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Produksi — pilih item + qty dari Material di Produksi satu project + branch
 * (gabungan semua serah terima project itu) menjadi barang jadi.
 *
 *  - Material boleh dipakai sebagian; barang ber-SN dipakai per SN (utuh).
 *  - Barang jadi tidak ber-SN: produk master yang sudah ada (harus Material —
 *    Barang ber-SN ditolak) atau dibuat baru di sini (Material, tanpa harga).
 *  - Barang jadi masuk ke gudang pilihan di branch yang sama sebagai stok
 *    milik project (log in). Harga modal = Σ qty × harga modal material ÷ qty.
 *    Biaya project sudah tercatat saat serah terima, jadi tidak bertambah.
 *  - Batal (void, can_approve) hanya selama baris barang jadi belum tersentuh.
 */
class ProductionController extends Controller
{
    public function index()
    {
        $visible = Production::visibleTo(Auth::user());

        return view('production.index', [
            'projects' => Project::whereIn('id', (clone $visible)->select('project_id'))->orderBy('project_number')->get(['id', 'project_number', 'project_name']),
            'branches' => Branch::whereIn('id', Warehouse::whereIn('id', (clone $visible)->select('output_warehouse_id'))->select('branch_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Production::visibleTo(Auth::user())
            ->with(['project:id,project_number,project_name', 'outputProduct:id,code,name,unit', 'outputWarehouse.branch', 'creator', 'items.handoverItem.stock']);

        $recordsTotal = (clone $query)->count();

        $query
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('project'), fn ($q, $id) => $q->where('project_id', $id))
            ->when($request->integer('branch'), fn ($q, $id) => $q->whereHas('outputWarehouse', fn ($w) => $w->where('branch_id', $id)))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('production_date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('production_date', '<=', $date))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()));

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('production_number', 'like', "%{$search}%")
                ->orWhereHas('outputProduct', fn ($p) => $p->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                ->orWhereHas('project', fn ($p) => $p->where('project_number', 'like', "%{$search}%")->orWhere('project_name', 'like', "%{$search}%"))
                ->orWhereHas('items.handoverItem.stock', fn ($s) => $s->where('serial_number', 'like', "%{$search}%")));
        }

        $recordsFiltered = (clone $query)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $rows = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->map(fn (Production $p, $i) => [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $p->id,
                'production_number' => $p->production_number,
                'production_date' => $p->production_date?->format('d/m/Y'),
                'project' => $p->project ? $p->project->project_number.' — '.$p->project->project_name : '—',
                'product' => ($p->outputProduct?->code ?? '').' — '.($p->outputProduct?->name ?? ''),
                'qty' => StockCost::qty($p->output_qty).' '.($p->outputProduct?->unit ?? ''),
                'warehouse' => $p->outputWarehouse ? $p->outputWarehouse->code.' · '.$p->outputWarehouse->branch_label : '—',
                'total_cost' => StockCost::format($p->totalCost()),
                'creator' => $p->creator?->display_name ?? '—',
                'status' => $p->status,
                'status_badge' => $p->statusBadgeHtml(),
            ]),
        ]);
    }

    public function create()
    {
        // Branch yang masih punya Material di Produksi.
        $groups = $this->openPool()
            ->groupBy(fn (MaterialHandoverItem $item) => $item->materialHandover->materialRequest->branch_id)
            ->map(function (Collection $items) {
                $branch = $items->first()->materialHandover->materialRequest->branch;

                return [
                    'branch_id' => $branch->id,
                    'label' => $branch->name,
                    'lines' => $items->count(),
                ];
            })
            ->sortBy('label')
            ->values();

        return view('production.form', [
            'groups' => $groups,
            'units' => MasterProduct::unitOptions(),
        ]);
    }

    /**
     * Material di Produksi (sisa) satu branch (semua project), plus gudang hasil.
     */
    public function pool(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
        ]);
        abort_unless(Auth::user()->canAccessBranch((int) $validated['branch_id']), 403, 'Branch bukan milik Anda.');

        $rows = MaterialHandoverItem::withUsedQty()
            ->whereHas('materialHandover.materialRequest', fn (Builder $q) => $q->where('branch_id', (int) $validated['branch_id']))
            ->with(['stock.masterProduct', 'materialHandover.materialRequest.project:id,project_number,project_name', 'outLog'])
            ->orderBy('id')
            ->get()
            ->filter(fn (MaterialHandoverItem $item) => $item->remainingQty() > 0)
            ->map(fn (MaterialHandoverItem $item) => [
                'id' => $item->id,
                'product_code' => $item->stock?->masterProduct?->code,
                'product_name' => $item->stock?->masterProduct?->name,
                'identity' => $item->stock?->identity_label,
                'serialized' => (bool) $item->stock?->isSerialized(),
                'remaining' => $item->remainingQty(),
                'remaining_label' => StockCost::qty($item->remainingQty()),
                'unit' => $item->stock?->unit,
                'unit_cost' => $item->unitCost(),
                'handover_number' => $item->materialHandover?->handover_number,
                'project_id' => $item->materialHandover?->materialRequest?->project_id,
                'project_label' => $item->materialHandover?->materialRequest?->project
                    ? $item->materialHandover->materialRequest->project->project_number.' — '.$item->materialHandover->materialRequest->project->project_name
                    : '—',
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'warehouses' => Warehouse::active()->where('branch_id', $validated['branch_id'])->orderBy('name')->get(['id', 'code', 'name']),
            // Project barang jadi: hanya project yang mengajukan Material Request di branch ini.
            'projects' => Project::whereIn('id', $this->requestingProjects((int) $validated['branch_id']))
                ->orderBy('project_number')->get(['id', 'project_number', 'project_name'])
                ->map(fn (Project $p) => ['id' => $p->id, 'label' => $p->project_number.' — '.$p->project_name]),
        ]);
    }

    /** id project yang punya Material Request Disetujui / Selesai di branch ini. */
    private function requestingProjects(int $branchId)
    {
        return MaterialRequest::where('branch_id', $branchId)
            ->whereIn('status', [MaterialRequest::STATUS_APPROVED, MaterialRequest::STATUS_COMPLETED])
            ->distinct()->pluck('project_id')->map(fn ($id) => (int) $id);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $products = MasterProduct::where('status', 'Active')
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")))
            ->orderBy('code')
            ->limit(30)
            ->get(['id', 'code', 'name', 'unit', 'is_material']);

        return response()->json(['results' => $products->map(fn (MasterProduct $p) => [
            'id' => $p->id,
            'text' => $p->code.' — '.$p->name,
            'unit' => $p->unit,
            'is_material' => $p->isMaterial(),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'production_date' => 'required|date',
            'note' => 'nullable|string|max:2000',
            'output_product_id' => 'nullable|integer|exists:master_products,id',
            'new_product' => 'required_without:output_product_id|nullable|array',
            'new_product.code' => 'required_with:new_product|nullable|string|max:50|unique:master_products,code',
            'new_product.name' => 'required_with:new_product|nullable|string|max:150|unique:master_products,name',
            'new_product.unit' => 'nullable|string|max:50',
            'output_qty' => 'required|numeric|gt:0',
            'output_warehouse_id' => 'required|integer|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.material_handover_item_id' => 'required|integer|distinct',
            'items.*.qty' => 'required|numeric|gt:0',
        ], [
            'new_product.required_without' => 'Pilih produk barang jadi atau buat produk baru.',
            'new_product.code.unique' => 'Kode produk sudah dipakai. Pilih produk itu dari daftar, atau pakai kode lain.',
            'new_product.name.unique' => 'Nama produk sudah dipakai di master produk.',
            'items.required' => 'Pilih minimal satu material yang dipakai.',
        ]);

        $fail = fn (string $message) => response()->json(['success' => false, 'message' => $message], 422);
        $projectId = (int) $validated['project_id'];
        $branchId = (int) $validated['branch_id'];

        if (! $this->requestingProjects($branchId)->contains($projectId)) {
            return $fail('Project barang jadi harus project yang mengajukan Material Request di branch ini.');
        }
        if (! Auth::user()->canAccessBranch($branchId)) {
            return response()->json(['success' => false, 'message' => 'Branch bukan milik Anda.'], 403);
        }
        $warehouse = Warehouse::active()->find($validated['output_warehouse_id']);
        if (! $warehouse || (int) $warehouse->branch_id !== $branchId) {
            return $fail('Gudang barang jadi harus gudang aktif di branch yang sama dengan material.');
        }
        if (! empty($validated['output_product_id'])) {
            $product = MasterProduct::find($validated['output_product_id']);
            if (! $product->isMaterial()) {
                return $fail($product->code.' tercatat sebagai Barang ber-SN. Barang jadi tidak ber-SN — ubah produk ini ke Material di Master Produk dulu, atau buat produk baru.');
            }
        }

        $service = app(StockService::class);
        $date = $validated['production_date'];
        $outputQty = StockService::round((float) $validated['output_qty']);
        $targetLabel = Project::whereKey($projectId)->value('project_number') ?? '#'.$projectId;

        try {
            $production = DB::transaction(function () use ($validated, $projectId, $branchId, $warehouse, $service, $date, $outputQty, $targetLabel) {
                $ids = collect($validated['items'])->pluck('material_handover_item_id')->map(fn ($id) => (int) $id)->sort()->values();
                $sources = MaterialHandoverItem::with(['stock.masterProduct', 'materialHandover.materialRequest.project'])
                    ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                $total = 0.0;
                $transfers = collect();
                foreach ($validated['items'] as $row) {
                    $source = $sources->get((int) $row['material_handover_item_id']);
                    $mr = $source?->materialHandover?->materialRequest;
                    if (! $source || (int) $mr->branch_id !== $branchId) {
                        throw new StockException('Material yang dipilih bukan Material di Produksi branch ini.');
                    }
                    $qty = StockService::round((float) $row['qty']);
                    $remaining = $source->remainingQty();
                    $label = $service->label($source->stock);
                    if ($qty - $remaining > Stock::EPSILON) {
                        throw new StockException('Sisa '.$label.' tinggal '.StockCost::qty($remaining).' (dipakai '.StockCost::qty($qty).').');
                    }
                    if ($source->stock->isSerialized() && abs($qty - $remaining) >= Stock::EPSILON) {
                        throw new StockException($label.' adalah barang ber-SN dan dipakai utuh.');
                    }
                    $total += $qty * $source->unitCost();
                    if ((int) $mr->project_id !== $projectId) {
                        $transfers->push([
                            'stock_id' => (int) $source->stock_id,
                            'master_product_id' => (int) $source->stock?->master_product_id,
                            'warehouse_id' => (int) $source->stock?->warehouse_id,
                            'is_material' => (bool) ($source->stock?->is_material ?? true),
                            'source_project_id' => (int) $mr->project_id,
                            'source_label' => $mr->project?->project_number ?? '#'.(int) $mr->project_id,
                            'qty' => $qty,
                            'unit_cost' => $source->unitCost(),
                        ]);
                    }
                }

                $product = ! empty($validated['output_product_id'])
                    ? MasterProduct::findOrFail($validated['output_product_id'])
                    : MasterProduct::create([
                        'code' => trim($validated['new_product']['code']),
                        'name' => trim($validated['new_product']['name']),
                        'unit' => trim((string) ($validated['new_product']['unit'] ?? '')) ?: null,
                        'is_material' => true,
                        'status' => 'Active',
                        'currency_id' => Currency::where('is_base', true)->where('status', 'Active')->value('id'),
                    ]);

                $production = Production::create([
                    'production_number' => Production::generateNumber($date),
                    'project_id' => $projectId,
                    'output_product_id' => $product->id,
                    'output_qty' => $outputQty,
                    'output_warehouse_id' => $warehouse->id,
                    'production_date' => $date,
                    'status' => Production::STATUS_POSTED,
                    'note' => $validated['note'] ?? null,
                    'created_by' => Auth::id(),
                ]);
                foreach ($validated['items'] as $row) {
                    $production->items()->create([
                        'material_handover_item_id' => (int) $row['material_handover_item_id'],
                        'qty' => StockService::round((float) $row['qty']),
                    ]);
                }

                // Material lintas-project: pindahkan biayanya dari project asal ke project barang jadi.
                foreach ($transfers as $t) {
                    $note = 'Alih biaya material '.$t['source_label'].' → '.$targetLabel.' ('.$production->production_number.')';
                    $base = [
                        'stock_id' => $t['stock_id'],
                        'master_product_id' => $t['master_product_id'],
                        'warehouse_id' => $t['warehouse_id'],
                        'is_material' => $t['is_material'],
                        'log_date' => $date,
                        'type' => StockLog::TYPE_ALLOCATE,
                        'qty_after' => 0,
                        'unit_cost' => $t['unit_cost'],
                        'ref_type' => StockLog::REF_PRODUCTION,
                        'ref_id' => $production->id,
                        'note' => $note,
                        'created_by' => Auth::id(),
                    ];
                    StockLog::create($base + ['project_id' => $t['source_project_id'], 'qty' => StockService::round(-$t['qty'])]);
                    StockLog::create($base + ['project_id' => $projectId, 'qty' => $t['qty']]);
                }

                $service->receive($product, $outputQty, [
                    'warehouse_id' => $warehouse->id,
                    'project_id' => $projectId,
                    'received_date' => $date,
                    'unit' => $product->unit,
                    'unit_cost' => round($total / $outputQty, 4),
                    'source_type' => Stock::SOURCE_PRODUCTION,
                    'source_id' => $production->id,
                ], [], [
                    'date' => $date,
                    'ref_type' => StockLog::REF_PRODUCTION,
                    'ref_id' => $production->id,
                    'note' => 'Hasil produksi '.$production->production_number,
                ]);

                return $production;
            });
        } catch (StockException $e) {
            return $fail($e->getMessage());
        }

        Log::record('create_production', 'Produksi '.$production->production_number.' dicatat oleh '.Auth::user()->display_name, 'MOD_PRODUCTION', $production);

        return response()->json(['success' => true, 'message' => 'Produksi '.$production->production_number.' tersimpan. Barang jadi masuk ke '.$warehouse->name.'.', 'id' => $production->id]);
    }

    public function show($id)
    {
        $production = Production::visibleTo(Auth::user())
            ->with(['project', 'outputProduct', 'outputWarehouse.branch', 'outputStock.warehouse', 'creator', 'canceller', 'items.handoverItem.stock.masterProduct', 'items.handoverItem.materialHandover.materialRequest.project', 'items.handoverItem.outLog'])
            ->findOrFail($id);

        return view('production.show', [
            'production' => $production,
            'outputStock' => $this->outputBalance($production),
            'canVoid' => $production->isPosted() && $this->untouched($production),
        ]);
    }

    /** Batal produksi: barang jadi keluar lagi, material kembali menjadi sisa. */
    public function void(Request $request, $id): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:255'], ['reason.required' => 'Alasan pembatalan wajib diisi.']);
        $production = Production::visibleTo(Auth::user())->findOrFail($id);
        if (! $production->isPosted()) {
            return response()->json(['success' => false, 'message' => 'Produksi sudah dibatalkan.'], 422);
        }

        $service = app(StockService::class);

        try {
            DB::transaction(function () use ($production, $service, $validated) {
                $stock = $this->outputBalance($production, true);
                if (! $stock || ! $this->untouched($production, $stock)) {
                    throw new StockException('Barang jadi sudah dipakai, dipindah, atau dikirim — produksi tidak bisa dibatalkan.');
                }
                // Material: hanya qty hasil produksi ini yang dikeluarkan dari saldo.
                $qty = $stock->isSerialized() ? (float) $stock->qty : (float) $production->output_qty;
                $service->take($stock, $qty, StockLog::TYPE_OUT, [
                    'date' => now()->toDateString(),
                    'ref_type' => StockLog::REF_PRODUCTION,
                    'ref_id' => $production->id,
                    'note' => 'Batal produksi '.$production->production_number.': '.$validated['reason'],
                ], false);

                // Kembalikan alih biaya material lintas-project ke project asal.
                StockLog::where('ref_type', StockLog::REF_PRODUCTION)
                    ->where('ref_id', $production->id)
                    ->where('type', StockLog::TYPE_ALLOCATE)
                    ->orderBy('id')
                    ->get()
                    ->each(function (StockLog $log) use ($production) {
                        StockLog::create([
                            'stock_id' => $log->stock_id,
                            'master_product_id' => $log->master_product_id,
                            'warehouse_id' => $log->warehouse_id,
                            'project_id' => $log->project_id,
                            'is_material' => $log->is_material,
                            'log_date' => now()->toDateString(),
                            'type' => StockLog::TYPE_ALLOCATE,
                            'qty' => StockService::round(-(float) $log->qty),
                            'qty_after' => 0,
                            'unit_cost' => (float) $log->unit_cost,
                            'ref_type' => StockLog::REF_PRODUCTION,
                            'ref_id' => $production->id,
                            'note' => 'Batal produksi '.$production->production_number.': kembalikan alih biaya material',
                            'created_by' => Auth::id(),
                        ]);
                    });

                $production->update([
                    'status' => Production::STATUS_CANCELLED,
                    'cancelled_by' => Auth::id(),
                    'cancelled_at' => now(),
                    'cancel_reason' => $validated['reason'],
                ]);
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Log::record('void_production', 'Produksi '.$production->production_number.' dibatalkan oleh '.Auth::user()->display_name.': '.$validated['reason'], 'MOD_PRODUCTION', $production);

        return response()->json(['success' => true, 'message' => 'Produksi dibatalkan. Material kembali menjadi Material di Produksi.']);
    }

    /** Baris barang jadi masih utuh di posisi awal (belum dipakai / dipindah / dikirim). */
    /**
     * Baris stok barang jadi: barang ber-SN = baris yang dibuat produksi ini;
     * material = saldo produk di gudang + project output (bisa berisi stok lain).
     */
    private function outputBalance(Production $production, bool $lock = false): ?Stock
    {
        $product = $production->outputProduct ?? MasterProduct::find($production->output_product_id);
        if ($product && $product->isMaterial()) {
            return StockService::balanceRow((int) $product->id, (int) $production->output_warehouse_id, $production->project_id ? (int) $production->project_id : null, $lock);
        }

        return $lock ? $production->outputStock()->lockForUpdate()->first() : $production->outputStock;
    }

    private function untouched(Production $production, ?Stock $stock = null): bool
    {
        $stock ??= $this->outputBalance($production);

        // Material: tidak bisa tahu unit mana yang dipakai; cukup saldo masih memuat qty hasil produksi.
        if ($stock && ! $stock->isSerialized()) {
            return StockService::available($stock) + Stock::EPSILON >= (float) $production->output_qty;
        }

        return $stock
            && abs($stock->qty - $stock->qty_in) < Stock::EPSILON
            && $stock->qty > Stock::EPSILON
            && (int) $stock->project_id === (int) $production->project_id
            && (int) $stock->warehouse_id === (int) $production->output_warehouse_id
            && ! $stock->children()->exists();
    }

    /** Material di Produksi yang masih bersisa, sesuai branch user. */
    private function openPool(): Collection
    {
        $user = Auth::user();

        return MaterialHandoverItem::withUsedQty()
            ->with(['materialHandover.materialRequest.project:id,project_number,project_name', 'materialHandover.materialRequest.branch:id,name'])
            ->when($user->branch_id, fn ($q) => $q->whereHas('materialHandover.materialRequest', fn ($m) => $m->where('branch_id', $user->branch_id)))
            ->get()
            ->filter(fn (MaterialHandoverItem $item) => $item->remainingQty() > 0)
            ->values();
    }
}
