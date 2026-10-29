<?php

namespace App\Http\Controllers;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\Log;
use App\Models\Project;
use App\Models\ProjectAchievement;
use App\Models\Quotation;
use App\Models\SalesAchievement;
use App\Models\User;
use App\Models\UserAccessControl;
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
 * menandai quotation Finish. Satu quotation boleh punya lebih dari satu
 * project (tidak unique). Sinkronisasi total/net read-only saat quotation
 * dipilih; project lama boleh tanpa quotation dengan nilai manual.
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
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $quotation = $this->resolveQuotation($request);

        if ($quotation && ! trim((string) $request->input('project_name'))) {
            $request->merge(['project_name' => $this->defaultProjectName($quotation)]);
        }

        $validated = $this->validateProject($request, null);

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

        $file = null;
        $path = null;
        if ($request->hasFile('po_document')) {
            $file = $request->file('po_document');
            $path = $file->store('project-po', 'public');
        }

        $project = DB::transaction(function () use ($validated, $quotation, $total, $net, $file, $path) {
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
        ])->findOrFail($id);

        $user = Auth::user();
        $canCreateInvoice = $user->role === 'Admin' || UserAccessControl::where('user_id', $user->id)
            ->whereHas('module', fn ($q) => $q->where('route_name', 'invoice'))
            ->where('can_create', true)
            ->exists();

        return view('project.show', [
            'project' => $project,
            'canCreateInvoice' => $canCreateInvoice,
        ]);
    }

    public function edit($id)
    {
        $project = Project::with(['salesAchievements', 'company', 'quotation'])->findOrFail($id);

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

        $total = $quotation ? (float) $quotation->grand_total : (float) $validated['total_sale_amount'];
        $net = $quotation
            ? (float) $quotation->profitEstimate->real_project_value
            : (float) $validated['net_sale_amount'];

        $newPath = null;
        if ($request->hasFile('po_document')) {
            $file = $request->file('po_document');
            $newPath = $file->store('project-po', 'public');
        }

        DB::transaction(function () use ($project, $validated, $total, $net, $newPath, $request) {
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

        Log::record(
            'delete_project',
            "Project {$project->project_number} ({$project->project_name}) dihapus oleh ".Auth::user()->display_name,
            'MOD_PROJECT',
            $project
        );

        if ($project->po_document_path && Storage::disk('public')->exists($project->po_document_path)) {
            Storage::disk('public')->delete($project->po_document_path);
        }

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
            })
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
            'deviceNames' => ProjectAchievement::query()
                ->whereNotNull('device_name')
                ->where('device_name', '!=', '')
                ->distinct()
                ->orderBy('device_name')
                ->pluck('device_name'),
            'achievements' => $project->achievements->map(fn ($row) => [
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

    private function selectableQuotations()
    {
        return Quotation::with(['opportunity.accountCompany', 'profitEstimate'])
            ->whereIn('status', [Quotation::STATUS_APPROVED, Quotation::STATUS_FINISH])
            ->whereNotNull('opportunity_id')
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
        ], [
            'po_number_company.unique' => 'Nomor PO customer sudah dipakai project lain.',
            'po_document.mimes' => 'File PO harus berformat PDF, JPG, atau PNG.',
            'po_document.max' => 'Ukuran file tidak boleh lebih dari 10MB.',
            'sales.required' => 'Pilih minimal satu sales.',
        ]);
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

    private function syncAchievements(Project $project, array $rows): void
    {
        ProjectAchievement::where('project_id', $project->id)->delete();

        $payload = [];
        foreach (array_values($rows) as $row) {
            $payload[] = [
                'project_id' => $project->id,
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
