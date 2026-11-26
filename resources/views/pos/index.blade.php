@extends('layouts.app')

@section('title', 'POS')
@section('page-title', 'POS (Point of Sale)')

@section('styles')
@include('partials.list-toolbar')
<style>
    .pos-sub { font-size: 11.5px; color: var(--text-muted); }
    .pos-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .pos-tag { display: inline-block; font-size: 10.5px; font-weight: 600; padding: 1px 7px; border-radius: 99px; white-space: nowrap; }
    .pos-tag.dp { background: #fef3c7; color: #92400e; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">POS</h1>
        <p class="page-header-sub">Sales documents created from quotations that already have a Profit Estimate. Drafts are submitted for approval; once approved, the project is created from the quotation.</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('pos.create') }}" class="btn-accent">Create POS</a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="pos-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Search POS no., customer, quotation, project, item…" aria-label="Search POS" autocomplete="off">
                <button type="button" class="lt-clear" title="Clear search" aria-label="Clear search"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" data-status-select aria-label="Status">
                <option value="" data-label="All Statuses">All Statuses</option>
                @foreach(\App\Models\Pos::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}" data-label="{{ $label }}">{{ $label }}</option>
                @endforeach
            </select>
            <select class="form-select" data-filter="company" data-searchable aria-label="Customer">
                <option value="">All Customers</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}">{{ $c->account_name }}</option>
                @endforeach
            </select>
            <div class="lt-date" title="POS date">
                <input type="date" class="form-control" data-filter="date_from" aria-label="From date">
                <span>–</span>
                <input type="date" class="form-control" data-filter="date_to" aria-label="To date">
            </div>
            <select class="form-select" data-filter="mine" aria-label="Created by">
                <option value="">All Creators</option>
                <option value="1">Created by me</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="pos-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>POS No.</th>
                        <th>Customer · Quotation</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Grand Total</th>
                        <th>Project</th>
                        <th>Status</th>
                        <th class="text-center" style="width:60px">Action</th>
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
function posLink(text, url) { return url ? '<a href="' + url + '" class="text-decoration-none">' + posEsc(text) + '</a>' : posEsc(text); }

$(document).ready(function() {
    posToolbar = ListToolbar('#pos-toolbar', function() { return posTable; });
    posTable = $('#pos-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)">No POS matches the search / filters.</span>',
            emptyTable: '<span style="color:var(--text-muted)">No POS yet.</span>'
        },
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("pos.data") }}', data: function(d) { return posToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'pos_number', orderable: false,
                render: function(data, type, row) {
                    return '<a href="' + posShowUrl.replace('__ID__', row.id) + '" class="fw-semibold text-decoration-none" style="font-variant-numeric:tabular-nums">' + posEsc(data) + '</a>'
                        + '<div class="pos-sub">' + posEsc(row.date) + ' · ' + posEsc(row.creator) + '</div>';
                } },
            { data: 'company', orderable: false,
                render: function(data, type, row) {
                    return posEsc(data)
                        + '<div class="pos-sub">' + (row.quotation ? posLink(row.quotation, row.quotation_url) : '—')
                        + (row.requires_dp ? ' <span class="pos-tag dp">DP required</span>' : '') + '</div>';
                } },
            { data: 'item_count', orderable: false, className: 'text-center' },
            { data: 'grand_total', orderable: false, className: 'pos-num' },
            { data: 'project', orderable: false,
                render: function(data, type, row) { return data ? posLink(data, row.project_url) : '<span style="color:var(--text-muted)">—</span>'; } },
            { data: 'status_badge', orderable: false },
            { data: 'id', orderable: false, className: 'text-center',
                render: function(data) { return '<a href="' + posShowUrl.replace('__ID__', data) + '" class="btn-icon" title="View detail" aria-label="View detail"><i class="fa fa-eye"></i></a>'; } }
        ]
    });
    posTable.on('xhr.dt', function(e, settings, json) { posToolbar.statusCounts(json); });
});
</script>
@endsection
