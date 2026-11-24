@php
    $money = fn ($v) => \App\Support\Number::money((float) $v);
    $positionColors = ['unscheduled' => '#cbd5e1', 'locked' => '#94a3b8', 'ready' => '#0ea5e9', 'in_process' => '#8b5cf6', 'receivable' => '#f59e0b', 'paid' => '#10b981'];
    $p = $r['positions'];
    $totalPiutang = round($p['in_process'] + $p['receivable'] + $p['paid'], 2);
    $sisaTip = 'Dalam proses '.$money($p['in_process']).' · siap ditagih '.$money($p['ready']).' · belum dijadwalkan/terkunci '.$money($p['unscheduled'] + $p['locked']).' · di luar timeline '.$money($r['outside_timeline']).' ('.$r['outside_timeline_count'].')';
    $status = $r['late_billing'] > 0
        ? ['Perlu perhatian', '#fef3c7', '#92400e']
        : (($r['due_days'] !== null && $r['due_days'] < 0) ? ['Jatuh tempo', '#fee2e2', '#991b1b'] : ['Lancar', '#dcfce7', '#166534']);
@endphp
<td class="text-center rr-muted">{{ $i + 1 }}</td>
<td>
    <a href="{{ route('project.show', $r['project']->id) }}#timeline-penagihan" class="fw-semibold">{{ $r['project']->project_number }}</a>
    <span class="status-badge ms-1" style="background:{{ $status[1] }};color:{{ $status[2] }}">{{ $status[0] }}</span>
    <div class="rr-muted">{{ $r['project']->project_name }} · {{ $r['project']->company?->account_name ?? '—' }}</div>
</td>
<td class="rr-num" title="{{ $r['scheduled_percent'] < 100 ? 'Terjadwal '.\App\Models\Invoice::formatNumber($r['scheduled_percent']).'%' : 'Seluruh nilai kontrak terjadwal' }}">{{ $money($r['contract']) }}</td>
<td><div class="rr-bar" title="Posisi termin">@foreach($p as $key => $value)@if($value > 0 && $r['contract'] > 0)<span style="width:{{ min(100, $value / $r['contract'] * 100) }}%;background:{{ $positionColors[$key] }}" title="{{ \App\Support\ReceivableReport::POSITIONS[$key] }}: {{ $money($value) }}"></span>@endif @endforeach</div></td>
<td class="rr-num" title="Sudah diinvoice (dalam proses + piutang + lunas)">{{ $totalPiutang > 0 ? $money($totalPiutang) : '—' }}</td>
<td class="rr-num">{{ $p['paid'] > 0 ? $money($p['paid']) : '—' }}</td>
<td class="rr-num fw-semibold" title="{{ $sisaTip }}">{{ $p['receivable'] > 0 ? $money($p['receivable']) : '—' }}</td>
<td>
    {{ $r['po_due_date']?->format('d/m/Y') ?? '—' }}
    @if($r['due_days'] !== null)
        <div class="rr-muted {{ $r['due_days'] < 0 ? 'text-danger' : '' }}">{{ $r['due_days'] < 0 ? 'lewat '.abs($r['due_days']).' hari' : ($r['due_days'] === 0 ? 'hari ini' : $r['due_days'].' hari lagi') }}</div>
    @endif
    @if($r['late_billing'] > 0)<div class="rr-muted text-danger fw-semibold">{{ $r['late_billing'] }} termin terlambat ditagih</div>@endif
</td>
