@php
    // Nominal dalam mata uang tagihan (desimal mengikuti mata uangnya).
    $money = fn ($v) => \App\Support\Number::moneyWithCode((float) $v, $r['currency']);
    $status = $r['sisa'] <= 0
        ? ['Paid', '#dcfce7', '#166534']
        : (($r['overdue'] ?? 0) > 0 ? ['Overdue', '#fee2e2', '#991b1b'] : ['Open', '#fef3c7', '#92400e']);
    $dueNote = $r['next_due'] ? ' · next due '.$r['next_due']->format('d/m/Y') : '';
@endphp
<td class="text-center pr-muted">{{ $i + 1 }}</td>
<td>
    <a href="{{ route('supplier-bill.index', ['supplier_id' => $r['supplier']?->id]) }}" class="fw-semibold">{{ $r['supplier']?->name }}</a>
    <div>
        <span class="pr-status" style="background:{{ $status[1] }};color:{{ $status[2] }}">{{ $status[0] }}</span>
        <span class="pr-muted ms-1">{{ $r['open_count'] }} open bill(s){{ $dueNote }}</span>
    </div>
</td>
<td class="pr-num">{{ $money($r['total']) }}</td>
<td class="pr-num">{{ $money($r['paid']) }}</td>
<td class="pr-num fw-semibold" title="Remaining amount still to be paid">{{ $r['sisa'] > 0 ? $money($r['sisa']) : '—' }}</td>
