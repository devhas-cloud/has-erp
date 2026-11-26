@php $money = fn ($v) => \App\Support\Number::moneyWithCode((float) $v, $r['currency']); @endphp
<td class="text-center pr-muted">{{ $i + 1 }}</td>
<td>
    @if($r['supplier'])
        <a href="{{ route('supplier-bill.index', ['supplier_id' => $r['supplier']->id]) }}" class="fw-semibold">{{ $r['supplier']->name }}</a>
    @else
        —
    @endif
    <div class="pr-muted">{{ $r['bill_count'] }} bill(s)</div>
</td>
<td>
    @if($r['project'])
        <a href="{{ route('project.show', $r['project']->id) }}" class="fw-semibold">{{ $r['project']->project_number }}</a>
        <div class="pr-muted">{{ $r['project']->project_name }}</div>
    @else
        —
    @endif
</td>
<td class="pr-num">{{ $money($r['total']) }}</td>
<td class="pr-num">{{ $money($r['paid']) }}</td>
<td class="pr-num fw-semibold" title="Remaining amount still to be paid">{{ $r['sisa'] > 0 ? $money($r['sisa']) : '—' }}</td>
