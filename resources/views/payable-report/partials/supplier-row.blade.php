@php
    $money = fn ($v) => \App\Support\Number::money((float) $v);
    $status = $r['sisa'] <= 0
        ? ['Lunas', '#dcfce7', '#166534']
        : (($r['overdue'] ?? 0) > 0 ? ['Tertunda', '#fee2e2', '#991b1b'] : ['Berjalan', '#fef3c7', '#92400e']);
    $dueNote = $r['next_due'] ? ' · jatuh tempo '.$r['next_due']->format('d/m/Y') : '';
@endphp
<td class="text-center pr-muted">{{ $i + 1 }}</td>
<td>
    <a href="{{ route('supplier-bill.index', ['supplier_id' => $r['supplier']?->id]) }}" class="fw-semibold">{{ $r['supplier']?->name }}</a>
    <span class="badge ms-1" style="background:#eef2f7;color:#334155;font-size:10px">{{ $r['currency'] }}</span>
    <span class="status-badge ms-1" style="background:{{ $status[1] }};color:{{ $status[2] }}">{{ $status[0] }}</span>
    <div class="pr-muted">{{ $r['open_count'] }} tagihan terbuka{{ $dueNote }}</div>
</td>
<td class="pr-num">{{ $money($r['total']) }}</td>
<td class="pr-num">{{ $money($r['paid']) }}</td>
<td class="pr-num fw-semibold">{{ $r['sisa'] > 0 ? $money($r['sisa']) : '—' }}</td>
