@php
    $money = fn ($v) => \App\Support\Number::money((float) $v);
    $status = $r['outstanding'] <= 0
        ? ['Lunas', '#dcfce7', '#166534']
        : ($r['overdue'] > 0 ? ['Tertunda', '#fee2e2', '#991b1b'] : ['Berjalan', '#fef3c7', '#92400e']);
    $sisaTip = trim(($r['overdue'] > 0 ? 'Terlambat '.$money($r['overdue']).' ('.$r['overdue_count'].' invoice)' : '')
        .($r['overdue'] > 0 && $r['next_due'] ? ' · ' : '')
        .($r['next_due'] ? 'jatuh tempo terdekat '.$r['next_due']->format('d/m/Y') : ''));
@endphp
<td class="text-center rr-muted">{{ $i + 1 }}</td>
<td>
    <a href="{{ route('invoice.index', ['company_id' => $r['company']->id]) }}" class="fw-semibold">{{ $r['company']->account_name }}</a>
    <span class="status-badge ms-1" style="background:{{ $status[1] }};color:{{ $status[2] }}">{{ $status[0] }}</span>
    <div class="rr-muted" style="font-weight:400"><a href="{{ route('invoice.index', ['company_id' => $r['company']->id, 'overdue' => 1]) }}">{{ $r['open_count'] }} invoice belum dibayar</a></div>
</td>
<td class="rr-num" title="Belum ditagih (sebelum PPN): {{ $money($r['unbilled']) }}">{{ $money($r['billed']) }}</td>
<td class="rr-num">{{ $money($r['received']) }}</td>
<td class="rr-num fw-semibold" title="{{ $sisaTip ?: 'Tidak ada sisa piutang' }}">{{ $r['outstanding'] > 0 ? $money($r['outstanding']) : '—' }}</td>
