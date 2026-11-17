<?php

namespace App\Http\Controllers;

use App\Models\AccountCompany;
use App\Models\Project;
use App\Support\ProjectTracker;
use App\Support\ReceivableReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Project Tracking — monitoring 7 tahap setiap project (Project, Pembelian,
 * Terima Barang, Produksi, Packing List, Delivery Order, Invoice), dihitung
 * App\Support\ProjectTracker dari dokumen yang ada. Default: project Active.
 * Detail per project di halaman tracking sendiri (show).
 */
class ProjectTrackingController extends Controller
{
    public function index()
    {
        $rows = $this->rows(Project::STATUS_ACTIVE);

        return view('project-tracking.index', [
            'summary' => [
                'active' => $rows->count(),
                'purchasing' => $rows->where('current', 2)->count(),
                'shipping' => $rows->filter(fn ($r) => in_array($r['current'], [5, 6], true))->count(),
                'unbilled' => $rows->filter(fn ($r) => $r['finance']['billed_pct'] < 100)->count(),
                'overdue' => $rows->filter(fn ($r) => $r['due_days'] !== null && $r['due_days'] < 0 && $r['stages'][6]['status'] !== ProjectTracker::DONE)->count(),
            ],
            'companies' => AccountCompany::whereIn('id', Project::select('company_id'))->orderBy('account_name')->get(['id', 'account_name']),
            'alertDays' => (int) config('project_tracking.alert_days', 14),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $status = $request->has('status') ? (string) $request->input('status') : Project::STATUS_ACTIVE;
        $rows = $this->rows($status === '' ? null : $status, $request->integer('company') ?: null)
            ->when($request->input('stage') !== null && $request->input('stage') !== '', function (Collection $rows) use ($request) {
                $stage = $request->input('stage');

                return $rows->filter(fn ($r) => $stage === 'done' ? $r['current'] === null : (string) $r['current'] === (string) $stage);
            })
            ->when($request->boolean('problem'), fn (Collection $rows) => $rows->filter(fn ($r) => count($r['alerts']) > 0));

        return response()->json(['data' => $rows->map(fn (array $r) => [
            'id' => $r['project']->id,
            'project_number' => $r['project']->project_number,
            'project_name' => $r['project']->project_name,
            'customer' => $r['project']->company?->account_name,
            'url' => route('project-tracking.show', $r['project']->id),
            'stages' => collect($r['stages'])->map(fn ($s, $n) => [
                'n' => $n,
                'name' => ProjectTracker::STAGES[$n],
                'status' => $s['status'],
                'status_label' => $s['status_label'],
                'summary' => $s['summary'],
            ])->values(),
            'current' => $r['current'],
            'current_label' => $r['current_label'],
            'current_summary' => $r['current'] ? $r['stages'][$r['current']]['summary'] : null,
            'due_date' => $r['due_date']?->format('d/m/Y'),
            'due_days' => $r['due_days'],
            'billed_pct' => $r['finance']['billed_pct'],
            'paid_pct' => $r['finance']['paid_pct'],
            'alerts' => $r['alerts'],
        ])->values()]);
    }

    /** Tracking satu project: ringkasan biaya & penagihan, peringatan, linimasa 7 tahap. */
    public function show($id)
    {
        $project = Project::with(['company', 'quotation.opportunity.owner', 'creator', 'billingTerms.invoices'])->findOrFail($id);
        $receivable = new ReceivableReport;

        return view('project-tracking.show', [
            'project' => $project,
            'tracking' => (new ProjectTracker)->forProject($project),
            // Termin penagihan: posisi (Terkunci / Siap ditagih / Piutang / Lunas ...) & nilai.
            'terms' => $project->billingTerms->map(fn ($term) => [
                'name' => $term->name,
                'percent' => (float) $term->percent,
                'amount' => $receivable->termAmount($project, $term),
                'position' => ReceivableReport::POSITIONS[$receivable->termPosition($term)] ?? '—',
                'target' => $term->target_date,
            ]),
        ]);
    }

    private function rows(?string $status, ?int $companyId = null): Collection
    {
        $projects = Project::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderByDesc('id')
            ->get();

        return (new ProjectTracker)->track($projects)->values()
            ->sortBy(fn ($r) => [count($r['alerts']) ? 0 : 1, $r['due_days'] ?? PHP_INT_MAX])
            ->values();
    }
}
