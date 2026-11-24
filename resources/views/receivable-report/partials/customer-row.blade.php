@php
    $money = fn ($v) => \App\Support\Number::money((float) $v);
    $status = $r['remaining'] <= 0
        ? ['Paid', '#dcfce7', '#166534']
        : ($r['overdue'] > 0 ? ['Overdue', '#fee2e2', '#991b1b'] : ['Open', '#fef3c7', '#92400e']);
    $sisaTip = trim(($r['overdue'] > 0 ? 'Overdue '.$money($r['overdue']).' ('.$r['overdue_count'].' invoice(s))' : '')
        .($r['overdue'] > 0 && $r['next_due'] ? ' · ' : '')
        .($r['next_due'] ? 'next due '.$r['next_due']->format('d/m/Y') : ''));
@endphp
<td class="text-center rr-muted">{{ $i + 1 }}</td>
<td>
    <a href="{{ route('invoice.index', ['company_id' => $r['company']->id]) }}" class="fw-semibold">{{ $r['company']->account_name }}</a>
    {{-- <div>
        <span class="rr-status" style="background:{{ $status[1] }};color:{{ $status[2] }}">{{ $status[0] }}</span>
        @if(($r['open_count'] ?? 0) > 0)
            <a href="{{ route('invoice.index', ['company_id' => $r['company']->id, 'overdue' => 1]) }}" class="rr-muted ms-1">{{ $r['open_count'] }} unpaid invoice(s)</a>
        @endif
        @if($r['overdue'] > 0)<span class="rr-muted text-danger ms-1">· overdue {{ $money($r['overdue']) }}</span>@endif
    </div> --}}
</td>
<td class="text-center fw-semibold">{{ $r['project_count'] }}</td>
<td class="rr-num" title="Total contract value of all projects (tax base before VAT)">{{ $money($r['contract']) }}</td>
<td class="rr-num" title="Total paid (tax base)">{{ $money($r['paid']) }}</td>
<td class="rr-num fw-semibold" title="{{ $sisaTip ?: 'Nothing outstanding' }}">{{ $r['remaining'] > 0 ? $money($r['remaining']) : '—' }}</td>
