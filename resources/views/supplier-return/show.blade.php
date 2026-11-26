@extends('layouts.app')

@section('title', 'Supplier Return Details')
@section('page-title', 'Supplier Return Details')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Return {{ $return->return_number }}</h1>
        <p class="page-header-sub">{{ $return->supplier?->name }} &middot; PO <a href="{{ route('purchase-order.show', $return->purchase_order_id) }}">{{ $return->purchaseOrder?->po_number }}</a> &middot; {{ $return->return_date?->format('d/m/Y') }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('supplier-return.index') }}" class="btn btn-secondary btn-sm">Back</a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom"><span>Information</span></div>
    <div class="card-body-custom">
        <table class="info-table" style="width:100%">
            <tr><td>Status</td><td><span class="status-badge status-active">Posted</span></td></tr>
            <tr><td>Source Warehouse</td><td>{{ $return->warehouse ? $return->warehouse->code.' — '.$return->warehouse->name : 'Direct use (no stock)' }}</td></tr>
            <tr><td>Reason</td><td>{{ $return->reason }}</td></tr>
            <tr><td>Created By</td><td>{{ $return->creator?->display_name ?? '—' }} · {{ $return->created_at?->format('d/m/Y H:i') }}</td></tr>
        </table>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom"><span>Returned Items</span></div>
    <div class="card-body-custom p-2">
        <table class="table table-custom align-middle mb-0">
            <thead><tr><th>Item</th><th>Project</th><th class="text-center">Qty</th><th>From</th></tr></thead>
            <tbody>
                @foreach($return->items as $item)
                    <tr>
                        <td><div class="fw-semibold" style="font-size:13px">{{ $item->purchaseOrderItem?->part_number ?: '—' }}</div><div style="font-size:12px;color:var(--text-muted)">{!! $item->purchaseOrderItem?->description !!}</div></td>
                        <td style="font-size:12.5px">{{ $item->project ? $item->project->project_number.' — '.$item->project->project_name : 'Stock / Internal' }}</td>
                        <td class="text-center">{{ \App\Support\StockCost::qty($item->qty) }} {{ $item->unit }}</td>
                        <td>
                            @if($item->destination === 'warehouse')
                                Warehouse stock
                                @if($item->stock)<div style="font-size:12px"><a href="{{ route('stock-detail.show', $item->stock_id) }}">{{ $item->stock->identity_label }}</a></div>@endif
                            @else
                                Direct use
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
