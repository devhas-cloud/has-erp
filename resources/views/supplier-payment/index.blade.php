@extends('layouts.app')

@section('title', 'Pembayaran Supplier')
@section('page-title', 'Pembayaran Supplier')

@section('styles')
@include('partials.list-toolbar')
<style>
    .sp-main { font-weight: 600; color: var(--text-primary); }
    .sp-sub { font-size: 12px; color: var(--text-muted); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Pembayaran Supplier</h1>
        <p class="page-header-sub">Dana keluar ke supplier untuk tagihan terverifikasi. Satu pembayaran bisa melunasi beberapa tagihan, boleh sebagian. Hutang berkurang setelah diposting approver Finance.</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('supplier-payment.create') }}" class="btn-accent"><i class="fa fa-plus"></i><span>Catat Pembayaran</span></a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-money-bill-transfer me-2" style="color:var(--accent)"></i>Daftar Pembayaran Supplier</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="sp-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. pembayaran, referensi, supplier, no. tagihan…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" data-status-select aria-label="Status">
                <option value="">Semua Status</option>
                @foreach(\App\Models\SupplierPayment::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}" data-label="{{ $label }}">{{ $label }}</option>
                @endforeach
            </select>
            @if($suppliers->count() > 1)
            <select class="form-select" data-filter="supplier_id" data-searchable aria-label="Supplier">
                <option value="">Semua Supplier</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>
            @endif
            <select class="form-select" data-filter="method" aria-label="Metode">
                <option value="">Semua Metode</option>
                @foreach(\App\Models\SupplierPayment::METHOD_LABELS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="lt-date" title="Tanggal bayar">
                <input type="date" class="form-control" data-filter="date_from" aria-label="Dari tanggal">
                <span>–</span>
                <input type="date" class="form-control" data-filter="date_to" aria-label="Sampai tanggal">
            </div>
            <select class="form-select" data-filter="mine" aria-label="Pembuat">
                <option value="">Semua Pembuat</option>
                <option value="1">Dibuat oleh Saya</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="sp-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Pembayaran</th>
                        <th>Supplier</th>
                        <th>Tanggal</th>
                        <th>Metode / Referensi</th>
                        <th class="text-center">Tagihan</th>
                        <th class="text-end">Nominal</th>
                        <th>Status</th>
                        <th class="text-center" style="width:70px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const spShowUrl = '{{ route("supplier-payment.show", "__ID__") }}';
let spTable = null;
let spToolbar = null;

function spEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    spToolbar = ListToolbar('#sp-toolbar', function() { return spTable; });
    spTable = $('#sp-table').DataTable({
        layout: ListToolbar.layout,
        language: ListToolbar.language,
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("supplier-payment.data") }}', data: function(d) { return spToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, className: 'text-center' },
            { data: 'payment_number', orderable: false, render: function(data, type, row) { return '<a href="' + spShowUrl.replace('__ID__', row.id) + '" class="sp-main text-decoration-none">' + spEsc(data) + '</a>'; } },
            { data: 'supplier_name', orderable: false, render: function(data) { return spEsc(data); } },
            { data: 'payment_date', orderable: false },
            { data: 'method_label', orderable: false, render: function(data, type, row) { return spEsc(data) + '<div class="sp-sub">' + spEsc(row.reference_number) + '</div>'; } },
            { data: 'bill_count', orderable: false, className: 'text-center' },
            { data: 'amount_label', orderable: false, className: 'text-end fw-semibold', render: function(data) { return '<span style="white-space:nowrap">' + data + '</span>'; } },
            { data: 'status_badge', orderable: false },
            { data: 'id', orderable: false, className: 'text-center', render: function(data) { return '<a href="' + spShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>'; } }
        ]
    });
    spTable.on('xhr.dt', function(e, settings, json) { spToolbar.statusCounts(json); });
});
</script>
@endsection
