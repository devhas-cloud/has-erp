<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\ProfitEstimate;
use App\Models\Quotation;
use App\Models\QuotationConfigItem;
use App\Models\QuotationCostItem;
use App\Models\QuotationItem;
use App\Models\QuoteConfiguration;
use App\Models\Task;
use App\Models\UserAccessControl;
use App\Support\TaskWorkflowLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    private const MODULE_CODE = 'MOD_QUOTATION';

    /**
     * Term & Conditions bawaan (sesuai contoh dokumen QUOTATION).
     */
    private const DEFAULT_TERMS = "1. Price : in Rupiah\n"
        ."2. Payment Term :  Termin 1 : DP 25% after received Purchased Order\n"
        ."                   Termin 2 : 70% after goods received at Site\n"
        ."                   Termin 3 : 5% after connected to local server\n"
        ."3. Validity     : 30 days from above mentioned Quotation date\n"
        ."4. Delivery     : Unit : Approx. 4 months after received payment term 1\n"
        ."                  Installation : 10-14 days\n"
        ."5. Franco       : Jakarta\n"
        ."6. Warranty     : 1 (one) year against manufacture defects, since installation complete\n"
        ."7. Other        : *Optional, costs can be cover by customer or PT Has Environmental\n"
        ."                  *Validation (third party) can be cover by customer or PT Has Environmental\n"
        .'                   Price can change based on survey location';

    public function index()
    {
        return view('quotation.index');
    }

    /**
     * Daftar quotation yang memiliki items — dipakai sebagai template isian
     * List Item Quotation. Setiap quotation baru dengan items otomatis tersedia.
     */
    private function templateList()
    {
        return Quotation::withCount('items')
            ->whereHas('items')
            ->where('status', Quotation::STATUS_APPROVED)
            ->orderByDesc('id')
            ->get(['id', 'quotation_number', 'to_name']);
    }

    /**
     * Daftar quotation yang memiliki cost items — dipakai sebagai template
     * isian Tab Biaya ("Pilih Template Biaya"). Setiap quotation baru dengan
     * biaya otomatis tersedia.
     */
    private function costTemplateList()
    {
        return Quotation::withCount('costItems')
            ->whereHas('costItems')
            ->where('status', Quotation::STATUS_APPROVED)
            ->orderByDesc('id')
            ->get(['id', 'quotation_number', 'to_name']);
    }

    /**
     * Task quote yang bisa dijadikan quotation: status in_progress.
     * Syarat config (dinamis): SEMUA configuration versi terakhir (is_current)
     * milik task harus berstatus Approved. Jika ada 2 config, keduanya harus
     * approved; jika hanya 1 config, config itu harus approved.
     * Task yang sudah punya quotation tidak ditampilkan sebagai task baru.
     */
    private function quotationTasks()
    {
        return Task::with([
            'opportunity.accountCompany',
            'opportunity.accountContact',
            'opportunity.owner',
            'lead.accountCompany',
            'lead.accountContact',
            'creator',
            'category',
            'quoteConfigurations.division',
        ])
            ->where('status', 'in_progress')
            ->where(function ($q) {
                $q->whereNotNull('opportunity_id')->orWhereNotNull('lead_id');
            })
            ->whereHas('quoteConfigurations', function ($q) {
                $q->where('status', QuoteConfiguration::STATUS_APPROVED)
                    ->where('is_current', true);
            })
            // Semua config versi terakhir (is_current) harus approved.
            ->whereDoesntHave('quoteConfigurations', function ($q) {
                $q->where('is_current', true)
                    ->where('status', '!=', QuoteConfiguration::STATUS_APPROVED);
            })
            // Task yang sudah punya quotation tidak tampil kembali.
            ->whereDoesntHave('quotations')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Config approved versi terakhir milik sebuah task (IMS + WATER).
     */
    private function approvedConfigsOfTask(Task $task)
    {
        $latestIds = QuoteConfiguration::query()
            ->selectRaw('MAX(id) as id')
            ->where('task_id', $task->id)
            ->where('status', QuoteConfiguration::STATUS_APPROVED)
            ->where('is_current', true)
            ->groupBy('group_id')
            ->pluck('id');

        return QuoteConfiguration::with([
            'opportunity.accountCompany',
            'opportunity.accountContact',
            'opportunity.owner',
            'task.creator',
            'division',
            'creator',
            'items',
        ])
            ->whereIn('id', $latestIds)
            ->orderBy('id')
            ->get();
    }

    /**
     * Cek apakah task masih memiliki configuration versi terakhir (is_current)
     * yang BELUM approved. Jika ada, quotation tidak boleh disimpan.
     */
    private function taskHasUnapprovedConfiguration(Task $task): bool
    {
        return QuoteConfiguration::query()
            ->where('task_id', $task->id)
            ->where('is_current', true)
            ->where('status', '!=', QuoteConfiguration::STATUS_APPROVED)
            ->exists();
    }

    public function create(Request $request)
    {
        $tasks = $this->quotationTasks();

        $preselected = null;
        $items = [];

        if ($request->filled('task_id')) {
            $task = $tasks->firstWhere('id', (int) $request->input('task_id'));

            if ($task) {
                $preselected = $task;
                $items = $this->configItemsForPrefill($this->approvedConfigsOfTask($task));
            }
        }

        return view('quotation.form', [
            'quotation' => null,
            'tasks' => $tasks,
            'preselected' => $preselected,
            'items' => $items,
            'configItems' => [],
            'costItems' => [],
            'currencies' => Currency::formOptions(),
            'formula' => null,
            'templates' => $this->templateList(),
            'costTemplates' => $this->costTemplateList(),
            'terms' => self::DEFAULT_TERMS,
        ]);
    }

    /**
     * Gabungkan item dari semua config terpilih menjadi daftar flat
     * (baris leaf) untuk prefill form.
     */
    private function configItemsForPrefill($configs): array
    {
        $items = [];
        $base = Currency::baseName();

        foreach ($configs as $config) {
            foreach ($config->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'quote_configuration_id' => $config->id,
                    'item_no' => $item->item_no,
                    'parent_id' => $item->parent_id,
                    'category' => $item->category,
                    'part_number' => $item->part_number,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    // price = IDR sesudah kurs; price_currency + currency = snapshot sebelum kurs.
                    'price' => $item->price,
                    'price_currency' => $item->price_currency ?? $item->price,
                    'currency' => strtoupper($item->currency ?: $base),
                    'unit' => $item->unit,
                ];
            }
        }

        return $items;
    }

    /**
     * Ambil data derived dari task terpilih + daftar config approved + produk
     * (item config) untuk popup Tambah Item.
     */
    public function fetchTask(Request $request): JsonResponse
    {
        $request->validate([
            'task_id' => 'required|exists:tasks,id',
        ]);

        $task = Task::with([
            'opportunity.accountCompany',
            'opportunity.accountContact',
            'opportunity.owner',
            'lead.accountCompany',
            'lead.accountContact',
            'creator',
            'category',
        ])->findOrFail($request->input('task_id'));

        $configs = $this->approvedConfigsOfTask($task);

        if ($configs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Task tidak memiliki quote configuration Approved versi terakhir.',
            ], 422);
        }

        $config = new QuoteConfiguration(['task_id' => $task->id]);
        $config->setRelation('task', $task);
        $config->setRelation('opportunity', $task->opportunity);

        // Daftar config terpilih (gabungan IMS + WATER).
        $configList = $configs->map(fn ($c) => [
            'id' => $c->id,
            'division_id' => $c->division_id,
            'division_name' => $c->division?->division_name ?? '—',
            'version' => $c->version,
            'label' => '#'.$c->id.' v'.$c->version.' — '.($c->division?->division_name ?? ''),
            'notes' => $c->notes,
        ])->all();

        // Item config digabung untuk prefill baris item.
        $items = $this->configItemsForPrefill($configs);

        // Produk unik (item config) untuk popup Tambah Item.
        $products = [];
        $seen = [];
        $base = Currency::baseName();
        foreach ($configs as $c) {
            foreach ($c->items as $item) {
                $key = ($item->part_number ?: '').'|'.($item->description ?: '').'|'.(string) $item->price;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $products[] = [
                    'quote_configuration_id' => $c->id,
                    'config_label' => $c->division?->division_name ?? '',
                    'category' => $item->category,
                    'part_number' => $item->part_number,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'price_currency' => $item->price_currency ?? $item->price,
                    'currency' => strtoupper($item->currency ?: $base),
                    'unit' => $item->unit,
                ];
            }
        }

        $sales = $task->creator;

        return response()->json([
            'success' => true,
            'data' => [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'opportunity_id' => $task->opportunity_id,
                'to_name' => $config->location,
                'address' => $config->address,
                'attn_name' => $config->pic_name,
                'attn_phone' => $config->pic_phone,
                'attn_email' => $config->pic_email,
                'from_name' => $config->sales_name,
                'contact_phone' => $sales?->phone_number,
                'date' => $task->due_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'configs' => $configList,
                'items' => $items,
                'products' => $products,
            ],
        ]);
    }

    /**
     * Pencarian MasterProduct (DataTables server-side) untuk modal "Add Item"
     * pada tab List Configuration. Opsional difilter per divisi config block.
     * Harga yang dikembalikan menyesuaikan kurs: master price × rate currency.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $searchValue = $request->input('search.value', '');
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 100);
        $divisionId = $request->input('division_id');

        $query = MasterProduct::query()
            ->with('currency')
            ->where('status', 'Active')
            ->orderBy('name');

        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }

        $recordsTotal = $query->count();

        if ($searchValue) {
            $query->where(function ($builder) use ($searchValue) {
                $builder->where('name', 'like', "%{$searchValue}%")
                    ->orWhere('code', 'like', "%{$searchValue}%")
                    ->orWhere('brand', 'like', "%{$searchValue}%")
                    ->orWhere('category', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%");
            });
        }

        $recordsFiltered = $query->count();

        $products = $query
            ->skip($start)
            ->take($length)
            ->get(['id', 'name', 'code', 'brand', 'category', 'description', 'price', 'currency_id']);

        $data = $products->map(fn ($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'brand' => $product->brand,
            'category' => $product->category,
            'description' => $product->description,
            'price' => $product->priceInBase(),
            'price_currency' => (float) $product->price,
            'currency' => strtoupper($product->currency?->name ?: Currency::baseName()),
        ])->all();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Ambil item quotation existing sebagai template isian List Item Quotation.
     * Dikembalikan datar berurutan DFS supaya parent dirender sebelum child.
     */
    public function fetchTemplate(Request $request): JsonResponse
    {
        $request->validate([
            'quotation_id' => 'required|exists:quotations,id',
        ]);

        $quotation = Quotation::with('items')->findOrFail($request->input('quotation_id'));

        $all = $quotation->items->keyBy('id');
        $children = $all->groupBy(fn ($item) => $item->parent_id ?: '_root');

        $rows = [];

        $walk = function ($parentId) use (&$walk, &$rows, $children) {
            foreach ($children[$parentId] ?? [] as $item) {
                $rows[] = [
                    '_key' => 'tpl-'.$item->id,
                    'parent_key' => $item->parent_id ? 'tpl-'.$item->parent_id : null,
                    'item_no' => $item->item_no,
                    'category' => $item->category,
                    'part_number' => $item->part_number,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'unit' => $item->unit,
                ];
                $walk($item->id);
            }
        };

        $walk('_root');

        return response()->json([
            'success' => true,
            'data' => [
                'quotation_id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'to_name' => $quotation->to_name,
                'items' => $rows,
            ],
        ]);
    }

    /**
     * Ambil cost items quotation existing sebagai template isian Tab Biaya.
     * Dikembalikan datar berurutan DFS supaya parent dirender sebelum child.
     * Harga TIDAK diikutsertakan — template hanya membawa struktur
     * (item_no, description, qty, unit), harga diisi manual per quotation.
     * Judul biaya dikembalikan terpisah (cost_title) karena hanya satu per biaya.
     */
    public function fetchCostTemplate(Request $request): JsonResponse
    {
        $request->validate([
            'quotation_id' => 'required|exists:quotations,id',
        ]);

        $quotation = Quotation::with('costItems')->findOrFail($request->input('quotation_id'));

        $all = $quotation->costItems->keyBy('id');
        $children = $all->groupBy(fn ($item) => $item->parent_id ?: '_root');

        $rows = [];

        $walk = function ($parentId) use (&$walk, &$rows, $children) {
            foreach ($children[$parentId] ?? [] as $item) {
                $rows[] = [
                    '_key' => 'tpl-cost-'.$item->id,
                    'parent_key' => $item->parent_id ? 'tpl-cost-'.$item->parent_id : null,
                    'item_no' => $item->item_no,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'unit' => $item->unit,
                ];
                $walk($item->id);
            }
        };

        $walk('_root');

        return response()->json([
            'success' => true,
            'data' => [
                'quotation_id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'to_name' => $quotation->to_name,
                'cost_title' => $quotation->cost_title,
                'items' => $rows,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        // Hanya tampilkan versi terakhir tiap group (pola water/ims configuration).
        $latestIds = Quotation::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('group_id')
            ->pluck('id');

        $query = Quotation::with(['creator', 'task', 'configurations'])
            ->whereIn('id', $latestIds);

        $recordsTotal = Quotation::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('group_id')
            ->get()
            ->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('quotation_number', 'like', "%{$searchValue}%")
                    ->orWhere('to_name', 'like', "%{$searchValue}%")
                    ->orWhere('from_name', 'like', "%{$searchValue}%")
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%"))
                    ->orWhereHas('task.opportunity', fn ($o) => $o->where('opportunity_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('task', fn ($t) => $t->where('title', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'asc');

        $columnOrderMap = [
            3 => 'date',
            6 => 'grand_total',
        ];

        if (isset($columnOrderMap[$orderColumnIndex])) {
            $query->orderBy($columnOrderMap[$orderColumnIndex], $orderDirection);
        }
        $query->orderBy('id', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $quotations = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($quotations as $i => $quotation) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $quotation->id,
                'version' => $quotation->version,
                'quotation_number' => $quotation->quotation_number ?? '—',
                'to_name' => $quotation->to_name ?? '—',
                'date' => $quotation->date?->format('d/m/Y') ?? '—',
                'date_raw' => $quotation->date?->toISOString(),
                'sales_name' => $quotation->from_name ?? '—',
                'item_count' => $quotation->items()->count(),
                'grand_total' => $quotation->grand_total,
                'grand_total_label' => Quotation::formatMoney($quotation->grand_total),
                'creator_name' => $quotation->creator?->username ?? '—',
                'status' => $quotation->status,
                'status_label' => $quotation->status_label,
                'status_badge' => $quotation->statusBadgeHtml(),
                'locked' => $quotation->isLocked(),
                'can_approve' => $this->isApprover(),
                'can_revise' => ($quotation->status === Quotation::STATUS_REJECTED
                    || ($quotation->status === Quotation::STATUS_APPROVED && $quotation->unlocked_at)),
                'is_creator' => (int) $quotation->created_by === (int) Auth::id(),
                'task_title' => $quotation->task?->title ?? '—',
                'source_config' => $quotation->configurations->isNotEmpty()
                    ? count($quotation->configurations).' config ('.$quotation->configurations->implode('division.division_name', ' + ').')'
                    : ($quotation->quoteConfiguration ? '#'.$quotation->quoteConfiguration->id.' (v'.$quotation->quoteConfiguration->version.')' : '—'),
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
        $validated = $this->validateQuotation($request);

        $task = Task::find($validated['task_id']);

        if (! $task) {
            return response()->json([
                'success' => false,
                'message' => 'Task tidak valid.',
            ], 422);
        }

        if ($this->taskHasUnapprovedConfiguration($task)) {
            return response()->json([
                'success' => false,
                'message' => 'Masih ada Quote Configuration versi terakhir yang belum Approved pada task ini. Semua configuration harus disetujui sebelum quotation disimpan.',
            ], 422);
        }

        if (Quotation::where('task_id', $validated['task_id'])
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation untuk task ini sudah ada. Hanya boleh membuat 1 quotation per task.',
            ], 422);
        }

        $configs = $this->approvedConfigsOfTask($task);
        $validIds = $configs->pluck('id')->all();

        $selectedIds = array_map('intval', $validated['quote_configuration_ids']);
        $invalid = array_diff($selectedIds, $validIds);

        if (! empty($invalid) || empty($selectedIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Config terpilih tidak valid. Hanya config Approved versi terakhir milik task ini yang bisa dipakai.',
            ], 422);
        }

        try {
            $quotation = DB::transaction(function () use ($validated, $task, $selectedIds) {
                $quotation = Quotation::create([
                    'quote_configuration_id' => $selectedIds[0] ?? null,
                    'opportunity_id' => $task->opportunity_id,
                    'task_id' => $task->id,
                    'date' => $validated['date'] ?? now()->toDateString(),
                    'currency' => $validated['currency'] ?? 'Rupiah',
                    'your_ref' => $validated['your_ref'] ?? null,
                    'no_of_pages' => (int) ($validated['no_of_pages'] ?? 1),
                    'is_portable' => (bool) ($validated['is_portable'] ?? false),
                    'to_name' => $validated['to_name'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'attn_name' => $validated['attn_name'] ?? null,
                    'attn_phone' => $validated['attn_phone'] ?? null,
                    'attn_email' => $validated['attn_email'] ?? null,
                    'from_name' => $validated['from_name'] ?? null,
                    'contact_phone' => $validated['contact_phone'] ?? null,
                    'parameter_note' => $validated['parameter_note'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'cost_notes' => $validated['cost_notes'] ?? null,
                    'cost_title' => $validated['cost_title'] ?? null,
                    'terms' => $validated['terms'] ?? self::DEFAULT_TERMS,
                    'status' => Quotation::STATUS_DRAFT,
                    'group_id' => null,
                    'version' => 1,
                    'is_current' => true,
                    'discount_percent' => $validated['discount_percent'] ?? null,
                    'discount_amount' => $validated['discount_amount'] ?? null,
                    'ppn_percent' => $validated['ppn_percent'] ?? null,
                    'ppn_amount' => $validated['ppn_amount'] ?? null,
                    'formula' => array_filter($validated['formula'] ?? []),
                    'created_by' => Auth::id(),
                ]);

                $quotation->update(['group_id' => $quotation->id]);

                $quotation->configurations()->sync($selectedIds);

                $this->syncItems($quotation, $validated['items']);
                $this->syncConfigItems($quotation, $validated['config_items'] ?? []);
                $this->syncCostItems($quotation, $validated['cost_items'] ?? []);

                $totals = Quotation::calculateTotals(
                    $validated['items'],
                    isset($validated['discount_percent']) && $validated['discount_percent'] !== '' ? (float) $validated['discount_percent'] : null,
                    isset($validated['discount_amount']) && $validated['discount_amount'] !== '' ? (float) $validated['discount_amount'] : null,
                    isset($validated['ppn_percent']) && $validated['ppn_percent'] !== '' ? (float) $validated['ppn_percent'] : null,
                    isset($validated['ppn_amount']) && $validated['ppn_amount'] !== '' ? (float) $validated['ppn_amount'] : null,
                );
                $quotation->update($totals);

                $quotation->update([
                    'quotation_number' => $quotation->generateQuotationNumber(),
                ]);

                return $quotation;
            });

            Log::record(
                'create_quotation',
                "Quotation #{$quotation->id} ({$quotation->quotation_number}) dibuat dari Task #{$task->id}",
                self::MODULE_CODE,
                $quotation
            );

            if ($quotation->task) {
                TaskWorkflowLogger::forTask(
                    $quotation->task,
                    'create_quotation',
                    "Quotation #{$quotation->id} ({$quotation->quotation_number}) dibuat dari Task #{$task->id} (Draft)"
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Quotation berhasil dibuat.',
                'id' => $quotation->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan quotation: '.$e->getMessage(),
            ], 422);
        }
    }

    public function edit($id)
    {
        $quotation = Quotation::with(['items', 'configItems', 'costItems', 'configurations', 'task'])->findOrFail($id);

        if ($quotation->status !== Quotation::STATUS_DRAFT) {
            return redirect()->route('quotation.index')
                ->with('error', 'Hanya quotation berstatus Draft yang bisa diedit. Gunakan Buat Revisi untuk quotation yang ditolak/approved.');
        }

        $tasks = $this->quotationTasks();

        // Pastikan task asal tetap ada di daftar walau task sudah berubah status.
        if ($quotation->task_id && $tasks->doesntContain('id', $quotation->task_id)) {
            $source = Task::with([
                'opportunity.accountCompany',
                'opportunity.accountContact',
                'creator',
                'quoteConfigurations.division',
            ])->find($quotation->task_id);

            if ($source) {
                $tasks->prepend($source);
            }
        }

        return view('quotation.form', [
            'quotation' => $quotation,
            'tasks' => $tasks,
            'preselected' => $quotation->task,
            'items' => $quotation->items->map(fn ($item) => [
                'id' => $item->id,
                'parent_id' => $item->parent_id,
                'item_no' => $item->item_no,
                'quote_configuration_id' => $item->quote_configuration_id,
                'category' => $item->category,
                'part_number' => $item->part_number,
                'description' => $item->description,
                'qty' => $item->qty,
                'price' => $item->price,
                'unit' => $item->unit,
                'formula' => $item->formula,
            ])->all(),
            'configItems' => $quotation->configItems->map(fn ($item) => [
                'id' => $item->id,
                'quote_configuration_id' => $item->quote_configuration_id,
                'item_no' => $item->item_no,
                'parent_id' => $item->parent_id,
                'category' => $item->category,
                'part_number' => $item->part_number,
                'description' => $item->description,
                'qty' => $item->qty,
                'price' => $item->price,
                'price_currency' => $item->price_currency,
                'currency' => $item->currency,
                'unit' => $item->unit,
                'formula' => $item->formula,
            ])->all(),
            'costItems' => $quotation->costItems->map(fn ($item) => [
                'id' => $item->id,
                'parent_id' => $item->parent_id,
                'item_no' => $item->item_no,
                'description' => $item->description,
                'qty' => $item->qty,
                'price' => $item->price,
                'unit' => $item->unit,
                'formula' => $item->formula,
            ])->all(),
            'currencies' => Currency::formOptions(),
            'formula' => $quotation->formula,
            'templates' => $this->templateList(),
            'costTemplates' => $this->costTemplateList(),
            'terms' => $quotation->terms ?? self::DEFAULT_TERMS,
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $quotation = Quotation::findOrFail($id);

        if ($quotation->status !== Quotation::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya quotation berstatus Draft yang bisa diedit. Gunakan Buat Revisi untuk quotation yang ditolak/approved.',
            ], 422);
        }

        $validated = $this->validateQuotation($request);

        $task = Task::find($validated['task_id']);

        if (! $task) {
            return response()->json([
                'success' => false,
                'message' => 'Task tidak valid.',
            ], 422);
        }

        if ($this->taskHasUnapprovedConfiguration($task)) {
            return response()->json([
                'success' => false,
                'message' => 'Masih ada Quote Configuration versi terakhir yang belum Approved pada task ini. Semua configuration harus disetujui sebelum quotation disimpan.',
            ], 422);
        }

        $configs = $this->approvedConfigsOfTask($task);
        $validIds = $configs->pluck('id')->all();

        $selectedIds = array_map('intval', $validated['quote_configuration_ids']);
        $invalid = array_diff($selectedIds, $validIds);

        if (! empty($invalid) || empty($selectedIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Config terpilih tidak valid. Hanya config Approved versi terakhir milik task ini yang bisa dipakai.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($quotation, $task, $selectedIds, $validated) {
                $quotation->update([
                    'quote_configuration_id' => $selectedIds[0] ?? null,
                    'opportunity_id' => $task->opportunity_id,
                    'task_id' => $task->id,
                    'date' => $validated['date'] ?? $quotation->date,
                    'currency' => $validated['currency'] ?? 'Rupiah',
                    'your_ref' => $validated['your_ref'] ?? null,
                    'no_of_pages' => (int) ($validated['no_of_pages'] ?? 1),
                    'is_portable' => (bool) ($validated['is_portable'] ?? false),
                    'to_name' => $validated['to_name'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'attn_name' => $validated['attn_name'] ?? null,
                    'attn_phone' => $validated['attn_phone'] ?? null,
                    'attn_email' => $validated['attn_email'] ?? null,
                    'from_name' => $validated['from_name'] ?? null,
                    'contact_phone' => $validated['contact_phone'] ?? null,
                    'parameter_note' => $validated['parameter_note'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'cost_notes' => $validated['cost_notes'] ?? null,
                    'cost_title' => $validated['cost_title'] ?? null,
                    'terms' => $validated['terms'] ?? self::DEFAULT_TERMS,
                    'discount_percent' => $validated['discount_percent'] ?? null,
                    'discount_amount' => $validated['discount_amount'] ?? null,
                    'ppn_percent' => $validated['ppn_percent'] ?? null,
                    'ppn_amount' => $validated['ppn_amount'] ?? null,
                    'formula' => array_filter($validated['formula'] ?? []),
                ]);

                $quotation->configurations()->sync($selectedIds);

                $this->syncItems($quotation, $validated['items']);
                $this->syncConfigItems($quotation, $validated['config_items'] ?? []);
                $this->syncCostItems($quotation, $validated['cost_items'] ?? []);

                $totals = Quotation::calculateTotals(
                    $validated['items'],
                    isset($validated['discount_percent']) && $validated['discount_percent'] !== '' ? (float) $validated['discount_percent'] : null,
                    isset($validated['discount_amount']) && $validated['discount_amount'] !== '' ? (float) $validated['discount_amount'] : null,
                    isset($validated['ppn_percent']) && $validated['ppn_percent'] !== '' ? (float) $validated['ppn_percent'] : null,
                    isset($validated['ppn_amount']) && $validated['ppn_amount'] !== '' ? (float) $validated['ppn_amount'] : null,
                );
                $quotation->update($totals);

                if (! $quotation->quotation_number) {
                    $quotation->update([
                        'quotation_number' => $quotation->generateQuotationNumber(),
                    ]);
                }
            });

            // Nilai quotation berubah: PL yang menempel ditandai outdated.
            ProfitEstimate::markOutdatedForQuotation($quotation->id);

            Log::record(
                'update_quotation',
                "Quotation #{$quotation->id} diupdate",
                self::MODULE_CODE,
                $quotation
            );

            if ($quotation->task) {
                TaskWorkflowLogger::forTask(
                    $quotation->task,
                    'update_quotation',
                    "Quotation #{$quotation->id} ({$quotation->quotation_number}) diupdate (Draft)"
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Quotation berhasil diupdate.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate quotation: '.$e->getMessage(),
            ], 422);
        }
    }

    public function show($id)
    {
        $quotation = Quotation::with([
            'items',
            'configItems',
            'costItems',
            'creator',
            'configurations.division',
            'quoteConfiguration.creator',
            'opportunity.accountCompany',
            'opportunity.accountContact',
            'task.creator',
            'profitEstimate',
        ])->findOrFail($id);

        $canViewProfitEstimate = ProfitEstimateController::userCanRead();

        return view('quotation.show', compact('quotation', 'canViewProfitEstimate'));
    }

    public function destroy($id): JsonResponse
    {
        $quotation = Quotation::findOrFail($id);

        if ($quotation->status !== Quotation::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya quotation berstatus Draft yang bisa dihapus.',
            ], 422);
        }

        $quotation->delete();

        Log::record(
            'delete_quotation',
            "Quotation #{$quotation->id} ({$quotation->quotation_number}) dihapus",
            self::MODULE_CODE,
            $quotation
        );

        return response()->json([
            'success' => true,
            'message' => 'Quotation berhasil dihapus.',
        ]);
    }

    /**
     * Perbarui isi catatan (notes) quotation langsung dari halaman show.
     * Berlaku untuk semua status; hanya creator atau Admin yang boleh.
     */
    public function updateNotes(Request $request, $id): JsonResponse
    {
        $quotation = Quotation::findOrFail($id);

        if ((int) $quotation->created_by !== (int) Auth::id() && Auth::user()->role !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pembuat quotation atau Admin yang bisa mengubah catatan.',
            ], 403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $notes = $validated['notes'] ?? null;

        $quotation->update(['notes' => $notes]);

        Log::record(
            'update_quotation_notes',
            "Catatan Quotation #{$quotation->id} ({$quotation->quotation_number}) diperbarui oleh ".Auth::user()->username,
            self::MODULE_CODE,
            $quotation
        );

        return response()->json([
            'success' => true,
            'message' => 'Catatan berhasil diperbarui.',
            'notes' => $notes,
        ]);
    }

    /**
     * Terbitkan quotation (draft -> issued). Setelah issued, dokumen terkunci.
     */
    // ── Approval & Versioning (mirip alur Quote Configuration) ──

    /**
     * Kirim quotation untuk approval (draft -> waiting_approval).
     */
    public function submit($id): JsonResponse
    {
        $quotation = Quotation::findOrFail($id);

        if ($quotation->created_by !== Auth::id() && Auth::user()->role !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pembuat quotation yang bisa submit untuk approval.',
            ], 403);
        }

        if ($quotation->status !== Quotation::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation tidak dalam status Draft.',
            ], 422);
        }

        if ($quotation->items()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation harus memiliki minimal 1 item sebelum di-submit.',
            ], 422);
        }

        $quotation->update(['status' => Quotation::STATUS_WAITING_APPROVAL]);

        Log::record(
            'submit_quotation',
            "Quotation #{$quotation->id} ({$quotation->quotation_number}) dikirim untuk approval",
            self::MODULE_CODE,
            $quotation
        );

        if ($quotation->task) {
            TaskWorkflowLogger::forTask(
                $quotation->task,
                'submit_quotation',
                "Quotation #{$quotation->id} ({$quotation->quotation_number}) → Waiting Approval"
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Quotation dikirim untuk approval.',
        ]);
    }

    /**
     * Approve quotation. User dengan hak approve (can_approve / Admin) boleh approve,
     * termasuk quotation yang dibuat oleh dirinya sendiri.
     */
    public function approve($id): JsonResponse
    {
        $quotation = Quotation::with('creator')->findOrFail($id);

        if ($quotation->status !== Quotation::STATUS_WAITING_APPROVAL) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation tidak dalam status Waiting Approval.',
            ], 422);
        }

        if (! $this->isApprover()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak approve quotation ini.',
            ], 403);
        }

        DB::transaction(function () use ($quotation) {
            $quotation->update([
                'status' => Quotation::STATUS_APPROVED,
                'final_checked_by' => Auth::id(),
                'approved_at' => now(),
                'is_current' => true,
            ]);

            // Versi approved lain dalam group menjadi riwayat.
            if ($quotation->group_id) {
                Quotation::where('group_id', $quotation->group_id)
                    ->where('id', '!=', $quotation->id)
                    ->update(['is_current' => false]);

                Quotation::where('group_id', $quotation->group_id)
                    ->where('id', '!=', $quotation->id)
                    ->where('status', Quotation::STATUS_APPROVED)
                    ->update(['status' => Quotation::STATUS_ARCHIVED]);
            }
        });

        Log::record(
            'approve_quotation',
            "Quotation #{$quotation->id} ({$quotation->quotation_number}) disetujui oleh ".Auth::user()->username,
            self::MODULE_CODE,
            $quotation
        );

        if ($quotation->task) {
            TaskWorkflowLogger::forTask(
                $quotation->task,
                'approve_quotation',
                "Quotation #{$quotation->id} ({$quotation->quotation_number}) disetujui oleh ".Auth::user()->username
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Quotation disetujui.',
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $quotation = Quotation::with('creator')->findOrFail($id);

        if ($quotation->status !== Quotation::STATUS_WAITING_APPROVAL) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation tidak dalam status Waiting Approval.',
            ], 422);
        }

        if (! $this->isApprover()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak menolak quotation ini.',
            ], 403);
        }

        $validated = $request->validate([
            'approval_note' => 'required|string|max:1000',
        ]);

        $quotation->update([
            'status' => Quotation::STATUS_REJECTED,
            'final_checked_by' => Auth::id(),
            'approval_note' => $validated['approval_note'],
            'rejected_at' => now(),
        ]);

        Log::record(
            'reject_quotation',
            "Quotation #{$quotation->id} ({$quotation->quotation_number}) ditolak oleh ".Auth::user()->username,
            self::MODULE_CODE,
            $quotation
        );

        if ($quotation->task) {
            TaskWorkflowLogger::forTask(
                $quotation->task,
                'reject_quotation',
                "Quotation #{$quotation->id} ({$quotation->quotation_number}) ditolak oleh ".Auth::user()->username
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Quotation ditolak.',
        ]);
    }

    /**
     * Buka kunci quotation approved agar bisa direvisi (hanya user berhak approve).
     */
    public function unlock($id): JsonResponse
    {
        $quotation = Quotation::with('creator')->findOrFail($id);

        if ($quotation->status !== Quotation::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya quotation berstatus Approved yang bisa dibuka kunci.',
            ], 422);
        }

        if (! $this->isApprover()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya user dengan hak approve yang bisa membuka kunci.',
            ], 403);
        }

        // jika Task yang terikat quote status done maka tidak bisa di buka
        if ($quotation->task && $quotation->task->status === 'done') {
            return response()->json([
                'success' => false,
                'message' => 'Quotation tidak bisa dibuka kunci karena Task terkait sudah selesai.',
            ], 422);
        }

        $quotation->update([
            'unlocked_by' => Auth::id(),
            'unlocked_at' => now(),
        ]);

        Log::record(
            'unlock_quotation',
            "Quotation #{$quotation->id} ({$quotation->quotation_number}) dibuka kunci oleh ".Auth::user()->username,
            self::MODULE_CODE,
            $quotation
        );

        if ($quotation->task) {
            TaskWorkflowLogger::forTask(
                $quotation->task,
                'unlock_quotation',
                "Quotation #{$quotation->id} ({$quotation->quotation_number}) dibuka kunci oleh ".Auth::user()->username
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Kunci dibuka. Quotation dapat direvisi.',
        ]);
    }

    /**
     * Buat versi baru (revisi) dari quotation rejected atau approved yang sudah di-unlock.
     * Setelah unlock, siapa pun yang memiliki akses modul bisa membuat revisi.
     */
    public function revise($id): JsonResponse
    {
        $source = Quotation::with(['items', 'configItems', 'costItems', 'configurations'])->findOrFail($id);

        $canRevise = $source->status === Quotation::STATUS_REJECTED
            || ($source->status === Quotation::STATUS_APPROVED && $source->unlocked_at);

        if (! $canRevise) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation ini belum bisa direvisi. Kunci harus dibuka oleh approver terlebih dahulu.',
            ], 422);
        }

        if (! $this->hasModuleAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk membuat revisi quotation.',
            ], 403);
        }

        $revision = DB::transaction(function () use ($source) {
            if ($source->status === Quotation::STATUS_APPROVED) {
                $source->update([
                    'status' => Quotation::STATUS_ARCHIVED,
                    'is_current' => false,
                ]);
            }

            $revision = Quotation::create([
                'quote_configuration_id' => $source->quote_configuration_id,
                'opportunity_id' => $source->opportunity_id,
                'task_id' => $source->task_id,
                'group_id' => $source->group_id ?: $source->id,
                'version' => $source->nextVersion(),
                'parent_id' => $source->id,
                'is_current' => false,
                'quotation_number' => $source->quotation_number,
                'date' => $source->date,
                'currency' => $source->currency,
                'your_ref' => $source->your_ref,
                'no_of_pages' => $source->no_of_pages,
                'is_portable' => $source->is_portable,
                'to_name' => $source->to_name,
                'address' => $source->address,
                'attn_name' => $source->attn_name,
                'attn_phone' => $source->attn_phone,
                'attn_email' => $source->attn_email,
                'from_name' => $source->from_name,
                'contact_phone' => $source->contact_phone,
                'parameter_note' => $source->parameter_note,
                'notes' => $source->notes,
                'cost_notes' => $source->cost_notes,
                'cost_title' => $source->cost_title,
                'terms' => $source->terms,
                'subtotal' => $source->subtotal,
                'dpp' => $source->dpp,
                'ppn' => $source->ppn,
                'grand_total' => $source->grand_total,
                'discount_percent' => $source->discount_percent,
                'discount_amount' => $source->discount_amount,
                'ppn_percent' => $source->ppn_percent,
                'ppn_amount' => $source->ppn_amount,
                'formula' => $source->formula ?: null,
                'status' => Quotation::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);

            // Salin item dengan remap parent_id (induk selalu muncul sebelum anak
            // karena urutan sort_order / DFS).
            $itemIdMap = [];
            foreach ($source->items as $item) {
                $new = $revision->items()->create([
                    'item_no' => $item->item_no,
                    'quote_configuration_id' => $item->quote_configuration_id,
                    'parent_id' => $item->parent_id ? ($itemIdMap[$item->parent_id] ?? null) : null,
                    'category' => $item->category,
                    'part_number' => $item->part_number,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'unit' => $item->unit,
                    'formula' => $item->formula ?: null,
                    'sort_order' => $item->sort_order,
                ]);
                $itemIdMap[$item->id] = $new->id;
            }

            foreach ($source->configItems as $item) {
                $revision->configItems()->create([
                    'quote_configuration_id' => $item->quote_configuration_id,
                    'category' => $item->category,
                    'part_number' => $item->part_number,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'price_currency' => $item->price_currency,
                    'currency' => $item->currency,
                    'unit' => $item->unit,
                    'formula' => $item->formula ?: null,
                    'sort_order' => $item->sort_order,
                ]);
            }

            // Salin biaya dengan remap parent_id.
            $costIdMap = [];
            foreach ($source->costItems as $item) {
                $new = $revision->costItems()->create([
                    'item_no' => $item->item_no,
                    'parent_id' => $item->parent_id ? ($costIdMap[$item->parent_id] ?? null) : null,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'unit' => $item->unit,
                    'formula' => $item->formula ?: null,
                    'sort_order' => $item->sort_order,
                ]);
                $costIdMap[$item->id] = $new->id;
            }

            foreach ($source->configurations as $config) {
                $revision->configurations()->attach($config->id);
            }

            return $revision;
        });

        // Quotation sumber digantikan versi baru: PL lama ditandai outdated.
        ProfitEstimate::markOutdatedForQuotation($source->id);

        Log::record(
            'revise_quotation',
            "Revisi dibuat dari Quotation #{$source->id} menjadi #{$revision->id}",
            self::MODULE_CODE,
            $revision
        );

        if ($revision->task) {
            TaskWorkflowLogger::forTask(
                $revision->task,
                'revise_quotation',
                "Revisi Quotation #{$revision->id} ({$revision->quotation_number}) dibuat dari #{$source->id}"
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Revisi (versi '.$revision->version.') berhasil dibuat.',
            'id' => $revision->id,
        ]);
    }

    /**
     * Daftar versi (riwayat) satu group quotation untuk modal Track.
     */
    public function versions($id): JsonResponse
    {
        $quotation = Quotation::findOrFail($id);
        $groupId = $quotation->group_id ?: $quotation->id;

        $versions = Quotation::with(['creator', 'finalChecker'])
            ->where('group_id', $groupId)
            ->orderBy('version', 'desc')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'version' => $v->version,
                'status' => $v->status,
                'status_badge' => $v->statusBadgeHtml(),
                'date' => $v->created_at?->format('d/m/Y H:i') ?? '—',
                'creator_name' => $v->creator?->username ?? '—',
                'item_count' => $v->items()->count(),
                'is_current' => (bool) $v->is_current,
                'show_url' => route('quotation.show', $v->id),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'versions' => $versions,
        ]);
    }

    /**
     * User berhak approve modul Quotation (UAC can_approve atau Admin).
     */
    private function isApprover(): bool
    {
        $user = Auth::user();

        if ($user->role === 'Admin') {
            return true;
        }

        $module = Module::where('module_code', self::MODULE_CODE)->first();
        if (! $module) {
            return false;
        }

        return UserAccessControl::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where('can_approve', true)
            ->exists();
    }

    /**
     * User memiliki akses modul Quotation (can_create / can_update / Admin).
     */
    private function hasModuleAccess(): bool
    {
        $user = Auth::user();

        if ($user->role === 'Admin') {
            return true;
        }

        $module = Module::where('module_code', self::MODULE_CODE)->first();
        if (! $module) {
            return false;
        }

        return UserAccessControl::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where(fn ($q) => $q->where('can_create', true)->orWhere('can_update', true))
            ->exists();
    }

    public function pdf($id)
    {
        $quotation = Quotation::with([
            'items',
            'costItems',
            'creator',
            'configurations.division',
            'quoteConfiguration',
            'opportunity.accountCompany',
            'opportunity.accountContact',
            'task.creator',
        ])->findOrFail($id);

        $pdf = $this->renderPdfWithPageCount('quotation.pdf', compact('quotation'));

        return $pdf->stream('Quotation-'.$quotation->id.'.pdf');
    }

    /**
     * Render view PDF sambil mengisi variabel $pageCount dengan jumlah halaman
     * hasil cetak yang sebenarnya.
     *
     * DomPDF hanya bisa mengganti {PAGE_COUNT} pada teks yang digambar lewat
     * canvas (header/footer), bukan pada teks di dalam alur dokumen seperti sel
     * tabel. Karena itu dokumen dicetak ulang: tahap pertama untuk menghitung
     * halaman, tahap berikutnya memakai angka tersebut. Pengulangan berhenti
     * begitu jumlah halaman stabil (umumnya cukup 2 tahap; tahap ekstra hanya
     * terpakai bila pergantian angka sampai menggeser jumlah halaman).
     */
    private function renderPdfWithPageCount(string $view, array $data, int $maxPasses = 3): \Barryvdh\DomPDF\PDF
    {
        $pageCount = 1;
        $pdf = null;

        for ($pass = 1; $pass <= $maxPasses; $pass++) {
            $pdf = Pdf::loadView($view, array_merge($data, ['pageCount' => $pageCount]))
                ->setPaper('a4', 'portrait');
            $pdf->render();

            $rendered = $pdf->getDomPDF()->getCanvas()->get_page_count();

            if ($rendered === $pageCount) {
                break;
            }

            $pageCount = $rendered;
        }

        return $pdf;
    }

    /**
     * PDF khusus tabel biaya (terpisah dari PDF quotation).
     */
    public function pdfCost($id)
    {
        $quotation = Quotation::with([
            'items',
            'costItems',
            'creator',
            'configurations.division',
            'quoteConfiguration',
            'opportunity.accountCompany',
            'opportunity.accountContact',
            'task.creator',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('quotation.pdf-cost', compact('quotation'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('Biaya-Quotation-'.$quotation->id.'.pdf');
    }

    private function validateQuotation(Request $request): array
    {
        return $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'quote_configuration_ids' => 'required|array|min:1',
            'quote_configuration_ids.*' => 'integer|exists:quote_configurations,id',
            'date' => 'nullable|date',
            'currency' => 'nullable|string|max:30',
            'your_ref' => 'nullable|string|max:100',
            'no_of_pages' => 'nullable|integer|min:1',
            'is_portable' => 'nullable|boolean',
            'to_name' => 'nullable|string|max:200',
            'address' => 'nullable|string',
            'attn_name' => 'nullable|string|max:150',
            'attn_phone' => 'nullable|string|max:50',
            'attn_email' => 'nullable|email|max:150',
            'from_name' => 'nullable|string|max:150',
            'contact_phone' => 'nullable|string|max:50',
            'parameter_note' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'cost_notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'ppn_percent' => 'nullable|numeric|min:0|max:100',
            'ppn_amount' => 'nullable|numeric|min:0',
            'formula' => 'nullable|array',
            'formula.discount_amount' => 'nullable|string|max:255',
            'formula.ppn_amount' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*._key' => 'required|string',
            'items.*.parent_key' => 'nullable|string',
            'items.*.item_no' => 'nullable|string|max:50',
            'items.*.quote_configuration_id' => 'nullable|integer|exists:quote_configurations,id',
            'items.*.category' => 'nullable|string|max:100',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.description' => 'required|string',
            'items.*.qty' => 'nullable|integer|min:0',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.formula' => 'nullable|array',
            'items.*.formula.qty' => 'nullable|string|max:255',
            'items.*.formula.price' => 'nullable|string|max:255',
            'config_items' => 'nullable|array',
            'config_items.*._key' => 'required|string',
            'config_items.*.parent_key' => 'nullable|string',
            'config_items.*.item_no' => 'nullable|string|max:50',
            'config_items.*.quote_configuration_id' => 'nullable|integer|exists:quote_configurations,id',
            'config_items.*.category' => 'nullable|string|max:100',
            'config_items.*.part_number' => 'nullable|string|max:100',
            'config_items.*.description' => 'nullable|string',
            'config_items.*.qty' => 'nullable|integer|min:0',
            'config_items.*.price' => 'nullable|numeric|min:0',
            'config_items.*.price_currency' => 'nullable|numeric|min:0',
            'config_items.*.currency' => 'nullable|string|max:10',
            'config_items.*.unit' => 'nullable|string|max:50',
            'config_items.*.formula' => 'nullable|array',
            'config_items.*.formula.qty' => 'nullable|string|max:255',
            'config_items.*.formula.price' => 'nullable|string|max:255',
            'cost_items' => 'nullable|array',
            'cost_items.*._key' => 'required|string',
            'cost_items.*.parent_key' => 'nullable|string',
            'cost_items.*.item_no' => 'nullable|string|max:50',
            'cost_items.*.title' => 'nullable|string|max:200',
            'cost_items.*.description' => 'nullable|string',
            'cost_items.*.qty' => 'nullable|integer|min:0',
            'cost_items.*.price' => 'nullable|numeric|min:0',
            'cost_items.*.unit' => 'nullable|string|max:50',
            'cost_items.*.formula' => 'nullable|array',
            'cost_items.*.formula.qty' => 'nullable|string|max:255',
            'cost_items.*.formula.price' => 'nullable|string|max:255',
            'cost_title' => 'nullable|string|max:500',
        ]);
    }

    /**
     * Simpan item hierarki: pass 1 insert semua baris (tanpa parent_id)
     * lalu pass 2 pasang parent_id berdasarkan parent_key.
     */
    private function syncItems(Quotation $quotation, array $items): void
    {
        $quotation->items()->delete();

        $keyMap = [];
        $payload = [];

        foreach (array_values($items) as $i => $item) {
            $keyMap[$item['_key']] = $i;
            $payload[] = [
                'quotation_id' => $quotation->id,
                'item_no' => $item['item_no'] ?? null,
                'quote_configuration_id' => $item['quote_configuration_id'] ?? null,
                'parent_id' => null,
                'category' => $item['category'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'description' => Quotation::sanitizeDescription($item['description'] ?? ''),
                'qty' => isset($item['qty']) && $item['qty'] !== '' ? (int) $item['qty'] : null,
                'price' => $item['price'] ?? null,
                'unit' => $item['unit'] ?? null,
                'formula' => isset($item['formula']) && is_array($item['formula']) ? json_encode(array_filter($item['formula'])) : null,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        QuotationItem::insert($payload);

        $inserted = QuotationItem::where('quotation_id', $quotation->id)
            ->orderBy('id')
            ->get();

        $updates = [];

        foreach ($items as $item) {
            $parentKey = $item['parent_key'] ?? null;

            if (! $parentKey || ! array_key_exists($parentKey, $keyMap)) {
                continue;
            }

            $childIndex = $keyMap[$item['_key']];
            $parentIndex = $keyMap[$parentKey];

            if ($childIndex === $parentIndex) {
                continue;
            }

            $child = $inserted[$childIndex] ?? null;
            $parent = $inserted[$parentIndex] ?? null;

            if ($child && $parent) {
                $updates[] = [
                    'id' => $child->id,
                    'parent_id' => $parent->id,
                ];
            }
        }

        foreach ($updates as $update) {
            QuotationItem::where('id', $update['id'])->update(['parent_id' => $update['parent_id']]);
        }
    }

    /**
     * Simpan salinan snapshot item config (Tab "List Configuration").
     * Flat tanpa hierarki, independen dari quotation_items (Tab 1).
     */
    private function syncConfigItems(Quotation $quotation, array $configItems): void
    {
        $quotation->configItems()->delete();

        $keyMap = [];
        $payload = [];

        // Skema harga (sama dengan quote_configuration_items):
        //   currency       = kode mata uang (currencies.name), base bila kosong
        //   price_currency = harga satuan sebelum kurs (input user)
        //   price          = harga satuan IDR = price_currency x rate (dihitung server)
        // Payload lama tanpa price_currency: price dianggap sudah IDR.
        $currencies = Currency::active()->get()->keyBy(fn ($c) => strtoupper($c->name));
        $base = Currency::baseName();

        foreach (array_values($configItems) as $i => $item) {
            $keyMap[$item['_key']] = $i;

            $hasPriceCurrency = isset($item['price_currency']) && $item['price_currency'] !== '';

            if ($hasPriceCurrency) {
                $code = strtoupper(trim((string) ($item['currency'] ?? ''))) ?: $base;
                $currency = $currencies[$code] ?? null;
                $rate = $currency && ! $currency->is_base ? (float) $currency->rate : 1.0;
                $priceCurrency = round((float) $item['price_currency'], 2);
                $price = round($priceCurrency * $rate, 2);
            } else {
                $code = $base;
                $price = isset($item['price']) && $item['price'] !== '' ? round((float) $item['price'], 2) : null;
                $priceCurrency = $price;
            }

            $payload[] = [
                'quotation_id' => $quotation->id,
                'quote_configuration_id' => $item['quote_configuration_id'] ?? null,
                'item_no' => $item['item_no'] ?? null,
                'parent_id' => null,
                'category' => $item['category'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'description' => Quotation::sanitizeDescription($item['description'] ?? ''),
                'qty' => isset($item['qty']) && $item['qty'] !== '' ? (int) $item['qty'] : null,
                'price' => $price,
                'price_currency' => $priceCurrency,
                'currency' => $code,
                'unit' => $item['unit'] ?? null,
                'formula' => isset($item['formula']) && is_array($item['formula']) ? json_encode(array_filter($item['formula'])) : null,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($payload)) {
            return;
        }

        QuotationConfigItem::insert($payload);

        $inserted = QuotationConfigItem::where('quotation_id', $quotation->id)
            ->orderBy('id')
            ->get();

        $updates = [];

        foreach ($configItems as $item) {
            $parentKey = $item['parent_key'] ?? null;

            if (! $parentKey || ! array_key_exists($parentKey, $keyMap)) {
                continue;
            }

            $childIndex = $keyMap[$item['_key']];
            $parentIndex = $keyMap[$parentKey];

            if ($childIndex === $parentIndex) {
                continue;
            }

            $child = $inserted[$childIndex] ?? null;
            $parent = $inserted[$parentIndex] ?? null;

            if ($child && $parent) {
                $updates[] = [
                    'id' => $child->id,
                    'parent_id' => $parent->id,
                ];
            }
        }

        foreach ($updates as $update) {
            QuotationConfigItem::where('id', $update['id'])->update(['parent_id' => $update['parent_id']]);
        }
    }

    /**
     * Simpan item biaya (Tab Biaya) dengan hierarki parent-child.
     * Dua-pass: insert semua baris tanpa parent, lalu pasang parent_id
     * berdasarkan parent_key. Nilai biaya TIDAK memengaruhi subtotal.
     * Judul biaya (cost_title) disimpan pada level quotation, bukan baris.
     */
    private function syncCostItems(Quotation $quotation, array $costItems): void
    {
        $quotation->costItems()->delete();

        $keyMap = [];
        $payload = [];

        foreach (array_values($costItems) as $i => $item) {
            $keyMap[$item['_key']] = $i;
            $payload[] = [
                'quotation_id' => $quotation->id,
                'item_no' => $item['item_no'] ?? null,
                'parent_id' => null,
                'title' => null,
                'description' => $item['description'] ?? null,
                'qty' => isset($item['qty']) && $item['qty'] !== '' ? (int) $item['qty'] : null,
                'price' => $item['price'] ?? null,
                'unit' => $item['unit'] ?? null,
                'formula' => isset($item['formula']) && is_array($item['formula']) ? json_encode(array_filter($item['formula'])) : null,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bersihkan sisa baris title lama (jika ada) dari data yang dulu tersimpan.
        QuotationCostItem::where('quotation_id', $quotation->id)
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->update(['parent_id' => null]);

        QuotationCostItem::where('quotation_id', $quotation->id)
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->delete();

        if (empty($payload)) {
            return;
        }

        QuotationCostItem::insert($payload);

        $inserted = QuotationCostItem::where('quotation_id', $quotation->id)
            ->orderBy('id')
            ->get();

        $updates = [];

        foreach ($costItems as $item) {
            $parentKey = $item['parent_key'] ?? null;

            if (! $parentKey || ! array_key_exists($parentKey, $keyMap)) {
                continue;
            }

            $childIndex = $keyMap[$item['_key']];
            $parentIndex = $keyMap[$parentKey];

            if ($childIndex === $parentIndex) {
                continue;
            }

            $child = $inserted[$childIndex] ?? null;
            $parent = $inserted[$parentIndex] ?? null;

            if ($child && $parent) {
                $updates[] = [
                    'id' => $child->id,
                    'parent_id' => $parent->id,
                ];
            }
        }

        foreach ($updates as $update) {
            QuotationCostItem::where('id', $update['id'])->update(['parent_id' => $update['parent_id']]);
        }
    }
}
