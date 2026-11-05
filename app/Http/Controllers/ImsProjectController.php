<?php

namespace App\Http\Controllers;

use App\Models\AccountCompany;
use App\Models\Document;
use App\Models\Log;
use App\Models\Project;
use App\Models\UserAccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Modul sementara IMS Project (group IMS): input project hybrid yang sudah
 * berjalan, cukup data PO, dokumen, dan masa garansi, untuk pemetaan
 * maintenance. Tidak ada tabel baru: data masuk ke projects, documents
 * (TYPE_AGREEMENT), dan project_warranties.
 *
 * Project dibuat tanpa quotation, nilai 0, dan tanpa sales. Nilai kontrak,
 * sales, dan timeline penagihan dilengkapi di modul Project. Daftar modul ini
 * sama dengan daftar Project (semua project), dan sebaliknya: kedua modul
 * saling melengkapi. IMS hanya mengubah data PO, dokumen, dan garansi;
 * company project dari quotation tetap mengikuti opportunity. Hapus hanya
 * untuk project yang belum lengkap (Project::isImsHybrid()).
 *
 * Gate akses hanya middleware access.control (route_name ims-project).
 */
class ImsProjectController extends Controller
{
    /** Divisi yang task-nya tampil di detail IMS Project. */
    public const TASK_DIVISION = 'IMS';

