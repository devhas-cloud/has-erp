<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TaskPlannerController extends Controller
{
    public function index()
    {
        $divisions = Division::where('status', 'Active')->get();
        $users = User::all();

        $user = Auth::user();
        $userId = $user->id;
        $userDivisionId = $user->division_id;

        $categories = TaskCategory::where(function ($q) use ($userDivisionId) {
            $q->whereNull('division_id')
                ->orWhere('division_id', $userDivisionId);
        })->get();

        return view('task-planner.index', compact('divisions', 'users', 'categories', 'userId'));
    }

    public function data(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $user = Auth::user()->load('hierarchyRole');
        $userRole = $user->hierarchyRole;

        $query = Task::with(['creator', 'category', 'division', 'assignees']);

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
                'due_date' => $task->due_date?->format('d M Y') ?? '—',
                'due_date_raw' => $task->due_date?->format('Y-m-d') ?? '',
                'is_overdue' => $task->due_date && $task->due_date->isPast() && ! in_array($task->status, ['done']),
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
        $user = User::with('hierarchyRole')->find($userId);
        $userRole = $user->hierarchyRole;

        $query = User::where('id', '!=', $userId);

        if ($userRole && ! $userRole->is_global_delegator) {
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:task_categories,id',
            'division_id' => 'nullable|exists:divisions,id',
            'start_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:start_date',
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

        return response()->json([
            'success' => true,
            'message' => 'Task berhasil dibuat.',
        ]);
    }

    public function edit($id)
    {
        $task = Task::with(['assignees', 'category', 'division'])->findOrFail($id);
        $divisions = Division::where('status', 'Active')->get();
        $categories = TaskCategory::all();
        $users = User::all();
        $statuses = ['todo', 'in_progress', 'waiting_approval', 'done'];

        return view('task-planner.edit', compact(
            'task', 'divisions', 'categories', 'users', 'statuses'
        ));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $task = Task::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:task_categories,id',
            'division_id' => 'nullable|exists:divisions,id',
            'start_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:start_date',
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
            'division',
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

        return response()->json(['success' => true, 'message' => 'Task disetujui.']);
    }

    public function transition(Request $request, $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|in:todo,in_progress,done',
        ]);

        $newStatus = $validated['status'];
        $isCreator = $task->creator_id === Auth::id();

        if ($task->requires_approval && ! $isCreator && $newStatus === 'done') {
            $task->update(['status' => 'waiting_approval']);

            return response()->json([
                'success' => true,
                'message' => 'Task dikirim ke creator untuk approval.',
            ]);
        }

        $task->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Status diupdate.',
        ]);
    }

    public function activities($id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $activities = $task->activities()->with(['user', 'attachments', 'replyTo.user'])->orderBy('created_at', 'asc')->get()
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
            'attachments.*' => 'file|max:2048|mimetypes:'.$mimeRule,
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

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas ditambahkan.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task berhasil dihapus.',
        ]);
    }
}
