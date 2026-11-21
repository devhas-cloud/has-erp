@extends('layouts.app')

@section('title', 'Stock Transfers')
@section('page-title', 'Stock Transfers')

@section('styles')
@include('partials.list-toolbar')
<style>
    #st-table th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    .st-sub { font-size: 11.5px; color: var(--text-muted); }
    .st-route { display: flex; align-items: center; gap: 8px; }
    .st-route .st-node { min-width: 0; }
    .st-route .st-wh { font-weight: 600; font-size: 13px; }
    .st-route .st-arrow { color: var(--accent); flex-shrink: 0; }
    .st-chip { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 999px; white-space: nowrap; }
    .st-chip-material { background: #e0f2fe; color: #075985; }
    .st-chip-serial { background: #fef3c7; color: #92400e; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Stock Transfers</h1>
        <p class="page-header-sub">Move stock between warehouses and/or ownership within one branch — allocate free stock to a project, release an allocation, or move warehouse.</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('stock-transfer.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>New Transfer</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-arrow-right-arrow-left me-2" style="color:var(--accent)"></i>Stock Transfer List</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="st-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Search transfer no., product, SN, warehouse, project…" autocomplete="off">
                <button type="button" class="lt-clear" title="Clear search"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="kind" aria-label="Transfer type">
                <option value="">All Types</option>
                @foreach(\App\Http\Controllers\StockTransferController::KIND_FILTERS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            @if($warehouses->count() > 1)
            <select class="form-select" data-filter="warehouse" aria-label="Warehouse">
                <option value="">All Warehouses</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>
                @endforeach
            </select>
            @endif
            <select class="form-select" data-filter="ownership" data-searchable aria-label="Ownership">
                <option value="">All Ownership</option>
                <option value="free">Involves free stock</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}">Project {{ $p->project_number }} — {{ $p->project_name }}</option>
                @endforeach
            </select>
            <div class="lt-date" title="Transfer date">
                <input type="date" class="form-control" data-filter="date_from" aria-label="From date">
                <span>–</span>
                <input type="date" class="form-control" data-filter="date_to" aria-label="To date">
            </div>
            <select class="form-select" data-filter="mine" aria-label="Created by">
                <option value="">All Creators</option>
                <option value="1">Created by Me</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="st-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Transfer No.</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Route</th>
                        <th class="text-center">Contents</th>
                        <th class="text-end">Value</th>
                        <th class="text-center" style="width:70px">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let stTable = null;
let stToolbar = null;
const stShowUrl = '{{ route("stock-transfer.show", "__ID__") }}';

function stEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    stToolbar = ListToolbar('#st-toolbar', function() { return stTable; });
    stTable = $('#st-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>No transfers match the search / filters.</span>',
            emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>No stock transfers yet.</span>'
        },
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("stock-transfer.data") }}', data: function(d) { return stToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'transfer_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<a href="' + stShowUrl.replace('__ID__', row.id) + '" style="font-weight:700;color:var(--text-primary);font-variant-numeric:tabular-nums">' + stEsc(data) + '</a>'
                        + '<div class="st-sub">' + stEsc(row.creator) + '</div>';
                } },
            { data: 'transfer_date', orderable: false, searchable: false },
            { data: 'kind', orderable: false, searchable: false,
                render: function(data) { return '<span class="status-badge" style="background:var(--accent-soft);color:var(--accent);">' + stEsc(data) + '</span>'; } },
            { data: 'from', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<div class="st-route">'
                        + '<div class="st-node"><div class="st-wh">' + stEsc(row.from_warehouse) + '</div><div class="st-sub">' + stEsc(row.from_owner) + '</div></div>'
                        + '<i class="fa fa-arrow-right st-arrow"></i>'
                        + '<div class="st-node"><div class="st-wh">' + stEsc(row.to_warehouse) + '</div><div class="st-sub">' + stEsc(row.to_owner) + '</div></div>'
                        + '</div>';
                } },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var chips = '';
                    if (row.material_lines) chips += '<span class="st-chip st-chip-material">' + row.material_lines + ' material</span> ';
                    if (row.serial_units) chips += '<span class="st-chip st-chip-serial">' + row.serial_units + ' SN units</span>';
                    return '<div class="fw-semibold">' + data + (data === 1 ? ' line' : ' lines') + '</div><div>' + chips + '</div>';
                } },
            { data: 'value', orderable: false, searchable: false, className: 'text-end',
                render: function(data) { return '<span style="font-variant-numeric:tabular-nums;font-weight:600">' + Num.format(data, 2) + '</span>'; } },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) { return '<a href="' + stShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Details"><i class="fa fa-eye"></i></a>'; } }
        ]
    });
});
</script>
@endsection
