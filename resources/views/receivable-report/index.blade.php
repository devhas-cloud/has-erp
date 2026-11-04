@extends('layouts.app')

@section('title', 'Laporan Piutang')
@section('page-title', 'Laporan Piutang')

@php
    $money = fn ($v) => \App\Support\Number::money((float) $v);
    $positionColors = ['unscheduled' => '#cbd5e1', 'locked' => '#94a3b8', 'ready' => '#0ea5e9', 'in_process' => '#8b5cf6', 'receivable' => '#f59e0b', 'paid' => '#10b981'];
@endphp

@section('styles')
<style>
    .rr-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
    @media (max-width: 991.98px) { .rr-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .rr-kpi { background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); padding: 14px 16px; }
    .rr-kpi .lbl { font-size: 12px; color: var(--text-muted); }
    .rr-kpi .val { font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; margin-top: 2px; }
    .rr-kpi.danger .val { color: #b91c1c; }
    .rr-kpi.warn .val { color: #b45309; }
    .rr-kpi.info .val { color: #0369a1; }
    .rr-tabs { display: flex; gap: 4px; flex-wrap: wrap; border-bottom: 1px solid var(--card-border); margin-bottom: 14px; }
    .rr-tabs a { padding: 8px 14px; font-size: 13px; font-weight: 500; color: var(--text-muted); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; }
    .rr-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
    .rr-tabs a:hover { color: var(--text-primary); }
    .rr-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .rr-muted { font-size: 12px; color: var(--text-muted); }
    .rr-bar { display: flex; height: 6px; border-radius: 99px; overflow: hidden; background: #eef2f7; min-width: 120px; }
    .rr-bar span { display: block; height: 100%; }
    .rr-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; color: var(--text-muted); margin-bottom: 10px; }
    .rr-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 4px; vertical-align: -1px; }
    .rr-group-row td { background: var(--accent-subtle) !important; font-weight: 600; }
    .rr-group-row.overdue td { background: #fef2f2 !important; color: #7f1d1d; }
    .rr-group-row.late td { background: #fffbeb !important; color: #92400e; }
    .rr-tag { display: inline-block; font-size: 10.5px; font-weight: 600; padding: 1px 7px; border-radius: 99px; }
    .rr-tag.certain { background: #dcfce7; color: #166534; }
    .rr-tag.estimate { background: #f1f5f9; color: #475569; }
    table.rr-table tfoot td { font-weight: 700; border-top: 2px solid var(--card-border); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Laporan Piutang</h1>
        <p class="page-header-sub">Posisi piutang customer per {{ now()->format('d/m/Y') }}. Invoice termasuk PPN; termin yang belum diinvoice dihitung sebelum PPN.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('receivable-report.export', ['tab' => $tab]) }}" class="btn btn-secondary btn-sm"><i class="fa fa-file-excel me-1"></i> Export Excel</a>
    </div>
</div>

<div class="rr-kpis">
    <div class="rr-kpi"><div class="lbl">Sisa piutang</div><div class="val">{{ $money($totals['outstanding']) }}</div><div class="rr-muted">{{ $totals['open_count'] }} invoice belum dibayar</div></div>
    <div class="rr-kpi danger"><div class="lbl">Lewat jatuh tempo</div><div class="val">{{ $money($totals['overdue']) }}</div><div class="rr-muted">{{ $totals['overdue_count'] }} invoice · <a href="{{ route('receivable-report.index', ['tab' => 'aging']) }}">lihat umur</a></div></div>
    <div class="rr-kpi warn"><div class="lbl">Jatuh tempo minggu ini</div><div class="val">{{ $money($totals['due_week']) }}</div><div class="rr-muted">s/d {{ now()->endOfWeek()->format('d/m/Y') }}</div></div>
    <div class="rr-kpi info"><div class="lbl">Siap ditagih (belum diinvoice)</div><div class="val">{{ $money($totals['ready']) }}</div><div class="rr-muted">sebelum PPN @if($totals['late_billing'] > 0)· <span class="text-danger fw-semibold">{{ $totals['late_billing'] }} termin terlambat ditagih</span>@endif</div></div>
</div>

<div class="card-custom fade-in">
    <div class="card-body-custom">
        <nav class="rr-tabs" aria-label="Jenis laporan">
            @foreach($tabs as $key => $label)
                <a href="{{ route('receivable-report.index', ['tab' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="table-responsive">
        @switch($tab)
            @case('aging')
                @php $agingColors = ['current' => '#10b981', 'd1_30' => '#f59e0b', 'd31_60' => '#f97316', 'd61_90' => '#ef4444', 'd90' => '#991b1b']; @endphp
                <p class="rr-muted mb-2">Invoice Approved yang belum dibayar, dikelompokkan menurut lama keterlambatan dari jatuh tempo invoice.</p>
                <table class="table table-custom align-middle mb-0 rr-table">
                    <thead><tr><th>Customer</th>@foreach(\App\Support\ReceivableReport::AGING_BUCKETS as $label)<th class="rr-num">{{ $label }}</th>@endforeach<th class="rr-num">Total</th><th style="width:140px">Komposisi</th></tr></thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr>
                                <td class="fw-semibold">{{ $r['company']?->account_name ?? '—' }}</td>
                                @foreach($r['buckets'] as $key => $value)<td class="rr-num {{ $key !== 'current' && $value > 0 ? 'text-danger' : '' }}">{{ $value > 0 ? $money($value) : '—' }}</td>@endforeach
                                <td class="rr-num fw-semibold">{{ $money($r['total']) }}</td>
                                <td><div class="rr-bar" title="Komposisi umur piutang">@foreach($r['buckets'] as $key => $value)@if($value > 0)<span style="width:{{ $r['total'] > 0 ? $value / $r['total'] * 100 : 0 }}%;background:{{ $agingColors[$key] }}"></span>@endif @endforeach</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-4 rr-muted">Tidak ada piutang terbuka.</td></tr>
                        @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                    <tfoot><tr><td>Total</td>@foreach(array_keys(\App\Support\ReceivableReport::AGING_BUCKETS) as $key)<td class="rr-num">{{ $money($rows->sum(fn ($r) => $r['buckets'][$key])) }}</td>@endforeach<td class="rr-num">{{ $money($rows->sum('total')) }}</td><td></td></tr></tfoot>
                    @endif
                </table>
                @break

            @case('forecast')
                <p class="rr-muted mb-2">
                    <span class="rr-tag certain">Pasti</span> invoice Approved menurut jatuh tempo (termasuk PPN).
                    <span class="rr-tag estimate ms-1">Estimasi</span> termin yang belum menjadi piutang menurut target tanggal termin; bila kosong memakai PO due date project (sebelum PPN, kecuali invoice yang sedang diproses).
                </p>
                <table class="table table-custom align-middle mb-0 rr-table">
                    <thead><tr><th>Customer / Project</th><th>Termin / Invoice</th><th>Tanggal</th><th>Posisi</th><th class="rr-num">Nominal</th></tr></thead>
                    <tbody>
                        @forelse($rows as $group)
                            <tr class="rr-group-row {{ $group['key'] === '0_overdue' ? 'overdue' : ($group['key'] === '0_late_billing' ? 'late' : '') }}">
                                <td colspan="4">{{ $group['label'] }} <span class="rr-muted">({{ $group['rows']->count() }})</span></td>
                                <td class="rr-num">{{ $money($group['certain'] + $group['estimate']) }}@if($group['certain'] > 0 && $group['estimate'] > 0)<div class="rr-muted" style="font-weight:400">pasti {{ $money($group['certain']) }} · estimasi {{ $money($group['estimate']) }}</div>@endif</td>
                            </tr>
                            @foreach($group['rows'] as $r)
                                <tr>
                                    <td>{{ $r['project']?->company?->account_name ?? '—' }}@if($r['project'])<div class="rr-muted"><a href="{{ route('project.show', $r['project']->id) }}#timeline-penagihan">{{ $r['project']->project_number }}</a> {{ $r['project']->project_name }}</div>@endif</td>
                                    <td>
                                        {{ $r['term'] ? $r['term']->name.' · '.\App\Models\Invoice::formatNumber($r['term']->percent).'%' : ($r['invoice']?->noted ?: 'Di luar timeline') }}
                                        @if($r['invoice'])<div class="rr-muted"><a href="{{ route('invoice.show', $r['invoice']->id) }}">{{ $r['invoice']->invoice_number }}</a></div>@endif
                                    </td>
                                    <td>{{ $r['date']?->format('d/m/Y') ?? '—' }}@if($r['kind'] === 'invoice' && $report->daysOverdue($r['invoice']) > 0)<div class="rr-muted text-danger">terlambat {{ $report->daysOverdue($r['invoice']) }} hari</div>@endif</td>
                                    <td><span class="rr-tag {{ $r['kind'] === 'invoice' ? 'certain' : 'estimate' }}">{{ $r['kind'] === 'invoice' ? 'Pasti' : 'Estimasi' }}</span> <span class="rr-muted">{{ \App\Support\ReceivableReport::POSITIONS[$r['position']] }}</span></td>
                                    <td class="rr-num">{{ $money($r['amount']) }}</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="5" class="text-center py-4 rr-muted">Tidak ada piutang maupun termin yang akan ditagih.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @break

            @case('project')
                <p class="rr-muted mb-2">Nilai kontrak (sebelum PPN) dibagi menurut posisi termin di timeline penagihan. PO due date menjadi acuan termin yang belum ditagih.</p>
                <div class="rr-legend">@foreach(\App\Support\ReceivableReport::POSITIONS as $key => $label)<span><i style="background:{{ $positionColors[$key] }}"></i>{{ $label }}</span>@endforeach</div>
                <table class="table table-custom align-middle mb-0 rr-table">
                    <thead><tr><th>Project</th><th class="rr-num">Nilai Kontrak</th><th style="width:150px">Progres</th><th class="rr-num">Belum Ditagih</th><th class="rr-num">Dalam Proses</th><th class="rr-num">Piutang</th><th class="rr-num">Lunas</th><th class="rr-num">Di Luar Timeline</th><th>PO Due Date</th></tr></thead>
                    <tbody>
                        @forelse($rows as $r)
                            @php $p = $r['positions']; $notBilled = $p['unscheduled'] + $p['locked'] + $p['ready']; @endphp
                            <tr>
                                <td><a href="{{ route('project.show', $r['project']->id) }}#timeline-penagihan" class="fw-semibold">{{ $r['project']->project_number }}</a><div class="rr-muted">{{ $r['project']->project_name }} · {{ $r['project']->company?->account_name ?? '—' }}</div></td>
                                <td class="rr-num">{{ $money($r['contract']) }}@if($r['scheduled_percent'] < 100)<div class="rr-muted text-warning">terjadwal {{ \App\Models\Invoice::formatNumber($r['scheduled_percent']) }}%</div>@endif</td>
                                <td><div class="rr-bar" title="Posisi termin">@foreach($p as $key => $value)@if($value > 0 && $r['contract'] > 0)<span style="width:{{ min(100, $value / $r['contract'] * 100) }}%;background:{{ $positionColors[$key] }}" title="{{ \App\Support\ReceivableReport::POSITIONS[$key] }}"></span>@endif @endforeach</div></td>
                                <td class="rr-num">{{ $notBilled > 0 ? $money($notBilled) : '—' }}@if($p['ready'] > 0)<div class="rr-muted" style="color:#0369a1">siap ditagih {{ $money($p['ready']) }}</div>@endif</td>
                                <td class="rr-num">{{ $p['in_process'] > 0 ? $money($p['in_process']) : '—' }}</td>
                                <td class="rr-num fw-semibold">{{ $p['receivable'] > 0 ? $money($p['receivable']) : '—' }}</td>
                                <td class="rr-num">{{ $p['paid'] > 0 ? $money($p['paid']) : '—' }}</td>
                                <td class="rr-num">{{ $r['outside_timeline'] > 0 ? $money($r['outside_timeline']).' ('.$r['outside_timeline_count'].')' : '—' }}</td>
                                <td>
                                    {{ $r['po_due_date']?->format('d/m/Y') ?? '—' }}
                                    @if($r['due_days'] !== null)
                                        <div class="rr-muted {{ $r['due_days'] < 0 ? 'text-danger' : '' }}">{{ $r['due_days'] < 0 ? 'lewat '.abs($r['due_days']).' hari' : ($r['due_days'] === 0 ? 'hari ini' : $r['due_days'].' hari lagi') }}</div>
                                    @endif
                                    @if($r['late_billing'] > 0)<div class="rr-muted text-danger fw-semibold">{{ $r['late_billing'] }} termin terlambat ditagih</div>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center py-4 rr-muted">Belum ada project dengan timeline penagihan atau invoice.</td></tr>
                        @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                    <tfoot><tr>
                        <td>Total</td>
                        <td class="rr-num">{{ $money($rows->sum('contract')) }}</td><td></td>
                        <td class="rr-num">{{ $money($rows->sum(fn ($r) => $r['positions']['unscheduled'] + $r['positions']['locked'] + $r['positions']['ready'])) }}</td>
                        <td class="rr-num">{{ $money($rows->sum(fn ($r) => $r['positions']['in_process'])) }}</td>
                        <td class="rr-num">{{ $money($rows->sum(fn ($r) => $r['positions']['receivable'])) }}</td>
                        <td class="rr-num">{{ $money($rows->sum(fn ($r) => $r['positions']['paid'])) }}</td>
                        <td class="rr-num">{{ $money($rows->sum('outside_timeline')) }}</td><td></td>
                    </tr></tfoot>
                    @endif
                </table>
                @break

            @default
                <p class="rr-muted mb-2">Sudah ditagih, diterima, dan sisa piutang dari invoice Approved (termasuk PPN). Belum ditagih = termin yang belum menjadi piutang (sebelum PPN).</p>
                <table class="table table-custom align-middle mb-0 rr-table">
                    <thead><tr><th>Customer</th><th class="rr-num">Sudah Ditagih</th><th class="rr-num">Sudah Diterima</th><th class="rr-num">Sisa Piutang</th><th class="rr-num">Terlambat</th><th>Jatuh Tempo Terdekat</th><th class="rr-num">Belum Ditagih</th></tr></thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr>
                                <td class="fw-semibold">{{ $r['company']->account_name }}<div class="rr-muted" style="font-weight:400">{{ $r['open_count'] }} invoice belum dibayar</div></td>
                                <td class="rr-num">{{ $money($r['billed']) }}</td>
                                <td class="rr-num">{{ $money($r['received']) }}</td>
                                <td class="rr-num fw-semibold">{{ $r['outstanding'] > 0 ? $money($r['outstanding']) : '—' }}</td>
                                <td class="rr-num {{ $r['overdue'] > 0 ? 'text-danger fw-semibold' : '' }}">{{ $r['overdue'] > 0 ? $money($r['overdue']).' ('.$r['overdue_count'].')' : '—' }}</td>
                                <td>{{ $r['next_due']?->format('d/m/Y') ?? '—' }}</td>
                                <td class="rr-num">{{ $r['unbilled'] > 0 ? $money($r['unbilled']) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-4 rr-muted">Belum ada data customer.</td></tr>
                        @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                    <tfoot><tr><td>Total</td><td class="rr-num">{{ $money($rows->sum('billed')) }}</td><td class="rr-num">{{ $money($rows->sum('received')) }}</td><td class="rr-num">{{ $money($rows->sum('outstanding')) }}</td><td class="rr-num">{{ $money($rows->sum('overdue')) }}</td><td></td><td class="rr-num">{{ $money($rows->sum('unbilled')) }}</td></tr></tfoot>
                    @endif
                </table>
        @endswitch
        </div>
    </div>
</div>

{{-- Status pengingat WhatsApp; pengaturannya di Master Data → Notifikasi Finance --}}
<div class="d-flex align-items-center flex-wrap gap-2 mt-3 rr-muted">
    <i class="fa-brands fa-whatsapp" style="color:#16a34a"></i>
    <span>Pengingat Piutang (WhatsApp):</span>
    <span class="status-badge" style="{{ $reminder['enabled'] ? 'background:#dcfce7;color:#166534' : 'background:#f3f4f6;color:#4b5563' }}">{{ $reminder['enabled'] ? 'Aktif · setiap hari '.$reminder['send_time'] : 'Nonaktif' }}</span>
    @if($reminder['last_sent_date'])<span>· terakhir terkirim {{ \Illuminate\Support\Carbon::parse($reminder['last_sent_date'])->format('d/m/Y') }}</span>@endif
    @if($canManageNotification)<a href="{{ route('finance-notification.index') }}" class="ms-1"><i class="fa fa-gear me-1"></i>Atur</a>@endif
</div>
@endsection
