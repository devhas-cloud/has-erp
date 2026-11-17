@extends('layouts.app')

@section('title', 'Retur ke Supplier')
@section('page-title', 'Retur ke Supplier')

@section('styles')
@include('partials.list-toolbar')
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Retur ke Supplier</h1>
        <p class="page-header-sub">Barang yang sudah diterima tapi dikembalikan ke supplier (rusak / salah kirim). Stok berkurang, barang retur tidak bisa ditagih, dan PO kembali menunggu barang pengganti.</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('supplier-return.create') }}" class="btn-accent"><i class="fa fa-plus"></i><span>Buat Retur</span></a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-truck-arrow-right me-2" style="color:var(--accent)"></i>Daftar Retur</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="sr-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. retur, PO, supplier, part number, alasan…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            @if($suppliers->count() > 1)
            <select class="form-select" data-filter="supplier" data-searchable aria-label="Supplier">
                <option value="">Semua Supplier</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>
            @endif
            <select class="form-select" data-filter="warehouse" aria-label="Gudang">
                <option value="">Semua Gudang</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
                @if($hasDirect)
                    <option value="direct">Langsung pakai</option>
                @endif
            </select>
            <div class="lt-date" title="Rentang tanggal retur">
                <input type="date" class="form-control" data-filter="date_from" aria-label="Dari tanggal">
                <span>–</span>
                <input type="date" class="form-control" data-filter="date_to" aria-label="Sampai tanggal">
            </div>
            <select class="form-select" data-filter="mine" aria-label="Pembuat">
                <option value="">Semua Pembuat</option>
                <option value="1">Dibuat Saya</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="sr-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Retur</th>
                        <th>Tanggal</th>
                        <th>PO</th>
                        <th>Supplier</th>
                        <th>Gudang</th>
                        <th class="text-center">Qty</th>
                        <th>Alasan</th>
                        <th>Dibuat Oleh</th>
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
let srTable = null;
let srToolbar = null;

const srShowUrl = '{{ route("supplier-return.show", "__ID__") }}';
const srPoShowUrl = '{{ route("purchase-order.show", "__ID__") }}';

function srEsc(text) { return $('<div>').text(text == null ? '' : text).html(); }

function initSrTable() {
    if (srTable) srTable.destroy();

    srTable = $('#sr-table').DataTable({
        layout: ListToolbar.layout,
        language: ListToolbar.language,
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("supplier-return.data") }}', data: function(d) { return srToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'return_number', orderable: false,
                render: function(data, type, row) {
                    return '<a href="' + srShowUrl.replace('__ID__', row.id) + '" class="fw-semibold" style="font-variant-numeric:tabular-nums">' + srEsc(data) + '</a>';
                }
            },
            { data: 'date', orderable: false },
            { data: 'po_number', orderable: false,
                render: function(data, type, row) {
                    return row.purchase_order_id
                        ? '<a href="' + srPoShowUrl.replace('__ID__', row.purchase_order_id) + '">' + srEsc(data) + '</a>'
                        : srEsc(data);
                }
            },
            { data: 'supplier_name', orderable: false,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + srEsc(data) + '</strong>'; }
            },
            { data: 'warehouse_name', orderable: false, render: srEsc },
            { data: 'qty', orderable: false, className: 'text-center' },
            { data: 'reason', orderable: false,
                render: function(data) { return '<span style="font-size:12.5px;display:inline-block;max-width:320px">' + srEsc(data) + '</span>'; }
            },
            { data: 'creator_name', orderable: false, render: srEsc },
            { data: 'id', orderable: false, className: 'text-center',
                render: function(data) {
                    return '<a href="' + srShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                }
            }
        ]
    });
}

$(document).ready(function() {
    srToolbar = ListToolbar('#sr-toolbar', function() { return srTable; });
    initSrTable();
});
</script>
@endsection