    public function index()
    {
        return view('ims-project.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = Project::with(['company', 'warranty', 'quotation']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('project_number', 'like', "%{$searchValue}%")
                    ->orWhere('project_name', 'like', "%{$searchValue}%")
                    ->orWhere('po_number_company', 'like', "%{$searchValue}%")
                    ->orWhereHas('company', fn ($c) => $c->where('account_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('quotation', fn ($qq) => $qq->where('quotation_number', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $projects = $query->orderBy('id', 'desc')->offset($start)->limit($length)->get();

        $data = [];
        foreach ($projects as $i => $project) {
            $warranty = $project->warranty;
            $meta = $warranty?->statusMeta();

            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $project->id,
                'project_number' => $project->project_number,
                'project_name' => $project->project_name,
                'company_name' => $project->company?->account_name ?? '—',
                'po_number_company' => $project->po_number_company,
                'po_date' => $project->po_date?->format('d/m/Y') ?? '—',
                'po_due_date' => $project->po_due_date?->format('d/m/Y') ?? '—',
                'warranty_period' => $warranty
                    ? $warranty->start_date->format('d/m/Y').' – '.$warranty->end_date->format('d/m/Y')
                    : null,
                'warranty_label' => $meta['label'] ?? null,
                'warranty_class' => $meta['class'] ?? null,
                'quotation_number' => $project->quotation?->quotation_number,
                'is_hybrid' => $project->isImsHybrid(),
                'status_badge' => $project->statusBadgeHtml(),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create()
    {
        return view('ims-project.form', ['project' => null, 'agreements' => []]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProject($request, null);

        $path = $request->hasFile('po_document') ? $request->file('po_document')->store('project-po', 'public') : null;

        $project = DB::transaction(function () use ($validated, $request, $path) {
            $file = $request->file('po_document');

            $project = Project::create([
                'project_number' => (new Project)->generateProjectNumber(),
                ...$this->projectFields($validated),
                'quotation_id' => null,
                'total_sale_amount' => 0,
                'net_sale_amount' => 0,
                'is_retention' => false,
                'type_of_achievement' => Project::TYPE_PROJECT,
                'status' => Project::STATUS_ACTIVE,
                'po_document_path' => $path,
                'po_document_name' => $file?->getClientOriginalName(),
                'po_uploaded_by' => $file ? Auth::id() : null,
                'po_uploaded_at' => $file ? now() : null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $project->syncWarranty($validated['warranty_start_date'] ?? null, $validated['warranty_end_date'] ?? null);
            $this->storeAgreements($project, $request);

            return $project;
        });

        Log::record(
            'create_project',
            "Project {$project->project_number} ({$project->project_name}) dibuat via IMS Project oleh ".Auth::user()->display_name,
            'MOD_IMS_PROJECT',
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
        $project = Project::query()
            ->with(['company', 'quotation', 'creator', 'updater', 'poUploader', 'documents.uploader', 'warranty',
                // Halaman IMS hanya menampilkan task divisi IMS (pembuat / assignee IMS).
                'tasks' => fn ($q) => $q->forDivision(self::TASK_DIVISION)->with(['creator', 'category', 'assignees']),
            ])
            ->findOrFail($id);

        $user = Auth::user();
        $canCompleteProject = $user->role === 'Admin' || UserAccessControl::where('user_id', $user->id)
            ->whereHas('module', fn ($q) => $q->where('route_name', 'project'))
            ->where('can_update', true)
            ->exists();

        return view('ims-project.show', [
            'project' => $project,
            'agreements' => $this->agreementItems($project),
            'canCompleteProject' => $canCompleteProject,
            ...TaskPlannerController::projectTaskContext($project),
        ]);
    }

    public function edit($id)
    {
        $project = Project::with(['company', 'quotation', 'documents.uploader', 'warranty'])->findOrFail($id);

        return view('ims-project.form', [
            'project' => $project,
            'agreements' => $this->agreementItems($project),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $project = Project::findOrFail($id);

        // Company project dari quotation terkunci mengikuti opportunity.
        if ($project->quotation_id) {
            $request->merge(['company_id' => $project->company_id]);
        }
        $validated = $this->validateProject($request, $project->id);

        $newPath = $request->hasFile('po_document') ? $request->file('po_document')->store('project-po', 'public') : null;

        DB::transaction(function () use ($project, $validated, $request, $newPath) {
            $fields = $this->projectFields($validated) + ['updated_by' => Auth::id()];

            if ($newPath) {
                if ($project->po_document_path && Storage::disk('public')->exists($project->po_document_path)) {
                    Storage::disk('public')->delete($project->po_document_path);
                }
                $fields['po_document_path'] = $newPath;
                $fields['po_document_name'] = $request->file('po_document')->getClientOriginalName();
                $fields['po_uploaded_by'] = Auth::id();
                $fields['po_uploaded_at'] = now();
            }

            $project->update($fields);
            $project->syncWarranty($validated['warranty_start_date'] ?? null, $validated['warranty_end_date'] ?? null);
            $this->storeAgreements($project, $request);
        });

        Log::record(
            'update_project',
            "Project {$project->project_number} ({$project->project_name}) diperbarui via IMS Project oleh ".Auth::user()->display_name,
            'MOD_IMS_PROJECT',
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

        if (! $project->isImsHybrid()) {
            return response()->json([
                'success' => false,
                'message' => 'Project ini sudah memiliki quotation / nilai kontrak. Hapus lewat modul Project.',
            ], 422);
        }

        $blockers = $project->deletionBlockers();
        if ($blockers) {
            return response()->json([
                'success' => false,
                'message' => 'Project tidak bisa dihapus karena sudah memiliki '.implode(', ', $blockers).'.',
            ], 422);
        }

        Log::record(
            'delete_project',
            "Project {$project->project_number} ({$project->project_name}) dihapus via IMS Project oleh ".Auth::user()->display_name,
            'MOD_IMS_PROJECT',
            $project
        );

        if ($project->po_document_path && Storage::disk('public')->exists($project->po_document_path)) {
            Storage::disk('public')->delete($project->po_document_path);
        }

        // Dokumen polimorfik tidak ikut cascade: hapus satu per satu agar file ikut terhapus.
        $project->load('documents');
        $project->documents->each->delete();
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

    public function viewDocument($id, $documentId)
    {
        $project = Project::findOrFail($id);
        $document = $this->agreementOf($project, $documentId);

        return $document->inlineResponse();
    }

    public function deleteDocument($id, $documentId): JsonResponse
    {
        $project = Project::findOrFail($id);
        $this->agreementOf($project, $documentId)->delete();

        return response()->json(['success' => true, 'message' => 'Dokumen dihapus.']);
    }

    public function searchCompanies(Request $request): JsonResponse
    {
        $companies = AccountCompany::where('status', 'Active')
            ->where('account_name', 'like', '%'.$request->input('q', '').'%')
            ->orderBy('account_name')
            ->limit(20)
            ->get();

        return response()->json([
            'results' => $companies->map(fn ($c) => ['id' => $c->id, 'text' => $c->account_name]),
        ]);
    }

    private function validateProject(Request $request, ?int $ignoreId): array
    {
        return $request->validate([
            'project_name' => 'required|string|max:255',
            'project_description' => 'nullable|string',
            'company_id' => 'required|integer|exists:account_companies,id',
            'po_number_company' => [
                'required', 'string', 'max:100',
                Rule::unique('projects', 'po_number_company')->ignore($ignoreId),
            ],
            'po_date' => 'required|date',
            'po_date_received' => 'required|date',
            'po_due_date' => 'required|date|after_or_equal:po_date',
            'po_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'agreement_files' => 'nullable|array|max:'.Document::MAX_FILES,
            'agreement_files.*' => Document::VALIDATION_RULE,
            'agreement_notes' => 'nullable|string|max:255',
            'warranty_start_date' => 'nullable|required_with:warranty_end_date|date',
            'warranty_end_date' => 'nullable|required_with:warranty_start_date|date|after_or_equal:warranty_start_date',
        ], [
            'po_number_company.unique' => 'Nomor PO customer sudah dipakai project lain.',
            'po_due_date.required' => 'Jatuh tempo wajib diisi.',
            'po_due_date.after_or_equal' => 'Jatuh tempo tidak boleh sebelum tanggal PO.',
            'po_document.mimes' => 'File PO harus berformat PDF, JPG, atau PNG.',
            'po_document.max' => 'Ukuran file tidak boleh lebih dari 10MB.',
            'agreement_files.max' => 'Maksimal '.Document::MAX_FILES.' file sekali upload.',
            'agreement_files.*.mimes' => 'Dokumen harus berformat PDF, JPG, atau PNG.',
            'agreement_files.*.max' => 'Ukuran setiap file tidak boleh lebih dari 10MB.',
            'warranty_start_date.required_with' => 'Tanggal mulai garansi wajib diisi bila tanggal akhir diisi.',
            'warranty_end_date.required_with' => 'Tanggal akhir garansi wajib diisi bila tanggal mulai diisi.',
            'warranty_end_date.after_or_equal' => 'Tanggal akhir garansi tidak boleh sebelum tanggal mulai.',
        ]);
    }

    private function projectFields(array $validated): array
    {
        return [
            'project_name' => $validated['project_name'],
            'project_description' => $validated['project_description'] ?? null,
            'company_id' => $validated['company_id'],
            'po_number_company' => $validated['po_number_company'],
            'po_date' => $validated['po_date'],
            'po_date_received' => $validated['po_date_received'],
            'po_due_date' => $validated['po_due_date'],
        ];
    }

    private function storeAgreements(Project $project, Request $request): void
    {
        foreach ((array) $request->file('agreement_files', []) as $file) {
            Document::storeFor($project, $file, Document::TYPE_AGREEMENT, $request->input('agreement_notes'));
        }
    }

    private function agreementOf(Project $project, $documentId): Document
    {
        return $project->documents()->where('type', Document::TYPE_AGREEMENT)->findOrFail($documentId);
    }

    private function agreementItems(Project $project): array
    {
        return $project->documents->where('type', Document::TYPE_AGREEMENT)
            ->map(fn (Document $doc) => $doc->toListItem(
                route('ims-project.documents.show', [$project->id, $doc->id]),
                route('ims-project.documents.destroy', [$project->id, $doc->id])
            ))->values()->all();
    }
}
