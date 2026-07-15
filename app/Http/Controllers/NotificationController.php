<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function count(): JsonResponse
    {
        $count = Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function index(): JsonResponse
    {
        $rawNotifications = Notification::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $grouped = $rawNotifications->groupBy('group_key');

        $taskIds = collect();
        $leadIds = collect();
        $opportunityIds = collect();

        foreach ($grouped as $groupKey => $items) {
            $first = $items->first();
            if ($first->group_type === 'task') {
                $taskIds->push($first->group_id);
            } elseif ($first->group_type === 'lead') {
                $leadIds->push($first->group_id);
            } elseif ($first->group_type === 'opportunity') {
                $opportunityIds->push($first->group_id);
            }
        }

        $taskTitles = Task::whereIn('id', $taskIds->unique()->values())->pluck('title', 'id');
        $leadTitles = Lead::whereIn('id', $leadIds->unique()->values())->pluck('lead_title', 'id');
        $opportunityTitles = Opportunity::whereIn('id', $opportunityIds->unique()->values())->pluck('opportunity_name', 'id');

        $data = $grouped->sortByDesc(function ($items) {
            return $items->max('created_at');
        })->values()->map(function ($items) use ($taskTitles, $leadTitles, $opportunityTitles) {
            $first = $items->first();

            $groupTitle = 'Unknown';
            $groupIcon = 'fa-bell';
            if ($first->group_type === 'task') {
                $groupTitle = $taskTitles->get($first->group_id, 'Task #'.$first->group_id);
                $groupIcon = 'fa-tasks';
            } elseif ($first->group_type === 'lead') {
                $groupTitle = $leadTitles->get($first->group_id, 'Lead #'.$first->group_id);
                $groupIcon = 'fa-flag';
            } elseif ($first->group_type === 'opportunity') {
                $groupTitle = $opportunityTitles->get($first->group_id, 'Opportunity #'.$first->group_id);
                $groupIcon = 'fa-bullseye';
            }

            $notifications = $items->sortByDesc('created_at')->values()->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'data' => $n->data,
                'read' => ! is_null($n->read_at),
                'time' => $n->created_at->diffForHumans(),
                'task_id' => $n->data['task_id'] ?? null,
                'lead_id' => $n->data['lead_id'] ?? null,
                'opportunity_id' => $n->data['opportunity_id'] ?? null,
                'activity_id' => $n->data['activity_id'] ?? null,
            ]);

            $unreadCount = $notifications->where('read', false)->count();

            return [
                'group_key' => $first->group_key,
                'group_type' => $first->group_type,
                'group_id' => $first->group_id,
                'group_title' => $groupTitle,
                'group_icon' => $groupIcon,
                'unread_count' => $unreadCount,
                'latest_time' => $notifications->first()['time'] ?? 'Baru saja',
                'notifications' => $notifications,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function markAsRead($id): JsonResponse
    {
        $notification = Notification::where('user_id', Auth::id())->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function all(): View
    {
        $notifications = Notification::with('user')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate(20);

        $grouped = collect();
        if ($notifications->isNotEmpty()) {
            $grouped = $notifications->groupBy('group_key');

            $taskIds = $this->extractGroupIds($grouped, 'task');
            $leadIds = $this->extractGroupIds($grouped, 'lead');
            $opportunityIds = $this->extractGroupIds($grouped, 'opportunity');

            $taskTitles = $taskIds->isNotEmpty() ? Task::whereIn('id', $taskIds->values())->pluck('title', 'id') : collect();
            $leadTitles = $leadIds->isNotEmpty() ? Lead::whereIn('id', $leadIds->values())->pluck('lead_title', 'id') : collect();
            $opportunityTitles = $opportunityIds->isNotEmpty() ? Opportunity::whereIn('id', $opportunityIds->values())->pluck('opportunity_name', 'id') : collect();
        } else {
            $taskTitles = collect();
            $leadTitles = collect();
            $opportunityTitles = collect();
        }

        return view('notifications.index', compact('notifications', 'grouped', 'taskTitles', 'leadTitles', 'opportunityTitles'));
    }

    private function extractGroupIds($grouped, string $type)
    {
        return $grouped->keys()
            ->filter(fn ($k) => str_starts_with($k, $type.'_'))
            ->map(fn ($k) => (int) substr($k, strlen($type) + 1))
            ->unique()
            ->values();
    }
}
