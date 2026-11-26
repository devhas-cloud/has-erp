@extends('layouts.app')

@section('title', 'Stock Transfer Details')
@section('page-title', 'Stock Transfer Details')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $isMaterial = fn ($item) => (bool) $item->stock?->masterProduct?->isMaterial();
    $card = fn ($item, $warehouseId, $projectId) => route('stock-report.card', $item->stock->master_product_id).'?'.http_build_query(['warehouse_id' => $warehouseId, 'ownership' => $projectId ?: 'free']);
    $outLog = fn ($item) => $logs->get($item->id, collect())->first(fn ($l) => $l->qty < 0);
    $inLog = fn ($item) => $logs->get($item->id, collect())->last(fn ($l) => $l->qty > 0);
    $lineValue = fn ($item) => ($log = $outLog($item)) ? abs($log->qty) * $log->unit_cost : 0;
    $totalValue = $transfer->items->sum($lineValue);
    $materialLines = $transfer->items->filter($isMaterial)->count();
    $serialUnits = $transfer->items->count() - $materialLines;
    $isAllocation = (int) $transfer->from_project_id !== (int) $transfer->to_project_id;
@endphp

@section('styles')
<style>
    .st-kind { font-size: 10px; font-weight: 600; padding: 1px 7px; border-radius: 999px; white-space: nowrap; }
    .st-kind-material { background: #e0f2fe; color: #075985; }
    .st-kind-serial { background: #fef3c7; color: #92400e; }
    .st-sub { font-size: 11.5px; color: var(--text-muted); }
    .st-items th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    .st-route { display: grid; grid-template-columns: minmax(0, 1fr) 48px minmax(0, 1fr); gap: 12px; align-items: stretch; }
    .st-route-side { border: 1px solid var(--card-border, #e5e7eb); border-radius: 10px; padding: 12px 14px; }
    .st-route-label { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); font-weight: 600; }
    .st-route-wh { font-size: 15px; font-weight: 700; margin-top: 2px; }
    .st-route-arrow { display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 20px; }
    @media (max-width: 767.98px) { .st-route { grid-template-columns: 1fr; } .st-route-arrow { transform: rotate(90deg); } }
    .st-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px 16px; margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--card-border, #e5e7eb); font-size: 13px; }
    @media (max-width: 991.98px) { .st-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .st-facts .k { font-size: 11px; color: var(--text-muted); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title d-flex align-items-center gap-2 flex-wrap">Transfer {{ $transfer->transfer_number }}
            <span class="status-badge" style="background:var(--accent-soft);color:var(--accent);">{{ $transfer->kindLabel() }}</span></h1>
        <p class="page-header-sub">{{ $transfer->transfer_date?->format('d/m/Y') }} &middot; {{ $transfer->items->count() }} {{ \Illuminate\Support\Str::plural('line', $transfer->items->count()) }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('stock-transfer.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom"><span><i class="fa-solid fa-route me-2" style="color:var(--accent)"></i>Route</span></div>
    <div class="card-body-custom">
        <div class="st-route">
            @foreach([['From', 'fa-circle-arrow-up', 'var(--danger)', $transfer->fromWarehouse, $transfer->fromProject], 'arrow', ['To', 'fa-circle-arrow-down', 'var(--success, #16a34a)', $transfer->toWarehouse, $transfer->toProject]] as $side)
                @if($side === 'arrow')
                    <div class="st-route-arrow"><i class="fa-solid fa-arrow-right-long"></i></div>
                @else
                    @php [$label, $icon, $color, $warehouse, $project] = $side; @endphp
                    <div class="st-route-side">
                        <div class="st-route-label"><i class="fa {{ $icon }} me-1" style="color:{{ $color }}"></i>{{ $label }}</div>
                        <div class="st-route-wh">{{ $warehouse ? $warehouse->code.' — '.$warehouse->name : '—' }}</div>
                        <div class="st-sub">{{ $warehouse?->branch_label }}</div>
                        <div class="mt-1" style="font-size:12.5px">
                            @if($project)
                                <span class="st-kind st-kind-serial">Project</span>
                                <a href="{{ route('project.show', $project->id) }}" class="fw-semibold">{{ \App\Models\StockTransfer::ownershipLabel($project) }}</a>
                            @else
                                <span class="st-kind" style="background:#f3f4f6;color:#4b5563">Free stock</span>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
        <div class="st-facts">
            <div><div class="k">Material lines</div><div class="fw-semibold">{{ $materialLines }}</div></div>
            <div><div class="k">SN units</div><div class="fw-semibold">{{ $serialUnits }}</div></div>
            <div><div class="k">{{ $isAllocation ? 'Value allocated (project cost)' : 'Value moved' }}</div><div class="fw-semibold" style="font-variant-numeric:tabular-nums">{{ \App\Models\Currency::baseName() }} {{ \App\Support\StockCost::format($totalValue) }}</div></div>
            <div><div class="k">Created By</div><div>{{ $transfer->creator?->display_name ?? '—' }} <span class="st-sub">· {{ $transfer->created_at?->format('d/m/Y H:i') }}</span></div></div>
            @if($transfer->note)
                <div style="grid-column:1/-1"><div class="k">Note</div><div>{{ $transfer->note }}</div></div>
            @endif
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Transferred Items</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0 st-items">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Product</th>
                        <th class="text-end" style="width:120px">Qty</th>
                        <th class="text-end" style="width:130px">Unit Cost</th>
                        <th class="text-end" style="width:140px">Value</th>
                        <th style="width:260px">Stock</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfer->items as $i => $item)
                    @php
                        $stock = $item->stock;
                        $product = $stock?->masterProduct;
                        $material = $isMaterial($item);
                        $out = $outLog($item);
                        $in = $inLog($item);
                    @endphp
                    <tr>
                        <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                        <td>
                            <div class="fw-semibold" style="font-size:13px">{{ $product?->name ?? '—' }}</div>
                            <div class="st-sub">{{ $product?->code ?? '—' }}
                                @if($product)<span class="st-kind {{ $material ? 'st-kind-material' : 'st-kind-serial' }} ms-1">{{ $material ? 'Material' : 'Serial' }}</span>@endif
                            </div>
                        </td>
                        <td class="text-end"><strong>{{ $fmtQty($item->qty) }}</strong> <span class="st-sub">{{ $item->unit }}</span></td>
                        <td class="text-end" style="font-variant-numeric:tabular-nums">{{ $out ? \App\Support\StockCost::format($out->unit_cost) : '—' }}</td>
                        <td class="text-end" style="font-variant-numeric:tabular-nums">{{ $out ? \App\Support\StockCost::format($lineValue($item)) : '—' }}</td>
                        <td style="font-size:12px">
                            @if($stock && $material)
                                <div>From source balance → into destination balance{{ $in ? ' ('.$fmtQty($in->qty_after).' '.$item->unit.' after)' : '' }}</div>
                                <a href="{{ $card($item, $transfer->from_warehouse_id, $transfer->from_project_id) }}"><i class="fa fa-clock-rotate-left me-1"></i>Source card</a>
                                <span class="st-sub mx-1">·</span>
                                <a href="{{ $card($item, $transfer->to_warehouse_id, $transfer->to_project_id) }}">Destination card</a>
                            @elseif($stock)
                                <a href="{{ route('stock-detail.show', $stock->id) }}" title="Unit history">{{ $stock->serial_number ? 'SN '.$stock->serial_number : $stock->identity_label }}</a>
                                @if(! $stock->serial_number)<div style="color:#b45309">SN not filled in yet</div>@endif
                                <div class="st-sub">Same unit, now at the destination</div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
