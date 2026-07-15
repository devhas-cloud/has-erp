<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\WhatsAppGroup;
use App\Models\Log;
use App\Models\TaskVisit;
use App\Services\MentionParser;
use App\Services\TaskExportService;
use App\Services\TaskImportService;
use App\Services\TaskXlsxTemplateGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskPlannerController extends Controller
{
    public function index()
    {
        $whatsappGroups = WhatsAppGroup::with('division')->where('status', 'Active')->get();
        $users = User::all();

        $user = Auth::user();
        $userId = $user->id;
        $userDivisionId = $user->division_id;

        $categories = TaskCategory::where(function ($q) use ($userDivisionId) {
            $q->whereNull('division_id')
                ->orWhere('division_id', $userDivisionId);
        })->get();

        return view('task-planner.index', compact('whatsappGroups', 'users', 'categories', 'userId'));
    }

    public function data(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $user = Auth::user()->load('hierarchyRole', 'division');
        $userRole = $user->hierarchyRole;
        $userDivisionId = $user->division_id;

        $query = Task::with(['creator', 'category', 'whatsappGroup.division', 'assignees']);

        if ($userRole && $userRole->is_global_delegator) {
        } elseif ($userRole) {
            $level = $userRole->hierarchy_level;
            $query->where(function ($q) use ($userId) {
                $q->where('creator_id', $userId)
                    ->orWhereHas('assignees', fn ($q) => $q->where('user_id', $userId));
            });
        } else {
            $query->where(function ($q) use ($userId) {
                $q->where('creator_id', $userId)
                    ->orWhereHas('assignees', fn ($q) => $q->where('user_id', $userId));
            });
        }

        $recordsTotal = $query->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('title', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhereHas('creator', function ($q) use ($searchValue) {
                        $q->where('username', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('category', function ($q) use ($searchValue) {
                        $q->where('name', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('assignees', function ($q) use ($searchValue) {
                        $q->where('username', 'like', "%{$searchValue}%");
                    });
            });
        }

        $statusFilter = $request->input('status');
        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $categoryFilter = $request->input('category_id');
        if ($categoryFilter) {
            $query->where('category_id', $categoryFilter);
        }

        $dueDateFrom = $request->input('due_date_from');
        if ($dueDateFrom) {
            $query->where('due_date', '>=', $dueDateFrom);
        }

        $dueDateTo = $request->input('due_date_to');
        if ($dueDateTo) {
            $query->where('due_date', '<=', $dueDateTo.' 23:59:59');
        }

        $recordsFiltered = $query->count();

        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'desc');

        $columnOrderMap = [
            1 => 'title',
            2 => 'category_id',
            3 => 'status',
            5 => 'due_date',
        ];

        if (isset($columnOrderMap[$orderColumnIndex])) {
            $query->orderBy($columnOrderMap[$orderColumnIndex], $orderDirection);
        }
        $query->orderBy('id', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $tasks = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($tasks as $i => $task) {
            $assigneeNames = $task->assignees->pluck('username')->join(', ');

            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $task->id,
                'creator_id' => $task->creator_id,
                'title' => $task->title,
                'category_name' => $task->category?->name ?? '—',
                'status_label' => $this->renderStatusBadge($task->status),
                'creator_name' => $task->creator?->username ?? '—',
                'assignees' => $assigneeNames ?: '—',
                'time' => $task->time ?? '',
                'due_date' => $task->due_date?->format('d M Y') ?? '—',
                'due_date_raw' => $task->due_date?->format('Y-m-d') ?? '',
                'is_overdue' => $task->due_date && $task->due_date->endOfDay()->isPast() && ! in_array($task->status, ['done']),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function renderStatusBadge(string $status): string
    {
        return match ($status) {
            'todo' => '<span class="status-badge status-pending">To Do</span>',
            'in_progress' => '<span class="status-badge" style="background:var(--info-soft);color:#1e40af;">In Progress</span>',
            'waiting_approval' => '<span class="status-badge" style="background:#fef3c7;color:#92400e;">Waiting Approval</span>',
            'done' => '<span class="status-badge status-active">Done</span>',
            default => '<span class="status-badge">'.$status.'</span>',
        };
    }

    public function fetchAssignees(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $user = User::with(['hierarchyRole', 'division'])->find($userId);
        $userDivision = $user->division;
        $userRole = $user->hierarchyRole;
        $query = User::where('id', '!=', $userId);

        if ($userRole && ! $userRole->is_global_delegator) {

            $query->where(function ($q) use ($userDivision) {
                if ($userDivision) {
                    $q->where('division_id', $userDivision->id);
                }
            });

            $query->whereHas('hierarchyRole', function ($q) use ($userRole) {
                $q->where('hierarchy_level', '>', $userRole->hierarchy_level);
            });
        }

        if ($q = $request->get('q')) {
            $query->where(function ($qry) use ($q) {
                $qry->where('username', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $results = $query->with('hierarchyRole')->limit(30)->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'text' => $u->username.($u->hierarchyRole ? ' ('.$u->hierarchyRole->role_name.')' : ''),
            ]);

        return response()->json(['results' => $results]);
    }

    public function fetchWhatsAppGroups(Request $request): JsonResponse
    {
        $query = WhatsAppGroup::with('division')->where('status', 'Active');

        if ($q = $request->get('q')) {
            $query->where(function ($qry) use ($q) {
                $qry->where('group_name', 'like', "%{$q}%")
                    ->orWhereHas('division', fn ($d) => $d->where('division_name', 'like', "%{$q}%"));
            });
        }

        $results = $query->limit(30)->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'text' => $g->group_name.' ('.$g->division?->division_name.')',
            ]);

        return response()->json(['results' => $results]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:task_categories,id',
            'whatsapp_group_id' => 'nullable|exists:whatsapp_groups,id',
            'due_date' => 'required|date',
            'time' => 'nullable|date_format:H:i',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
            'alert_type' => 'required|in:none,email,whatsapp,both',
            'alert_target' => 'required|in:personal,group,both',
            'alert_time' => 'nullable|date',
        ]);

        $validated['creator_id'] = Auth::id();
        $validated['status'] = 'todo';

        $assigneeIds = $request->input('assignees', []);

        if (empty($assigneeIds)) {
            $assigneeIds = [Auth::id()];
        }

        $otherAssignees = array_values(array_diff($assigneeIds, [Auth::id()]));
        $validated['requires_approval'] = ! empty($otherAssignees);

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

        Log::record('create_task', "Task #{$task->id}: {$task->title}", 'MOD_TASK_PLANNER', $task);

        return response()->json([
            'success' => true,
            'message' => 'Task berhasil dibuat.',
        ]);
    }

    public function edit($id)
    {
        $task = Task::with(['assignees', 'category', 'whatsappGroup'])->findOrFail($id);
        $whatsappGroups = WhatsAppGroup::with('division')->where('status', 'Active')->get();
        $categories = TaskCategory::all();
        $users = User::all();
        $statuses = ['todo', 'in_progress', 'waiting_approval', 'done'];

        return view('task-planner.edit', compact(
            'task', 'whatsappGroups', 'categories', 'users', 'statuses'
        ));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $task = Task::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:task_categories,id',
            'whatsapp_group_id' => 'nullable|exists:whatsapp_groups,id',
            'due_date' => 'required|date',
            'time' => 'nullable|date_format:H:i',
            'status' => 'required|in:todo,in_progress,waiting_approval,done',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
            'alert_type' => 'required|in:none,email,whatsapp,both',
            'alert_target' => 'required|in:personal,group,both',
            'alert_time' => 'nullable|date',
        ]);

        $userId = Auth::id();
        $isCreator = $task->creator_id === $userId;
        $newStatus = $validated['status'];

        if ($isCreator && $task->status === 'waiting_approval') {
            $validated['status'] = 'done';
        } elseif ($task->requires_approval && ! $isCreator && $newStatus === 'done') {
            $validated['status'] = 'waiting_approval';
        }

        $assigneeIds = $request->input('assignees', []);
        if (empty($assigneeIds)) {
            $assigneeIds = [$task->creator_id];
        }

        $otherAssignees = array_values(array_diff($assigneeIds, [$task->creator_id]));
        $validated['requires_approval'] = ! empty($otherAssignees);

        $task->update($validated);
        $task->assignees()->sync($assigneeIds);

        Log::record('update_task', "Task #{$task->id}: {$task->title}", 'MOD_TASK_PLANNER', $task);

        return response()->json([
            'success' => true,
            'message' => 'Task berhasil diupdate.',
        ]);
    }

    public function show($id)
    {
        $task = Task::with([
            'creator.hierarchyRole',
            'category',
            'whatsappGroup.division',
            'assignees.hierarchyRole',
        ])->findOrFail($id);

        return view('task-planner.show', compact('task'));
    }

    public function approve($id): JsonResponse
    {
        $task = Task::findOrFail($id);

        if ($task->creator_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Hanya creator yang bisa approve.'], 403);
        }

        if ($task->status !== 'waiting_approval') {
            return response()->json(['success' => false, 'message' => 'Task tidak dalam status waiting approval.'], 422);
        }

        $task->update(['status' => 'done']);

        Log::record('approve_task', "Task #{$task->id}: {$task->title} disetujui", 'MOD_TASK_PLANNER', $task);

        return response()->json(['success' => true, 'message' => 'Task disetujui.']);
    }

    public function reject($id): JsonResponse
    {
        $task = Task::findOrFail($id);

        if ($task->creator_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Hanya creator yang bisa reject.'], 403);
        }

        if ($task->status !== 'waiting_approval') {
            return response()->json(['success' => false, 'message' => 'Task tidak dalam status waiting approval.'], 422);
        }

        $task->update(['status' => 'in_progress']);

        Log::record('reject_task', "Task #{$task->id}: {$task->title} ditolak", 'MOD_TASK_PLANNER', $task);

        return response()->json(['success' => true, 'message' => 'Task ditolak, status kembali ke In Progress.']);
    }

    public function transition(Request $request, $id): JsonResponse
    {
        $task = Task::findOrFail($id);


        $validated = $request->validate([
            'status' => 'required|in:todo,in_progress,done',
        ]);


        // jika task category = "visit" maka wajib record google map location saat transition status done
        $taskCategory = $task->category;
        if ($taskCategory && strtolower($taskCategory->name) === 'visit' && $validated['status'] === 'done') {
            $cekLocation = TaskVisit::where('task_id', $task->id)->where('user_id', Auth::id())->first();
            if (! $cekLocation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task kategori Visit harus record lokasi sebelum menandai selesai.',
                ], 422);
            }
        }



        $newStatus = $validated['status'];
        $oldStatus = $task->status;
        $isCreator = $task->creator_id === Auth::id();

        $statusLabels = [
            'todo' => 'To Do',
            'in_progress' => 'In Progress',
            'waiting_approval' => 'Waiting Approval',
            'done' => 'Done',
        ];

        if ($task->requires_approval && ! $isCreator && $newStatus === 'done') {
            $task->update(['status' => 'waiting_approval']);

            $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
            Log::record('transition_task', "Task #{$task->id}: {$oldLabel} → Waiting Approval", 'MOD_TASK_PLANNER', $task);

            return response()->json([
                'success' => true,
                'message' => 'Task dikirim ke creator untuk approval.',
            ]);
        }

        $task->update(['status' => $newStatus]);


        // cek apakah task terikat dengan lead
        $lead = $task->lead;
        // jika status lead adalah "New", dan Task diubah menjadi "done", maka ubah status lead menjadi "approach"
        if ($lead && $oldStatus !== 'done' && $newStatus === 'done' && $lead->lead_status === 'New') {
            $lead->update(['lead_status' => 'Approach']);
        }

        // jika status lead adalah "Unqualified", dan Task diubah menjadi "done", maka ubah status lead menjadi "Qualified"
        // if ($lead && $oldStatus !== 'done' && $newStatus === 'done' && $lead->lead_status === 'Unqualified') {
        //     $lead->update(['lead_status' => 'Qualified']);
        // }

        $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
        $newLabel = $statusLabels[$newStatus] ?? $newStatus;
        Log::record('transition_task', "Task #{$task->id}: {$oldLabel} → {$newLabel}", 'MOD_TASK_PLANNER', $task);

        return response()->json([
            'success' => true,
            'message' => 'Status diupdate.',
        ]);
    }

    public function activities($id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $activities = $task->activities()->with(['user', 'attachments', 'replyTo.user', 'replies.user', 'replies.attachments'])->whereNull('reply_to_id')->orderBy('created_at', 'asc')->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'user_id' => $a->user_id,
                'username' => $a->user->username,
                'initials' => strtoupper(substr($a->user->username, 0, 2)),
                'content' => $a->content,
                'attachments' => $a->attachments->map(fn ($att) => [
                    'url' => $att->attachment_url,
                    'type' => $att->attachment_type,
                    'name' => $att->attachment_name,
                ])->toArray(),
                'reply_to' => $a->reply_to_id ? [
                    'id' => $a->replyTo->id,
                    'username' => $a->replyTo->user?->username,
                    'content' => Str::limit($a->replyTo->content ?? '', 120),
                ] : null,
                'replies' => $a->replies->map(fn ($r) => [
                    'id' => $r->id,
                    'user_id' => $r->user_id,
                    'username' => $r->user->username,
                    'content' => $r->content,
                    'created_at' => $r->created_at->toIso8601String(),
                    'attachments' => $r->attachments->map(fn ($att) => [
                        'url' => $att->attachment_url,
                        'type' => $att->attachment_type,
                        'name' => $att->attachment_name,
                    ])->toArray(),
                ])->toArray(),
                'created_at' => $a->created_at->toIso8601String(),
                'time' => $a->created_at->diffForHumans(),
                'timestamp' => $a->created_at->format('d M Y H:i'),
            ]);

        return response()->json(['data' => $activities]);
    }

    public function storeActivity(Request $request, $id): JsonResponse
    {
        $task = Task::findOrFail($id);

        $mimeRule = 'image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,application/zip,application/x-zip-compressed,application/octet-stream';
        $validated = $request->validate([
            'content' => 'nullable|string|max:2000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:10240|mimetypes:'.$mimeRule,
            'reply_to_id' => 'nullable|exists:task_activities,id',
        ]);

        $hasContent = ! empty($validated['content'] ?? null);
        $hasFiles = $request->hasFile('attachments');

        if (! $hasContent && ! $hasFiles) {
            return response()->json([
                'success' => false,
                'message' => 'Isi teks atau lampirkan file.',
            ], 422);
        }

        $activity = TaskActivity::create([
            'task_id' => $id,
            'user_id' => Auth::id(),
            'content' => $validated['content'] ?? null,
            'reply_to_id' => $validated['reply_to_id'] ?? null,
        ]);

        if ($hasFiles) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('task-activities', 'public');
                $mime = $file->getMimeType();
                $activity->attachments()->create([
                    'attachment_path' => $path,
                    'attachment_type' => str_starts_with($mime, 'image/') ? 'image' : 'file',
                    'attachment_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        $activity->load(['user', 'attachments']);

        // Mention notifications
        $mentionedIds = MentionParser::extractMentionedIds($activity->content ?? '');
        foreach ($mentionedIds as $uid) {
            if ((int) $uid !== Auth::id()) {
                Notification::create([
                    'user_id' => $uid,
                    'type' => 'mention',
                    'title' => 'You were mentioned by '.Auth::user()->username,
                    'body' => Str::limit($activity->content ?? '', 120),
                    'notifiable_type' => TaskActivity::class,
                    'notifiable_id' => $activity->id,
                    'data' => ['activity_id' => $activity->id, 'task_id' => $activity->task_id, 'mentioned_by' => Auth::id()],
                ]);
            }
        }

        // Notify parent activity author on reply
        if ($activity->reply_to_id) {
            $parent = TaskActivity::find($activity->reply_to_id);
            if ($parent && $parent->user_id !== Auth::id()) {
                Notification::create([
                    'user_id' => $parent->user_id,
                    'type' => 'mention',
                    'title' => 'Balasan pada aktivitas Anda',
                    'body' => Auth::user()->username.' membalas: '.Str::limit($activity->content ?? '', 80),
                    'notifiable_type' => TaskActivity::class,
                    'notifiable_id' => $parent->id,
                    'data' => ['activity_id' => $activity->id, 'task_id' => $activity->task_id, 'mentioned_by' => Auth::id()],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas ditambahkan.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $task = Task::findOrFail($id);

        Log::record('delete_task', "Task #{$task->id}: {$task->title} dihapus", 'MOD_TASK_PLANNER', $task);

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task berhasil dihapus.',
        ]);
    }

    public function export(Request $request)
    {
        $userId = Auth::id();
        $user = Auth::user()->load('hierarchyRole');
        $userRole = $user->hierarchyRole;

        $query = Task::with(['creator', 'category', 'whatsappGroup.division', 'assignees']);

        if ($userRole && $userRole->is_global_delegator) {
        } elseif ($userRole) {
            $level = $userRole->hierarchy_level;
            $query->where(function ($q) use ($userId, $level) {
                $q->where('creator_id', $userId)
                    ->orWhereHas('assignees', fn ($q) => $q->where('user_id', $userId))
                    ->orWhereHas('creator.hierarchyRole', fn ($q) => $q->where('hierarchy_level', '>', $level));
            });
        } else {
            $query->where(function ($q) use ($userId) {
                $q->where('creator_id', $userId)
                    ->orWhereHas('assignees', fn ($q) => $q->where('user_id', $userId));
            });
        }

        $statusFilter = $request->input('status');
        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $categoryFilter = $request->input('category_id');
        if ($categoryFilter) {
            $query->where('category_id', $categoryFilter);
        }

        $dueDateFrom = $request->input('due_date_from');
        if ($dueDateFrom) {
            $query->where('due_date', '>=', $dueDateFrom);
        }

        $dueDateTo = $request->input('due_date_to');
        if ($dueDateTo) {
            $query->where('due_date', '<=', $dueDateTo.' 23:59:59');
        }

        $tasks = $query->orderBy('id', 'desc')->get();

        $headers = [
            'ID', 'Title', 'Description', 'Category', 'Status',
            'Creator', 'Assignees', 'WhatsApp Group', 'Due Date', 'Time',
            'Requires Approval', 'Alert Type', 'Alert Target', 'Alert Time', 'Created At',
        ];

        $service = new TaskExportService;
        $filePath = $service->export($tasks, $headers);

        return response()->download($filePath, 'tasks-export-'.date('Y-m-d').'.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function downloadTemplate()
    {
        $references = TaskImportService::getReferenceData();
        $filePath = storage_path('app/temp/task-template-'.uniqid().'.xlsx');
        $dir = dirname($filePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $generator = new TaskXlsxTemplateGenerator;
        $generator->generate($references, $filePath);

        return response()->download($filePath, 'task-import-template.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $file = $request->file('file');
        $filePath = $file->storeAs('imports', 'task-import-'.uniqid().'.'.$file->getClientOriginalExtension());

        $fullPath = Storage::path($filePath);

        try {
            $service = new TaskImportService;
            $result = $service->import($fullPath);

            return response()->json([
                'success' => $result['failed'] === 0,
                'message' => "Import selesai. {$result['success']} berhasil, {$result['failed']} gagal.",
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor: '.$e->getMessage(),
            ], 422);
        } finally {
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    public function storeVisit(Request $request, $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $isCreator = $task->creator_id === Auth::id();
        $isAssignee = $task->assignees->contains('id', Auth::id());
        if (! $isCreator && ! $isAssignee) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:255',
        ]);

        $visit = $task->visits()->create([
            'user_id' => Auth::id(),
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'address' => $validated['address'] ?? null,
        ]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => Auth::id(),
            'content' => '📍 Recorded a visit (lat: '.$validated['latitude'].', lng: '.$validated['longitude'].')',
        ]);

        // Notify creator and other assignees about the visit
        $visitorId = Auth::id();
        $notifiedIds = [$visitorId];
        $task->load(['creator', 'assignees']);

        if ($task->creator_id !== $visitorId) {
            Notification::create([
                'user_id' => $task->creator_id,
                'type' => 'visit_recorded',
                'title' => "Kunjungan: {$task->title}",
                'body' => Auth::user()->username.' merekam kunjungan',
                'notifiable_type' => Task::class,
                'notifiable_id' => $task->id,
                'data' => ['task_id' => $task->id, 'visitor_id' => $visitorId],
            ]);
            $notifiedIds[] = $task->creator_id;
        }

        foreach ($task->assignees as $assignee) {
            if (in_array($assignee->id, $notifiedIds)) {
                continue;
            }
            Notification::create([
                'user_id' => $assignee->id,
                'type' => 'visit_recorded',
                'title' => "Kunjungan: {$task->title}",
                'body' => Auth::user()->username.' merekam kunjungan',
                'notifiable_type' => Task::class,
                'notifiable_id' => $task->id,
                'data' => ['task_id' => $task->id, 'visitor_id' => $visitorId],
            ]);
        }

        $visit->load('user');

        return response()->json(['success' => true, 'message' => 'Visit recorded.', 'visit' => $visit]);
    }

    public function visits($id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $visits = $task->visits()->with('user')->latest()->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'user_id' => $v->user_id,
                'username' => $v->user->username,
                'initials' => strtoupper(substr($v->user->username, 0, 2)),
                'latitude' => (float) $v->latitude,
                'longitude' => (float) $v->longitude,
                'address' => $v->address,
                'created_at' => $v->created_at->toIso8601String(),
                'time' => $v->created_at->diffForHumans(),
            ]);

        return response()->json($visits);
    }
}
