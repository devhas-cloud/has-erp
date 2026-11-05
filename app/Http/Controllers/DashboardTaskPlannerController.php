<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardTaskPlannerController extends Controller
{
    public function index(Request $request)
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
        ) + ['visit' => $this->visitSchedule($request)]);
    }

    /** Jumlah hari kerja (Senin–Jumat) yang ditampilkan di jadwal visit. */
    public const VISIT_WORKDAYS = 7;

    /**
     * Jadwal visit 7 hari kerja: task berkategori visit (task_categories.visit = true),
     * satu baris per assignee, dikelompokkan per divisi. Terlihat oleh semua
     * pengguna dashboard (lintas divisi). Sabtu/Minggu tidak ditampilkan;
     * task Done tetap tampil untuk memantau realisasi & check-in.
     */
    private function visitSchedule(Request $request): array
    {
        $start = $this->nextWorkday(rescue(fn () => Carbon::parse($request->input('visit_start')), now(), false)->startOfDay());
        $days = $this->workdays($start, self::VISIT_WORKDAYS);
        $first = $days[0];
        $last = end($days);
        $today = now()->startOfDay();

        $filters = [
            'division_id' => $request->integer('visit_division') ?: null,
            'category_id' => $request->integer('visit_category') ?: null,
            'status' => in_array($request->input('visit_status'), ['todo', 'in_progress', 'waiting_approval', 'done', 'late'], true)
                ? $request->input('visit_status') : null,
            'q' => trim((string) $request->input('visit_q', '')),
            'show_empty' => $request->boolean('visit_empty'),
        ];

        $visitCategories = TaskCategory::where('visit', true)->orderBy('name')->get(['id', 'name']);

        $tasks = Task::query()
            ->whereHas('category', fn ($q) => $q->where('visit', true))
            ->whereDate('due_date', '>=', $first->toDateString())
            ->whereDate('due_date', '<=', $last->toDateString())
            ->when($filters['category_id'], fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['status'] === 'late', fn ($q) => $q->where('status', '!=', 'done')->whereDate('due_date', '<', $today->toDateString()))
            ->when($filters['status'] && $filters['status'] !== 'late', fn ($q) => $q->where('status', $filters['status']))
            ->with(['category', 'assignees.division', 'accountContact.accountCompany', 'project', 'visits'])
            ->orderBy('due_date')
            ->orderByRaw('time IS NULL, time')
            ->get();

        $dayKeys = array_map(fn (Carbon $d) => $d->toDateString(), $days);
        $emptyDays = array_fill_keys($dayKeys, []);
        $matches = fn (User $u) => (! $filters['division_id'] || (int) $u->division_id === $filters['division_id'])
            && ($filters['q'] === '' || stripos($u->display_name, $filters['q']) !== false);

        $rows = [];
        $taskIds = [];
        foreach ($tasks as $task) {
            $day = $task->due_date->toDateString();
            if (! array_key_exists($day, $emptyDays)) {
                continue; // Sabtu/Minggu di dalam rentang tidak ditampilkan.
            }
            foreach ($task->assignees as $user) {
                if (! $matches($user)) {
                    continue;
                }
                $rows[$user->id] ??= ['user' => $user, 'days' => $emptyDays, 'count' => 0];
                $rows[$user->id]['days'][$day][] = [
                    'id' => $task->id,
                    'title' => $task->title,
                    'time' => $task->time ? substr($task->time, 0, 5) : null,
                    'category' => $task->category?->name,
                    'target' => $task->accountContact?->accountCompany?->account_name
                        ?? $task->accountContact?->full_name
                        ?? $task->project?->project_number,
                    'status' => $task->status,
                    'late' => $task->status !== 'done' && $task->due_date->lt($today),
                    'checked_in' => $task->visits->contains('user_id', $user->id),
                ];
                $rows[$user->id]['count']++;
                $taskIds[$task->id] = true;
            }
        }

        if ($filters['show_empty']) {
            User::with('division')->whereNotNull('division_id')
                ->when($filters['division_id'], fn ($q, $id) => $q->where('division_id', $id))
                ->whereNotIn('id', array_keys($rows))
                ->get()
                ->filter($matches)
                ->each(function (User $u) use (&$rows, $emptyDays) {
                    $rows[$u->id] = ['user' => $u, 'days' => $emptyDays, 'count' => 0];
                });
        }

        $groups = collect($rows)
            ->groupBy(fn ($r) => $r['user']->division?->division_name ?? 'Tanpa Divisi')
            ->map(fn ($items, $name) => [
                'name' => $name,
                'rows' => $items->sortBy(fn ($r) => mb_strtolower($r['user']->display_name))->values(),
                'visits' => $items->sum('count'),
            ])
            ->sortBy(fn ($g) => [$g['name'] === 'Tanpa Divisi' ? 1 : 0, mb_strtolower($g['name'])])
            ->values();

        $dayTotals = array_map(fn ($key) => collect($rows)->sum(fn ($r) => count($r['days'][$key])), $dayKeys);

        return [
            'days' => $days,
            'today' => $today,
            'groups' => $groups,
            'day_totals' => array_combine($dayKeys, $dayTotals),
            'summary' => [
                'tasks' => count($taskIds),
                'people' => collect($rows)->where('count', '>', 0)->count(),
                'divisions' => $groups->filter(fn ($g) => $g['visits'] > 0)->count(),
            ],
            'filters' => $filters,
            'categories' => $visitCategories,
            'divisions' => Division::whereIn('id', User::whereNotNull('division_id')->distinct()->pluck('division_id'))
                ->orderBy('division_name')->get(['id', 'division_name']),
            'prev_start' => $this->workdaysBack($first, self::VISIT_WORKDAYS)->toDateString(),
            'next_start' => $this->nextWorkday($last->copy()->addDay())->toDateString(),
            'is_current' => $first->equalTo($this->nextWorkday($today->copy())),
        ];
    }

    private function nextWorkday(Carbon $date): Carbon
    {
        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date;
    }

    /** @return Carbon[] */
    private function workdays(Carbon $start, int $count): array
    {
        $days = [];
        $cursor = $start->copy();
        while (count($days) < $count) {
            if (! $cursor->isWeekend()) {
                $days[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        return $days;
    }

    private function workdaysBack(Carbon $from, int $count): Carbon
    {
        $cursor = $from->copy();
        $found = 0;
        while ($found < $count) {
            $cursor->subDay();
            if (! $cursor->isWeekend()) {
                $found++;
            }
        }

        return $cursor;
    }
}
