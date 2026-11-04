@extends('layouts.app')

@section('title', 'Tagihan Supplier')
@section('page-title', 'Tagihan Supplier')

@section('styles')
@include('partials.list-toolbar')
<style>
    .sb-main { font-weight: 600; color: var(--text-primary); }
    .sb-sub { font-size: 12px; color: var(--text-muted); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Tagihan Supplier</h1>
        <p class="page-header-sub">Invoice supplier yang dicocokkan dengan PO dan Penerimaan Barang (3-way match). Setelah diverifikasi Finance, tagihan tercatat sebagai hutang.</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('supplier-bill.create', ['type' => 'advance']) }}" class="btn btn-secondary btn-sm"><i class="fa fa-hand-holding-dollar me-1"></i> Uang Muka</a>
        <a href="{{ route('supplier-bill.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Catat Tagihan</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Daftar Tagihan Supplier</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="sb-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. tagihan, no. invoice, faktur, supplier, no. PO…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" data-status-select aria-label="Status">
                <option value="">Semua Status</option>
                @foreach(\App\Models\SupplierBill::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}" data-label="{{ $label }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @if($suppliers->count() > 1 || request('supplier_id'))
            <select class="form-select" data-filter="supplier_id" data-searchable aria-label="Supplier">
                <option value="">Semua Supplier</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((int) request('supplier_id') === $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
            @endif
            <select class="form-select" data-filter="bill_type" aria-label="Tipe tagihan">
                <option value="">Semua Tipe</option>
                <option value="regular">Reguler</option>
                <option value="advance">Uang Muka</option>
            </select>
            <select class="form-select" data-filter="pph_pending" aria-label="Bukti potong">
                <option value="">Semua PPh</option>
                <option value="1">Bukti potong belum ada</option>
            </select>
            <select class="form-select" data-filter="overdue" aria-label="Jatuh tempo">
                <option value="">Semua Jatuh Tempo</option>
                <option value="1" @selected(request('overdue') == 1)>Terlambat bayar</option>
            </select>
            <div class="lt-date" title="Tanggal tagihan">
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
            <table id="sb-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Tagihan</th>
                        <th>Supplier</th>
                        <th>Sumber</th>
                        <th>Tanggal</th>
                        <th>Jatuh Tempo</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Sisa Hutang</th>
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
const sbShowUrl = '{{ route("supplier-bill.show", "__ID__") }}';
let sbTable = null;
let sbToolbar = null;

function sbEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    sbToolbar = ListToolbar('#sb-toolbar', function() { return sbTable; });
    sbTable = $('#sb-table').DataTable({
        layout: ListToolbar.layout,
        language: ListToolbar.language,
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("supplier-bill.data") }}', data: function(d) { return sbToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'bill_number', orderable: false,
                render: function(data, type, row) {
                    return '<a href="' + sbShowUrl.replace('__ID__', row.id) + '" class="sb-main text-decoration-none">' + sbEsc(data) + '</a>'
                        + (row.bill_type === 'advance' ? ' <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px">Uang Muka</span>' : '')
                        + '<div class="sb-sub">Inv. ' + sbEsc(row.supplier_invoice_number) + '</div>';
                }
            },
            { data: 'supplier_name', orderable: false, render: function(data) { return sbEsc(data); } },
            { data: 'source', orderable: false, render: function(data) { return '<span class="sb-sub">' + sbEsc(data) + '</span>'; } },
            { data: 'bill_date', orderable: false },
            { data: 'due_date', orderable: false,
                render: function(data, type, row) { return row.is_overdue ? '<span class="text-danger fw-semibold">' + data + ' <i class="fa fa-exclamation-circle"></i></span>' : data; }
            },
            { data: 'total_label', orderable: false, className: 'text-end', render: function(data) { return '<span style="white-space:nowrap">' + data + '</span>'; } },
            { data: 'outstanding_label', orderable: false, className: 'text-end fw-semibold', render: function(data) { return '<span style="white-space:nowrap">' + data + '</span>'; } },
            { data: 'status_badge', orderable: false },
            { data: 'id', orderable: false, className: 'text-center',
                render: function(data) { return '<a href="' + sbShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>'; }
            }
        ]
    });
    sbTable.on('xhr.dt', function(e, settings, json) { sbToolbar.statusCounts(json); });
});
</script>
@endsection
