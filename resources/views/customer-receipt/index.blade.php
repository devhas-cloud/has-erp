@extends('layouts.app')

@section('title', 'Penerimaan Customer')
@section('page-title', 'Penerimaan Customer')

@section('styles')
@include('partials.list-toolbar')
<style>
    .cr-main { font-weight: 600; color: var(--text-primary); }
    .cr-sub { font-size: 12px; color: var(--text-muted); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Penerimaan Customer</h1>
        <p class="page-header-sub">Dana masuk dari customer untuk invoice yang sudah Approved. Nominal mengikuti total invoice; invoice menjadi Lunas setelah penerimaan diposting approver Finance.</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('customer-receipt.create') }}" class="btn-accent"><i class="fa fa-plus"></i><span>Catat Penerimaan</span></a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-hand-holding-dollar me-2" style="color:var(--accent)"></i>Daftar Penerimaan Customer</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="cr-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. penerimaan, invoice, referensi, customer, project…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" data-status-select aria-label="Status">
                <option value="">Semua Status</option>
                @foreach(\App\Models\CustomerReceipt::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}" data-label="{{ $label }}">{{ $label }}</option>
                @endforeach
            </select>
            @if($companies->count() > 1)
            <select class="form-select" data-filter="company_id" data-searchable aria-label="Customer">
                <option value="">Semua Customer</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->account_name }}</option>
                @endforeach
            </select>
            @endif
            <select class="form-select" data-filter="method" aria-label="Metode">
                <option value="">Semua Metode</option>
                @foreach(\App\Models\CustomerReceipt::METHOD_LABELS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="lt-date" title="Tanggal terima">
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
            <table id="cr-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Penerimaan</th>
                        <th>Customer / Project</th>
                        <th>Invoice</th>
                        <th>Tanggal</th>
                        <th>Metode / Referensi</th>
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
const crShowUrl = '{{ route("customer-receipt.show", "__ID__") }}';
const crInvoiceUrl = '{{ route("invoice.show", "__ID__") }}';
let crTable = null;
let crToolbar = null;

function crEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    crToolbar = ListToolbar('#cr-toolbar', function() { return crTable; });
    crTable = $('#cr-table').DataTable({
        layout: ListToolbar.layout,
        language: ListToolbar.language,
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("customer-receipt.data") }}', data: function(d) { return crToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, className: 'text-center' },
            { data: 'receipt_number', orderable: false, render: function(data, type, row) { return '<a href="' + crShowUrl.replace('__ID__', row.id) + '" class="cr-main text-decoration-none">' + crEsc(data) + '</a>'; } },
            { data: 'company_name', orderable: false, render: function(data, type, row) { return crEsc(data) + '<div class="cr-sub">' + crEsc(row.project_label) + '</div>'; } },
            { data: 'invoice_number', orderable: false, render: function(data, type, row) { return '<a href="' + crInvoiceUrl.replace('__ID__', row.invoice_id) + '" class="text-decoration-none">' + crEsc(data) + '</a>'; } },
            { data: 'receipt_date', orderable: false },
            { data: 'method_label', orderable: false, render: function(data, type, row) { return crEsc(data) + '<div class="cr-sub">' + crEsc(row.reference_number) + '</div>'; } },
            { data: 'amount_label', orderable: false, className: 'text-end fw-semibold', render: function(data) { return '<span style="white-space:nowrap">' + crEsc(data) + '</span>'; } },
            { data: 'status_badge', orderable: false },
            { data: 'id', orderable: false, className: 'text-center', render: function(data) { return '<a href="' + crShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>'; } }
        ]
    });
    crTable.on('xhr.dt', function(e, settings, json) { crToolbar.statusCounts(json); });
});
</script>
@endsection
