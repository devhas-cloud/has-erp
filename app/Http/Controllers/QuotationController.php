<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Quotation;
use App\Models\QuotationConfigItem;
use App\Models\QuotationItem;
use App\Models\QuoteConfiguration;
use App\Models\Task;
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
            ->has('items')
            ->orderByDesc('id')
            ->get(['id', 'quotation_number', 'to_name']);
    }

    /**
     * Task quote yang bisa dijadikan quotation: status in_progress dan memiliki
     * minimal 1 quote configuration Approved versi terakhir (IMS maupun WATER).
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
            'templates' => $this->templateList(),
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

        foreach ($configs as $config) {
            foreach ($config->items as $item) {
                $items[] = [
                    'quote_configuration_id' => $config->id,
                    'category' => $item->category,
                    'part_number' => $item->part_number,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'price' => $item->price,
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
            'division_name' => $c->division?->division_name ?? '—',
            'version' => $c->version,
            'label' => '#'.$c->id.' v'.$c->version.' — '.($c->division?->division_name ?? ''),
        ])->all();

        // Item config digabung untuk prefill baris item.
        $items = $this->configItemsForPrefill($configs);

        // Produk unik (item config) untuk popup Tambah Item.
        $products = [];
        $seen = [];
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

    public function data(Request $request): JsonResponse
    {
        $query = Quotation::with(['creator', 'task', 'configurations']);

        $recordsTotal = Quotation::count();

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
                    'to_name' => $validated['to_name'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'attn_name' => $validated['attn_name'] ?? null,
                    'attn_phone' => $validated['attn_phone'] ?? null,
                    'attn_email' => $validated['attn_email'] ?? null,
                    'from_name' => $validated['from_name'] ?? null,
                    'contact_phone' => $validated['contact_phone'] ?? null,
                    'parameter_note' => $validated['parameter_note'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'terms' => $validated['terms'] ?? self::DEFAULT_TERMS,
                    'status' => Quotation::STATUS_DRAFT,
                    'created_by' => Auth::id(),
                ]);

                $quotation->configurations()->sync($selectedIds);

                $this->syncItems($quotation, $validated['items']);
                $this->syncConfigItems($quotation, $validated['config_items'] ?? []);

                $totals = Quotation::calculateTotals($validated['items']);
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
        $quotation = Quotation::with(['items', 'configItems', 'configurations', 'task'])->findOrFail($id);

        if ($quotation->isLocked()) {
            return redirect()->route('quotation.index')
                ->with('error', 'Quotation yang sudah Issued tidak bisa diedit.');
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
            ])->all(),
            'configItems' => $quotation->configItems->map(fn ($item) => [
                'quote_configuration_id' => $item->quote_configuration_id,
                'category' => $item->category,
                'part_number' => $item->part_number,
                'description' => $item->description,
                'qty' => $item->qty,
                'price' => $item->price,
                'unit' => $item->unit,
            ])->all(),
            'templates' => $this->templateList(),
            'terms' => $quotation->terms ?? self::DEFAULT_TERMS,
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $quotation = Quotation::findOrFail($id);

        if ($quotation->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation yang sudah Issued tidak bisa diedit.',
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
                    'to_name' => $validated['to_name'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'attn_name' => $validated['attn_name'] ?? null,
                    'attn_phone' => $validated['attn_phone'] ?? null,
                    'attn_email' => $validated['attn_email'] ?? null,
                    'from_name' => $validated['from_name'] ?? null,
                    'contact_phone' => $validated['contact_phone'] ?? null,
                    'parameter_note' => $validated['parameter_note'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'terms' => $validated['terms'] ?? self::DEFAULT_TERMS,
                ]);

                $quotation->configurations()->sync($selectedIds);

                $this->syncItems($quotation, $validated['items']);
                $this->syncConfigItems($quotation, $validated['config_items'] ?? []);

                $totals = Quotation::calculateTotals($validated['items']);
                $quotation->update($totals);

                if (! $quotation->quotation_number) {
                    $quotation->update([
                        'quotation_number' => $quotation->generateQuotationNumber(),
                    ]);
                }
            });

            Log::record(
                'update_quotation',
                "Quotation #{$quotation->id} diupdate",
                self::MODULE_CODE,
                $quotation
            );

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
            'creator',
            'configurations.division',
            'quoteConfiguration.creator',
            'opportunity.accountCompany',
            'opportunity.accountContact',
            'task.creator',
        ])->findOrFail($id);

        return view('quotation.show', compact('quotation'));
    }

    public function destroy($id): JsonResponse
    {
        $quotation = Quotation::findOrFail($id);

        if ($quotation->isLocked()) {
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
     * Terbitkan quotation (draft -> issued). Setelah issued, dokumen terkunci.
     */
    public function issue($id): JsonResponse
    {
        $quotation = Quotation::findOrFail($id);

        if ($quotation->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation sudah dalam status Issued.',
            ], 422);
        }

        if ($quotation->items()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation harus memiliki minimal 1 item sebelum di-issue.',
            ], 422);
        }

        $quotation->update(['status' => Quotation::STATUS_ISSUED]);

        Log::record(
            'issue_quotation',
            "Quotation #{$quotation->id} ({$quotation->quotation_number}) diterbitkan oleh ".Auth::user()->username,
            self::MODULE_CODE,
            $quotation
        );

        return response()->json([
            'success' => true,
            'message' => 'Quotation diterbitkan (Issued).',
        ]);
    }

    public function pdf($id)
    {
        $quotation = Quotation::with([
            'items',
            'creator',
            'configurations.division',
            'quoteConfiguration',
            'opportunity.accountCompany',
            'opportunity.accountContact',
            'task.creator',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('quotation.pdf', compact('quotation'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('Quotation-'.$quotation->id.'.pdf');
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
            'to_name' => 'nullable|string|max:200',
            'address' => 'nullable|string',
            'attn_name' => 'nullable|string|max:150',
            'attn_phone' => 'nullable|string|max:50',
            'attn_email' => 'nullable|email|max:150',
            'from_name' => 'nullable|string|max:150',
            'contact_phone' => 'nullable|string|max:50',
            'parameter_note' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
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
            'config_items' => 'nullable|array',
            'config_items.*.quote_configuration_id' => 'nullable|integer|exists:quote_configurations,id',
            'config_items.*.category' => 'nullable|string|max:100',
            'config_items.*.part_number' => 'nullable|string|max:100',
            'config_items.*.description' => 'nullable|string',
            'config_items.*.qty' => 'nullable|integer|min:0',
            'config_items.*.price' => 'nullable|numeric|min:0',
            'config_items.*.unit' => 'nullable|string|max:50',
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
                'description' => $item['description'],
                'qty' => isset($item['qty']) && $item['qty'] !== '' ? (int) $item['qty'] : null,
                'price' => $item['price'] ?? null,
                'unit' => $item['unit'] ?? null,
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

        $payload = [];
        foreach (array_values($configItems) as $i => $item) {
            $payload[] = [
                'quotation_id' => $quotation->id,
                'quote_configuration_id' => $item['quote_configuration_id'] ?? null,
                'category' => $item['category'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'description' => $item['description'] ?? null,
                'qty' => isset($item['qty']) && $item['qty'] !== '' ? (int) $item['qty'] : null,
                'price' => $item['price'] ?? null,
                'unit' => $item['unit'] ?? null,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($payload)) {
            QuotationConfigItem::insert($payload);
        }
    }
}
