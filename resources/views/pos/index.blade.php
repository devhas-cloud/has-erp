@extends('layouts.app')

@section('title', 'POS')
@section('page-title', 'POS (Point of Sale)')

@section('styles')
@include('partials.list-toolbar')
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">POS</h1>
        <p class="page-header-sub">Point of Sale (Sales Order) — dokumen penjualan datar dari quotation, item berpenamaan customer</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('pos.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat POS</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-cash-register me-2" style="color:var(--accent)"></i>Daftar POS</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="pos-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. POS, customer, quotation, project, item…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" aria-label="Status">
                <option value="">Semua Status</option>
                @foreach(\App\Models\Pos::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="lt-date" title="Tanggal">
                <input type="date" class="form-control" data-filter="date_from" aria-label="Dari tanggal">
                <span>–</span>
                <input type="date" class="form-control" data-filter="date_to" aria-label="Sampai tanggal">
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="pos-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. POS</th>
                        <th>Tanggal</th>
                        <th>Customer</th>
                        <th class="text-center">Item</th>
                        <th class="text-end">Total</th>
                        <th>Project</th>
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
let posTable = null;
let posToolbar = null;
const posShowUrl = '{{ route("pos.show", "__ID__") }}';

function posEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    posToolbar = ListToolbar('#pos-toolbar', function() { return posTable; });
    posTable = $('#pos-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Tidak ada POS yang cocok dengan pencarian / filter.</span>',
            emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Belum ada POS.</span>'
        },
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("pos.data") }}', data: function(d) { return posToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'pos_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<strong style="color:var(--text-primary);font-variant-numeric:tabular-nums">' + posEsc(data) + '</strong>'
                        + '<div style="font-size:11.5px;color:var(--text-muted)">' + posEsc(row.creator) + '</div>';
                } },
            { data: 'date', orderable: false, searchable: false },
            { data: 'company', orderable: false, searchable: false, render: function(data) { return posEsc(data); } },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'grand_total', orderable: false, searchable: false, className: 'text-end' },
            { data: 'project', orderable: false, searchable: false, render: function(data) { return posEsc(data); } },
            { data: 'status_badge', orderable: false, searchable: false },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) { return '<a href="' + posShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>'; } }
        ]
    });
});
</script>
@endsection
