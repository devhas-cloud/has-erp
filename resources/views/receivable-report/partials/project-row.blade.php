@php
    $money = fn ($v) => \App\Support\Number::money((float) $v);
    $status = $r['remaining'] <= 0
        ? ['Paid', '#dcfce7', '#166534']
        : (($r['due_days'] !== null && $r['due_days'] < 0) ? ['Past due', '#fee2e2', '#991b1b'] : ['Open', '#fef3c7', '#92400e']);
@endphp
<td class="text-center rr-muted">{{ $i + 1 }}</td>
<td>{{ $r['project']->company?->account_name ?? '—' }}</td>
<td>
    <a href="{{ route('project.show', $r['project']->id) }}#timeline-penagihan" class="fw-semibold">{{ $r['project']->project_number }}</a>
    {{-- <span class="rr-status ms-1" style="background:{{ $status[1] }};color:{{ $status[2] }}">{{ $status[0] }}</span> --}}
    <div class="rr-muted">{{ $r['project']->project_name }}</div>
</td>
<td class="text-center">{{ $r['po_date']?->format('d/m/Y') ?? '—' }}</td>
<td class="text-center">{{ $r['po_date_received']?->format('d/m/Y') ?? '—' }}</td>
<td class="text-center">
    {{ $r['po_due_date']?->format('d/m/Y') ?? '—' }}
    @if($r['due_days'] !== null)
        <div class="rr-muted {{ $r['due_days'] < 0 ? 'text-danger' : '' }}">{{ $r['due_days'] < 0 ? abs($r['due_days']).' day(s) past' : ($r['due_days'] === 0 ? 'today' : 'in '.$r['due_days'].' day(s)') }}</div>
    @endif
    @if($r['late_billing'] > 0)<div class="rr-muted text-danger fw-semibold">{{ $r['late_billing'] }} term(s) billed late</div>@endif
</td>
<td class="text-center">{{ $r['delivery_date']?->format('d/m/Y') ?? '—' }}</td>
<td class="rr-num" title="Project contract value (tax base before VAT)">{{ $money($r['contract']) }}</td>
<td class="rr-num" title="Total paid (tax base)">{{ $money($r['paid']) }}</td>
<td class="rr-num fw-semibold" title="Remaining contractual amount">{{ $r['remaining'] > 0 ? $money($r['remaining']) : '—' }}</td>
