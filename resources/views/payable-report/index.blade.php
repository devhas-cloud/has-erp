@extends('layouts.app')

@section('title', 'Payables Report')
@section('page-title', 'Payables Report')

@section('styles')
@include('partials.list-toolbar')
<style>
    .pr-tabs { display: flex; gap: 4px; flex-wrap: wrap; border-bottom: 1px solid var(--card-border); margin-bottom: 6px; }
    .pr-tabs a { padding: 8px 14px; font-size: 13px; font-weight: 500; color: var(--text-muted); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; display: inline-flex; align-items: center; gap: 6px; }
    .pr-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
    .pr-tabs a:hover { color: var(--text-primary); }
    .pr-toolbar { position: sticky; top: calc(var(--topbar-h) + 8px); z-index: 5; background: #f8fafc; border: 1px solid var(--card-border); border-radius: var(--radius-sm); }
    .pr-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .pr-muted { font-size: 12px; color: var(--text-muted); }
    table.pr-table { width: 100%; }
    table.pr-table thead th { font-weight: 700; white-space: nowrap; }
    table.pr-table th, table.pr-table td { vertical-align: middle; }
    table.pr-table tbody tr td { transition: background-color .12s ease; }
    table.pr-table tbody tr:hover td { background: #f8fafc; }
    table.pr-table tbody tr.pr-row-overdue td { background: #fef2f2; }
    table.pr-table tbody tr.pr-row-overdue td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
    table.pr-table tfoot td { font-weight: 700; border-top: 2px solid var(--card-border); background: #f8fafc; line-height: 1.5; }
    /* Lebar kolom tetap supaya header, body, dan footer sejajar */
    table.pr-table .pr-col-no { width: 48px; }
    table.pr-table .pr-col-date { width: 110px; }
    table.pr-table .pr-col-money { width: 160px; }
    .pr-status { display: inline-block; font-size: 10.5px; font-weight: 600; padding: 1px 7px; border-radius: 99px; }
    .pr-cur { font-size: 11px; color: var(--text-muted); font-weight: 500; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Payables Report</h1>
        <p class="page-header-sub">Supplier payables as of {{ now()->format('d/m/Y') }}.
            @if($tab === 'fx')
                Realized exchange differences on posted foreign-currency payments / refunds: actual value (bank rate) versus book value (bill / advance rate), in {{ $base }}. Positive = <span class="text-danger">loss</span>, negative = <span style="color:#15803d">gain</span>.
            @else
                Amounts are in each bill's own currency; totals are summed per currency.
            @endif
        </p>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-body-custom">
        <nav class="pr-tabs" aria-label="Report type">
            @foreach($tabs as $key => $label)
                <a href="{{ route('payable-report.index', ['tab' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="list-toolbar pr-toolbar" id="pr-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search autocomplete="off"
                       placeholder="@if($tab === 'supplier')Search supplier…@elseif($tab === 'project')Search supplier, project, bill no.…@else Search document, supplier, project…@endif">
                <button type="button" class="lt-clear" title="Clear search"><i class="fa fa-xmark"></i></button>
            </div>
            @if($tab !== 'supplier' && $suppliers->isNotEmpty())
            <select class="form-select" data-filter="supplier" data-searchable aria-label="Supplier">
                <option value="">All Suppliers</option>
                @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </select>
            @endif
            <select class="form-select" data-filter="outstanding" aria-label="Outstanding">
                <option value="">{{ $tab === 'fx' ? 'All Bills' : 'All Payables' }}</option>
                <option value="yes">{{ $tab === 'fx' ? 'Bill still outstanding' : 'Still outstanding' }}</option>
                <option value="no">Fully paid</option>
            </select>
            <select class="form-select" data-filter="currency" aria-label="Currency">
                <option value="">All Currencies</option>
                @foreach($currencies as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
            </select>
            @if($tab !== 'supplier')
            <div class="lt-date" title="{{ $tab === 'fx' ? 'Payment / refund date' : 'Bill date' }}">
                <input type="date" class="form-control" data-filter="from" aria-label="From date" @if($tab === 'fx') value="{{ $period['from'] }}" @endif>
                <span>–</span>
                <input type="date" class="form-control" data-filter="to" aria-label="To date" @if($tab === 'fx') value="{{ $period['to'] }}" @endif>
            </div>
            @endif
            <a href="#" class="btn btn-sm btn-outline-secondary pr-export" title="Export this tab to Excel with the active filters">Export Excel</a>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>

        <div class="table-responsive">
        @switch($tab)
            @case('fx')
                <table id="pr-fx" class="table table-custom align-middle mb-0 pr-table" style="width:100%">
                    <thead><tr>
                        <th class="text-center pr-col-no">No</th>
                        <th class="text-center pr-col-date">Date</th>
                        <th>Project</th>
                        <th>Document</th>
                        <th>Supplier</th>
                        <th class="pr-num pr-col-money">Amount</th>
                        <th class="pr-num">Book → Actual Rate</th>
                        <th class="pr-num pr-col-money">Book Value <span class="pr-cur">({{ $base }})</span></th>
                        <th class="pr-num pr-col-money">Actual Value <span class="pr-cur">({{ $base }})</span></th>
                        <th class="pr-num pr-col-money">Difference <span class="pr-cur">({{ $base }})</span></th>
                    </tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><td class="text-center"></td><td></td><td></td><td></td><td></td><td class="pr-num"></td><td class="pr-num"></td><td class="pr-num"></td><td class="pr-num"></td><td class="pr-num"></td></tr></tfoot>
                </table>
                @break

            @case('project')
                <table id="pr-project" class="table table-custom align-middle mb-0 pr-table" style="width:100%">
                    <thead><tr>
                        <th class="text-center pr-col-no">No</th>
                        <th>Supplier</th>
                        <th>Project</th>
                        <th class="pr-num pr-col-money">Total Payable</th>
                        <th class="pr-num pr-col-money">Paid</th>
                        <th class="pr-num pr-col-money">Outstanding</th>
                    </tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><td class="text-center"></td><td></td><td></td><td class="pr-num"></td><td class="pr-num"></td><td class="pr-num"></td></tr></tfoot>
                </table>
                @break

            @default
                <table id="pr-supplier" class="table table-custom align-middle mb-0 pr-table" style="width:100%">
                    <thead><tr>
                        <th class="text-center pr-col-no">No</th>
                        <th>Supplier Name</th>
                        <th class="pr-num pr-col-money">Total Payable</th>
                        <th class="pr-num pr-col-money">Paid</th>
                        <th class="pr-num pr-col-money">Outstanding</th>
                    </tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><td class="text-center"></td><td></td><td class="pr-num"></td><td class="pr-num"></td><td class="pr-num"></td></tr></tfoot>
                </table>
        @endswitch
        </div>
    </div>
</div>

{{-- Status pengingat WhatsApp; pengaturannya di Master Data → Notifikasi Finance --}}
<div class="d-flex align-items-center flex-wrap gap-2 mt-3 pr-muted">
    <span>Due date reminder (WhatsApp):</span>
    <span class="status-badge" style="{{ $reminder['enabled'] ? 'background:#dcfce7;color:#166534' : 'background:#f3f4f6;color:#4b5563' }}">{{ $reminder['enabled'] ? 'Active · daily at '.$reminder['send_time'] : 'Inactive' }}</span>
    @if($reminder['last_sent_date'])<span>· last sent {{ \Illuminate\Support\Carbon::parse($reminder['last_sent_date'])->format('d/m/Y') }}</span>@endif
    @if($canManageNotification)<a href="{{ route('finance-notification.index') }}" class="ms-1">Reminder settings</a>@endif
</div>
@endsection

@section('scripts')
<script>
const prDataUrl = '{{ route("payable-report.data") }}';
const prExportBase = '{{ route("payable-report.export") }}';
const prTab = '{{ $tab }}';
const prLang = {
    lengthMenu: 'Show _MENU_ rows',
    info: 'Showing _START_–_END_ of _TOTAL_ rows',
    infoEmpty: 'No rows',
    infoFiltered: '(filtered from _MAX_)',
    processing: 'Loading…',
    zeroRecords: '<span style="color:var(--text-muted)">No rows match the search / filters.</span>',
    emptyTable: '<span style="color:var(--text-muted)">{{ $tab === 'fx' ? 'No foreign-currency payments / refunds yet.' : 'No payables yet.' }}</span>',
    paginate: { first: '«', previous: '‹', next: '›', last: '»' }
};
let prToolbar = null;

function prToolbarParams() {
    var params = { tab: prTab };
    $('#pr-toolbar [data-filter]').each(function() { var v = $(this).val(); if (v) params[$(this).data('filter')] = v; });
    var q = $('#pr-toolbar [data-search]').val();
    if (q) params.q = q;
    return params;
}
function prSyncExport() { $('.pr-export').attr('href', prExportBase + '?' + $.param(prToolbarParams())); }

$(document).ready(function() {
    // Export mengikuti filter aktif.
    $('#pr-toolbar').on('change input click', '[data-filter], [data-search], .lt-clear, [data-reset]', function() { setTimeout(prSyncExport, 60); });

    // Jumlah & kelas kolom DataTables mengikuti header (header, body, footer harus sama).
    var $prTableEl = $('#pr-' + prTab);
    prToolbar = ListToolbar('#pr-toolbar', function() { return prTable; });
    var prTable = $prTableEl.DataTable({
        layout: ListToolbar.layout,
        autoWidth: false,
        language: prLang,
        processing: true,
        serverSide: true,
        ajax: { url: prDataUrl, data: function(d) { d.tab = prTab; return prToolbar.params(d); } },
        order: [],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        columns: $prTableEl.find('thead th').map(function() {
            return { data: null, defaultContent: '', className: this.className.replace(/pr-col-\S+/g, '').trim(), render: function() { return ''; } };
        }).get(),
        columnDefs: [{ orderable: false, targets: '_all' }],
        createdRow: function(row, data) { $(row).html(data.html); }
    });
    prTable.on('xhr.dt', function(e, s, json) {
        if (json && json.footer) { json.footer.forEach(function(cell, idx) { $prTableEl.find('tfoot td').eq(idx).html(cell); }); }
        prSyncExport();
    });
    prSyncExport();
});
</script>
@endsection
