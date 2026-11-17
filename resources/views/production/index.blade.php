@extends('layouts.app')

@section('title', 'Produksi')
@section('page-title', 'Produksi')

@section('styles')
@include('partials.list-toolbar')
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Produksi</h1>
        <p class="page-header-sub">Material di Produksi project yang dirakit menjadi barang jadi</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('production.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Catat Produksi</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-industry me-2" style="color:var(--accent)"></i>Daftar Produksi</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="mo-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. produksi, barang jadi, project, SN…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" aria-label="Status">
                <option value="">Semua Status</option>
                @foreach(\App\Models\Production::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <select class="form-select" data-filter="project" data-searchable aria-label="Project">
                <option value="">Semua Project</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->project_number }} — {{ $p->project_name }}</option>
                @endforeach
            </select>
            @if($branches->count() > 1)
            <select class="form-select" data-filter="branch" aria-label="Branch">
                <option value="">Semua Branch</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                @endforeach
            </select>
            @endif
            <div class="lt-date" title="Tanggal produksi">
                <input type="date" class="form-control" data-filter="date_from" aria-label="Dari tanggal">
                <span>–</span>
                <input type="date" class="form-control" data-filter="date_to" aria-label="Sampai tanggal">
            </div>
            <select class="form-select" data-filter="mine" aria-label="Pencatat">
                <option value="">Semua Pencatat</option>
                <option value="1">Dicatat oleh Saya</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="mo-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Produksi</th>
                        <th>Tanggal</th>
                        <th>Project</th>
                        <th>Barang Jadi</th>
                        <th class="text-end">Qty</th>
                        <th>Gudang</th>
                        <th class="text-end">Biaya</th>
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
let moTable = null;
let moToolbar = null;
const moShowUrl = '{{ route("production.show", "__ID__") }}';

function moEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    moToolbar = ListToolbar('#mo-toolbar', function() { return moTable; });
    moTable = $('#mo-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Tidak ada produksi yang cocok dengan pencarian / filter.</span>',
            emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Belum ada produksi.</span>'
        },
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("production.data") }}', data: function(d) { return moToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'production_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<strong style="color:var(--text-primary);font-variant-numeric:tabular-nums">' + moEsc(data) + '</strong>'
                        + '<div style="font-size:11.5px;color:var(--text-muted)">' + moEsc(row.creator) + '</div>';
                } },
            { data: 'production_date', orderable: false, searchable: false },
            { data: 'project', orderable: false, searchable: false, render: function(data) { return moEsc(data); } },
            { data: 'product', orderable: false, searchable: false, render: function(data) { return moEsc(data); } },
            { data: 'qty', orderable: false, searchable: false, className: 'text-end' },
            { data: 'warehouse', orderable: false, searchable: false, render: function(data) { return moEsc(data); } },
            { data: 'total_cost', orderable: false, searchable: false, className: 'text-end' },
            { data: 'status_badge', orderable: false, searchable: false },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) { return '<a href="' + moShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>'; } }
        ]
    });
});
</script>
@endsection
