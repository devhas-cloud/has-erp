<?php

namespace App\Http\Controllers;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\Document;
use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\ProjectAchievement;
use App\Models\ProjectBillingTerm;
use App\Models\Quotation;
use App\Models\SalesAchievement;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use App\Support\PayableReport;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Modul Project: kelanjutan quotation yang sudah deal. Pembuatan project
 * menggantikan upload PO pada quotation — project menyimpan dokumen PO dan
 * menandai quotation Finish. Form hanya menawarkan quotation yang belum
 * punya project (selectableQuotations()); di tingkat data quotation_id tidak
 * unique. Sinkronisasi total/net read-only saat quotation
 * dipilih; project lama boleh tanpa quotation dengan nilai manual.
 *
 * Timeline penagihan (project_billing_terms) disusun di form project — boleh
 * saat dibuat atau menyusul — dan menjadi sumber invoice per termin. Kontrak /
 * perjanjian disimpan sebagai Document project (disk private); dokumen
 * pendukung termin di-upload dari halaman detail project / invoice.
 *
 * Gate akses hanya middleware access.control (tidak ada query UAC di sini).
 */
class ProjectController extends Controller
{
    public function index()
    {
        return view('project.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = Project::with(['company', 'quotation', 'creator']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('project_number', 'like', "%{$searchValue}%")
                    ->orWhere('project_name', 'like', "%{$searchValue}%")
                    ->orWhere('po_number_company', 'like', "%{$searchValue}%")
                    ->orWhereHas('company', fn ($c) => $c->where('account_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('quotation', fn ($qq) => $qq->where('quotation_number', 'like', "%{$searchValue}%")
                        ->orWhere('to_name', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $projects = $query->orderBy('id', 'desc')->offset($start)->limit($length)->get();

        $data = [];
        foreach ($projects as $i => $project) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $project->id,
                'project_number' => $project->project_number,
                'project_name' => $project->project_name,
                'quotation_number' => $project->quotation?->quotation_number ?? '—',
                'company_name' => $project->company?->account_name ?? '—',
                'po_number_company' => $project->po_number_company,
                'po_date' => $project->po_date?->format('d/m/Y') ?? '—',
                'total_sale_amount' => $project->total_sale_amount,
                'net_sale_amount' => $project->net_sale_amount,
                'type_label' => $project->type_label,
                'status' => $project->status,
                'status_badge' => $project->statusBadgeHtml(),
                'creator_name' => $project->creator?->display_name ?? '—',
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        $quotations = $this->selectableQuotations();

        $preselected = null;
        if ($request->filled('quotation_id')) {
            $preselected = $quotations->firstWhere('id', (int) $request->input('quotation_id'));
        }

        return view('project.form', [
            'project' => null,
            'quotations' => $quotations,
            'preselected' => $preselected,
            'salesIds' => [],
            'salesOptions' => [],
            'billingTerms' => [],
            'billingBase' => 0,
            'netLocked' => false,
            'agreements' => [],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $quotation = $this->resolveQuotation($request);

        if ($quotation && ! trim((string) $request->input('project_name'))) {
            $request->merge(['project_name' => $this->defaultProjectName($quotation)]);
        }

        $validated = $this->validateProject($request, null);
        $terms = $this->validatedBillingTerms($validated['billing_terms'] ?? [], null);

        if ($quotation && ! $request->hasFile('po_document')) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen PO wajib diupload untuk project yang terhubung quotation.',
            ], 422);
        }

        $total = $quotation ? (float) $quotation->grand_total : (float) $validated['total_sale_amount'];
        $net = $quotation
            ? (float) $quotation->profitEstimate->real_project_value
            : (float) $validated['net_sale_amount'];

        if (! $quotation) {
            $this->assertManualAmounts($total, $net, null);
        }

        $file = null;
        $path = null;
        if ($request->hasFile('po_document')) {
            $file = $request->file('po_document');
            $path = $file->store('project-po', 'public');
        }

        $project = DB::transaction(function () use ($validated, $quotation, $total, $net, $file, $path, $terms, $request) {
            $project = Project::create([
                'project_number' => (new Project)->generateProjectNumber(),
                'project_name' => $validated['project_name'],
                'project_description' => $validated['project_description'] ?? null,
                'quotation_id' => $quotation?->id,
                'company_id' => $validated['company_id'],
                'po_number_company' => $validated['po_number_company'],
                'po_date' => $validated['po_date'],
                'po_date_received' => $validated['po_date_received'],
                'po_due_date' => $validated['po_due_date'],
                'total_sale_amount' => $total,
                'net_sale_amount' => $net,
                'is_retention' => $validated['is_retention'],
                'type_of_achievement' => $validated['type_of_achievement'],
                'status' => Project::STATUS_ACTIVE,
                'po_document_path' => $file ? $path : null,
                'po_document_name' => $file?->getClientOriginalName(),
                'po_uploaded_by' => $file ? Auth::id() : null,
                'po_uploaded_at' => $file ? now() : null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $this->syncSalesAchievements($project, $validated['sales'], $net);
            $this->syncBillingTerms($project, $terms);
            $this->storeAgreements($project, $request);

            if ($quotation && $quotation->status !== Quotation::STATUS_FINISH) {
                $quotation->update(['status' => Quotation::STATUS_FINISH]);
            }

            return $project;
        });

        Log::record(
            'create_project',
            "Project {$project->project_number} ({$project->project_name}) dibuat oleh ".Auth::user()->display_name,
            'MOD_PROJECT',
            $project
        );

        return response()->json([
            'success' => true,
            'message' => 'Project berhasil dibuat.',
            'id' => $project->id,
        ]);
    }

    public function show($id)
    {
        $project = Project::with([
            'company', 'quotation.opportunity', 'creator', 'updater', 'poUploader',
            'achievements.division', 'salesAchievements.salesPerson', 'invoices',
            'documents.uploader', 'billingTerms.invoices', 'billingTerms.documents.uploader', 'billingTerms.unlocker',
            'deliveryOrders.warehouse',
        ])->findOrFail($id);

        $user = Auth::user();
        $canCreateInvoice = $user->role === 'Admin' || UserAccessControl::where('user_id', $user->id)
            ->whereHas('module', fn ($q) => $q->where('route_name', 'invoice'))
            ->where('can_create', true)
            ->exists();

        $billingBase = $project->billingBaseAmount();
        $canCreateDeliveryOrder = $user->role === 'Admin' || UserAccessControl::where('user_id', $user->id)
            ->whereHas('module', fn ($q) => $q->where('route_name', 'delivery-order'))
            ->where('can_create', true)
            ->exists();

        return view('project.show', [
            'projectGoods' => $this->projectGoods($project),
            'purchaseCost' => (new PayableReport)->projectCosts($project->id)->first(),
            'canCreateDeliveryOrder' => $canCreateDeliveryOrder,
            'project' => $project,
            'canCreateInvoice' => $canCreateInvoice,
            'billingBase' => $billingBase,
            'termDocuments' => $project->billingTerms->mapWithKeys(fn (ProjectBillingTerm $term) => [
                $term->id => $term->documents->map(fn (Document $doc) => $this->documentItem($project, $doc, $term))->values(),
            ]),
            'agreements' => $project->documents->where('type', Document::TYPE_AGREEMENT)
                ->map(fn (Document $doc) => $this->documentItem($project, $doc))->values(),
        ]);
    }

    /**
     * Barang milik project: saldo per gudang (teralokasi, belum dikirim) dan
     * total terkirim lewat Delivery Order, per produk.
     */
    private function projectGoods(Project $project)
    {
        $balances = StockMovement::balancesQuery()->where('project_id', $project->id)->get();
        $delivered = StockMovement::where('project_id', $project->id)
            ->where('type', StockMovement::TYPE_DELIVERY)
            ->groupBy('master_product_id')
            ->selectRaw('master_product_id, SUM(qty) AS qty')
            ->pluck('qty', 'master_product_id');

        $products = MasterProduct::whereIn('id', $balances->pluck('master_product_id'))->get(['id', 'code', 'name'])->keyBy('id');
        $warehouses = Warehouse::whereIn('id', $balances->pluck('warehouse_id'))->get(['id', 'code'])->keyBy('id');

        return $balances->groupBy('master_product_id')
            ->map(fn ($rows, $productId) => [
                'master_product_id' => (int) $productId,
                'code' => $products[$productId]->code ?? '—',
                'name' => $products[$productId]->name ?? '—',
                'unit' => $rows->pluck('unit')->filter()->first(),
                'in_stock' => (int) $rows->sum('balance'),
                'locations' => $rows->filter(fn ($row) => $row->balance > 0)
                    ->map(fn ($row) => ($warehouses[$row->warehouse_id]->code ?? '?').': '.(int) $row->balance)->values()->all(),
                'delivered' => (int) ($delivered[$productId] ?? 0),
            ])
            ->filter(fn ($row) => $row['in_stock'] > 0 || $row['delivered'] > 0)
            ->sortBy('code')
            ->values();
    }

    /**
     * Upload dokumen: kontrak/perjanjian project, atau dokumen pendukung termin
     * bila billing_term_id dikirim. Bisa beberapa file sekaligus.
     */
    public function uploadDocument(Request $request, $id): JsonResponse
    {
        $project = Project::findOrFail($id);

        $validated = $request->validate([
            'billing_term_id' => ['nullable', 'integer', Rule::exists('project_billing_terms', 'id')->where('project_id', $project->id)],
            'files' => 'required|array|min:1|max:'.Document::MAX_FILES,
            'files.*' => Document::VALIDATION_RULE,
            'notes' => 'nullable|string|max:255',
        ], $this->documentMessages());

        $owner = isset($validated['billing_term_id'])
            ? ProjectBillingTerm::findOrFail($validated['billing_term_id'])
            : $project;
        $type = $owner instanceof ProjectBillingTerm ? Document::TYPE_SUPPORT : Document::TYPE_AGREEMENT;

        $documents = DB::transaction(fn () => collect($request->file('files'))
            ->map(fn ($file) => Document::storeFor($owner, $file, $type, $validated['notes'] ?? null)));

        $term = $owner instanceof ProjectBillingTerm ? $owner : null;

        return response()->json([
            'success' => true,
            'message' => $documents->count().' dokumen berhasil diupload.',
            'documents' => $owner->documents()->with('uploader')->get()
                ->map(fn (Document $doc) => $this->documentItem($project, $doc, $term))->values(),
        ]);
    }

    /**
     * Gerbang tagihan termin (hak can_update Project, PATCH): buka (Open, boleh
     * dibuatkan invoice) atau kunci lagi selama belum ada invoice aktif.
     * Membuka termin mengirim notifikasi ke pembuat invoice (can_create Invoice).
     * Route sengaja tidak berakhiran "unlock" — middleware memetakan verb itu ke can_approve.
     */
    public function updateTermAccess(Request $request, $id, $termId): JsonResponse
    {
        $project = Project::findOrFail($id);
        $term = $project->billingTerms()->with('invoices')->findOrFail($termId);

        $validated = $request->validate(['locked' => 'required|boolean']);
        $lock = (bool) $validated['locked'];

        if ($lock && $term->hasActiveInvoice()) {
            return response()->json([
                'success' => false,
                'message' => '"'.$term->name.'" tidak bisa dikunci karena sudah ditagih lewat invoice '.$term->activeInvoice()->invoice_number.'.',
            ], 422);
        }

        if ($lock === ! $term->isUnlocked()) {
            return response()->json([
                'success' => true,
                'message' => '"'.$term->name.'" sudah '.($lock ? 'terkunci' : 'terbuka').'.',
            ]);
        }

        $term->update([
            'unlocked_at' => $lock ? null : now(),
            'unlocked_by' => $lock ? null : Auth::id(),
        ]);

        $actor = Auth::user()->display_name;
        Log::record(
            $lock ? 'lock_billing_term' : 'unlock_billing_term',
            'Termin "'.$term->name.'" project '.$project->project_number.' '.($lock ? 'dikunci' : 'dibuka untuk ditagih').' oleh '.$actor,
            'MOD_PROJECT',
            $project
        );

        if (! $lock) {
            $this->notifyTermUnlocked($project, $term, $actor);
        }

        return response()->json([
            'success' => true,
            'message' => $lock
                ? '"'.$term->name.'" dikunci kembali.'
                : '"'.$term->name.'" dibuka untuk ditagih. Tim invoice sudah diberi notifikasi.',
        ]);
    }

    public function viewDocument($id, $documentId)
    {
        $project = Project::findOrFail($id);
        $document = Document::findOrFail($documentId);

        abort_unless($project->ownsDocument($document), 404);

        return $document->inlineResponse();
    }

    public function deleteDocument($id, $documentId): JsonResponse
    {
        $project = Project::findOrFail($id);
        $document = Document::findOrFail($documentId);

        abort_unless($project->ownsDocument($document), 404);

        $owner = $document->documentable;
        if ($owner instanceof ProjectBillingTerm && ! $owner->documentsDeletable()) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak bisa dihapus karena invoice termin ini sudah Approved. Dokumen hanya bisa ditambah.',
            ], 422);
        }

        $document->delete();

        return response()->json(['success' => true, 'message' => 'Dokumen dihapus.']);
    }

    public function edit($id)
    {
        $project = Project::with(['salesAchievements', 'company', 'quotation', 'billingTerms.invoices', 'documents.uploader'])->findOrFail($id);

        $quotations = $this->selectableQuotations();
        if ($project->quotation && ! $quotations->contains('id', $project->quotation_id)) {
            $quotations->prepend($project->quotation);
        }

        $salesIds = $project->salesAchievements->pluck('sales_person_id')->all();
        $salesOptions = User::whereIn('id', $salesIds)->get()
            ->map(fn ($u) => ['id' => $u->id, 'text' => $u->display_name])
            ->values()
            ->all();

        return view('project.form', [
            'project' => $project,
            'quotations' => $quotations,
            'preselected' => $project->quotation,
            'salesIds' => $salesIds,
            'salesOptions' => $salesOptions,
            'billingTerms' => $project->billingTerms->map(fn (ProjectBillingTerm $term) => [
                'id' => $term->id,
                'name' => $term->name,
                'percent' => $term->percent,
                'description' => $term->description,
                'target_date' => $term->target_date?->toDateString(),
                // billed: sudah ada invoice aktif -> persen terkunci & tidak bisa dihapus.
                'billed' => $term->hasActiveInvoice(),
                'invoice_number' => $term->activeInvoice()?->invoice_number,
            ])->values()->all(),
            'billingBase' => $project->billingBaseAmount(),
            // Project manual: net sale terkunci bila sudah ada invoice termin.
            'netLocked' => ! $project->quotation_id && $project->billingTerms->contains(fn (ProjectBillingTerm $term) => $term->hasActiveInvoice()),
            'agreements' => $project->documents->where('type', Document::TYPE_AGREEMENT)
                ->map(fn (Document $doc) => $this->documentItem($project, $doc))->values()->all(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $project = Project::findOrFail($id);

        // Quotation tidak bisa diganti setelah project dibuat; input diabaikan.
        $request->merge(['quotation_id' => $project->quotation_id]);
        $quotation = $this->resolveQuotation($request);

        if ($quotation && ! trim((string) $request->input('project_name'))) {
            $request->merge(['project_name' => $this->defaultProjectName($quotation)]);
        }

        $validated = $this->validateProject($request, $project->id);
        $terms = $this->validatedBillingTerms($validated['billing_terms'] ?? [], $project);

        $total = $quotation ? (float) $quotation->grand_total : (float) $validated['total_sale_amount'];
        $net = $quotation
            ? (float) $quotation->profitEstimate->real_project_value
            : (float) $validated['net_sale_amount'];

        if (! $quotation) {
            $this->assertManualAmounts($total, $net, $project);
        }

        $newPath = null;
        if ($request->hasFile('po_document')) {
            $file = $request->file('po_document');
            $newPath = $file->store('project-po', 'public');
        }

        DB::transaction(function () use ($project, $validated, $total, $net, $newPath, $request, $terms) {
            $fields = [
                'project_name' => $validated['project_name'],
                'project_description' => $validated['project_description'] ?? null,
                'company_id' => $validated['company_id'],
                'po_number_company' => $validated['po_number_company'],
                'po_date' => $validated['po_date'],
                'po_date_received' => $validated['po_date_received'],
                'po_due_date' => $validated['po_due_date'],
                'total_sale_amount' => $total,
                'net_sale_amount' => $net,
                'is_retention' => $validated['is_retention'],
                'type_of_achievement' => $validated['type_of_achievement'],
                'updated_by' => Auth::id(),
            ];

            if ($request->hasFile('po_document')) {
                if ($project->po_document_path && Storage::disk('public')->exists($project->po_document_path)) {
                    Storage::disk('public')->delete($project->po_document_path);
                }
                $fields['po_document_path'] = $newPath;
                $fields['po_document_name'] = $request->file('po_document')->getClientOriginalName();
                $fields['po_uploaded_by'] = Auth::id();
                $fields['po_uploaded_at'] = now();
            }

            $project->update($fields);

            $this->syncSalesAchievements($project, $validated['sales'], $net);
            $this->syncBillingTerms($project, $terms);
            $this->storeAgreements($project, $request);
        });

        Log::record(
            'update_project',
            "Project {$project->project_number} ({$project->project_name}) diperbarui oleh ".Auth::user()->display_name,
            'MOD_PROJECT',
            $project
        );

        return response()->json([
            'success' => true,
            'message' => 'Project berhasil diperbarui.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $project = Project::findOrFail($id);

        $blockers = $project->deletionBlockers();
        if ($blockers) {
            return response()->json([
                'success' => false,
                'message' => 'Project tidak bisa dihapus karena sudah memiliki '.implode(', ', $blockers)
                    .'. Bila project batal, ubah statusnya menjadi Cancelled agar riwayat transaksi tetap utuh.',
            ], 422);
        }

        Log::record(
            'delete_project',
            "Project {$project->project_number} ({$project->project_name}) dihapus oleh ".Auth::user()->display_name,
            'MOD_PROJECT',
            $project
        );

        if ($project->po_document_path && Storage::disk('public')->exists($project->po_document_path)) {
            Storage::disk('public')->delete($project->po_document_path);
        }

        // Dokumen polimorfik tidak ikut cascade: hapus satu per satu agar file ikut terhapus.
        $project->load('documents', 'billingTerms.documents');
        $project->documents->each->delete();
        $project->billingTerms->each(fn (ProjectBillingTerm $term) => $term->documents->each->delete());

        $project->delete();

        return response()->json(['success' => true, 'message' => 'Project dihapus.']);
    }

    public function viewPo($id)
    {
        $project = Project::findOrFail($id);

        if (! $project->po_document_path || ! Storage::disk('public')->exists($project->po_document_path)) {
            abort(404, 'File PO tidak ditemukan.');
        }

        return Storage::disk('public')->response($project->po_document_path, $project->po_document_name, [
            'Content-Disposition' => 'inline; filename="'.$project->po_document_name.'"',
        ]);
    }

    /**
     * Data quotation untuk form: nilai read-only + company + nama project.
     * Quotation harus Approved/Finish dan sudah punya Profit Estimate.
     */
    public function fetchQuotation(Request $request): JsonResponse
    {
        $request->validate(['quotation_id' => 'required|integer|exists:quotations,id']);

        $quotation = Quotation::with(['opportunity.accountCompany', 'profitEstimate'])
            ->findOrFail($request->input('quotation_id'));

        if (! in_array($quotation->status, [Quotation::STATUS_APPROVED, Quotation::STATUS_FINISH], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation harus berstatus Approved atau Finish.',
            ], 422);
        }

        $estimate = $quotation->profitEstimate;
        if (! $estimate || $estimate->real_project_value === null) {
            return response()->json([
                'success' => false,
                'message' => 'Profit Estimate belum tersedia untuk quotation ini. Buat Profit Estimate terlebih dahulu.',
            ], 422);
        }

        $company = $quotation->opportunity?->accountCompany;
        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation ini tidak terikat pada company (opportunity belum punya account company).',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'quotation_number' => $quotation->quotation_number,
                'project_name' => $quotation->opportunity?->opportunity_name
                    ?: ($quotation->to_name ?: $company->account_name),
                'company_id' => $company->id,
                'company_name' => $company->account_name,
                'total_sale_amount' => (float) $quotation->grand_total,
                'net_sale_amount' => (float) $estimate->real_project_value,
                'billing_base_amount' => (new Project)->setRelation('quotation', $quotation)->billingBaseAmount(),
                // Rincian dari Estimasi PL: Nilai Awal -> PPN -> Discount -> Referen -> Nilai Real Project.
                'breakdown' => [
                    'nilai_awal' => (float) $estimate->nilai_awal,
                    'ppn_amount' => (float) $estimate->ppn_amount,
                    'subtotal_1' => (float) $estimate->subtotal_1,
                    'discount_amount' => (float) $estimate->discount_amount,
                    'subtotal_2' => (float) $estimate->subtotal_2,
                    'referral_percent' => (float) $estimate->referral_percent,
                    'referral_amount' => (float) $estimate->referral_amount,
                    'real_project_value' => (float) $estimate->real_project_value,
                    'profit_estimate_url' => route('profit-estimate.show', $estimate->id),
                ],
            ],
        ]);
    }

    public function searchCompanies(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $companies = AccountCompany::where('status', 'Active')
            ->where('account_name', 'like', "%{$q}%")
            ->orderBy('account_name')
            ->limit(20)
            ->get();

        return response()->json([
            'results' => $companies->map(fn ($c) => ['id' => $c->id, 'text' => $c->account_name]),
        ]);
    }

    public function searchSales(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $users = User::query()
            ->where(function ($query) use ($q) {
                $query->where('username', 'like', "%{$q}%")
                    ->orWhere('full_name', 'like', "%{$q}%");
            })->where('division_id', 6) // Hanya menampilkan user dengan division_id = 6 (Sales)
            ->orderBy('full_name')
            ->limit(20)
            ->get();

        return response()->json([
            'results' => $users->map(fn ($u) => ['id' => $u->id, 'text' => $u->display_name]),
        ]);
    }

    /**
     * Data tab "Achievements" pada index: satu baris per project_achievement.
     */
    public function achievementsData(Request $request): JsonResponse
    {
        $query = ProjectAchievement::with([
            'project.company',
            'project.salesAchievements.salesPerson',
            'division',
        ]);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('device_name', 'like', "%{$searchValue}%")
                    ->orWhereHas('division', fn ($d) => $d->where('division_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('project', fn ($p) => $p->where('project_number', 'like', "%{$searchValue}%")
                        ->orWhere('po_number_company', 'like', "%{$searchValue}%")
                        ->orWhereHas('company', fn ($c) => $c->where('account_name', 'like', "%{$searchValue}%")))
                    ->orWhereHas('project.salesAchievements.salesPerson', fn ($u) => $u->where('username', 'like', "%{$searchValue}%")
                        ->orWhere('full_name', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $achievements = $query->orderByDesc('project_id')->orderBy('id')
            ->offset($start)->limit($length)->get();

        $data = [];
        foreach ($achievements as $i => $row) {
            $salesNames = $row->project?->salesAchievements
                ->map(fn ($s) => $s->salesPerson?->display_name)
                ->filter()
                ->implode(', ');

            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $row->id,
                'project_id' => $row->project_id,
                'project_number' => $row->project?->project_number ?? '—',
                'company_name' => $row->project?->company?->account_name ?? '—',
                'device_name' => $row->device_name,
                'qty' => (float) $row->qty,
                'unit' => $row->unit,
                'amount' => (float) $row->amount,
                'po_date' => $row->project?->po_date?->format('d/m/Y') ?? '—',
                'division_name' => $row->division?->division_name ?? '—',
                'sales_names' => $salesNames !== '' ? $salesNames : '—',
                'po_number_company' => $row->project?->po_number_company ?? '—',
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Form terpisah untuk mengelola rincian device (project_achievements).
     */
    public function achievementForm($id)
    {
        $project = Project::with('achievements')->findOrFail($id);

        return view('project.achievements', [
            'project' => $project,
            'divisions' => Division::where('status', 'Active')->where('type', 'Internal')->orderBy('division_name')->get(),
            'deviceNames' => $this->achievementSuggestions('device_name'),
            'principals' => $this->achievementSuggestions('principal'),
            // Brand: yang pernah dipakai di rincian + brand master produk.
            'brands' => $this->achievementSuggestions('brand')
                ->merge(MasterProduct::whereNotNull('brand')->where('brand', '!=', '')->distinct()->pluck('brand'))
                ->map(fn ($brand) => trim($brand))->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'achievements' => $project->achievements->map(fn ($row) => [
                'principal' => $row->principal,
                'brand' => $row->brand,
                'device_name' => $row->device_name,
                'qty' => $row->qty,
                'unit' => $row->unit,
                'amount' => $row->amount,
                'division_id' => $row->division_id,
            ])->all(),
        ]);
    }

    public function saveAchievements(Request $request, $id): JsonResponse
    {
        $project = Project::findOrFail($id);

        $validated = $request->validate([
            'achievements' => 'required|array|min:1',
            'achievements.*.principal' => 'nullable|string|max:150',
            'achievements.*.brand' => 'nullable|string|max:150',
            'achievements.*.device_name' => 'required|string|max:255',
            'achievements.*.qty' => 'required|numeric|min:0',
            'achievements.*.unit' => 'nullable|string|max:50',
            'achievements.*.amount' => 'required|numeric|min:0',
            'achievements.*.division_id' => 'required|integer|exists:divisions,id',
        ], [
            'achievements.required' => 'Tambahkan minimal satu rincian achievement.',
        ]);

        if (! $this->achievementTotalMatches($validated['achievements'], (float) $project->net_sale_amount)) {
            return $this->achievementMismatch((float) $project->net_sale_amount);
        }

        DB::transaction(function () use ($project, $validated) {
            $this->syncAchievements($project, $validated['achievements']);
        });

        Log::record(
            'update_project_achievement',
            "Rincian device project {$project->project_number} diperbarui oleh ".Auth::user()->display_name,
            'MOD_PROJECT',
            $project
        );

        return response()->json([
            'success' => true,
            'message' => 'Rincian achievement berhasil disimpan.',
        ]);
    }

    private function defaultProjectName(Quotation $quotation): string
    {
        return $quotation->opportunity?->opportunity_name
            ?: ($quotation->to_name ?: ($quotation->opportunity?->accountCompany?->account_name ?? ''));
    }

    /**
     * Quotation yang bisa dipilih di form project: Approved/Finish dan belum
     * pernah dibuatkan project. Halaman edit menambahkan kembali quotation milik
     * project yang sedang diedit (lihat edit()).
     */
    private function selectableQuotations()
    {
        return Quotation::with(['opportunity.accountCompany', 'profitEstimate'])
            ->whereIn('status', [Quotation::STATUS_APPROVED, Quotation::STATUS_FINISH])
            ->whereNotNull('opportunity_id')
            ->whereNotIn('id', Project::whereNotNull('quotation_id')->select('quotation_id'))
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Ambil dan validasi quotation dari request. Null bila project lama tanpa
     * quotation. Nilai total/net/company read-only dipaksa di sini.
     */
    private function resolveQuotation(Request $request): ?Quotation
    {
        if (! $request->filled('quotation_id')) {
            return null;
        }

        $quotation = Quotation::with(['opportunity', 'profitEstimate'])
            ->findOrFail($request->input('quotation_id'));

        if (! in_array($quotation->status, [Quotation::STATUS_APPROVED, Quotation::STATUS_FINISH], true)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Quotation harus berstatus Approved atau Finish.',
            ], 422));
        }

        if (! $quotation->profitEstimate || $quotation->profitEstimate->real_project_value === null) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Profit Estimate belum tersedia untuk quotation ini. Buat Profit Estimate terlebih dahulu.',
            ], 422));
        }

        $companyId = $quotation->opportunity?->account_companies_id;
        if (! $companyId) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Quotation ini tidak terikat pada company (opportunity belum punya account company).',
            ], 422));
        }

        // Company read-only: paksa dari quotation, abaikan input client.
        $request->merge(['company_id' => $companyId]);

        return $quotation;
    }

    private function validateProject(Request $request, ?int $ignoreId): array
    {
        return $request->validate([
            'quotation_id' => 'nullable|integer|exists:quotations,id',
            'project_name' => 'required|string|max:255',
            'project_description' => 'nullable|string',
            'company_id' => 'required|integer|exists:account_companies,id',
            'po_number_company' => [
                'required', 'string', 'max:100',
                Rule::unique('projects', 'po_number_company')->ignore($ignoreId),
            ],
            'po_date' => 'required|date',
            'po_date_received' => 'required|date',
            'po_due_date' => 'required|date',
            'total_sale_amount' => 'required_without:quotation_id|nullable|numeric|min:0',
            'net_sale_amount' => 'required_without:quotation_id|nullable|numeric|min:0',
            'is_retention' => 'required|boolean',
            'type_of_achievement' => 'required|in:project,retail',
            'po_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'sales' => 'required|array|min:1',
            'sales.*' => 'required|integer|distinct|exists:users,id',
            'billing_terms' => 'nullable|array|max:20',
            'billing_terms.*.id' => 'nullable|integer',
            'billing_terms.*.name' => 'required|string|max:100',
            'billing_terms.*.percent' => 'required|numeric|gt:0|max:100',
            'billing_terms.*.description' => 'nullable|string|max:255',
            'billing_terms.*.target_date' => 'nullable|date',
            'agreement_files' => 'nullable|array|max:'.Document::MAX_FILES,
            'agreement_files.*' => Document::VALIDATION_RULE,
            'agreement_notes' => 'nullable|string|max:255',
        ], $this->documentMessages('agreement_files') + [
            'billing_terms.*.name.required' => 'Nama pembayaran pada timeline wajib diisi.',
            'billing_terms.*.percent.required' => 'Persentase pada timeline wajib diisi.',
            'billing_terms.*.percent.gt' => 'Persentase pada timeline harus lebih dari 0.',
            'po_number_company.unique' => 'Nomor PO customer sudah dipakai project lain.',
            'po_document.mimes' => 'File PO harus berformat PDF, JPG, atau PNG.',
            'po_document.max' => 'Ukuran file tidak boleh lebih dari 10MB.',
            'sales.required' => 'Pilih minimal satu sales.',
        ]);
    }

    /**
     * Project manual: net sale (sebelum PPN) menjadi dasar penagihan, jadi tidak
     * boleh melebihi total sale dan terkunci begitu ada invoice termin aktif
     * (nilai invoice termin harus sama dengan nilai kontrak).
     */
    private function assertManualAmounts(float $total, float $net, ?Project $project): void
    {
        $fail = fn (string $message) => throw new HttpResponseException(response()->json(['success' => false, 'message' => $message], 422));

        if ($net > $total) {
            $fail('Net Sale Amount (sebelum PPN) tidak boleh melebihi Total Sale Amount.');
        }

        if ($project && abs($net - (float) $project->net_sale_amount) > 0.005) {
            $billed = $project->billingTerms()->with('invoices')->get()->first(fn (ProjectBillingTerm $term) => $term->hasActiveInvoice());
            if ($billed) {
                $fail('Net Sale Amount terkunci karena sudah ada invoice termin '.$billed->activeInvoice()->invoice_number.'. Batalkan invoice tersebut dulu bila nilai project berubah.');
            }
        }
    }

    /**
     * Cek timeline sebelum disimpan: total <= 100%, termin yang sudah punya
     * invoice aktif tidak boleh dihapus dan persennya terkunci.
     */
    private function validatedBillingTerms(array $rows, ?Project $project): array
    {
        $rows = array_values($rows);
        $fail = fn (string $message) => throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
        ], 422));

        $total = round(array_sum(array_map(fn ($row) => (float) $row['percent'], $rows)), 2);
        if ($total > 100) {
            $fail('Total persentase timeline penagihan '.Project::formatNumber($total).'% melebihi 100%.');
        }

        if (! $project) {
            return array_map(fn ($row) => array_merge($row, ['id' => null]), $rows);
        }

        $existing = $project->billingTerms()->with('invoices')->get()->keyBy('id');
        $keptIds = [];

        foreach ($rows as $i => $row) {
            $termId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            if ($termId && ! $existing->has($termId)) {
                $fail('Termin timeline tidak ditemukan. Muat ulang halaman lalu coba lagi.');
            }

            $term = $termId ? $existing[$termId] : null;
            if ($term && $term->hasActiveInvoice() && abs($term->percent - (float) $row['percent']) > 0.001) {
                $fail('Persentase "'.$term->name.'" terkunci karena sudah ada invoice '.$term->activeInvoice()->invoice_number.'.');
            }

            $rows[$i]['id'] = $termId;
            if ($termId) {
                $keptIds[] = $termId;
            }
        }

        foreach ($existing as $term) {
            if (! in_array($term->id, $keptIds, true) && $term->hasActiveInvoice()) {
                $fail('"'.$term->name.'" tidak bisa dihapus karena sudah ada invoice '.$term->activeInvoice()->invoice_number.'.');
            }
        }

        return $rows;
    }

    private function syncBillingTerms(Project $project, array $rows): void
    {
        $keptIds = [];

        foreach ($rows as $i => $row) {
            $data = [
                'sort_order' => $i + 1,
                'name' => trim($row['name']),
                'percent' => round((float) $row['percent'], 2),
                'description' => isset($row['description']) && trim($row['description']) !== '' ? trim($row['description']) : null,
                'target_date' => $row['target_date'] ?? null,
            ];

            $term = $row['id']
                ? tap($project->billingTerms()->findOrFail($row['id']))->update($data)
                : $project->billingTerms()->create($data);

            $keptIds[] = $term->id;
        }

        // Termin yang dihapus dari form (sudah dipastikan tidak punya invoice aktif).
        $project->billingTerms()->whereNotIn('id', $keptIds)->with('documents')->get()
            ->each(function (ProjectBillingTerm $term) {
                $term->documents->each->delete();
                $term->delete();
            });
    }

    private function storeAgreements(Project $project, Request $request): void
    {
        foreach ((array) $request->file('agreement_files', []) as $file) {
            Document::storeFor($project, $file, Document::TYPE_AGREEMENT, $request->input('agreement_notes'));
        }
    }

    /**
     * Notifikasi ke pembuat invoice (can_create modul Invoice), kecuali pelaku.
     */
    private function notifyTermUnlocked(Project $project, ProjectBillingTerm $term, string $actor): void
    {
        $recipientIds = UserAccessControl::where('can_create', true)
            ->whereHas('module', fn ($q) => $q->where('route_name', 'invoice'))
            ->where('user_id', '!=', Auth::id())
            ->pluck('user_id')
            ->unique();

        User::whereIn('id', $recipientIds)->get()->each(fn (User $user) => $project->notify(
            $user,
            'billing_term_unlocked',
            'Termin siap ditagih',
            'Termin '.$term->name.' ('.Project::formatNumber($term->percent).'%) project '.$project->project_number.' — '.$project->project_name.' sudah dibuka untuk ditagih oleh '.$actor.'.',
            [
                'project_id' => $project->id,
                'billing_term_id' => $term->id,
                'group_title' => $project->project_number.' — '.$project->project_name,
                'url' => route('project.show', $project->id).'#timeline-penagihan',
            ]
        ));
    }

    private function documentItem(Project $project, Document $document, ?ProjectBillingTerm $term = null): array
    {
        $deletable = ! $term || $term->documentsDeletable();

        return $document->toListItem(
            route('project.documents.show', [$project->id, $document->id]),
            $deletable ? route('project.documents.destroy', [$project->id, $document->id]) : null
        );
    }

    private function documentMessages(string $field = 'files'): array
    {
        return [
            $field.'.max' => 'Maksimal '.Document::MAX_FILES.' file sekali upload.',
            $field.'.*.mimes' => 'Dokumen harus berformat PDF, JPG, atau PNG.',
            $field.'.*.max' => 'Ukuran setiap file tidak boleh lebih dari 10MB.',
            $field.'.required' => 'Pilih minimal satu file.',
        ];
    }

    private function achievementTotalMatches(array $rows, float $net): bool
    {
        $totalCents = 0;
        foreach ($rows as $row) {
            $totalCents += (int) round(((float) $row['amount']) * 100);
        }

        return $totalCents === (int) round($net * 100);
    }

    private function achievementMismatch(float $net): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Total amount rincian achievement harus sama dengan Net Sale Amount ('.number_format($net, 2, ',', '.').').',
        ], 422);
    }

    /**
     * Saran isian (autocomplete) dari nilai yang pernah dipakai di rincian device.
     */
    private function achievementSuggestions(string $column)
    {
        return ProjectAchievement::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }

    private function syncAchievements(Project $project, array $rows): void
    {
        ProjectAchievement::where('project_id', $project->id)->delete();

        $payload = [];
        foreach (array_values($rows) as $row) {
            $payload[] = [
                'project_id' => $project->id,
                'principal' => isset($row['principal']) && trim($row['principal']) !== '' ? trim($row['principal']) : null,
                'brand' => isset($row['brand']) && trim($row['brand']) !== '' ? trim($row['brand']) : null,
                'device_name' => $row['device_name'],
                'qty' => (float) $row['qty'],
                'unit' => $row['unit'] ?? null,
                'amount' => (float) $row['amount'],
                'division_id' => (int) $row['division_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ProjectAchievement::insert($payload);
    }

    /**
     * Bagi net_sale_amount sama rata ke seluruh sales. Sisa pembulatan
     * (dalam sen) diberikan ke sales terakhir agar jumlah tetap pas.
     */
    private function syncSalesAchievements(Project $project, array $salesIds, float $net): void
    {
        SalesAchievement::where('project_id', $project->id)->delete();

        $ids = array_values(array_unique(array_map('intval', $salesIds)));
        $count = count($ids);
        if ($count === 0) {
            return;
        }

        $netCents = (int) round($net * 100);
        $base = intdiv($netCents, $count);

        $payload = [];
        foreach ($ids as $i => $userId) {
            $cents = $i === $count - 1 ? $netCents - ($base * ($count - 1)) : $base;
            $payload[] = [
                'project_id' => $project->id,
                'sales_person_id' => $userId,
                'amount_achieved' => $cents / 100,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        SalesAchievement::insert($payload);
    }
}
