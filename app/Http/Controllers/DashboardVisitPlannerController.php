<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\VisitBaseline;
use App\Support\ModuleAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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

        $overdueSub = (clone $query)->whereNotIn('status', ['done', 'cancelled'])
            ->where('due_date', '<', now()->endOfDay());

        $totalTasks = (clone $query)->where('status', '!=', 'cancelled')->count();
        $todoCount = (clone $query)->where('status', 'todo')->count();
        $inProgressCount = (clone $query)->where('status', 'in_progress')->count();
        $doneCount = (clone $query)->where('status', 'done')->count();
        $overdueCount = $overdueSub->count();

        $upcomingTasks = (clone $query)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        $categories = TaskCategory::all()->map(function ($cat) use ($query) {
            $catTasks = (clone $query)->where('category_id', $cat->id)
                ->where('status', '!=', 'cancelled');
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
        ) + [
            'visit' => $this->visitSchedule($request),
            'visitDone' => $this->visitDone($request),
            'canSeeVisitDetail' => $this->canSeeVisitDetail(),
        ]);
    }

    public const MODULE_CODE = 'MOD_DASHBOARD_VISIT_PLANNER';

    /** Detail per salesperson di Report Visit hanya untuk pemegang hak approve modul ini (Admin selalu). */
    private function canSeeVisitDetail(): bool
    {
        return ModuleAccess::for(Auth::user())->module(self::MODULE_CODE)->canApprove();
    }

    /** Jadwal visit 7 hari kerja (filter yang sama dengan dashboard) sebagai PDF landscape. */
    public function schedulePdf(Request $request)
    {
        $visit = $this->visitSchedule($request);
        $filters = $visit['filters'];
        $activeFilters = array_values(array_filter([
            $filters['division_id'] ? 'Divisi: '.($visit['divisions']->firstWhere('id', $filters['division_id'])?->division_name ?? '—') : null,
            $filters['category_id'] ? 'Kategori: '.($visit['categories']->firstWhere('id', $filters['category_id'])?->name ?? '—') : null,
            $filters['status'] ? 'Status: '.(self::STATUS_LABELS[$filters['status']] ?? $filters['status']) : null,
            $filters['q'] !== '' ? 'Nama: '.$filters['q'] : null,
        ]));

        $first = $visit['days'][0];
        $last = end($visit['days']);

        return Pdf::loadView('dashboard-visit-planner.schedule-pdf', [
            'vs' => $visit,
            'activeFilters' => $activeFilters,
            'statusLabels' => self::STATUS_LABELS,
            'printedBy' => Auth::user()->display_name,
        ])->setPaper('a4', 'landscape')
            // Diunduh sebagai file (bukan inline) agar terbuka di semua browser / viewer.
            ->download('Jadwal-Visit-'.$first->format('Ymd').'-'.$last->format('Ymd').'.pdf');
    }

    /** Detail visit Done satu salesperson pada periode Report Visit (sama persis dengan angka card). */
    public function visitDetail(Request $request): JsonResponse
    {
        abort_unless($this->canSeeVisitDetail(), 403, 'Anda tidak punya akses melihat detail visit.');

        $userId = $request->integer('user_id');
        abort_unless(User::where('salesperson', true)->whereKey($userId)->exists(), 404, 'Salesperson tidak ditemukan.');

        $data = $this->visitDone($request, $userId);
        $person = $data['people']->firstWhere(fn ($p) => (int) $p['user']->id === $userId);

        return response()->json([
            'name' => $person['user']->display_name,
            'division' => $person['division'],
            'period' => $data['start']->format('d M Y').' – '.$data['display_end']->format('d M Y'),
            'summary' => [
                'done' => $person['done'],
                'erp' => $person['erp'],
                'baseline' => $person['baseline'],
                'planned' => $person['planned'],
                'checked_in' => $person['checked_in'],
            ],
            'visits' => $data['detail']['visits'],
            'baselines' => $data['detail']['baselines'],
        ]);
    }

    public const STATUS_LABELS = [
        'todo' => 'To Do',
        'in_progress' => 'In Progress',
        'waiting_approval' => 'Waiting Approval',
        'done' => 'Done',
        'cancelled' => 'Cancelled',
        'late' => 'Terlambat',
    ];

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
            'status' => in_array($request->input('visit_status'), ['todo', 'in_progress', 'waiting_approval', 'done', 'cancelled', 'late'], true)
                ? $request->input('visit_status') : null,
            'q' => trim((string) $request->input('visit_q', '')),
        ];

        $visitCategories = TaskCategory::where('visit', true)->orderBy('name')->get(['id', 'name']);

        $tasks = Task::query()
            ->whereHas('category', fn ($q) => $q->where('visit', true))
            ->whereHas('assignees', fn ($q) => $q->where('salesperson', true))
            ->whereDate('due_date', '>=', $first->toDateString())
            ->whereDate('due_date', '<=', $last->toDateString())
            ->when($filters['status'] !== 'cancelled', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->when($filters['category_id'], fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['status'] === 'late', fn ($q) => $q->whereNotIn('status', ['done', 'cancelled'])->whereDate('due_date', '<', $today->toDateString()))
            ->when($filters['status'] && $filters['status'] !== 'late', fn ($q) => $q->where('status', $filters['status']))
            ->with(['category', 'assignees.division', 'accountContact.accountCompany', 'accountContact.jobTitle', 'project.company', 'visits'])
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
        $weekendTaskIds = [];
        foreach ($tasks as $task) {
            $day = $task->due_date->toDateString();
            // Sabtu/Minggu di dalam rentang tidak ditampilkan di grid, tetapi tetap dihitung jumlah visitnya.
            $isWeekend = ! array_key_exists($day, $emptyDays);
            foreach ($task->assignees as $user) {
                if (! $matches($user)) {
                    continue;
                }
                $rows[$user->id] ??= ['user' => $user, 'days' => $emptyDays, 'count' => 0, 'weekend' => 0];
                $taskIds[$task->id] = true;
                $rows[$user->id]['count']++;
                if ($isWeekend) {
                    $rows[$user->id]['weekend']++;
                    $weekendTaskIds[$task->id] = true;

                    continue;
                }
                $rows[$user->id]['days'][$day][] = [
                    'id' => $task->id,
                    'title' => $task->title,
                    'time' => $task->time ? substr($task->time, 0, 5) : null,
                    'category' => $task->category?->name,
                    'target' => $task->accountContact?->accountCompany?->account_name
                        ?? $task->accountContact?->full_name
                        ?? $task->project?->project_number,
                    // Kolom export PDF: perusahaan, PIC & jabatannya (dari kontak task / company project).
                    'company' => $task->accountContact?->accountCompany?->account_name ?? $task->project?->company?->account_name,
                    'pic' => $task->accountContact?->full_name,
                    'pic_title' => $task->accountContact?->jobTitle?->title_name,
                    'status' => $task->status,
                    'late' => ! in_array($task->status, ['done', 'cancelled'], true) && $task->due_date->lt($today),
                    'checked_in' => $task->visits->contains('user_id', $user->id),
                ];
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
                'weekend' => count($weekendTaskIds),
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
    private function visitDone(Request $request, ?int $detailUserId = null): array
    {
        $period = in_array($request->input('done_period'), ['week', 'quarter', 'custom'], true) ? $request->input('done_period') : 'week';
        $year = (int) $request->input('done_year', now()->year);
        $year = $year >= 2000 && $year <= 2100 ? $year : now()->year;
        $quarter = (int) $request->input('done_quarter', now()->quarter);
        $quarter = $quarter >= 1 && $quarter <= 4 ? $quarter : now()->quarter;

        // Minggu ini: ditampilkan Senin–Jumat, tetapi visit Sabtu/Minggu tetap ikut dihitung.
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        [$start, $end] = [$weekStart, $weekStart->copy()->addDays(6)];

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

        $tasks = (clone $inRange)->with(array_merge(['assignees', 'visits'], $detailUserId
            ? ['category', 'accountContact.accountCompany', 'project']
            : []))->orderBy('due_date')->orderByRaw('time IS NULL, time')->get();
        // Rincian untuk satu salesperson (modal detail card); baris = visit yang membentuk angka card.
        $detail = ['visits' => [], 'baselines' => []];

        // Semua salesperson tampil (termasuk 0 visit) agar yang belum visit ikut terpantau.
        $people = User::with('division')->where('salesperson', true)->get()
            ->mapWithKeys(fn (User $u) => [$u->id => [
                'user' => $u,
                'division' => $u->division?->division_name ?? 'Tanpa Divisi',
                'done' => 0,      // total = erp + baseline
                'erp' => 0,       // visit Done dari ERP (di luar jendela baseline)
                'baseline' => 0,  // angka awal dari SF
                'planned' => 0,
                'checked_in' => 0,
            ]])->all();

        // Baseline visit dari SF menggantikan data ERP pada jendela tanggalnya
        // (angka SF sudah mencakup visit di rentang itu). Baseline hanya dipakai
        // bila periode laporan mencakup seluruh jendela baseline — baseline tidak
        // bisa dipecah per hari, sehingga periode yang hanya menyinggung sebagian
        // (mis. tampilan mingguan) tetap memakai data ERP apa adanya.
        $baselines = VisitBaseline::query()
            ->whereDate('start_date', '>=', $start->toDateString())
            ->whereDate('end_date', '<=', $end->toDateString())
            ->get();

        $windows = [];
        foreach ($baselines as $b) {
            $uid = (int) $b->user_id;
            if (! isset($people[$uid])) {
                continue;
            }
            $people[$uid]['baseline'] += (int) $b->visits;
            if ($uid === $detailUserId) {
                $detail['baselines'][] = [
                    'period' => $b->start_date->format('d M Y').' – '.$b->end_date->format('d M Y'),
                    'visits' => (int) $b->visits,
                    'note' => $b->note,
                ];
            }
            $windows[$uid][] = [$b->start_date->toDateString(), $b->end_date->toDateString()];
        }

        $coveredByBaseline = function (int $uid, string $day) use ($windows): bool {
            foreach ($windows[$uid] ?? [] as [$from, $to]) {
                if ($day >= $from && $day <= $to) {
                    return true;
                }
            }

            return false;
        };

        // Ringkasan dihitung per task unik; per orang dihitung per assignee.
        $planned = $erpDone = $checkedIn = 0;

        foreach ($tasks as $task) {
            if ($task->status === 'cancelled') {
                continue;
            }
            $day = $task->due_date->toDateString();
            $isDone = $task->status === 'done';
            $counted = $taskCheckedIn = false;

            foreach ($task->assignees as $user) {
                if (! isset($people[$user->id]) || $coveredByBaseline($user->id, $day)) {
                    continue;
                }
                $counted = true;
                $people[$user->id]['planned']++;
                if ($isDone) {
                    $people[$user->id]['erp']++;
                    $checkIn = $task->visits->where('user_id', $user->id)->sortBy('id')->first();
                    if ($checkIn) {
                        $people[$user->id]['checked_in']++;
                        $taskCheckedIn = true;
                    }
                    if ($user->id === $detailUserId) {
                        $detail['visits'][] = [
                            'id' => $task->id,
                            'url' => route('task-planner.show', $task->id),
                            'date' => $task->due_date->format('d/m/Y'),
                            'day' => $task->due_date->locale('id')->isoFormat('ddd'),
                            'time' => $task->time ? substr($task->time, 0, 5) : null,
                            'category' => $task->category?->name,
                            'title' => $task->title,
                            'target' => $task->accountContact?->accountCompany?->account_name
                                ?? $task->accountContact?->full_name
                                ?? $task->project?->project_number,
                            'check_in' => $checkIn ? [
                                'at' => $checkIn->created_at?->format('d/m/Y H:i'),
                                'address' => $checkIn->address,
                                'map' => $checkIn->latitude && $checkIn->longitude
                                    ? 'https://www.google.com/maps?q='.$checkIn->latitude.','.$checkIn->longitude
                                    : null,
                            ] : null,
                        ];
                    }
                }
            }

            if ($counted) {
                $planned++;
                $erpDone += (int) $isDone;
                $checkedIn += (int) $taskCheckedIn;
            }
        }

        foreach ($people as &$p) {
            $p['done'] = $p['erp'] + $p['baseline'];
        }
        unset($p);

        $baselineTotal = array_sum(array_column($people, 'baseline'));

        return [
            'period' => $period,
            'year' => $year,
            'quarter' => $quarter,
            'start' => $start,
            'end' => $end,
            'display_end' => $period === 'week' ? $start->copy()->addDays(4) : $end,
            'total' => $erpDone + $baselineTotal,
            'erp' => $erpDone,
            'baseline' => $baselineTotal,
            'planned' => $planned,
            // Rate dari ERP murni: task Done / rencana (di luar jendela baseline).
            'rate' => $planned > 0 ? (int) round($erpDone / $planned * 100) : null,
            'checked_in' => $checkedIn,
            'detail' => $detail,
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
