@extends('layouts.app')

@section('title', 'Stock Entry Details')
@section('page-title', 'Stock Entry Details')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $cardUrl = fn ($item) => route('stock-report.card', $item->master_product_id).'?'.http_build_query(['warehouse_id' => $entry->warehouse_id, 'ownership' => $entry->project_id ?: 'free']);
    $materialLines = $entry->items->filter(fn ($i) => $i->masterProduct?->isMaterial())->count();
    $serialUnits = (int) $entry->items->filter(fn ($i) => $i->masterProduct && ! $i->masterProduct->isMaterial())->sum('qty');
    $totalValue = $entry->items->sum(fn ($i) => $i->unit_cost * $i->qty);
@endphp

@section('styles')
<style>
    .se-kind { font-size: 10px; font-weight: 600; padding: 1px 7px; border-radius: 999px; white-space: nowrap; }
    .se-kind-material { background: #e0f2fe; color: #075985; }
    .se-kind-serial { background: #fef3c7; color: #92400e; }
    .se-sub { font-size: 11.5px; color: var(--text-muted); }
    .se-items th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    .se-stat { border-radius: var(--radius-sm); padding: 12px 14px; background: var(--accent-subtle); border: 1px solid var(--card-border); }
    .se-stat .label { font-size: 11.5px; color: var(--text-muted); }
    .se-stat .value { font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1.2; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Stock Entry {{ $entry->entry_number }}</h1>
        <p class="page-header-sub">{{ $entry->reason_label }} &middot; {{ $entry->entry_date?->format('d/m/Y') }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('stock-entry.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Information</span>
            </div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Reason</td><td><span class="status-badge" style="background:var(--accent-soft);color:var(--accent);">{{ $entry->reason_label }}</span></td></tr>
                    <tr><td>Warehouse</td><td>{{ $entry->warehouse ? $entry->warehouse->code.' — '.$entry->warehouse->name.' ('.$entry->warehouse->branch_label.')' : '—' }}</td></tr>
                    <tr>
                        <td>Ownership</td>
                        <td>
                            @if($entry->project)
                                <a href="{{ route('project.show', $entry->project_id) }}" class="fw-semibold">{{ $entry->project->project_number }} — {{ $entry->project->project_name }}</a>
                            @else
                                Free stock
                            @endif
                        </td>
                    </tr>
                    <tr><td>Created By</td><td>{{ $entry->creator?->display_name ?? '—' }} &middot; {{ $entry->created_at?->format('d/m/Y H:i') }}</td></tr>
                    <tr><td>Description</td><td>{{ $entry->note ?: '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-chart-simple me-2" style="color:var(--accent)"></i>Summary</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-2">
                    <div class="col-6"><div class="se-stat"><div class="label">Material lines</div><div class="value">{{ $materialLines }}</div></div></div>
                    <div class="col-6"><div class="se-stat"><div class="label">SN units</div><div class="value">{{ $serialUnits }}</div></div></div>
                </div>
                <div class="d-flex justify-content-between mt-3 px-1" style="font-size:13px">
                    <span style="color:var(--text-muted)">Value added to stock</span>
                    <span class="fw-semibold" style="font-variant-numeric:tabular-nums">{{ \App\Models\Currency::baseName() }} {{ \App\Support\StockCost::format($totalValue) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Incoming Items</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0 se-items">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Product</th>
                        <th class="text-end" style="width:120px">Qty</th>
                        <th class="text-end" style="width:150px">Unit Cost</th>
                        <th class="text-end" style="width:150px">Value</th>
                        <th style="width:230px">Stock</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entry->items as $i => $item)
                    @php
                        $isMaterial = (bool) $item->masterProduct?->isMaterial();
                        $logs = $inLogs->get($item->id, collect());
                        $missing = $item->stocks->filter(fn ($st) => ! $st->canLeave())->count();
                        $serials = collect($item->serial_numbers ?? []);
                    @endphp
                    <tr>
                        <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                        <td>
                            <div class="fw-semibold" style="font-size:13px">{{ $item->masterProduct?->name ?? '—' }}</div>
                            <div class="se-sub">{{ $item->masterProduct?->code ?? '—' }}
                                @if($item->masterProduct)<span class="se-kind {{ $isMaterial ? 'se-kind-material' : 'se-kind-serial' }} ms-1">{{ $isMaterial ? 'Material' : 'Serial' }}</span>@endif
                            </div>
                        </td>
                        <td class="text-end"><strong>{{ $fmtQty($item->qty) }}</strong> <span class="se-sub">{{ $item->unit }}</span></td>
                        <td class="text-end" style="font-variant-numeric:tabular-nums">{{ \App\Support\StockCost::format($item->unit_cost) }}</td>
                        <td class="text-end" style="font-variant-numeric:tabular-nums">{{ \App\Support\StockCost::format($item->unit_cost * $item->qty) }}</td>
                        <td style="font-size:12px">
                            @if($isMaterial && $logs->isNotEmpty())
                                <div>Added to balance → <strong>{{ $fmtQty($logs->last()->qty_after) }}</strong> {{ $item->unit }} at that time</div>
                            @elseif(! $isMaterial)
                                <div>{{ $item->qty == 1 ? '1 stock row' : $fmtQty($item->qty).' stock rows' }} (one per unit)</div>
                            @endif
                            @if($item->master_product_id && $entry->warehouse_id)
                                <a href="{{ $cardUrl($item) }}"><i class="fa fa-clock-rotate-left me-1"></i>Stock card</a>
                            @endif
                        </td>
                    </tr>
                    @if($item->batch_number || $item->expiry_date || $item->warranty_until || $item->note || $serials->isNotEmpty() || $missing)
                    <tr>
                        <td></td>
                        <td colspan="5" style="font-size:12px;padding-top:0;color:var(--text-muted)">
                            @if($item->batch_number)<span class="me-3">Batch / MFG No.: <strong>{{ $item->batch_number }}</strong></span>@endif
                            @if($item->expiry_date)<span class="me-3">Expiry: <strong>{{ $item->expiry_date->format('d/m/Y') }}</strong></span>@endif
                            @if($item->warranty_until)<span class="me-3">Warranty until: <strong>{{ $item->warranty_until->format('d/m/Y') }}</strong>{{ $item->supplier ? ' ('.$item->supplier->name.')' : '' }}</span>@endif
                            @if($item->note)<span class="me-3">Note: {{ $item->note }}</span>@endif
                            @if($serials->isNotEmpty())<div class="mt-1">SN: {{ $serials->implode(', ') }}</div>@endif
                            @if($missing)
                                <div class="mt-1" style="color:#b45309"><i class="fa fa-triangle-exclamation me-1"></i>{{ $missing }} SN {{ \Illuminate\Support\Str::plural('unit', $missing) }} not filled in —
                                    <a href="{{ route('stock-detail.index', ['product_id' => $item->master_product_id, 'warehouse_id' => $entry->warehouse_id]) }}">complete now</a></div>
                            @endif
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
