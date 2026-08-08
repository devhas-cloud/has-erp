<?php

namespace App\Http\Controllers;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\Activity;
use App\Models\ActivityAttachment;
use App\Models\Division;
use App\Models\DivisionHandler;
use App\Models\Forecast;
use App\Models\Lead;
use App\Models\Log;
use App\Models\LossReason;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Source;
use App\Models\Stage;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\MentionParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OpportunityManagementController extends Controller
{
    public function index()
    {
        $stages = Stage::where('status', 'Active')->get();
        $forecasts = Forecast::where('status', 'Active')->get();
        $lossReasons = LossReason::where('status', 'Active')->get();
        $divisions = Division::where('status', 'Active')->get();
        $sources = Source::where('status', 'Active')->get();
        $users = User::all();
        $accountCompanies = AccountCompany::where('status', 'Active')->orderBy('account_name')->get();

        return view('opportunity-management.index', compact(
            'stages', 'forecasts', 'lossReasons', 'divisions',
            'sources', 'users', 'accountCompanies'
        ));
    }

    public function data(Request $request): JsonResponse
    {
        $query = Opportunity::with(['accountCompany', 'stage', 'owner']);
        $user = Auth::user();
        $isSales = $user->division && $user->division->division_name === 'Sales';

        // jika user adalah sales, filter hanya untuk opportunity yang dimiliki oleh user tersebut
        if ($isSales) {
            $query->where('owner_id', $user->id);
        }

        $recordsTotal = Opportunity::count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('opportunity_name', 'like', "%{$searchValue}%")
                    ->orWhereHas('accountCompany', function ($q) use ($searchValue) {
                        $q->where('account_name', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('stage', function ($q) use ($searchValue) {
                        $q->where('stage_name', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('owner', function ($q) use ($searchValue) {
                        $q->where('username', 'like', "%{$searchValue}%");
                    });
            });
        }

        $recordsFiltered = $query->count();

        $orderColumnIndex = $request->input('order.0.column', 1);
        $orderDirection = $request->input('order.0.dir', 'desc');

        $columnOrderMap = [
            1 => 'opportunity_name',
            2 => 'account_companies.account_name',
            4 => 'close_won_date',
            5 => 'users.username',
        ];

        if (isset($columnOrderMap[$orderColumnIndex])) {
            $sortField = $columnOrderMap[$orderColumnIndex];
            if ($sortField === 'account_companies.account_name') {
                $query->leftJoin('account_companies', 'opportunities.account_companies_id', '=', 'account_companies.id')
                    ->select('opportunities.*')
                    ->orderBy('account_companies.account_name', $orderDirection);
            } elseif ($sortField === 'users.username') {
                $query->leftJoin('users', 'opportunities.owner_id', '=', 'users.id')
                    ->select('opportunities.*')
                    ->orderBy('users.username', $orderDirection);
            } else {
                $query->orderBy($sortField, $orderDirection);
            }
        }
        $query->orderBy('id', $orderDirection === 'desc' ? 'desc' : 'asc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $opportunities = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($opportunities as $i => $opp) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $opp->id,
                'opportunity_name' => $opp->opportunity_name ?? '—',
                'account_name' => $opp->accountCompany?->account_name ?? '—',
                'company_initials' => strtoupper(substr($opp->accountCompany?->account_name ?? '?', 0, 2)),
                'stage_name' => $opp->stage?->stage_name ?? '—',
                'close_won_date' => $opp->close_won_date?->format('d M Y') ?? '—',
                'owner_name' => $opp->owner?->username ?? '—',
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
            'opportunity_name' => 'required|string|max:150',
            'account_companies_id' => 'required|exists:account_companies,id',
            'type' => 'nullable|in:Existing Business,New Business',
            'account_contacts_id' => 'nullable|exists:account_contacts,id',
            'stage_id' => 'nullable|exists:stages,id',
            'probability' => 'required|integer|min:0|max:100',
            'forecast_id' => 'required|exists:forecasts,id',
            'loss_reasons_id' => 'nullable|exists:loss_reasons,id',
            'quote_ready' => 'boolean',
            'division_id' => 'nullable|exists:divisions,id',
            'source_id' => 'nullable|exists:sources,id',
            'lead_id' => 'nullable|exists:leads,id',
            'next_step' => 'nullable|string',
            'close_date' => 'nullable|date',
            'end_user_id' => 'nullable|exists:account_companies,id',
            'budget' => 'boolean',
            'authorize' => 'boolean',
            'timeline' => 'boolean',
            'close_won_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $validated['owner_id'] = Auth::id();

        DB::beginTransaction();
        try {
            $opportunity = Opportunity::create($validated);

            Log::record('create_opportunity', "Opportunity #{$opportunity->id}: {$opportunity->opportunity_name}", 'MOD_OPPORTUNITY_MANAGEMENT', $opportunity);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Opportunity successfully added.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to add opportunity: '.$e->getMessage(),
            ], 500);
        }
    }

    public function fetch($id): JsonResponse
    {
        $opportunity = Opportunity::with(['accountCompany', 'accountContact', 'stage', 'forecast', 'lossReason', 'lead', 'owner', 'endUser'])->findOrFail($id);

        return response()->json(['opportunity' => $opportunity]);
    }

    public function searchCompanies(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        $companies = AccountCompany::where('status', 'Active')
            ->where('account_name', 'like', "%{$q}%")
            ->limit(20)
            ->get();

        $data = $companies->map(fn ($c) => [
            'id' => $c->id,
            'text' => $c->account_name,
        ]);

        return response()->json(['results' => $data]);
    }

    public function searchContacts(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        $companyId = $request->get('company_id');

        $query = AccountContact::where('status', 'Active')
            ->where(function ($qry) use ($q) {
                $qry->where('full_name', 'like', "%{$q}%");
            });

        if ($companyId) {
            $query->where('account_companies_id', $companyId);
        }

        $contacts = $query->limit(20)->get();

        $data = $contacts->map(fn ($c) => [
            'id' => $c->id,
            'text' => $c->full_name.($c->email ? " ({$c->email})" : ''),
        ]);

        return response()->json(['results' => $data]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $opportunity = Opportunity::findOrFail($id);

        $validated = $request->validate([
            'opportunity_name' => 'required|string|max:150',
            'account_companies_id' => 'required|exists:account_companies,id',
            'type' => 'nullable|in:Existing Business,New Business',
            'account_contacts_id' => 'nullable|exists:account_contacts,id',
            'stage_id' => 'nullable|exists:stages,id',
            'probability' => 'nullable|integer|min:0|max:100',
            'forecast_id' => 'required|exists:forecasts,id',
            'loss_reasons_id' => 'nullable|exists:loss_reasons,id',
            'quote_ready' => 'boolean',
            'division_id' => 'nullable|exists:divisions,id',
            'source_id' => 'nullable|exists:sources,id',
            'lead_id' => 'nullable|exists:leads,id',
            'next_step' => 'nullable|string',
            'close_date' => 'nullable|date',
            'end_user_id' => 'nullable|exists:account_companies,id',
            'budget' => 'boolean',
            'authorize' => 'boolean',
            'timeline' => 'boolean',
            'close_won_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $opportunity->update($validated);

            Log::record('update_opportunity', "Opportunity #{$opportunity->id}: {$opportunity->opportunity_name}", 'MOD_OPPORTUNITY_MANAGEMENT', $opportunity);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Opportunity successfully updated.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update opportunity: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $opportunity = Opportunity::with([
            'accountCompany.segmentation',
            'accountCompany.accountType',
            'accountCompany.businessEntity',
            'accountCompany.businessValue',
            'accountCompany.interactionLevel',
            'accountCompany.typesAccountsCompany',
            'accountContact.jobTitle',
            'accountContact.division',
            'accountContact.contactMethod',
            'accountContact.roleInProject',
            'stage',
            'forecast',
            'lossReason',
            'lead.accountCompany',
            'lead.accountContact',
            'owner',
            'division',
            'source',
            'endUser',
        ])->findOrFail($id);

        $stages = Stage::where('status', 'Active')->get();
        $forecasts = Forecast::where('status', 'Active')->get();
        $lossReasons = LossReason::where('status', 'Active')->get();
        $divisions = Division::where('status', 'Active')->get();
        $sources = Source::where('status', 'Active')->get();
        $users = User::all();
        $accountCompanies = AccountCompany::where('status', 'Active')->orderBy('account_name')->get();
        $accountContacts = AccountContact::where('status', 'Active')->orderBy('full_name')->get();
        $categories = TaskCategory::with('division')->get();
        $categoryHandlerMap = TaskCategory::pluck('use_division_handler', 'id')
            ->map(fn ($v) => (bool) $v);

        return view('opportunity-management.show', compact(
            'opportunity', 'stages', 'forecasts', 'lossReasons', 'divisions',
            'sources', 'users', 'accountCompanies', 'accountContacts', 'categories', 'categoryHandlerMap'
        ));
    }

    public function destroy($id): JsonResponse
    {
        $opportunity = Opportunity::findOrFail($id);

        DB::beginTransaction();
        try {
            Log::record('delete_opportunity', "Opportunity #{$opportunity->id}: {$opportunity->opportunity_name} dihapus", 'MOD_OPPORTUNITY_MANAGEMENT', $opportunity);

            $opportunity->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Opportunity successfully deleted.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete opportunity.',
            ], 500);
        }
    }

    public function fetchActivities($id): JsonResponse
    {
        $opportunity = Opportunity::findOrFail($id);

        $activities = $opportunity->activities()
            ->whereNull('reply_to_id')
            ->with(['user', 'attachments', 'replies.user', 'replies.attachments', 'task'])
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json($activities);
    }

    public function storeActivity(Request $request, $id): JsonResponse
    {
        $opportunity = Opportunity::findOrFail($id);

        $mimeRule = 'image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,application/zip,application/x-zip-compressed,application/octet-stream';
        $validated = $request->validate([
            'content' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:10240|mimetypes:'.$mimeRule,
            'reply_to_id' => 'nullable|exists:activities,id',
        ]);

        $hasContent = ! empty($validated['content'] ?? null);
        $hasFiles = $request->hasFile('attachments');

        if (! $hasContent && ! $hasFiles) {
            return response()->json(['message' => 'Content or file attachment required.'], 422);
        }

        $activity = Activity::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => Auth::id(),
            'content' => $validated['content'] ?? '',
            'reply_to_id' => $validated['reply_to_id'] ?? null,
        ]);

        if ($hasFiles) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('activity-attachments', 'public');
                $mime = $file->getMimeType();
                $activity->attachments()->create([
                    'attachment_path' => $path,
                    'attachment_type' => str_starts_with($mime, 'image/') ? 'image' : 'file',
                    'attachment_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        $activity->load(['user', 'attachments']);

        $mentionedIds = MentionParser::extractMentionedIds($activity->content);
        foreach ($mentionedIds as $uid) {
            if ((int) $uid !== Auth::id()) {
                Notification::create([
                    'user_id' => $uid,
                    'type' => 'mention',
                    'title' => 'You were mentioned by '.Auth::user()->username,
                    'body' => Str::limit($activity->content, 120),
                    'notifiable_type' => Activity::class,
                    'notifiable_id' => $activity->id,
                    'data' => ['activity_id' => $activity->id, 'opportunity_id' => $activity->opportunity_id, 'mentioned_by' => Auth::id()],
                ]);
            }
        }

        // Notify parent activity author on reply
        if ($activity->reply_to_id) {
            $parent = Activity::find($activity->reply_to_id);
            if ($parent && $parent->user_id !== Auth::id()) {
                Notification::create([
                    'user_id' => $parent->user_id,
                    'type' => 'mention',
                    'title' => 'Balasan pada aktivitas Anda',
                    'body' => Auth::user()->username.' membalas: '.Str::limit($activity->content, 80),
                    'notifiable_type' => Activity::class,
                    'notifiable_id' => $parent->id,
                    'data' => ['activity_id' => $activity->id, 'opportunity_id' => $activity->opportunity_id, 'mentioned_by' => Auth::id()],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Activity posted.',
            'activity' => $activity,
        ]);
    }

    public function uploadActivityAttachment(Request $request, Activity $activity): JsonResponse
    {
        $request->validate(['file' => 'required|file|max:10240']);

        $file = $request->file('file');
        $path = $file->store('activity-attachments', 'public');

        $attachment = ActivityAttachment::create([
            'activity_id' => $activity->id,
            'attachment_path' => $path,
            'attachment_type' => str_starts_with($file->getMimeType(), 'image/') ? 'image' : 'file',
            'attachment_name' => $file->getClientOriginalName(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded.',
            'attachment' => $attachment->append('attachment_url'),
        ]);
    }

    public function destroyActivity(Activity $activity): JsonResponse
    {
        if ($activity->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $activity->attachments()->delete();
        $activity->replies()->delete();
        $activity->delete();

        return response()->json(['success' => true, 'message' => 'Activity deleted.']);
    }

    public function fetchTasks($id): JsonResponse
    {
        $opportunity = Opportunity::findOrFail($id);

        $tasks = Task::where('opportunity_id', $opportunity->id)
            ->with(['creator', 'assignees', 'category'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
    }

    public function storeTask(Request $request, $id): JsonResponse
    {
        $opportunity = Opportunity::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:task_categories,id',
            'handling_division_id' => 'nullable|exists:divisions,id',
            'whatsapp_group_id' => 'nullable|exists:whatsapp_groups,id',
            'due_date' => 'required|date',
            'time' => 'nullable|date_format:H:i',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
            'alert_type' => 'nullable|in:none,email,whatsapp,both',
            'alert_target' => 'required|in:personal,group,both',
            'alert_time' => 'nullable|date',
        ]);

        $validated['creator_id'] = Auth::id();
        $validated['status'] = 'todo';
        $validated['opportunity_id'] = $opportunity->id;

        $assigneeIds = $request->input('assignees', []);
        $handlingDivisionId = $request->input('handling_division_id');
        if ($handlingDivisionId) {
            $roster = DivisionHandler::where('division_id', $handlingDivisionId)->pluck('user_id')->all();
            $assigneeIds = array_values(array_unique(array_merge($assigneeIds, $roster)));
        }

        if (empty($assigneeIds)) {
            $assigneeIds = [Auth::id()];
        }

        $otherAssignees = array_values(array_diff($assigneeIds, [Auth::id()]));
        $validated['requires_approval'] = ! empty($otherAssignees);

        DB::beginTransaction();
        try {
            $task = Task::create($validated);
            $task->assignees()->sync($assigneeIds);

            $task->load('creator');
            foreach ($otherAssignees as $assigneeId) {
                $assignee = User::find($assigneeId);
                if ($assignee) {
                    Notification::create([
                        'user_id' => $assignee->id,
                        'type' => 'task_assigned',
                        'title' => "Tugas baru: {$task->title}",
                        'body' => "{$task->creator->username} menugaskan Anda",
                        'notifiable_type' => Task::class,
                        'notifiable_id' => $task->id,
                        'data' => ['task_id' => $task->id, 'creator' => $task->creator->username],
                    ]);
                }
            }

            Activity::create([
                'opportunity_id' => $opportunity->id,
                'task_id' => $task->id,
                'user_id' => Auth::id(),
                'content' => "📋 Created task: {$task->title}",
            ]);

            DB::commit();

            $task->load(['creator', 'assignees']);

            return response()->json([
                'success' => true,
                'message' => 'Task created.',
                'task' => $task,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create task: '.$e->getMessage(),
            ], 500);
        }
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        $users = User::where('username', 'like', "%{$q}%")
            ->orWhere('email', 'like', "%{$q}%")
            ->limit(10)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username,
                'initials' => strtoupper(substr($u->username, 0, 2)),
            ]);

        return response()->json(['results' => $users->toArray()]);
    }

    public function searchLeads(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        $leads = Lead::with('accountCompany', 'accountContact')
            ->where('lead_title', 'like', "%{$q}%")
            ->limit(10)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'text' => $l->lead_title.' ('.($l->accountCompany?->account_name ?? 'N/A').')',
            ]);

        return response()->json(['results' => $leads->toArray()]);
    }
}
