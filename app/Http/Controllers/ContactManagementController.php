<?php

namespace App\Http\Controllers;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\ContactMethod;
use App\Models\Division;
use App\Models\Forecast;
use App\Models\JobTitle;
use App\Models\Lead;
use App\Models\Log;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\RoleInProject;
use App\Models\Source;
use App\Models\Stage;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\ContactExportService;
use App\Services\ContactImportService;
use App\Services\ContactXlsxTemplateGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ContactManagementController extends Controller
{
    private const ACTIVITY_MIME_RULE = 'image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,application/zip,application/x-zip-compressed,application/octet-stream';

    public function index()
    {
        $accountCompanies = AccountCompany::where('status', 'Active')->orderBy('account_name')->get();
        $jobTitles = JobTitle::where('status', 'Active')->get();
        $sources = Source::where('status', 'Active')->get();
        $divisions = Division::where('type','External')->where('status', 'Active')->get();
        $contactMethods = ContactMethod::where('status', 'Active')->get();
        $roleInProjects = RoleInProject::where('status', 'Active')->get();

        // Pilihan "Assigned To" hanya user divisi Sales — kontak pada
        // akhirnya harus dikerjakan oleh sales, siapa pun yang membuatnya.
        $assignableUsers = User::whereHas('division', fn ($q) => $q->whereRaw('LOWER(division_name) = ?', ['sales']))
            ->orderBy('full_name')
            ->get();

        // Sales non-manager hanya melihat kontaknya sendiri: filter Assigned To disembunyikan.
        $restrictedSales = $this->isRestrictedSales();

        return view('contacts-management.index', compact(
            'accountCompanies', 'jobTitles', 'sources', 'divisions',
            'contactMethods', 'roleInProjects', 'assignableUsers', 'restrictedSales'
        ));
    }

    /** User Sales yang bukan Manager hanya boleh melihat kontak yang ditugaskan / dimilikinya. */
    private function isRestrictedSales(): bool
    {
        return strtolower((string) Auth::user()->division?->division_name) === 'sales'
            && strtolower((string) Auth::user()->taskRole?->role_name) !== 'manager';
    }

    /** Kontak aktif yang boleh dilihat user (dipakai daftar & export). */
    private function visibleContacts()
    {
        $query = AccountContact::query()->where('status', 'Active');
        if ($this->isRestrictedSales()) {
            $query->where(function ($q) {
                $q->where('assigned_to_id', Auth::id())
                    ->orWhere('contact_owner_id', Auth::id());
            });
        }

        return $query;
    }

    /**
     * Cari + filter toolbar: account, department, job title, source, role in project,
     * assigned to, aktivitas terakhir, dan "milik saya". Dipakai daftar & export.
     */
    private function applyFilters($query, Request $request, ?string $search): void
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhereHas('accountCompany', fn ($q) => $q->where('account_name', 'like', "%{$search}%"))
                    ->orWhereHas('contactOwner', fn ($q) => $q->where('username', 'like', "%{$search}%")->orWhere('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('jobTitle', fn ($q) => $q->where('title_name', 'like', "%{$search}%"));
            });
        }

        foreach ([
            'account' => 'account_companies_id',
            'division' => 'divisions_id',
            'job_title' => 'job_titles_id',
            'source' => 'sources_id',
            'role' => 'role_in_projects_id',
        ] as $param => $column) {
            if ($request->filled($param)) {
                $query->where($column, (int) $request->input($param));
            }
        }

        if ($request->filled('assigned') && ! $this->isRestrictedSales()) {
            $request->input('assigned') === 'none'
                ? $query->whereNull('assigned_to_id')
                : $query->where('assigned_to_id', (int) $request->input('assigned'));
        }

        match ($request->input('activity')) {
            '7d' => $query->where('last_activity_at', '>=', now()->subDays(7)),
            '30d' => $query->where('last_activity_at', '>=', now()->subDays(30)),
            'stale' => $query->where('last_activity_at', '<', now()->subDays(30)),
            'none' => $query->whereNull('last_activity_at'),
            default => null,
        };

        if ($request->boolean('mine')) {
            $query->where('contact_owner_id', Auth::id());
        }
    }

    public function data(Request $request): JsonResponse
    {

        $query = $this->visibleContacts()->with(['accountCompany', 'contactOwner', 'assignedTo', 'jobTitle']);
        $recordsTotal = (clone $query)->count();

        $this->applyFilters($query, $request, $request->input('search.value'));

        $recordsFiltered = $query->count();

        $orderColumnIndex = (int) $request->input('order.0.column', 1);
        $orderDirection = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $columnOrderMap = [
            1 => 'full_name',
            4 => 'mobile',
            5 => 'email',
            8 => 'last_activity_at',
        ];

        if ($orderColumnIndex === 8) {
            $query->orderByRaw("last_activity_at IS NULL, last_activity_at {$orderDirection}");
        } elseif (isset($columnOrderMap[$orderColumnIndex])) {
            $query->orderBy($columnOrderMap[$orderColumnIndex], $orderDirection);
        }
        $query->orderBy('id', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $contacts = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($contacts as $i => $contact) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $contact->id,
                'full_name' => $contact->full_name ?? '—',
                'initials' => strtoupper(substr($contact->full_name ?? '?', 0, 2)),
                'icon' => $contact->icon_url,
                'name_display' => $contact->full_name ?? '—',
                'title' => $contact->jobTitle?->title_name ?? '—',
                'account_name' => $contact->accountCompany?->account_name ?? '—',
                'company_icon' => $contact->accountCompany?->icon_url,
                'company_initials' => strtoupper(substr((string) ($contact->accountCompany?->account_name ?? '?'), 0, 2)),
                'company_name_display' => $contact->accountCompany?->account_name ?? '—',
                'mobile' => $contact->mobile ?? '—',
                'email' => $contact->email ?? '—',
                'owner_name' => $contact->contactOwner?->display_name ?? '—',
                'assigned_to_name' => $contact->assignedTo?->display_name ?? '—',
                'last_activity_at' => $contact->last_activity_at?->format('d/m/Y'),
                'last_activity_full' => $contact->last_activity_at?->format('d/m/Y H:i'),
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
        $isReferralSource = $this->isReferralSource($request->sources_id);

        $validated = $request->validate([
            'salutation' => 'required|in:Ibu,Bapak',
            'full_name' => 'required|string|max:150',
            'icon' => 'nullable|image|max:2048',
            'account_companies_id' => 'required|exists:account_companies,id',
            'email' => 'required|email|max:100|unique:account_contacts,email',
            'phone' => 'nullable|string|max:30',
            'mobile' => 'required|string|max:30|unique:account_contacts,mobile',
            'job_titles_id' => 'required|exists:job_titles,id',
            'sources_id' => 'required|exists:sources,id',
            'name_referral' => ['nullable', 'string', 'max:150', Rule::requiredIf($isReferralSource)],
            'divisions_id' => 'required|exists:divisions,id',
            'contact_methods_id' => 'required|exists:contact_methods,id',
            'role_in_projects_id' => 'required|exists:role_in_projects,id',
            'assigned_to_id' => 'nullable|exists:users,id',
            'address_street' => 'nullable|string',
            'address_city' => 'nullable|string|max:100',
            'address_province' => 'nullable|string|max:100',
            'address_postal_code' => 'nullable|string|max:10',
            'address_country' => 'nullable|string|max:100',
        ]);

        // Default assigned_to ke pembuat kontak kalau tidak diisi (field ini
        // disembunyikan untuk user divisi Sales, jadi selalu jatuh ke sini).
        $validated['assigned_to_id'] = $validated['assigned_to_id'] ?? Auth::id();

        // name_referral hanya relevan untuk source Referral; default null.
        $validated['name_referral'] = $isReferralSource
            ? (trim((string) ($validated['name_referral'] ?? '')) ?: null)
            : null;

        if ($request->hasFile('icon')) {
            $validated['icon'] = $request->file('icon')->store('contact-icons', 'public');
        }

        AccountContact::create(array_merge($validated, [
            'contact_owner_id' => Auth::id(),
            'status' => 'Active',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Contact added.',
        ]);
    }

    public function edit($id): JsonResponse
    {
        $contact = AccountContact::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $contact->toArray(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $contact = AccountContact::findOrFail($id);

        $isReferralSource = $this->isReferralSource($request->sources_id);

        $validated = $request->validate([
            'salutation' => 'required|in:Ibu,Bapak',
            'full_name' => 'required|string|max:150',
            'icon' => 'nullable|image|max:2048',
            'account_companies_id' => 'required|exists:account_companies,id',
            'email' => 'required|email|max:100|unique:account_contacts,email,'.$contact->id,
            'phone' => 'nullable|string|max:30',
            'mobile' => 'required|string|max:30|unique:account_contacts,mobile,'.$contact->id,
            'job_titles_id' => 'required|exists:job_titles,id',
            'sources_id' => 'required|exists:sources,id',
            'name_referral' => ['nullable', 'string', 'max:150', Rule::requiredIf($isReferralSource)],
            'divisions_id' => 'required|exists:divisions,id',
            'contact_methods_id' => 'required|exists:contact_methods,id',
            'role_in_projects_id' => 'required|exists:role_in_projects,id',
            'assigned_to_id' => 'nullable|exists:users,id',
            'address_street' => 'nullable|string',
            'address_city' => 'nullable|string|max:100',
            'address_province' => 'nullable|string|max:100',
            'address_postal_code' => 'nullable|string|max:10',
            'address_country' => 'nullable|string|max:100',
        ]);

        // name_referral hanya relevan untuk source Referral; default null.
        $validated['name_referral'] = $isReferralSource
            ? (trim((string) ($validated['name_referral'] ?? '')) ?: null)
            : null;

        if ($request->hasFile('icon')) {
            $this->deleteContactIcon($contact->icon);
            $validated['icon'] = $request->file('icon')->store('contact-icons', 'public');
        }

        $contact->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Contact updated.',
        ]);
    }

    public function show($id)
    {
        $user = Auth::user();
        $isSales = strtolower($user->division?->division_name) === 'sales' && strtolower($user->hierarchyRole?->role_name) !== 'manager';
        $contact = AccountContact::with([
            'accountCompany', 'contactOwner', 'assignedTo', 'jobTitle',
            'source', 'division', 'contactMethod', 'roleInProject',
            'leads.leadOwner',
            'leads.source',
        ])->findOrFail($id);

        $opportunities = Opportunity::where('account_contacts_id', $contact->id)
            ->with(['stage', 'owner', 'forecast'])
            ->orderByDesc('created_at')
            ->get();

$sources = Source::where('status', 'Active')->get();
        $stages = Stage::where('status', 'Active')->get();
        $forecasts = Forecast::where('status', 'Active')->get();
        $users = User::orderBy('full_name')->get();
        $accountCompanies = AccountCompany::where('status', 'Active')->orderBy('account_name')->get();
        $divisions = Division::where('type', 'Internal')->where('status', 'Active')->get();

        $user = Auth::user();
        $isSales = strtolower($user->division?->division_name ?? '') === 'sales'
            && strtolower($user->hierarchyRole?->role_name ?? '') !== 'manager';

        // Task yang terhubung dengan contact: via tasks.account_contact_id
        // ATAU via tasks.opportunity->account_contacts_id.
        $activityTasks = Task::with(['accountContact', 'opportunity', 'category', 'creator', 'assignees'])
            ->where(fn ($q) => $q
                ->where('account_contact_id', $contact->id)
                ->orWhereHas('opportunity', fn ($o) => $o->where('account_contacts_id', $contact->id)))
            ->orderByDesc('created_at')
            ->get();
        $taskCategories = TaskCategory::with('division')->orderBy('name')->get();

        return view('contacts-management.show', compact(
            'contact', 'opportunities',
            'sources', 'stages', 'forecasts', 'users', 'isSales', 'accountCompanies', 'divisions',
            'activityTasks', 'taskCategories'
        ));
    }

    public function storeLead(Request $request, $id): JsonResponse
    {
        $contact = AccountContact::findOrFail($id);

        $isReferralSource = str_contains(strtolower((string) Source::find($request->source_id)?->source_name), 'referral');

        $validated = $request->validate([
            'lead_title' => 'required|string|max:500',
            'lead_status' => 'required|in:New,Approach,Qualified,Unqualified',
            'source_id' => 'required|exists:sources,id',
            'name_referral' => ['nullable', 'string', 'max:150', Rule::requiredIf($isReferralSource)],
            'lead_follow_up_date' => 'required|date',
            'assigned_to' => 'nullable|exists:users,id',
            'unqualified_reason' => 'nullable|string|max:1000',
        ]);

        $lead = Lead::create([
            'lead_status' => $validated['lead_status'],
            'lead_title' => $validated['lead_title'],
            'account_companies_id' => $contact->account_companies_id,
            'account_contacts_id' => $contact->id,
            'source_id' => $validated['source_id'],
            'name_referral' => trim((string) ($validated['name_referral'] ?? '')) ?: null,
            'unqualified_reason' => $validated['unqualified_reason'] ?? null,
            'lead_follow_up_date' => $validated['lead_follow_up_date'] ?? null,
            'lead_owner_id' => Auth::id(),
            'assigned_to' => $validated['assigned_to'] ?? null,
        ]);

        Log::record('create_lead', "Lead #{$lead->id}: {$lead->lead_title} dibuat dari contact #{$contact->id} ({$contact->full_name})", 'MOD_CONTACT_MANAGEMENT', $lead);

        return response()->json([
            'success' => true,
            'message' => 'Lead created.',
            'data' => $lead,
        ]);
    }

    public function storeTask(Request $request, $id): JsonResponse
    {
        $contact = AccountContact::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
            'category_id' => 'required|exists:task_categories,id',
            'due_date' => 'required|date',
            'time' => 'nullable|date_format:H:i',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:10240|mimetypes:'.self::ACTIVITY_MIME_RULE,
        ]);

        $category = TaskCategory::findOrFail($validated['category_id']);
        $isLog = str_contains(mb_strtolower($category->name), 'log');

        $validated['account_contact_id'] = $contact->id;
        $validated['creator_id'] = Auth::id();
        $validated['status'] = $isLog ? 'done' : 'todo';

        if ($isLog) {
            $validated['due_date'] = now()->toDateString();
            $validated['time'] = null;
        }

        $assigneeIds = $isLog ? [] : $request->input('assignees', []);
        if (empty($assigneeIds)) {
            $assigneeIds = [Auth::id()];
        }
        $otherAssignees = array_values(array_diff($assigneeIds, [Auth::id()]));
        $validated['requires_approval'] = ! empty($otherAssignees);

        DB::beginTransaction();
        try {
            $task = Task::create($validated);
            $task->assignees()->sync($assigneeIds);

            // Kategori log: description + file menjadi activity task,
            // bukan lampiran activity lead.
            $hasDescription = filled($validated['description'] ?? null);
            $hasAttachments = $request->hasFile('attachments');

            if ($isLog && ($hasDescription || $hasAttachments)) {
                $taskActivity = TaskActivity::create([
                    'task_id' => $task->id,
                    'user_id' => Auth::id(),
                    'content' => $validated['description'] ?? null,
                ]);

                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store('task-activities', 'public');
                    $taskActivity->attachments()->create([
                        'attachment_path' => $path,
                        'attachment_type' => str_starts_with($file->getMimeType(), 'image/') ? 'image' : 'file',
                        'attachment_name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            $task->load('creator');
            foreach ($otherAssignees as $assigneeId) {
                $assignee = User::find($assigneeId);
                if ($assignee) {
                    Notification::create([
                        'user_id' => $assignee->id,
                        'type' => 'task_assigned',
                        'title' => "Tugas baru: {$task->title}",
                        'body' => "{$task->creator->display_name} menugaskan Anda",
                        'notifiable_type' => Task::class,
                        'notifiable_id' => $task->id,
                        'data' => ['task_id' => $task->id, 'creator' => $task->creator->display_name],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task created.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create the task: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroyTask($id, $task): JsonResponse
    {
        $contact = AccountContact::findOrFail($id);

        $task = Task::where('id', $task)
            ->where(fn ($q) => $q
                ->where('account_contact_id', $contact->id)
                ->orWhereHas('opportunity', fn ($o) => $o->where('account_contacts_id', $contact->id)))
            ->first();

        if (! $task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.',
            ], 404);
        }

        $task->delete();

        Log::record('delete_task', "Task #{$task->id}: {$task->title} dihapus dari contact #{$contact->id} ({$contact->full_name})", 'MOD_CONTACT_MANAGEMENT', $task);

        return response()->json([
            'success' => true,
            'message' => 'Task deleted.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $contact = AccountContact::findOrFail($id);
        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted.',
        ]);
    }

    public function downloadTemplate()
    {
        $path = storage_path('app/private/templates/contact_import_template.xlsx');

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $references = ContactImportService::getReferenceData();

        $generator = new ContactXlsxTemplateGenerator;
        $generator->generate($references, $path);

        return response()->download($path, 'Contact_Import_Template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $filePath = $file->storeAs('imports', 'contact-import-'.uniqid().'.'.$ext);

        $fullPath = Storage::path($filePath);

        try {
            $service = new ContactImportService;
            $result = $service->import($fullPath, Auth::id());

            return response()->json([
                'success' => $result['failed'] === 0,
                'message' => "Import finished: {$result['success']} succeeded, {$result['failed']} failed out of ".($result['success'] + $result['failed']).' row(s).',
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: '.$e->getMessage(),
            ], 500);
        } finally {
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    private function isReferralSource($sourceId): bool
    {
        return str_contains(strtolower((string) Source::find($sourceId)?->source_name), 'referral');
    }

    private function deleteContactIcon(?string $path): void
    {
        if ($path && ! preg_match('#^(https?:)?/#i', $path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function export(Request $request)
    {
        $query = $this->visibleContacts()->with([
            'accountCompany',
            'contactOwner',
            'assignedTo',
            'jobTitle',
            'source',
            'division',
            'contactMethod',
            'roleInProject',
        ]);
        // Export mengikuti filter & pencarian aktif di daftar.
        $this->applyFilters($query, $request, $request->input('q'));

        $contacts = $query->orderBy('full_name')->get();

        $headers = [
            'Salutation', 'Full Name', 'Account', 'Email', 'Phone', 'Mobile',
            'Job Title', 'Contact Source', 'Referral Name', 'Department', 'Contact Method', 'Role in Project',
            'Assigned To', 'Contact Owner',
            'Address Street', 'Address City', 'Address Province', 'Address Postal Code', 'Address Country',
            'Status', 'Created At',
        ];

        $service = new ContactExportService;
        $filePath = $service->export($contacts, $headers);

        return response()->download($filePath, 'contacts-export-'.date('Y-m-d').'.xlsx')
            ->deleteFileAfterSend(true);
    }
}
