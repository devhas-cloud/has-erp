@extends('layouts.app')

@section('title', 'Serah Terima Material')
@section('page-title', 'Serah Terima Material')

@section('styles')
@include('partials.list-toolbar')
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Serah Terima Material</h1>
        <p class="page-header-sub">Material dari gudang yang diserahkan ke produksi project</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('material-handover.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Serah Terima</span>
            @if($pendingCount > 0)<span class="badge rounded-pill ms-1" style="background:#fff;color:var(--accent)">{{ $pendingCount }}</span>@endif
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-people-carry-box me-2" style="color:var(--accent)"></i>Daftar Serah Terima</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="mh-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. serah terima, MR, project, SN…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
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
            <div class="lt-date" title="Tanggal serah terima">
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
            <table id="mh-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Serah Terima</th>
                        <th>Tanggal</th>
                        <th>Material Request</th>
                        <th>Project</th>
                        <th>Branch</th>
                        <th class="text-center">Baris</th>
                        <th class="text-end">Nilai</th>
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
let mhTable = null;
let mhToolbar = null;
const mhShowUrl = '{{ route("material-handover.show", "__ID__") }}';

function mhEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    mhToolbar = ListToolbar('#mh-toolbar', function() { return mhTable; });
    mhTable = $('#mh-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Tidak ada serah terima yang cocok dengan pencarian / filter.</span>',
            emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Belum ada serah terima.</span>'
        },
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("material-handover.data") }}', data: function(d) { return mhToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'handover_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<strong style="color:var(--text-primary);font-variant-numeric:tabular-nums">' + mhEsc(data) + '</strong>'
                        + '<div style="font-size:11.5px;color:var(--text-muted)">' + mhEsc(row.creator) + '</div>';
                } },
            { data: 'handover_date', orderable: false, searchable: false },
            { data: 'request_number', orderable: false, searchable: false, render: function(data) { return mhEsc(data); } },
            { data: 'project', orderable: false, searchable: false, render: function(data) { return mhEsc(data); } },
            { data: 'branch', orderable: false, searchable: false, render: function(data) { return mhEsc(data); } },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'total_value', orderable: false, searchable: false, className: 'text-end' },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) { return '<a href="' + mhShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>'; } }
        ]
    });
});
</script>
@endsection
