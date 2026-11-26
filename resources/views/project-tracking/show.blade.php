@extends('layouts.app')

@section('title', 'Tracking '.$project->project_number)
@section('page-title', 'Project Tracking')

@php
    $fmt = fn ($v) => \App\Support\StockCost::format($v);
    $fin = $tracking['finance'];
    $stages = \App\Support\ProjectTracker::STAGES;
    $hints = \App\Support\ProjectTracker::STAGE_HINTS;
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '—';
    // Tanggal tahap: "12/11/2026 → 20/11/2026", "Started 12/11/2026", atau kosong.
    $range = function (array $s) {
        if ($s['start'] && $s['end']) {
            return $s['start']->isSameDay($s['end']) ? $s['start']->format('d/m/Y') : $s['start']->format('d/m/Y').' → '.$s['end']->format('d/m/Y');
        }

        return $s['start'] ? 'Started '.$s['start']->format('d/m/Y') : null;
    };
    $used = collect($tracking['stages'])->reject(fn ($s) => $s['status'] === \App\Support\ProjectTracker::NA);
    $doneCount = $used->where('status', \App\Support\ProjectTracker::DONE)->count();
@endphp

@section('styles')
<style>
    .pt-flag { display: inline-block; font-size: 11.5px; font-weight: 600; padding: 2px 9px; border-radius: 999px; margin: 1px 4px 1px 0; }
    .pt-flag.danger { background: var(--danger-soft); color: #b42318; }
    .pt-flag.warn { background: var(--warning-soft, #fef3c7); color: #92400e; }
    .pt-kpi { border: 1px solid var(--card-border); border-radius: var(--radius-sm); padding: 12px 14px; background: var(--card, #fff); height: 100%; }
    .pt-kpi .l { font-size: 12px; color: var(--text-muted); }
    .pt-kpi .v { font-size: 18px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.25; }
    .pt-kpi .s { font-size: 11.5px; color: var(--text-muted); }
    .pt-muted { font-size: 12px; color: var(--text-muted); }

    /* Alur horizontal 9 tahap */
    .pt-flow { display: grid; grid-template-columns: repeat(9, minmax(0, 1fr)); position: relative; padding: 4px 0 2px; }
    .pt-flow::before { content: ''; position: absolute; top: 19px; left: 5.5%; right: 5.5%; height: 3px; background: #e5e7eb; border-radius: 2px; }
    .pt-flow-fill { position: absolute; top: 19px; left: 5.5%; height: 3px; background: #2f7a4b; border-radius: 2px; }
    .pt-node { position: relative; text-align: center; text-decoration: none; color: var(--text-primary); padding: 0 3px; }
    .pt-node .c { width: 32px; height: 32px; margin: 3px auto 6px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; background: #fff; border: 2px solid #d1d5db; color: #6b7280; position: relative; z-index: 1; }
    .pt-node.done .c { background: #2f7a4b; border-color: #2f7a4b; color: #fff; }
    .pt-node.run .c { background: #fff7ed; border-color: #d97706; color: #b45309; }
    .pt-node.late .c { background: #fef2f2; border-color: #b42318; color: #b42318; }
    .pt-node.na .c { border-style: dashed; color: #9ca3af; }
    .pt-node.current .c { box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 25%, transparent); }
    .pt-node .nm { font-size: 12px; font-weight: 600; line-height: 1.25; }
    .pt-node .st { font-size: 11px; color: var(--text-muted); }
    .pt-node.late .st { color: #b42318; }
    .pt-node .dt { font-size: 10.5px; color: var(--text-muted); margin-top: 1px; }
    .pt-node:hover .nm { color: var(--accent); }
    @media (max-width: 991.98px) {
        .pt-flow { grid-template-columns: repeat(3, minmax(0, 1fr)); row-gap: 14px; }
        .pt-flow::before, .pt-flow-fill { display: none; }
    }

    /* Timeline vertikal */
    .pt-tl { position: relative; }
    .pt-tl-item { display: grid; grid-template-columns: 34px minmax(0, 1fr) 90px; gap: 12px; padding: 12px 6px; border-bottom: 1px solid var(--card-border); scroll-margin-top: 90px; }
    .pt-tl-item:last-child { border-bottom: 0; }
    .pt-tl-item.current { background: var(--accent-subtle); border-radius: var(--radius-sm); }
    .pt-tl-item .c { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; background: #e5e7eb; color: #6b7280; }
    .pt-tl-item.done .c { background: #2f7a4b; color: #fff; }
    .pt-tl-item.run .c { background: #d97706; color: #fff; }
    .pt-tl-item.late .c { background: #b42318; color: #fff; }
    .pt-tl-item.na .c { background: transparent; border: 1.5px dashed #cbd5e1; color: #9ca3af; }
    .pt-tl-item .t1 { font-weight: 600; font-size: 14px; }
    .pt-tl-item .t1 .badge-st { font-size: 11px; font-weight: 600; padding: 1px 8px; border-radius: 999px; margin-left: 6px; background: #f1f5f9; color: var(--text-secondary); }
    .pt-tl-item.done .badge-st { background: #dcfce7; color: #166534; }
    .pt-tl-item.run .badge-st { background: #fef3c7; color: #92400e; }
    .pt-tl-item.late .badge-st { background: #fee2e2; color: #b42318; }
    .pt-tl-item .hint { font-size: 11.5px; color: var(--text-muted); }
    .pt-tl-item .t2 { font-size: 13px; color: var(--text-secondary); margin-top: 3px; }
    .pt-tl-item .when { font-size: 12px; color: var(--text-primary); margin-top: 3px; }
    .pt-tl-item .pct { text-align: right; font-size: 13px; font-weight: 600; font-variant-numeric: tabular-nums; }
    .pt-bar { height: 5px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin-top: 4px; }
    .pt-bar span { display: block; height: 100%; background: var(--accent); }
    .pt-doc { display: inline-block; font-size: 11.5px; padding: 1px 7px; margin: 5px 4px 0 0; border-radius: 4px; background: #f1f5f9; color: var(--text-secondary); text-decoration: none; }
    .pt-doc:hover { background: var(--accent-soft); color: var(--accent); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $project->project_number }} — {{ $project->project_name }}</h1>
        <p class="page-header-sub">
            {{ $project->company?->account_name ?? '—' }}
            &middot; Current stage: <strong>{{ $tracking['current'] ? $tracking['current'].' · ' : '' }}{{ $tracking['current_label'] }}</strong>
            @if($tracking['current_since']) (since {{ $tracking['current_since']->format('d/m/Y') }}) @endif
            &middot; {{ $doneCount }} of {{ $used->count() }} stages done
            @if($tracking['due_date'])
                &middot; Due {{ $tracking['due_date']->format('d/m/Y') }}
                @if($tracking['due_days'] < 0)
                    <span style="color:#b42318">({{ abs($tracking['due_days']) }} day(s) past)</span>
                @else
                    (in {{ $tracking['due_days'] }} day(s))
                @endif
            @endif
        </p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a href="{{ route('project-tracking.index') }}" class="btn btn-secondary btn-sm">Back</a>
        <a href="{{ route('project.show', $project->id) }}" class="btn btn-outline-primary btn-sm">Open Project</a>
    </div>
</div>

@if($tracking['alerts'])
    <div class="mb-3">@foreach($tracking['alerts'] as [$level, $text])<span class="pt-flag {{ $level }}">{{ $text }}</span>@endforeach</div>
@endif

{{-- Alur proses 9 tahap --}}
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Project Journey</span>
        <span class="pt-muted">Click a stage to jump to its details</span>
    </div>
    <div class="card-body-custom">
        @php
            // Garis hijau sampai tahap selesai terakhir yang berurutan.
            $lastDone = 0;
            foreach ($tracking['stages'] as $n => $s) {
                if (in_array($s['status'], [\App\Support\ProjectTracker::DONE, \App\Support\ProjectTracker::NA], true)) { $lastDone = $n; } else { break; }
            }
        @endphp
        <div class="pt-flow">
            @if($lastDone > 1)<span class="pt-flow-fill" style="width:{{ ($lastDone - 1) / 8 * 89 }}%"></span>@endif
            @foreach($tracking['stages'] as $n => $s)
                <a href="#stage-{{ $n }}" class="pt-node {{ $s['status'] }} {{ $tracking['current'] === $n ? 'current' : '' }}" title="{{ $hints[$n] }}">
                    <div class="c">{{ $n }}</div>
                    <div class="nm">{{ $stages[$n] }}</div>
                    <div class="st">{{ $s['status_label'] }}</div>
                    @if($range($s))<div class="dt">{{ $range($s) }}</div>@endif
                </a>
            @endforeach
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span>Project Summary</span>
                {!! $project->statusBadgeHtml() !!}
            </div>
            <div class="card-body-custom">
                @php
                    $quotation = $project->quotation;
                    $opportunity = $quotation?->opportunity;
                @endphp
                <table class="info-table" style="width:100%">
                    <tr><td>Customer</td><td class="fw-semibold">{{ $project->company?->account_name ?? '—' }}</td></tr>
                    <tr><td>Opportunity</td><td>{{ $opportunity?->opportunity_name ?? '—' }}@if($opportunity?->owner) <span class="pt-muted">&middot; {{ $opportunity->owner->display_name }}</span>@endif</td></tr>
                    <tr><td>Quotation</td><td>{{ $quotation?->quotation_number ?? '—' }}</td></tr>
                    <tr><td>Customer PO</td><td>{{ $project->po_number_company ?: '—' }} <span class="pt-muted">&middot; PO date {{ $date($project->po_date) }} &middot; received {{ $date($project->po_date_received) }}</span></td></tr>
                    <tr><td>Due Date</td><td>{{ $date($project->po_due_date) }}</td></tr>
                    <tr><td>Type</td><td>{{ $project->type_label }}@if($project->is_retention) <span class="badge ms-1" style="background:#f1f5f9;color:var(--text-secondary)">Retention</span>@endif</td></tr>
                    <tr><td>Created</td><td>{{ $project->creator?->display_name ?? '—' }} <span class="pt-muted">&middot; {{ $project->created_at?->format('d/m/Y') }}</span></td></tr>
                    @if($project->project_description)
                    <tr><td>Description</td><td style="white-space:pre-line">{{ $project->project_description }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span>Billing Terms</span></div>
            <div class="card-body-custom p-2">
                @forelse($terms as $term)
                    <div class="d-flex justify-content-between align-items-start px-2 py-2" style="border-bottom:1px solid var(--card-border);font-size:13px">
                        <span>
                            <span class="fw-semibold">{{ $term['name'] }}</span> <span class="pt-muted">{{ \App\Support\StockCost::qty($term['percent']) }}%</span>
                            <div class="pt-muted" style="font-size:11.5px">{{ $term['position'] }}@if($term['target']) &middot; target {{ $date($term['target']) }}@endif</div>
                        </span>
                        <span class="text-nowrap" style="font-variant-numeric:tabular-nums">{{ $fmt($term['amount']) }}</span>
                    </div>
                @empty
                    <div class="text-center py-3 pt-muted" style="font-size:13px">No billing terms yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-lg"><div class="pt-kpi"><div class="l">Contract value (tax base)</div><div class="v">{{ $fmt($fin['contract']) }}</div></div></div>
    <div class="col-6 col-lg"><div class="pt-kpi"><div class="l">Estimated cost</div><div class="v">{{ $fin['estimate'] !== null ? $fmt($fin['estimate']) : '—' }}</div></div></div>
    <div class="col-6 col-lg"><div class="pt-kpi"><div class="l">Actual cost</div><div class="v" @if($fin['estimate'] && $fin['actual'] > $fin['estimate']) style="color:#b42318" @endif>{{ $fmt($fin['actual']) }}</div></div></div>
    <div class="col-6 col-lg"><div class="pt-kpi"><div class="l">Billed</div><div class="v">{{ $fmt($fin['billed']) }}</div><div class="s">{{ $fin['billed_pct'] }}% of contract</div></div></div>
    <div class="col-6 col-lg"><div class="pt-kpi"><div class="l">Collected</div><div class="v">{{ $fmt($fin['paid']) }}</div><div class="s">{{ $fin['paid_pct'] }}% of contract</div></div></div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Stage Timeline</span>
        <span class="pt-muted">Dates come from the documents / tasks of each stage</span>
    </div>
    <div class="card-body-custom pt-tl">
        @foreach($tracking['stages'] as $n => $s)
            <div class="pt-tl-item {{ $s['status'] }} {{ $tracking['current'] === $n ? 'current' : '' }}" id="stage-{{ $n }}">
                <div class="c">{{ $n }}</div>
                <div style="min-width:0">
                    <div class="t1">{{ $stages[$n] }}<span class="badge-st">{{ $s['status_label'] }}</span></div>
                    <div class="hint">{{ $hints[$n] }}</div>
                    @if($range($s))<div class="when">{{ $range($s) }}</div>@endif
                    <div class="t2">{{ $s['summary'] }}</div>
                    @if($s['docs'])
                        <div>@foreach($s['docs'] as [$label, $url])<a href="{{ $url }}" class="pt-doc">{{ $label }}</a>@endforeach</div>
                    @endif
                </div>
                <div class="pct">
                    @if($s['progress'] !== null)
                        {{ $s['progress'] }}%
                        <div class="pt-bar"><span style="width:{{ $s['progress'] }}%"></span></div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
