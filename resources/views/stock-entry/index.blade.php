@extends('layouts.app')

@section('title', 'Stock Entries')
@section('page-title', 'Stock Entries')

@section('styles')
@include('partials.list-toolbar')
<style>
    #se-table th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    .se-sub { font-size: 11.5px; color: var(--text-muted); }
    .se-chip { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 999px; white-space: nowrap; }
    .se-chip-material { background: #e0f2fe; color: #075985; }
    .se-chip-serial { background: #fef3c7; color: #92400e; }
    .se-own-project { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 999px; background: #fef3c7; color: #92400e; }
    .se-own-free { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 999px; background: #f3f4f6; color: #4b5563; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Stock Entries</h1>
        <p class="page-header-sub">Goods coming in without a purchase document — opening balance, returns from project / customer, grants / samples. <strong>Materials</strong> are added to the warehouse balance; <strong>serial items</strong> are recorded per unit.</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('stock-entry.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>New Stock Entry</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-square-plus me-2" style="color:var(--accent)"></i>Stock Entry List</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="se-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Search entry no., product, warehouse, project, description…" autocomplete="off">
                <button type="button" class="lt-clear" title="Clear search"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="reason" aria-label="Reason">
                <option value="">All Reasons</option>
                @foreach(\App\Models\StockEntry::REASON_LABELS as $key => $label)
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
                <option value="free">Free stock</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}">Project {{ $p->project_number }} — {{ $p->project_name }}</option>
                @endforeach
            </select>
            <div class="lt-date" title="Entry date">
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
            <table id="se-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Entry No.</th>
                        <th>Date</th>
                        <th>Reason</th>
                        <th>Warehouse</th>
                        <th>Ownership</th>
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
let seTable = null;
let seToolbar = null;
const seShowUrl = '{{ route("stock-entry.show", "__ID__") }}';

function seEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    seToolbar = ListToolbar('#se-toolbar', function() { return seTable; });
    seTable = $('#se-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>No stock entries match the search / filters.</span>',
            emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>No stock entries yet.</span>'
        },
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("stock-entry.data") }}', data: function(d) { return seToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'entry_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<a href="' + seShowUrl.replace('__ID__', row.id) + '" style="font-weight:700;color:var(--text-primary);font-variant-numeric:tabular-nums">' + seEsc(data) + '</a>'
                        + '<div class="se-sub">' + seEsc(row.creator) + '</div>';
                } },
            { data: 'entry_date', orderable: false, searchable: false },
            { data: 'reason_label', orderable: false, searchable: false,
                render: function(data) { return '<span class="status-badge" style="background:var(--accent-soft);color:var(--accent);">' + seEsc(data) + '</span>'; } },
            { data: 'warehouse_name', orderable: false, searchable: true,
                render: function(data, type, row) { return seEsc(data) + (row.warehouse_branch ? '<div class="se-sub">' + seEsc(row.warehouse_branch) + '</div>' : ''); } },
            { data: 'ownership', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return row.project_id ? '<span class="se-own-project">Project</span><div class="se-sub">' + seEsc(data) + '</div>' : '<span class="se-own-free">Free stock</span>';
                } },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var chips = '';
                    if (row.material_lines) chips += '<span class="se-chip se-chip-material" title="Added to the material balance">' + row.material_lines + ' material</span> ';
                    if (row.serial_units) chips += '<span class="se-chip se-chip-serial" title="Serial units (one stock row per unit)">' + row.serial_units + ' SN units</span>';
                    return '<div class="fw-semibold">' + data + (data === 1 ? ' line' : ' lines') + '</div><div>' + chips + '</div>';
                } },
            { data: 'value', orderable: false, searchable: false, className: 'text-end',
                render: function(data) { return '<span style="font-variant-numeric:tabular-nums;font-weight:600">' + Num.format(data, 2) + '</span>'; } },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) { return '<a href="' + seShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Details"><i class="fa fa-eye"></i></a>'; } }
        ]
    });
});
</script>
@endsection
