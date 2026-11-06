<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardVisitPlannerController extends Controller
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

        return view('dashboard-visit-planner.index', compact(
            'totalTasks', 'todoCount', 'inProgressCount', 'overdueCount', 'doneCount',
            'upcomingTasks', 'categories'
        ) + ['visit' => $this->visitSchedule($request), 'visitDone' => $this->visitDone($request)]);
    }

    /** Jumlah hari kerja (Senin–Jumat) yang ditampilkan di jadwal visit. */
    public const VISIT_WORKDAYS = 7;

    /**
     * Jadwal visit 7 hari kerja: task berkategori visit (task_categories.visit = true),
     * satu baris per assignee salesperson (users.salesperson = true), dikelompokkan per divisi. Terlihat oleh semua
     * pengguna dashboard (lintas divisi). Sabtu/Minggu tidak ditampilkan;
     * task Done tetap tampil untuk memantau realisasi & check-in.
     */
    private function visitSchedule(Request $request): array
    {
        $start = $this->nextWorkday(($request->filled('visit_start')
            ? rescue(fn () => Carbon::parse($request->input('visit_start')), now(), false)
            : now())->startOfDay());
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
        ];

        $visitCategories = TaskCategory::where('visit', true)->orderBy('name')->get(['id', 'name']);

        $tasks = Task::query()
            ->whereHas('category', fn ($q) => $q->where('visit', true))
            ->whereHas('assignees', fn ($q) => $q->where('salesperson', true))
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
        $matches = fn (User $u) => $u->salesperson
            && (! $filters['division_id'] || (int) $u->division_id === $filters['division_id'])
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
            'divisions' => Division::whereIn('id', User::where('salesperson', true)->whereNotNull('division_id')->distinct()->pluck('division_id'))
                ->orderBy('division_name')->get(['id', 'division_name']),
            'prev_start' => $this->workdaysBack($first, self::VISIT_WORKDAYS)->toDateString(),
            'next_start' => $this->nextWorkday($last->copy()->addDay())->toDateString(),
            'is_current' => $first->equalTo($this->nextWorkday($today->copy())),
        ];
    }

    /**
     * Rekap visit selesai (task kategori visit berstatus Done) per periode jatuh tempo,
     * hanya task yang punya assignee salesperson; per orang/divisi hanya salesperson.
     * Default Senin–Jumat minggu ini; bisa per kuartal atau rentang tanggal bebas.
     */
    private function visitDone(Request $request): array
    {
        $period = in_array($request->input('done_period'), ['week', 'quarter', 'custom'], true) ? $request->input('done_period') : 'week';
        $year = (int) $request->input('done_year', now()->year);
        $year = $year >= 2000 && $year <= 2100 ? $year : now()->year;
        $quarter = (int) $request->input('done_quarter', now()->quarter);
        $quarter = $quarter >= 1 && $quarter <= 4 ? $quarter : now()->quarter;

        $weekStart = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        [$start, $end] = [$weekStart, $weekStart->copy()->addDays(4)];

        if ($period === 'quarter') {
            $start = Carbon::create($year, ($quarter - 1) * 3 + 1, 1)->startOfDay();
            $end = $start->copy()->addMonthsNoOverflow(3)->subDay();
        } elseif ($period === 'custom') {
            $parse = fn (string $key) => $request->filled($key)
                ? rescue(fn () => Carbon::parse($request->input($key))->startOfDay(), null, false)
                : null;
            $from = $parse('done_start');
            $to = $parse('done_end');
            if ($from && $to) {
                [$start, $end] = $from->lte($to) ? [$from, $to] : [$to, $from];
            } else {
                $period = 'week';
            }
        }

        $inRange = Task::query()
            ->whereHas('category', fn ($q) => $q->where('visit', true))
            ->whereHas('assignees', fn ($q) => $q->where('salesperson', true))
            ->whereDate('due_date', '>=', $start->toDateString())
            ->whereDate('due_date', '<=', $end->toDateString());

        $tasks = (clone $inRange)->with(['assignees', 'visits'])->get();
        $planned = $tasks->count();
        $done = $tasks->where('status', 'done');

        // Semua salesperson tampil (termasuk 0 visit) agar yang belum visit ikut terpantau.
        $people = User::with('division')->where('salesperson', true)->get()
            ->mapWithKeys(fn (User $u) => [$u->id => [
                'user' => $u,
                'division' => $u->division?->division_name ?? 'Tanpa Divisi',
                'done' => 0,
                'planned' => 0,
                'checked_in' => 0,
            ]])->all();

        foreach ($tasks as $task) {
            foreach ($task->assignees as $user) {
                if (! isset($people[$user->id])) {
                    continue;
                }
                $people[$user->id]['planned']++;
                if ($task->status === 'done') {
                    $people[$user->id]['done']++;
                    if ($task->visits->contains('user_id', $user->id)) {
                        $people[$user->id]['checked_in']++;
                    }
                }
            }
        }

        $total = $done->count();

        return [
            'period' => $period,
            'year' => $year,
            'quarter' => $quarter,
            'start' => $start,
            'end' => $end,
            'total' => $total,
            'planned' => $planned,
            'rate' => $planned > 0 ? (int) round($total / $planned * 100) : null,
            'checked_in' => $done->filter(fn (Task $t) => $t->visits->whereIn('user_id', $t->assignees->where('salesperson', true)->pluck('id'))->isNotEmpty())->count(),
            'people' => collect($people)->sortBy([
                ['done', 'desc'],
                ['planned', 'desc'],
                fn ($a, $b) => strcasecmp($a['user']->display_name, $b['user']->display_name),
            ])->values(),
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
