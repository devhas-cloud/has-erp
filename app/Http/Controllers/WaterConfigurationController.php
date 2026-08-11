<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\QuoteConfiguration;
use App\Models\QuoteConfigurationItem;
use App\Models\Task;
use App\Models\User;
use App\Models\UserAccessControl;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WaterConfigurationController extends Controller
{
    private const MODULE_CODE = 'MOD_WATER_CONFIGURATION';

    public function index()
    {
        return view('water-configuration.index');
    }

    /**
     * Halaman form untuk membuat quote configuration baru.
     */
    public function create()
    {
        $categories = $this->categorySuggestions();
        $tasks = $this->quoteTasks();

        return view('water-configuration.form', [
            'quotation' => null,
            'items' => [],
            'categories' => $categories,
            'tasks' => $tasks,
        ]);
    }

    /**
     * Daftar task bertipe Quote (kategori yang mengaktifkan divisi penanganan),
     * yang memiliki opportunity/lead agar data customer bisa di-derive.
     */
    private function quoteTasks()
    {
        return Task::with([
            'opportunity.accountCompany',
            'opportunity.accountContact',
            'opportunity.owner',
            'lead.accountCompany',
            'lead.accountContact',
            'creator',
            'category',
        ])
            ->whereHas('category', fn ($q) => $q->where('use_division_handler', true))
            ->where('status', 'in_progress')
            ->where(function ($q) {
                $q->whereNotNull('opportunity_id')->orWhereNotNull('lead_id');
            })
            ->whereDoesntHave('quoteConfigurations', function ($q) {
                $q->where('division_id', Auth::user()->division_id);
            })
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Kategori fleksibel: saran diambil dari master_products + item yang sudah pernah dipakai.
     */
    private function categorySuggestions(): array
    {
        $fromProducts = MasterProduct::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        $fromItems = QuoteConfigurationItem::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        return $fromProducts
            ->merge($fromItems)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Ambil data derived dari task quote terpilih untuk prefill form.
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

        $config = new QuoteConfiguration(['task_id' => $task->id]);
        $config->setRelation('task', $task);
        $config->setRelation('opportunity', $task->opportunity);

        return response()->json([
            'success' => true,
            'data' => [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'opportunity_id' => $task->opportunity_id,
                'to_name' => $config->to_name,
                'location' => $config->location,
                'address' => $config->address,
                'pic_name' => $config->pic_name,
                'pic_phone' => $config->pic_phone,
                'pic_email' => $config->pic_email,
                'sales_name' => $config->sales_name,
                'date' => $task->due_date?->format('Y-m-d'),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $divisionId = Division::where('division_name', 'WATER')->value('id');

        // Hanya tampilkan versi terbaru tiap group milik divisi WATER.
        $latestIds = QuoteConfiguration::query()
            ->selectRaw('MAX(id) as id')
            ->where('division_id', $divisionId)
            ->groupBy('group_id')
            ->pluck('id');

        $query = QuoteConfiguration::whereIn('id', $latestIds)
            ->where('division_id', $divisionId)
            ->with(['creator', 'task', 'opportunity.accountCompany', 'opportunity.accountContact']);

        $recordsTotal = QuoteConfiguration::query()
            ->selectRaw('MAX(id) as id')
            ->where('division_id', $divisionId)
            ->groupBy('group_id')
            ->get()
            ->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->whereHas('opportunity', fn ($o) => $o->where('opportunity_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('opportunity.accountCompany', fn ($c) => $c->where('account_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('task', fn ($t) => $t->where('title', 'like', "%{$searchValue}%"))
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'asc');

        $columnOrderMap = [
            4 => 'date',
        ];

        if (isset($columnOrderMap[$orderColumnIndex])) {
            $query->orderBy($columnOrderMap[$orderColumnIndex], $orderDirection);
        }
        $query->orderBy('id', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $configurations = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($configurations as $i => $quotation) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $quotation->id,
                'group_id' => $quotation->group_id,
                'version' => $quotation->version,
                'opportunity_name' => $quotation->opportunity?->opportunity_name ?? $quotation->task?->title ?? '—',
                'location' => $quotation->location ?? '—',
                'to_name' => $quotation->to_name ?? '—',
                'date' => $quotation->date?->format('d/m/Y') ?? '—',
                'date_raw' => $quotation->date?->toISOString(),
                'item_count' => $quotation->items()->count(),
                'creator_name' => $quotation->creator?->username ?? '—',
                'status' => $quotation->status,
                'status_label' => $quotation->status_label,
                'status_badge' => $quotation->statusBadgeHtml(),
                'locked' => $quotation->isLocked(),
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
        $validated = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'date' => 'nullable|date',
            'parameter_note' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:master_products,id',
            'items.*.category' => 'nullable|string|max:100',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.description' => 'required|string',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.unit' => 'nullable|string|max:50',
        ]);

        if (QuoteConfiguration::where('task_id', $validated['task_id'])
            ->where('division_id', Auth::user()->division_id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Divisi ini sudah memiliki configuration untuk task tersebut. Tidak bisa membuat configuration ganda.',
            ], 422);
        }

        try {
            $quotation = DB::transaction(function () use ($validated) {
                $task = Task::findOrFail($validated['task_id']);

                $quotation = QuoteConfiguration::create([
                    'division_id' => Auth::user()->division_id,
                    'opportunity_id' => $task->opportunity_id,
                    'task_id' => $task->id,
                    'date' => $validated['date'] ?? $task->due_date,
                    'parameter_note' => $validated['parameter_note'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'status' => QuoteConfiguration::STATUS_DRAFT,
                    'created_by' => Auth::id(),
                    'group_id' => null,
                    'version' => 1,
                    'is_current' => true,
                ]);

                $quotation->update(['group_id' => $quotation->id]);

                $this->syncItems($quotation, $validated['items']);

                return $quotation;
            });

            Log::record(
                'create_water_configuration',
                "Quote Configuration #{$quotation->id} dibuat untuk task {$quotation->task?->title}",
                self::MODULE_CODE,
                $quotation
            );

            return response()->json([
                'success' => true,
                'message' => 'Quote configuration berhasil dibuat. Silakan submit untuk approval.',
                'id' => $quotation->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan quote configuration: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Halaman form edit. Hanya configuration berstatus Draft yang bisa diedit.
     * Konfigurasi rejected/approved direvisi lewat "Buat Revisi" (revise).
     */
    public function edit($id)
    {
        $quotation = QuoteConfiguration::with(['items.product', 'task'])->findOrFail($id);

        if ($quotation->status !== QuoteConfiguration::STATUS_DRAFT) {
            return redirect()->route('water-configuration.index')
                ->with('error', 'Quote configuration yang bukan Draft tidak bisa diedit langsung. Gunakan Buat Revisi.');
        }

        $categories = $this->categorySuggestions();
        $tasks = $this->quoteTasks();

        // Jika task yang direferensikan sudah 'done', tetap tampilkan agar bisa dipertahankan saat edit.
        if ($quotation->task_id && $tasks->doesntContain('id', $quotation->task_id)) {
            $current = Task::with([
                'opportunity.accountCompany',
                'opportunity.accountContact',
                'opportunity.owner',
                'lead.accountCompany',
                'lead.accountContact',
                'creator',
                'category',
            ])->find($quotation->task_id);

            if ($current) {
                $tasks->prepend($current);
            }
        }

        return view('water-configuration.form', [
            'quotation' => $quotation,
            'items' => $quotation->items,
            'categories' => $categories,
            'tasks' => $tasks,
        ]);
    }

    /**
     * Pencarian produk master_products (hanya aktif milik divisi WATER).
     * Format DataTables server-side agar bisa dipaginasi 100 baris/halaman.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $searchValue = $request->input('search.value', '');
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 100);

        $waterId = Division::where('division_name', 'WATER')->value('id');

        $query = MasterProduct::query()
            ->where('status', 'Active')
            ->where('division_id', $waterId)
            ->orderBy('name');

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
            ->get(['id', 'name', 'code', 'brand', 'category', 'description', 'price']);

        $data = $products->map(fn ($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'brand' => $product->brand,
            'category' => $product->category,
            'description' => $product->description,
            'price' => $product->price,
        ])->all();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $quotation = QuoteConfiguration::findOrFail($id);

        if ($quotation->status !== QuoteConfiguration::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya configuration berstatus Draft yang bisa diedit. Gunakan Buat Revisi untuk configuration yang ditolak/approved.',
            ], 422);
        }

        $validated = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'date' => 'nullable|date',
            'parameter_note' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:master_products,id',
            'items.*.category' => 'nullable|string|max:100',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.description' => 'required|string',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.unit' => 'nullable|string|max:50',
        ]);

        if (QuoteConfiguration::where('task_id', $validated['task_id'])
            ->where('division_id', Auth::user()->division_id)
            ->where('group_id', '!=', $quotation->group_id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Divisi ini sudah memiliki configuration untuk task tersebut. Tidak bisa membuat configuration ganda.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($quotation, $validated) {
                $task = Task::findOrFail($validated['task_id']);

                $quotation->update([
                    'opportunity_id' => $task->opportunity_id,
                    'task_id' => $task->id,
                    'date' => $validated['date'] ?? $task->due_date,
                    'parameter_note' => $validated['parameter_note'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $this->syncItems($quotation, $validated['items']);
            });

            Log::record(
                'update_water_configuration',
                "Quote Configuration #{$quotation->id} diupdate",
                self::MODULE_CODE,
                $quotation
            );

            return response()->json([
                'success' => true,
                'message' => 'Quote configuration berhasil diupdate.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate quote configuration: '.$e->getMessage(),
            ], 422);
        }
    }

    public function destroy($id): JsonResponse
    {
        $quotation = QuoteConfiguration::findOrFail($id);

        if ($quotation->status !== QuoteConfiguration::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya quote configuration berstatus Draft yang bisa dihapus.',
            ], 422);
        }

        $quotation->delete();

        Log::record(
            'delete_water_configuration',
            "Quote Configuration #{$quotation->id} dihapus",
            self::MODULE_CODE,
            $quotation
        );

        return response()->json([
            'success' => true,
            'message' => 'Quote configuration berhasil dihapus.',
        ]);
    }

    public function show($id)
    {
        $quotation = QuoteConfiguration::with([
            'items',
            'creator',
            'finalChecker',
            'task.opportunity.accountCompany',
            'task.opportunity.accountContact',
            'task.opportunity.owner',
            'task.creator',
        ])->findOrFail($id);

        $isSameDivisionApprover = $this->isSameDivisionApprover($quotation);

        $back = request('back');

        return view('water-configuration.show', compact('quotation', 'isSameDivisionApprover', 'back'));
    }

    /**
     * Kirim configuration untuk approval (status draft -> waiting_approval).
     * Notifikasi dikirim ke semua user satu divisi dengan pembuat (selain pembuat)
     * yang memiliki hak approve pada modul ini.
     */
    public function submit($id): JsonResponse
    {
        $quotation = QuoteConfiguration::findOrFail($id);

        if ($quotation->created_by !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pembuat configuration yang bisa submit untuk approval.',
            ], 403);
        }

        if ($quotation->status !== QuoteConfiguration::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration tidak dalam status Draft.',
            ], 422);
        }

        if ($quotation->items()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration harus memiliki minimal 1 item sebelum di-submit.',
            ], 422);
        }

        $quotation->update(['status' => QuoteConfiguration::STATUS_WAITING_APPROVAL]);

        $this->notifyApprovers($quotation);

        Log::record(
            'submit_water_configuration',
            "Quote Configuration #{$quotation->id} dikirim untuk approval",
            self::MODULE_CODE,
            $quotation
        );

        return response()->json([
            'success' => true,
            'message' => 'Quote configuration dikirim untuk approval.',
        ]);
    }

    /**
     * ATURAN APPROVAL DIVISI (cek UAC can_approve ditangani middleware access.control):
     * 1. User dengan divisi yang sama dengan pembuat TIDAK BISA approve dokumen yang dia buat sendiri.
     * 2. Yang bisa approve adalah user LAIN yang SATU DIVISI dengan pembuat.
     * 3. User dari divisi lain TIDAK BISA approve.
     * 4. Role Admin selalu bisa approve (override).
     */
    public function approve($id): JsonResponse
    {
        $quotation = QuoteConfiguration::with('creator')->findOrFail($id);

        if ($quotation->status !== QuoteConfiguration::STATUS_WAITING_APPROVAL) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration tidak dalam status Waiting Approval.',
            ], 422);
        }

        if (! $this->isSameDivisionApprover($quotation)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak bisa approve configuration ini. Pembuat tidak bisa approve dokumennya sendiri, dan hanya user satu divisi dengan pembuat yang bisa approve.',
            ], 403);
        }

        DB::transaction(function () use ($quotation) {
            $quotation->update([
                'status' => QuoteConfiguration::STATUS_APPROVED,
                'final_checked_by' => Auth::id(),
                'approved_at' => now(),
                'is_current' => true,
            ]);

            // Versi approved lain dalam group menjadi riwayat (bukan current) & diarsipkan.
            if ($quotation->group_id) {
                QuoteConfiguration::where('group_id', $quotation->group_id)
                    ->where('id', '!=', $quotation->id)
                    ->update(['is_current' => false]);

                QuoteConfiguration::where('group_id', $quotation->group_id)
                    ->where('id', '!=', $quotation->id)
                    ->where('status', QuoteConfiguration::STATUS_APPROVED)
                    ->update(['status' => QuoteConfiguration::STATUS_ARCHIVED]);
            }
        });

        $this->notifyCreator(
            $quotation,
            'quotation_approved',
            'Quote Configuration Disetujui',
            "Quote Configuration #{$quotation->id} telah disetujui oleh ".Auth::user()->username.'.'
        );

        Log::record(
            'approve_water_configuration',
            "Quote Configuration #{$quotation->id} disetujui oleh ".Auth::user()->username,
            self::MODULE_CODE,
            $quotation
        );

        return response()->json([
            'success' => true,
            'message' => 'Quote configuration disetujui.',
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $quotation = QuoteConfiguration::with('creator')->findOrFail($id);

        if ($quotation->status !== QuoteConfiguration::STATUS_WAITING_APPROVAL) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration tidak dalam status Waiting Approval.',
            ], 422);
        }

        if (! $this->isSameDivisionApprover($quotation)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak bisa menolak configuration ini. Hanya user satu divisi dengan pembuat (bukan pembuatnya) yang bisa reject.',
            ], 403);
        }

        $validated = $request->validate([
            'approval_note' => 'required|string|max:1000',
        ]);

        $quotation->update([
            'status' => QuoteConfiguration::STATUS_REJECTED,
            'final_checked_by' => Auth::id(),
            'approval_note' => $validated['approval_note'],
            'rejected_at' => now(),
        ]);

        $this->notifyCreator(
            $quotation,
            'quotation_rejected',
            'Quote Configuration Ditolak',
            "Quote Configuration #{$quotation->id} ditolak oleh ".Auth::user()->username.'. Alasan: '.$validated['approval_note']
        );

        Log::record(
            'reject_water_configuration',
            "Quote Configuration #{$quotation->id} ditolak oleh ".Auth::user()->username,
            self::MODULE_CODE,
            $quotation
        );

        return response()->json([
            'success' => true,
            'message' => 'Quote configuration ditolak.',
        ]);
    }

    /**
     * Buka kunci konfigurasi yang sudah approved agar bisa direvisi (hanya approver divisi).
     */
    public function unlock($id): JsonResponse
    {
        $quotation = QuoteConfiguration::with('creator')->findOrFail($id);

        if ($quotation->status !== QuoteConfiguration::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya configuration berstatus Approved yang bisa dibuka kunci.',
            ], 422);
        }

        if (! $this->isSameDivisionApprover($quotation)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya approver (user lain satu divisi dengan pembuat) yang bisa membuka kunci.',
            ], 403);
        }

        $quotation->update([
            'unlocked_by' => Auth::id(),
            'unlocked_at' => now(),
        ]);

        Log::record(
            'unlock_water_configuration',
            "Quote Configuration #{$quotation->id} dibuka kunci oleh ".Auth::user()->username,
            self::MODULE_CODE,
            $quotation
        );

        return response()->json([
            'success' => true,
            'message' => 'Kunci dibuka. Pembuat dapat membuat revisi baru.',
        ]);
    }

    /**
     * Buat versi baru (revisi) dari konfigurasi approved (setelah unlock) atau rejected.
     * Header + detail disalin ke baris baru; versi lama tetap sebagai riwayat.
     */
    public function revise($id): JsonResponse
    {
        $source = QuoteConfiguration::with('items')->findOrFail($id);

        $canRevise = $source->status === QuoteConfiguration::STATUS_REJECTED
            || ($source->status === QuoteConfiguration::STATUS_APPROVED && $source->unlocked_at);

        if (! $canRevise) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration ini belum bisa direvisi. Kunci harus dibuka oleh approver terlebih dahulu.',
            ], 422);
        }

        if ($source->created_by !== Auth::id() && Auth::user()->role !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pembuat configuration yang bisa membuat revisi.',
            ], 403);
        }

        $revision = DB::transaction(function () use ($source) {
            // Sumber yang sudah approved menjadi riwayat (Archived) saat revisi dibuat.
            if ($source->status === QuoteConfiguration::STATUS_APPROVED) {
                $source->update([
                    'status' => QuoteConfiguration::STATUS_ARCHIVED,
                    'is_current' => false,
                ]);
            }

            $revision = QuoteConfiguration::create([
                'division_id' => Auth::user()->division_id,
                'group_id' => $source->group_id ?: $source->id,
                'version' => $source->nextVersion(),
                'parent_id' => $source->id,
                'is_current' => false,
                'opportunity_id' => $source->opportunity_id,
                'task_id' => $source->task_id,
                'date' => $source->date,
                'parameter_note' => $source->parameter_note,
                'notes' => $source->notes,
                'status' => QuoteConfiguration::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);

            foreach ($source->items as $item) {
                $revision->items()->create([
                    'product_id' => $item->product_id,
                    'category' => $item->category,
                    'part_number' => $item->part_number,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'unit' => $item->unit,
                    'sort_order' => $item->sort_order,
                ]);
            }

            return $revision;
        });

        Log::record(
            'revise_water_configuration',
            "Revisi dibuat dari Configuration #{$source->id} menjadi #{$revision->id}",
            self::MODULE_CODE,
            $revision
        );

        return response()->json([
            'success' => true,
            'message' => 'Revisi (versi '.$revision->version.') berhasil dibuat.',
            'id' => $revision->id,
        ]);
    }

    /**
     * Daftar versi (riwayat) satu group configuration untuk modal Track.
     */
    public function versions($id): JsonResponse
    {
        $quotation = QuoteConfiguration::findOrFail($id);
        $groupId = $quotation->group_id ?: $quotation->id;

        $versions = QuoteConfiguration::with(['creator', 'finalChecker'])
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
                'show_url' => route('water-configuration.show', $v->id),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'versions' => $versions,
        ]);
    }

    public function pdf($id)
    {
        $quotation = QuoteConfiguration::with([
            'items',
            'creator',
            'finalChecker',
            'task.opportunity.accountCompany',
            'task.opportunity.accountContact',
            'task.opportunity.owner',
            'task.creator',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('water-configuration.pdf', compact('quotation'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('Quote-Configuration-'.$quotation->id.'.pdf');
    }

    /**
     * Aturan inti approval divisi (dipakai di approve() dan show()).
     */
    /**
     * Aturan bisnis approval divisi (cek UAC can_approve ditangani middleware access.control):
     * Admin selalu boleh; pembuat tidak boleh approve dokumennya sendiri; hanya user
     * satu divisi dengan pembuat yang boleh approve.
     */
    private function isSameDivisionApprover(QuoteConfiguration $quotation): bool
    {
        $user = Auth::user();

        if ($user->role === 'Admin') {
            return true;
        }

        // Pembuat tidak bisa approve dokumennya sendiri.
        if ($quotation->created_by === $user->id) {
            return false;
        }

        // Approver harus SATU DIVISI dengan pembuat.
        return $user->division_id === $quotation->creator?->division_id;
    }

    /**
     * Kirim notifikasi ke semua user satu divisi dengan pembuat (selain pembuat)
     * yang memiliki hak approve modul ini.
     */
    private function notifyApprovers(QuoteConfiguration $quotation): void
    {
        $module = Module::where('module_code', self::MODULE_CODE)->first();
        if (! $module) {
            return;
        }

        $creator = $quotation->creator;
        if (! $creator || ! $creator->division_id) {
            return;
        }

        $uacUserIds = UserAccessControl::where('module_id', $module->id)
            ->where('can_approve', true)
            ->pluck('user_id')
            ->toArray();

        $approvers = User::where('division_id', $creator->division_id)
            ->where('id', '!=', $creator->id)
            ->whereIn('id', $uacUserIds)
            ->get();

        foreach ($approvers as $approver) {
            $quotation->notify(
                $approver,
                'quotation_approval_required',
                'Quote Configuration Menunggu Approval',
                "Quote Configuration #{$quotation->id} dari {$creator->username} menunggu approval Anda.",
                [
                    'quote_configuration_id' => $quotation->id,
                    'task_id' => $quotation->task_id,
                ]
            );
        }
    }

    private function notifyCreator(QuoteConfiguration $quotation, string $type, string $title, string $body): void
    {
        $creator = $quotation->creator;
        if (! $creator) {
            return;
        }

        $quotation->notify($creator, $type, $title, $body, [
            'quote_configuration_id' => $quotation->id,
            'task_id' => $quotation->task_id,
        ]);
    }

    private function syncItems(QuoteConfiguration $quotation, array $items): void
    {
        $quotation->items()->delete();

        $payload = [];
        foreach (array_values($items) as $i => $item) {
            $payload[] = [
                'quote_configuration_id' => $quotation->id,
                'product_id' => $item['product_id'] ?? null,
                'category' => $item['category'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'description' => $item['description'],
                'qty' => (int) ($item['qty'] ?? 1),
                'price' => $item['price'] ?? null,
                'unit' => $item['unit'] ?? null,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        QuoteConfigurationItem::insert($payload);
    }
}
