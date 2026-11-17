@extends('layouts.app')

@section('title', 'Packing List')
@section('page-title', 'Packing List')

@section('styles')
@include('partials.list-toolbar')
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Packing List</h1>
        <p class="page-header-sub">Barang milik project yang dikemas per koli untuk dikirim lewat Delivery Order</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('packing-list.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Packing List</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-box me-2" style="color:var(--accent)"></i>Daftar Packing List</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="pkl-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. PKL, project, penerima, PO, SN…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" aria-label="Status">
                <option value="">Semua Status</option>
                @foreach(\App\Models\PackingList::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <select class="form-select" data-filter="project" data-searchable aria-label="Project">
                <option value="">Semua Project</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->project_number }} — {{ $p->project_name }}</option>
                @endforeach
            </select>
            @if($warehouses->count() > 1)
            <select class="form-select" data-filter="warehouse" aria-label="Gudang">
                <option value="">Semua Gudang</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>
                @endforeach
            </select>
            @endif
            <div class="lt-date" title="Tanggal pengiriman">
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
            <table id="pkl-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. PKL</th>
                        <th>Tgl Kirim</th>
                        <th>Project</th>
                        <th>Penerima</th>
                        <th>Gudang</th>
                        <th class="text-center">Koli</th>
                        <th class="text-end">Berat (kg)</th>
                        <th>DO</th>
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
let pklTable = null;
let pklToolbar = null;
const pklShowUrl = '{{ route("packing-list.show", "__ID__") }}';

function pklEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    pklToolbar = ListToolbar('#pkl-toolbar', function() { return pklTable; });
    pklTable = $('#pkl-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Tidak ada Packing List yang cocok dengan pencarian / filter.</span>',
            emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Belum ada Packing List.</span>'
        },
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("packing-list.data") }}', data: function(d) { return pklToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'packing_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<strong style="color:var(--text-primary);font-variant-numeric:tabular-nums">' + pklEsc(data) + '</strong>'
                        + '<div style="font-size:11.5px;color:var(--text-muted)">' + pklEsc(row.creator) + '</div>';
                } },
            { data: 'packing_date', orderable: false, searchable: false },
            { data: 'project', orderable: false, searchable: false, render: function(data) { return pklEsc(data); } },
            { data: 'recipient', orderable: false, searchable: false, render: function(data) { return pklEsc(data); } },
            { data: 'warehouse', orderable: false, searchable: false, render: function(data) { return pklEsc(data); } },
            { data: 'boxes', orderable: false, searchable: false, className: 'text-center' },
            { data: 'weight', orderable: false, searchable: false, className: 'text-end' },
            { data: 'do_number', orderable: false, searchable: false, render: function(data) { return data ? pklEsc(data) : '<span style="color:var(--text-muted)">—</span>'; } },
            { data: 'status_badge', orderable: false, searchable: false },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) { return '<a href="' + pklShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>'; } }
        ]
    });
});
</script>
@endsection
