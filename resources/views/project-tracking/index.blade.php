@extends('layouts.app')

@section('title', 'Project Tracking')
@section('page-title', 'Project Tracking')

@section('styles')
@include('partials.list-toolbar')
<style>
    .pt-flag { display: inline-block; font-size: 11px; font-weight: 600; padding: 1px 8px; border-radius: 999px; margin: 1px 3px 1px 0; white-space: nowrap; }
    .pt-flag.danger { background: var(--danger-soft); color: #b42318; }
    .pt-flag.warn { background: var(--warning-soft, #fef3c7); color: #92400e; }
    .pt-bar { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; min-width: 80px; position: relative; }
    .pt-bar span { position: absolute; left: 0; top: 0; height: 100%; }
    .pt-bar .billed { background: color-mix(in srgb, var(--accent) 40%, transparent); }
    .pt-bar .paid { background: var(--accent); }
    .pt-kpi { border: 1px solid var(--card-border); border-radius: var(--radius-sm); padding: 12px 14px; background: var(--card, #fff); height: 100%; }
    .pt-kpi .l { font-size: 12px; color: var(--text-muted); }
    .pt-kpi .v { font-size: 22px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.2; }
    .pt-sub { font-size: 11.5px; color: var(--text-muted); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Project Tracking</h1>
        <p class="page-header-sub">The journey of every project from customer PO to invoice, in 9 stages calculated from existing documents and tasks.</p>
    </div>
</div>

<div class="row g-2 mb-3">
    @foreach([
        ['Active projects', $summary['active'], null],
        ['In purchasing (stages 2–5)', $summary['purchasing'], null],
        ['In production & delivery (6–8)', $summary['shipping'], null],
        ['Not fully billed', $summary['unbilled'], null],
        ['Past due date', $summary['overdue'], '#b42318'],
    ] as [$label, $value, $color])
        <div class="col-6 col-md">
            <div class="pt-kpi"><div class="l">{{ $label }}</div><div class="v" @if($color && $value) style="color:{{ $color }}" @endif>{{ $value }}</div></div>
        </div>
    @endforeach
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">Projects</div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="pt-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Search project no. / name, customer…" autocomplete="off">
                <button type="button" class="lt-clear" title="Clear search"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" aria-label="Project status">
                <option value="active" selected>Active projects</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="">All statuses</option>
            </select>
            <select class="form-select" data-filter="company" data-searchable aria-label="Customer">
                <option value="">All customers</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}">{{ $c->account_name }}</option>
                @endforeach
            </select>
            <select class="form-select" data-filter="stage" aria-label="Current stage">
                <option value="">All stages</option>
                @foreach(\App\Support\ProjectTracker::STAGES as $n => $name)
                    @continue($n === 1)
                    <option value="{{ $n }}">At stage {{ $n }}: {{ $name }}</option>
                @endforeach
                <option value="done">All stages done</option>
            </select>
            <select class="form-select" data-filter="problem" aria-label="Alerts">
                <option value="">All projects</option>
                <option value="1">With alerts only</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="pt-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Current Stage</th>
                        <th>Due Date</th>
                        <th style="width:140px">Billed / Collected</th>
                        <th>Alerts</th>
                    </tr>
                </thead>
            </table>
        </div>
        <div class="px-2 pt-2 pt-sub">Open a project to see its full 9-stage journey. "Stuck" alerts use a {{ $alertDays }}-day threshold.</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let ptTable = null;
let ptToolbar = null;

function ptEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    // Reset toolbar mengosongkan semua filter -> status kosong = semua status.
    ptToolbar = ListToolbar('#pt-toolbar', function() { return ptTable; });
    ptTable = $('#pt-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            processing: 'Loading…',
            lengthMenu: 'Show _MENU_ rows',
            info: 'Showing _START_–_END_ of _TOTAL_ projects',
            infoEmpty: 'No projects',
            infoFiltered: '(filtered from _MAX_)',
            zeroRecords: '<span style="color:var(--text-muted)">No projects match the search / filters.</span>',
            emptyTable: '<span style="color:var(--text-muted)">No projects.</span>',
            paginate: { first: '«', previous: '‹', next: '›', last: '»' }
        },
        processing: true,
        serverSide: false,
        pageLength: 25,
        ajax: { url: '{{ route("project-tracking.data") }}', data: function(d) { return ptToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'project_number', render: function(data, type, row) {
                if (type !== 'display') return data + ' ' + row.project_name + ' ' + (row.customer || '');
                return '<a href="' + row.url + '" class="fw-semibold text-decoration-none">' + ptEsc(data) + '</a>'
                    + '<div style="font-size:12px;color:var(--text-secondary)">' + ptEsc(row.project_name) + '</div>'
                    + '<div class="pt-sub">' + ptEsc(row.customer || '—') + '</div>';
            } },
            { data: 'current_label', render: function(data, type, row) {
                if (type !== 'display') return row.current || 99;
                return '<span class="fw-semibold">' + (row.current ? row.current + ' · ' : '') + ptEsc(data) + '</span>'
                    + (row.current_since ? '<div class="pt-sub">since ' + ptEsc(row.current_since) + '</div>' : '')
                    + (row.current_summary ? '<div class="pt-sub">' + ptEsc(row.current_summary) + '</div>' : '')
                    + '<div class="pt-sub">' + row.progress.done + ' of ' + row.progress.total + ' stages done</div>';
            } },
            { data: 'due_days', render: function(data, type, row) {
                if (type !== 'display') return data == null ? 999999 : data;
                if (!row.due_date) return '<span style="color:var(--text-muted)">—</span>';
                var note = data < 0 ? '<div class="pt-sub" style="color:#b42318">' + Math.abs(data) + ' day(s) past</div>' : '<div class="pt-sub">in ' + data + ' day(s)</div>';
                return ptEsc(row.due_date) + note;
            } },
            { data: 'billed_pct', render: function(data, type, row) {
                if (type !== 'display') return data;
                return '<div class="pt-bar"><span class="billed" style="width:' + data + '%"></span><span class="paid" style="width:' + row.paid_pct + '%"></span></div>'
                    + '<span class="pt-sub">' + data + '% / ' + row.paid_pct + '%</span>';
            } },
            { data: 'alerts', orderable: false, render: function(alerts, type) {
                if (type !== 'display') return alerts.length;
                return alerts.map(function(a) { return '<span class="pt-flag ' + a[0] + '">' + ptEsc(a[1]) + '</span>'; }).join('');
            } }
        ]
    });
});
</script>
@endsection
