<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskCategory;
use Illuminate\Support\Facades\Auth;

class DashboardTaskPlannerController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $user = Auth::user()->load('hierarchyRole');
        $userRole = $user->hierarchyRole;

        $query = Task::with(['creator', 'category', 'assignees']);

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

        $overdueSub = (clone $query)->whereNotIn('status', ['done'])
            ->where('due_date', '<', now()->endOfDay());

        $totalTasks = (clone $query)->count();
        $todoCount = (clone $query)->where('status', 'todo')->count();
        $inProgressCount = (clone $query)->where('status', 'in_progress')->count();
        $doneCount = (clone $query)->where('status', 'done')->count();
        $overdueCount = $overdueSub->count();

        $upcomingTasks = (clone $query)
            ->whereNotIn('status', ['done'])
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        $categories = TaskCategory::all()->map(function ($cat) use ($query) {
            $catTasks = (clone $query)->where('category_id', $cat->id);
            $total = $catTasks->count();
            $done = (clone $catTasks)->where('status', 'done')->count();

            return [
                'name' => $cat->name,
                'total' => $total,
                'done' => $done,
                'pct' => $total > 0 ? round(($done / $total) * 100) : 0,
            ];
        })->filter(fn ($c) => $c['total'] > 0)->values();

        return view('dashboard-task-planner.index', compact(
            'totalTasks', 'todoCount', 'inProgressCount', 'overdueCount', 'doneCount',
            'upcomingTasks', 'categories'
        ));
    }
}
