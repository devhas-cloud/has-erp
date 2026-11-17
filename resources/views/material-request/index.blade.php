@extends('layouts.app')

@section('title', 'Material Request')
@section('page-title', 'Material Request')

@section('styles')
@include('partials.list-toolbar')
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Material Request</h1>
        <p class="page-header-sub">Permintaan material project untuk produksi</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('material-request.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Material Request</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-clipboard-list me-2" style="color:var(--accent)"></i>Daftar Material Request</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="mr-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. MR, project, material…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" aria-label="Status">
                <option value="">Semua Status</option>
                @foreach(\App\Models\MaterialRequest::STATUS_LABELS as $key => $label)
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
            <div class="lt-date" title="Tanggal MR">
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
            <table id="mr-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. MR</th>
                        <th>Tanggal</th>
                        <th>Project</th>
                        <th>Branch</th>
                        <th class="text-center">Material</th>
                        <th style="width:130px">Diserahkan</th>
                        <th>Status</th>
                        <th class="text-center" style="width:90px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let mrTable = null;
let mrToolbar = null;
const mrShowUrl = '{{ route("material-request.show", "__ID__") }}';
const mrEditUrl = '{{ route("material-request.edit", "__ID__") }}';

function mrEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    mrToolbar = ListToolbar('#mr-toolbar', function() { return mrTable; });
    mrTable = $('#mr-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Tidak ada Material Request yang cocok dengan pencarian / filter.</span>',
            emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Belum ada Material Request.</span>'
        },
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("material-request.data") }}', data: function(d) { return mrToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'request_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<strong style="color:var(--text-primary);font-variant-numeric:tabular-nums">' + mrEsc(data) + '</strong>'
                        + '<div style="font-size:11.5px;color:var(--text-muted)">' + mrEsc(row.creator) + '</div>';
                } },
            { data: 'request_date', orderable: false, searchable: false },
            { data: 'project', orderable: false, searchable: true, render: function(data) { return mrEsc(data); } },
            { data: 'branch', orderable: false, searchable: false, render: function(data) { return mrEsc(data); } },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'progress', orderable: false, searchable: false,
                render: function(data) {
                    return '<div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:6px"><div class="progress-bar" style="width:' + data + '%;background:var(--accent)"></div></div>'
                        + '<span style="font-size:11.5px;color:var(--text-muted);min-width:32px">' + data + '%</span></div>';
                } },
            { data: 'status_badge', orderable: false, searchable: false },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    btn += '<a href="' + mrShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    @if($canUpdate)
                    if (row.can_edit) btn += '<a href="' + mrEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Ubah"><i class="fa fa-pen"></i></a>';
                    @endif
                    return btn + '</div>';
                } }
        ]
    });
});
</script>
@endsection
