@extends('layouts.app')

@section('title', 'Goods Receipt Details')
@section('page-title', 'Goods Receipt Details')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $sourceUrl = $receipt->purchase_order_id
        ? route('purchase-order.show', $receipt->purchase_order_id)
        : ($receipt->direct_purchase_id ? route('direct-purchase.show', $receipt->direct_purchase_id) : null);
    $toStock = $receipt->items->where('destination', '!=', 'direct');
    $direct = $receipt->items->where('destination', 'direct');
    $missingTotal = $toStock->sum(fn ($item) => $item->stocks->filter(fn ($st) => ! $st->canLeave())->count());
    $cardUrl = fn ($item) => route('stock-report.card', $item->master_product_id).'?'.http_build_query(['warehouse_id' => $receipt->warehouse_id, 'ownership' => $item->project_id ?: 'free']);
    $stockValue = $inLogs->flatten()->sum(fn ($log) => $log->qty * $log->unit_cost);
@endphp

@section('styles')
<style>
    .gr-stat { border-radius: var(--radius-sm); padding: 12px 14px; background: var(--accent-subtle); border: 1px solid var(--card-border); }
    .gr-stat .label { font-size: 11.5px; color: var(--text-muted); }
    .gr-stat .value { font-size: 20px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.2; }
    .gr-stat.info { background: var(--info-soft); }
    .gr-sn { display: inline-block; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; padding: 1px 6px; margin: 2px 2px 0 0; border-radius: 5px; background: #f1f5f9; color: var(--text-secondary); }
    .gr-detail-row td { border-top: 0 !important; padding-top: 0 !important; }
    .gr-kind { font-size: 10px; font-weight: 600; padding: 1px 7px; border-radius: 999px; white-space: nowrap; }
    .gr-kind-material { background: #e0f2fe; color: #075985; }
    .gr-kind-serial { background: #fef3c7; color: #92400e; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $receipt->receipt_number ?: 'Receipt #'.$receipt->id }}</h1>
        <p class="page-header-sub">{{ $receipt->supplierName() ?? '—' }} &middot; {{ $receipt->received_date?->format('d M Y') ?? '—' }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('goods-receipt.index') }}" class="btn btn-secondary btn-sm">
            Back
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span>Information</span>
                {!! $receipt->statusBadgeHtml() !!}
            </div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr>
                        <td>Document</td>
                        <td>
                            @if($sourceUrl)<a href="{{ $sourceUrl }}" class="fw-semibold">{{ $receipt->sourceNumber() }}</a>@else{{ $receipt->sourceNumber() }}@endif
                            <span style="color:var(--text-muted)">&middot; {{ $receipt->supplierName() ?? '—' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>Warehouse</td>
                        <td>
                            @if($receipt->warehouse)
                                {{ $receipt->warehouse->code }} — {{ $receipt->warehouse->name }}
                                <span style="color:var(--text-muted)">&middot; {{ $receipt->warehouse->branch_label }}</span>
                            @else
                                <span style="color:var(--text-muted)">All for direct use</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>Received Date</td>
                        <td>{{ $receipt->received_date?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td>Recorded By</td>
                        <td>{{ $receipt->creator?->display_name ?? '—' }} <span style="color:var(--text-muted)">&middot; {{ $receipt->created_at?->format('d/m/Y H:i') }}</span></td>
                    </tr>
                    @if($receipt->note)
                    <tr>
                        <td>Note</td>
                        <td>{{ $receipt->note }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span>Summary</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="gr-stat">
                            <div class="label">To Warehouse</div>
                            <div class="value">{{ $toStock->count() }}</div>
                            <div class="label">
                                {{ $toStock->filter(fn ($i) => $i->masterProduct?->isMaterial())->count() }} material ·
                                {{ (int) $toStock->filter(fn ($i) => $i->masterProduct && ! $i->masterProduct->isMaterial())->sum('qty') }} SN units
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="gr-stat info">
                            <div class="label">Direct Use</div>
                            <div class="value">{{ $direct->count() }}</div>
                            <div class="label">items</div>
                        </div>
                    </div>
                </div>
                @if($stockValue > 0)
                    <div class="d-flex justify-content-between mt-3 px-1" style="font-size:13px">
                        <span style="color:var(--text-muted)">Value received into stock</span>
                        <span class="fw-semibold" style="font-variant-numeric:tabular-nums">{{ \App\Models\Currency::baseName() }} {{ \App\Support\StockCost::format($stockValue) }}</span>
                    </div>
                @endif
                @if($missingTotal > 0)
                    <a href="{{ route('stock-detail.index', ['goods_receipt_id' => $receipt->id]) }}" class="d-flex justify-content-between align-items-center mt-3 px-3 py-2 text-decoration-none"
                       style="border-radius:var(--radius-sm);background:var(--warning-soft);color:#92400e;font-size:13px">
                        <span>{{ $missingTotal }} SN {{ \Illuminate\Support\Str::plural('unit', $missingTotal) }} not filled in</span>
                        <span class="fw-semibold">Complete now</span>
                    </a>
                @else
                    <div class="mt-3 px-3 py-2" style="border-radius:var(--radius-sm);background:#f8fafc;color:var(--text-muted);font-size:12.5px">
                        Item data is complete
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span>Received Items</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Item</th>
                        <th>For</th>
                        <th class="text-end" style="width:110px">Qty</th>
                        <th class="text-end" style="width:130px">Unit Cost</th>
                        <th style="width:220px">Destination</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipt->items as $i => $item)
                        @php
                            $isStock = $item->destination !== 'direct';
                            $serials = $isStock ? $item->stocks->pluck('serial_number')->filter() : collect();
                            $missing = $isStock ? $item->stocks->filter(fn ($st) => ! $st->canLeave())->count() : 0;
                            $hasDetail = $item->batch_number || $item->expiry_date || $item->warranty_until || $item->note || $serials->isNotEmpty() || $missing;
                            $isMaterial = (bool) $item->masterProduct?->isMaterial();
                            $logs = $inLogs->get($item->id, collect());
                            $cost = $logs->isNotEmpty() ? $logs->sum(fn ($l) => $l->qty * $l->unit_cost) / max($logs->sum('qty'), 0.0001) : null;
                        @endphp
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold" style="color:var(--text-primary)">{{ $item->masterProduct?->name ?: '—' }}</div>
                                <div style="font-size:11.5px;color:var(--text-muted)">{{ $item->masterProduct?->code ?: '—' }}
                                    @if($item->masterProduct)<span class="gr-kind {{ $isMaterial ? 'gr-kind-material' : 'gr-kind-serial' }} ms-1">{{ $isMaterial ? 'Material' : 'Serial' }}</span>@endif
                                </div>
                            </td>
                            <td>
                                @if($item->project)
                                    <span class="badge" style="background:#fef3c7;color:#92400e;white-space:normal">{{ $item->project->project_number }} — {{ $item->project->project_name }}</span>
                                @else
                                    <span style="color:var(--text-muted)">Free stock</span>
                                @endif
                            </td>
                            <td class="text-end"><strong>{{ $fmtQty($item->qty) }}</strong> <span style="color:var(--text-muted);font-size:12px">{{ $item->unit }}</span></td>
                            <td class="text-end" style="font-variant-numeric:tabular-nums">{{ $cost !== null ? \App\Support\StockCost::format($cost) : '—' }}
                                @if($item->price !== null && ! \App\Models\Currency::isBaseCode($item->currency))
                                    <div style="font-size:11px;color:var(--text-muted);white-space:nowrap" title="Document price × exchange rate used at receipt">
                                        {{ strtoupper($item->currency) }} {{ \App\Support\Number::format($item->price, 2) }}{{ $item->exchange_rate ? ' × '.\App\Support\Number::format($item->exchange_rate, 2) : ' · rate not recorded' }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($isStock)
                                    <span class="badge" style="background:var(--accent-soft);color:var(--accent-hover)">To Warehouse</span>
                                    @if($isMaterial && $logs->isNotEmpty())
                                        <div style="font-size:11.5px;color:var(--text-muted);margin-top:3px">Added to balance → {{ $fmtQty($logs->last()->qty_after) }} {{ $item->unit }} at that time</div>
                                    @elseif(! $isMaterial && $isStock)
                                        <div style="font-size:11.5px;color:var(--text-muted);margin-top:3px">{{ $item->stocks->count() }} stock rows (one per unit)</div>
                                    @endif
                                    @if($item->master_product_id && $receipt->warehouse_id)
                                        <a href="{{ $cardUrl($item) }}" style="font-size:11.5px">Stock card</a>
                                    @endif
                                @else
                                    <span class="badge" style="background:var(--info-soft);color:#4338ca">Direct Use</span>
                                @endif
                            </td>
                        </tr>
                        @if($hasDetail)
                            <tr class="gr-detail-row">
                                <td></td>
                                <td colspan="5" style="font-size:12px;color:var(--text-secondary)">
                                    @if($item->batch_number)<span class="me-3"><span style="color:var(--text-muted)">Batch</span> {{ $item->batch_number }}</span>@endif
                                    @if($item->expiry_date)<span class="me-3"><span style="color:var(--text-muted)">Exp</span> {{ $item->expiry_date->format('d/m/Y') }}</span>@endif
                                    @if($item->warranty_until)<span class="me-3"><span style="color:var(--text-muted)">Warranty</span> {{ $item->warranty_until->format('d/m/Y') }}</span>@endif
                                    @if($item->note)<span class="me-3">Note: {{ $item->note }}</span>@endif
                                    @if($serials->isNotEmpty())
                                        <div>@foreach($serials as $sn)<span class="gr-sn">{{ $sn }}</span>@endforeach</div>
                                    @endif
                                    @if($missing > 0)
                                        <div class="mt-1" style="color:#b45309">{{ $missing }} SN {{ \Illuminate\Support\Str::plural('unit', $missing) }} not filled in</div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4" style="color:var(--text-muted)">No items yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('partials.purchase-documents', [
    'documents' => $documents,
    'types' => $receipt->documentTypes(),
    'uploadUrl' => route('goods-receipt.documents.store', $receipt->id),
    'canUpload' => $canCreate,
    'hint' => 'Optional — supplier delivery note / invoice.',
])
@endsection
