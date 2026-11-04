@extends('layouts.app')

@section('title', 'Refund Supplier')
@section('page-title', 'Refund Supplier')

@section('styles')
@include('partials.list-toolbar')
<style>
    .sr-sub { font-size: 12px; color: var(--text-muted); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Refund Supplier</h1>
        <p class="page-header-sub">Uang muka PO yang tidak terpakai (PO batal atau barang kurang) wajib dikembalikan supplier. Catat penerimaan refund di sini.</p>
    </div>
    @if($canCreate && $pending->isNotEmpty())
    <div class="page-header-actions">
        <a href="{{ route('supplier-refund.create') }}" class="btn-accent"><i class="fa fa-plus"></i><span>Catat Refund</span></a>
    </div>
    @endif
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom"><span><i class="fa-solid fa-hourglass-half me-2" style="color:var(--danger)"></i>Menunggu Refund <span class="sr-sub">({{ $pending->count() }} PO)</span></span></div>
    <div class="card-body-custom p-2">
        @if($pending->isEmpty())
            <div class="text-center py-3 sr-sub"><i class="fa-solid fa-circle-check me-1" style="color:var(--accent)"></i>Tidak ada uang muka yang menunggu dikembalikan.</div>
        @else
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead><tr><th>PO</th><th>Supplier</th><th>Alasan</th><th class="text-end">Uang Muka Dibayar</th><th class="text-end">Harus Dikembalikan</th><th class="text-center" style="width:120px"></th></tr></thead>
                <tbody>
                    @foreach($pending as $po)
                        <tr>
                            <td><a href="{{ route('purchase-order.show', $po->id) }}" class="fw-semibold">{{ $po->po_number }}</a></td>
                            <td>{{ $po->supplier?->name ?? $po->supplier_name }}</td>
                            <td class="sr-sub">{{ $po->status === 'cancelled' ? 'PO dibatalkan' : 'Barang lengkap, sisa uang muka' }}</td>
                            <td class="text-end" style="white-space:nowrap">{{ $po->currencyCode() }} {{ \App\Models\SupplierBill::formatMoney($po->advancePaid()) }}</td>
                            <td class="text-end fw-semibold text-danger" style="white-space:nowrap">{{ $po->currencyCode() }} {{ \App\Models\SupplierBill::formatMoney($po->refundDue()) }}</td>
                            <td class="text-center">@if($canCreate)<a href="{{ route('supplier-refund.create', ['purchase_order_id' => $po->id]) }}" class="btn btn-soft btn-sm">Catat Refund</a>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-rotate-left me-2" style="color:var(--accent)"></i>Riwayat Refund</span></div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="sr-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. refund, referensi, supplier, no. PO…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" data-status-select aria-label="Status">
                <option value="">Semua Status</option>
                @foreach(\App\Models\SupplierRefund::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}" data-label="{{ $label }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="lt-date" title="Tanggal refund">
                <input type="date" class="form-control" data-filter="date_from" aria-label="Dari tanggal">
                <span>–</span>
                <input type="date" class="form-control" data-filter="date_to" aria-label="Sampai tanggal">
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="sr-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead><tr><th style="width:50px">#</th><th>No. Refund</th><th>Supplier</th><th>PO</th><th>Tanggal</th><th>Referensi</th><th class="text-end">Nominal</th><th>Status</th><th class="text-center" style="width:70px">Aksi</th></tr></thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const srShowUrl = '{{ route("supplier-refund.show", "__ID__") }}';
let srTable = null;
let srToolbar = null;
function srEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    srToolbar = ListToolbar('#sr-toolbar', function() { return srTable; });
    srTable = $('#sr-table').DataTable({
        layout: ListToolbar.layout,
        language: ListToolbar.language,
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("supplier-refund.data") }}', data: function(d) { return srToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, className: 'text-center' },
            { data: 'refund_number', orderable: false, render: function(data, type, row) { return '<a href="' + srShowUrl.replace('__ID__', row.id) + '" class="fw-semibold text-decoration-none">' + srEsc(data) + '</a>'; } },
            { data: 'supplier_name', orderable: false, render: function(d) { return srEsc(d); } },
            { data: 'po_number', orderable: false, render: function(d) { return srEsc(d); } },
            { data: 'refund_date', orderable: false },
            { data: 'reference_number', orderable: false, render: function(d) { return srEsc(d); } },
            { data: 'amount_label', orderable: false, className: 'text-end fw-semibold', render: function(d) { return '<span style="white-space:nowrap">' + d + '</span>'; } },
            { data: 'status_badge', orderable: false },
            { data: 'id', orderable: false, className: 'text-center', render: function(d) { return '<a href="' + srShowUrl.replace('__ID__', d) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>'; } }
        ]
    });
    srTable.on('xhr.dt', function(e, settings, json) { srToolbar.statusCounts(json); });
});
</script>
@endsection
