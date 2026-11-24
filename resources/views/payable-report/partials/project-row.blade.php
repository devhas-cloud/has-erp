@php $money = fn ($v) => \App\Support\Number::money((float) $v); @endphp
<td class="text-center pr-muted">{{ $i + 1 }}</td>
<td>
    <a href="{{ route('project.show', $r['project']->id) }}" class="fw-semibold">{{ $r['project']->project_number }}</a>
    <span class="badge ms-1" style="background:#eef2f7;color:#334155;font-size:10px">{{ $r['currency'] }}</span>
    <div class="pr-muted">{{ $r['project']->project_name }}@if($r['bill']?->supplier) · {{ $r['bill']->supplier->name }}@endif</div>
    @if($r['bill'])<div class="pr-muted"><a href="{{ route('supplier-bill.show', $r['bill']->id) }}">{{ $r['bill']->bill_number }}</a>@if($r['bill']->supplier_invoice_number) · Inv. {{ $r['bill']->supplier_invoice_number }}@endif</div>@endif
</td>
<td>{{ $r['date']?->format('d/m/Y') ?? '—' }}</td>
<td class="pr-num">{{ $money($r['total']) }}</td>
<td class="pr-num">{{ $money($r['paid']) }}</td>
<td class="pr-num fw-semibold">{{ $r['sisa'] > 0 ? $money($r['sisa']) : '—' }}</td>
