@extends('layouts.app')

@section('title', 'Detail Retur ke Supplier')
@section('page-title', 'Detail Retur ke Supplier')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Retur {{ $return->return_number }}</h1>
        <p class="page-header-sub">{{ $return->supplier?->name }} &middot; PO <a href="{{ route('purchase-order.show', $return->purchase_order_id) }}">{{ $return->purchaseOrder?->po_number }}</a> &middot; {{ $return->return_date?->format('d/m/Y') }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('supplier-return.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom"><span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span></div>
    <div class="card-body-custom">
        <table class="info-table" style="width:100%">
            <tr><td>Status</td><td><span class="status-badge status-active">Posted</span></td></tr>
            <tr><td>Gudang Asal</td><td>{{ $return->warehouse ? $return->warehouse->code.' — '.$return->warehouse->name : 'Langsung pakai (tanpa stok)' }}</td></tr>
            <tr><td>Alasan</td><td>{{ $return->reason }}</td></tr>
            <tr><td>Dibuat</td><td>{{ $return->creator?->display_name ?? '—' }} · {{ $return->created_at?->format('d/m/Y H:i') }}</td></tr>
        </table>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Barang Diretur</span></div>
    <div class="card-body-custom p-2">
        <table class="table table-custom align-middle mb-0">
            <thead><tr><th>Barang</th><th>Project</th><th class="text-center">Qty</th><th>Dari</th></tr></thead>
            <tbody>
                @foreach($return->items as $item)
                    <tr>
                        <td><div class="fw-semibold" style="font-size:13px">{{ $item->purchaseOrderItem?->part_number ?: '—' }}</div><div style="font-size:12px;color:var(--text-muted)">{!! $item->purchaseOrderItem?->description !!}</div></td>
                        <td style="font-size:12.5px">{{ $item->project ? $item->project->project_number.' — '.$item->project->project_name : 'Stok / Internal' }}</td>
                        <td class="text-center">{{ $item->qty }} {{ $item->unit }}</td>
                        <td>{{ $item->destination === 'warehouse' ? 'Stok gudang' : 'Langsung pakai' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
