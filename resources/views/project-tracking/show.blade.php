@extends('layouts.app')

@section('title', 'Tracking '.$project->project_number)
@section('page-title', 'Project Tracking')

@php
    $fmt = fn ($v) => \App\Support\StockCost::format($v);
    $fin = $tracking['finance'];
    $stages = \App\Support\ProjectTracker::STAGES;
@endphp

@section('styles')
<style>
    .pt-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: #d1d5db; vertical-align: middle; flex-shrink: 0; }
    .pt-dot.done { background: #2f7a4b; }
    .pt-dot.run { background: #d97706; }
    .pt-dot.late { background: #b42318; }
    .pt-dot.na { background: transparent; border: 1.5px dashed #9ca3af; }
    .pt-flag { display: inline-block; font-size: 11.5px; font-weight: 600; padding: 2px 9px; border-radius: 999px; margin: 1px 4px 1px 0; }
    .pt-flag.danger { background: var(--danger-soft); color: #b42318; }
    .pt-flag.warn { background: var(--warning-soft, #fef3c7); color: #92400e; }
    .pt-kpi { border: 1px solid var(--card-border); border-radius: var(--radius-sm); padding: 12px 14px; background: var(--card, #fff); height: 100%; }
    .pt-kpi .l { font-size: 12px; color: var(--text-muted); }
    .pt-kpi .v { font-size: 18px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.25; }
    .pt-kpi .s { font-size: 11.5px; color: var(--text-muted); }
    .pt-rail { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; }
    @media (max-width: 767.98px) { .pt-rail { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .pt-rail a { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid var(--card-border); border-radius: var(--radius-sm); text-decoration: none; color: var(--text-primary); font-size: 12.5px; background: var(--card, #fff); }
    .pt-rail a.current { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent) inset; background: var(--accent-subtle); }
    .pt-rail .n { font-size: 11px; color: var(--text-muted); }
    .pt-step { display: grid; grid-template-columns: 22px minmax(0, 1fr) 70px; gap: 12px; padding: 12px 6px; border-bottom: 1px solid var(--card-border); scroll-margin-top: 90px; }
    .pt-step:last-child { border-bottom: 0; }
    .pt-step .t1 { font-weight: 600; font-size: 14px; }
    .pt-step .t1 .st { font-weight: 400; font-size: 12px; color: var(--text-muted); }
    .pt-step .t2 { font-size: 13px; color: var(--text-secondary); margin-top: 2px; }
    .pt-step .pct { text-align: right; font-size: 13px; font-weight: 600; font-variant-numeric: tabular-nums; }
    .pt-bar { height: 5px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin-top: 4px; }
    .pt-bar span { display: block; height: 100%; background: var(--accent); }
    .pt-doc { display: inline-block; font-size: 11.5px; padding: 1px 7px; margin: 4px 4px 0 0; border-radius: 4px; background: #f1f5f9; color: var(--text-secondary); text-decoration: none; }
    .pt-doc:hover { background: var(--accent-soft); color: var(--accent); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $project->project_number }} — {{ $project->project_name }}</h1>
        <p class="page-header-sub">
            {{ $project->company?->account_name ?? '—' }}
            &middot; Tahap saat ini: <strong>{{ $tracking['current_label'] }}</strong>
            @if($tracking['due_date'])
                &middot; Jatuh tempo {{ $tracking['due_date']->format('d/m/Y') }}
                @if($tracking['due_days'] < 0)
                    <span style="color:#b42318">(lewat {{ abs($tracking['due_days']) }} hari)</span>
                @else
                    ({{ $tracking['due_days'] }} hari lagi)
                @endif
            @endif
        </p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a href="{{ route('project-tracking.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
        <a href="{{ route('project.show', $project->id) }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-folder-open me-1"></i> Buka Project</a>
    </div>
</div>

@if($tracking['alerts'])
    <div class="mb-3">@foreach($tracking['alerts'] as [$level, $text])<span class="pt-flag {{ $level }}"><i class="fa fa-triangle-exclamation me-1"></i>{{ $text }}</span>@endforeach</div>
@endif

@php
    $quotation = $project->quotation;
    $opportunity = $quotation?->opportunity;
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '—';
@endphp
<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Ringkasan Project</span>
                {!! $project->statusBadgeHtml() !!}
            </div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Customer</td><td class="fw-semibold">{{ $project->company?->account_name ?? '—' }}</td></tr>
                    <tr><td>Opportunity</td><td>{{ $opportunity?->opportunity_name ?? '—' }}@if($opportunity?->owner) <span style="color:var(--text-muted)">&middot; {{ $opportunity->owner->display_name }}</span>@endif</td></tr>
                    <tr><td>Quotation</td><td>{{ $quotation?->quotation_number ?? '—' }}</td></tr>
                    <tr><td>PO Customer</td><td>{{ $project->po_number_company ?: '—' }} <span style="color:var(--text-muted)">&middot; tgl PO {{ $date($project->po_date) }} &middot; diterima {{ $date($project->po_date_received) }}</span></td></tr>
                    <tr><td>Jatuh Tempo</td><td>{{ $date($project->po_due_date) }}</td></tr>
                    <tr><td>Jenis</td><td>{{ $project->type_label }}@if($project->is_retention) <span class="badge ms-1" style="background:#f1f5f9;color:var(--text-secondary)">Retensi</span>@endif</td></tr>
                    <tr><td>Dibuat</td><td>{{ $project->creator?->display_name ?? '—' }} <span style="color:var(--text-muted)">&middot; {{ $project->created_at?->format('d/m/Y') }}</span></td></tr>
                    @if($project->project_description)
                    <tr><td>Deskripsi</td><td style="white-space:pre-line">{{ $project->project_description }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-file-invoice-dollar me-2" style="color:var(--accent)"></i>Termin Penagihan</span></div>
            <div class="card-body-custom p-2">
                @forelse($terms as $term)
                    <div class="d-flex justify-content-between align-items-start px-2 py-2" style="border-bottom:1px solid var(--card-border);font-size:13px">
                        <span>
                            <span class="fw-semibold">{{ $term['name'] }}</span> <span style="color:var(--text-muted)">{{ \App\Support\StockCost::qty($term['percent']) }}%</span>
                            <div style="font-size:11.5px;color:var(--text-muted)">{{ $term['position'] }}@if($term['target']) &middot; target {{ $date($term['target']) }}@endif</div>
                        </span>
                        <span class="text-nowrap" style="font-variant-numeric:tabular-nums">{{ $fmt($term['amount']) }}</span>
                    </div>
                @empty
                    <div class="text-center py-3" style="color:var(--text-muted);font-size:13px">Belum ada termin penagihan.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-lg"><div class="pt-kpi"><div class="l">Nilai kontrak (DPP)</div><div class="v">{{ $fmt($fin['contract']) }}</div></div></div>
    <div class="col-6 col-lg"><div class="pt-kpi"><div class="l">Estimasi biaya</div><div class="v">{{ $fin['estimate'] !== null ? $fmt($fin['estimate']) : '—' }}</div></div></div>
    <div class="col-6 col-lg"><div class="pt-kpi"><div class="l">Biaya aktual</div><div class="v" @if($fin['estimate'] && $fin['actual'] > $fin['estimate']) style="color:#b42318" @endif>{{ $fmt($fin['actual']) }}</div></div></div>
    <div class="col-6 col-lg"><div class="pt-kpi"><div class="l">Ditagih</div><div class="v">{{ $fmt($fin['billed']) }}</div><div class="s">{{ $fin['billed_pct'] }}% dari kontrak</div></div></div>
    <div class="col-6 col-lg"><div class="pt-kpi"><div class="l">Diterima</div><div class="v">{{ $fmt($fin['paid']) }}</div><div class="s">{{ $fin['paid_pct'] }}% dari kontrak</div></div></div>
</div>

<div class="pt-rail mb-3">
    @foreach($tracking['stages'] as $n => $s)
        <a href="#tahap-{{ $n }}" class="{{ $tracking['current'] === $n ? 'current' : '' }}" title="{{ $s['status_label'] }}">
            <i class="pt-dot {{ $s['status'] }}"></i>
            <span><span class="n">{{ $n }}</span> {{ $stages[$n] }}</span>
        </a>
    @endforeach
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-route me-2" style="color:var(--accent)"></i>Linimasa Tahap</span>
        <span style="font-size:12px;color:var(--text-muted)">
            <i class="pt-dot done"></i> Selesai &nbsp; <i class="pt-dot run"></i> Berjalan &nbsp; <i class="pt-dot late"></i> Terlambat &nbsp; <i class="pt-dot"></i> Belum mulai &nbsp; <i class="pt-dot na"></i> Tidak dipakai
        </span>
    </div>
    <div class="card-body-custom">
        @foreach($tracking['stages'] as $n => $s)
            <div class="pt-step" id="tahap-{{ $n }}">
                <i class="pt-dot {{ $s['status'] }}" style="margin-top:4px" title="{{ $s['status_label'] }}"></i>
                <div>
                    <div class="t1">{{ $n }} · {{ $stages[$n] }} <span class="st">· {{ $s['status_label'] }}</span></div>
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
