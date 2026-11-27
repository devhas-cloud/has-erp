@extends('layouts.app')

@section('title', 'Item Loans')
@section('page-title', 'Item Loans')

@section('styles')
@include('partials.list-toolbar')
<style>
    #il-table th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    #il-table td { vertical-align: top; }
    .il-sub { font-size: 11.5px; color: var(--text-muted); }
    .il-tag { display: inline-block; font-size: 10px; font-weight: 600; padding: 0 7px; border-radius: 99px; margin-left: 4px; }
    .il-tag.ext { background: #ede9fe; color: #5b21b6; }
    .il-tag.int { background: #e0f2fe; color: #075985; }
    .il-due-late { color: #b42318; font-weight: 600; }
    .il-due-soon { color: #b45309; font-weight: 600; }
    .list-toolbar .il-btn { height: 32px; padding: 0 14px; font-size: 12.5px; white-space: nowrap; }
    .il-alert { display: none; width: 100%; text-align: left; border: 1px solid #fecaca; background: #fef2f2; color: #7f1d1d; border-radius: var(--radius-sm); padding: 8px 14px; margin: 0 6px 10px; font-size: 13px; transition: background-color .12s ease, border-color .12s ease; }
    .il-alert:hover { background: #fee2e2; }
    .il-alert.is-active { border-color: #dc2626; background: #fee2e2; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Item Loans</h1>
        <p class="page-header-sub">Serial-numbered units lent to staff or outside parties. Units on loan stay in stock (value unchanged) but cannot be used by other documents until their return is recorded; only lost units reduce stock.</p>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="il-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Search loan no., borrower, SN, product…" aria-label="Search loans" autocomplete="off">
                <button type="button" class="lt-clear" title="Clear search" aria-label="Clear search"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" aria-label="Status">
                <option value="">All Statuses</option>
                <option value="on_loan">On Loan</option>
                <option value="overdue">Overdue</option>
                <option value="returned">Returned</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <select class="form-select" data-filter="borrower_type" aria-label="Borrower type">
                <option value="">All Borrowers</option>
                <option value="internal">Internal staff</option>
                <option value="external">External party</option>
            </select>
            @if($warehouses->count() > 1)
            <select class="form-select" data-filter="warehouse" aria-label="Warehouse">
                <option value="">All Warehouses</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>
                @endforeach
            </select>
            @endif
            <div class="lt-date" title="Loan date">
                <input type="date" class="form-control" data-filter="date_from" aria-label="Loan date from">
                <span>–</span>
                <input type="date" class="form-control" data-filter="date_to" aria-label="Loan date to">
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
            @if($canCreate)
                <a href="{{ route('item-loan.create') }}" class="btn btn-sm btn-accent il-btn">New Loan</a>
                <button type="button" class="btn btn-sm btn-outline-secondary il-btn" data-bs-toggle="modal" data-bs-target="#il-reminder-modal">Reminder</button>
            @endif
        </div>
        <button type="button" class="il-alert" id="il-overdue-alert" aria-pressed="false" title="Click to show only overdue loans; click again to show all">
            <span id="il-overdue-text"></span>
        </button>
        <div class="table-responsive">
            <table id="il-table" class="table table-custom mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Loan</th>
                        <th>Borrower</th>
                        <th>Units</th>
                        <th>Loan → Return</th>
                        <th>Status</th>
                        <th class="text-center" style="width:60px">Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@if($canCreate)
@push('modals')
<div class="modal fade" id="il-reminder-modal" tabindex="-1" aria-labelledby="il-reminder-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="il-reminder-title">WhatsApp Reminder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="il-rm-enabled" @checked($reminder['enabled'])>
                    <label class="form-check-label" for="il-rm-enabled">Send daily reminders</label>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="il-rm-time">Send time</label>
                        <input type="time" class="form-control" id="il-rm-time" value="{{ $reminder['send_time'] }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="il-rm-days">Remind days before due</label>
                        <input type="number" class="form-control" id="il-rm-days" min="0" max="30" value="{{ $reminder['days_ahead'] }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="il-rm-group">Inventory WhatsApp group</label>
                        <select class="form-select" id="il-rm-group">
                            <option value="">— No group —</option>
                            @foreach($whatsappGroups as $g)
                                <option value="{{ $g->id }}" @selected((int) $reminder['whatsapp_group_id'] === $g->id)>{{ $g->group_name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Receives a daily summary of overdue and soon-due loans (including external borrowers' phone numbers).</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="il-rm-borrowers" @checked($reminder['notify_borrowers'])>
                            <label class="form-check-label" for="il-rm-borrowers">Also send a personal message to each borrower with a phone number</label>
                        </div>
                    </div>
                </div>
                @if($reminder['last_sent_date'])
                    <div class="form-text mt-3">Last sent {{ \Illuminate\Support\Carbon::parse($reminder['last_sent_date'])->format('d/m/Y') }}.</div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="il-rm-save">Save Settings</button>
            </div>
        </div>
    </div>
</div>
@endpush
@endif

@section('scripts')
<script>
let ilTable = null;
let ilToolbar = null;
const ilShowUrl = '{{ route("item-loan.show", "__ID__") }}';

function ilEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function ilSyncOverdue(total) {
    var active = $('#il-toolbar [data-filter="status"]').val() === 'overdue';
    $('#il-overdue-alert').css('display', total > 0 || active ? 'block' : 'none').toggleClass('is-active', active).attr('aria-pressed', active ? 'true' : 'false');
    $('#il-overdue-text').html('<strong>' + total + ' loan' + (total === 1 ? '' : 's') + '</strong> past the return date — follow up with the borrowers.');
}

$(document).ready(function() {
    ilToolbar = ListToolbar('#il-toolbar', function() { return ilTable; });
    ilTable = $('#il-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)">No loans match the search / filters.</span>',
            emptyTable: '<span style="color:var(--text-muted)">No item loans yet.</span>'
        },
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("item-loan.data") }}', data: function(d) { return ilToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'loan_number', orderable: false, render: function(data, type, row) {
                return '<a href="' + ilShowUrl.replace('__ID__', row.id) + '" class="fw-semibold text-decoration-none" style="font-variant-numeric:tabular-nums">' + ilEsc(data) + '</a>'
                    + '<div class="il-sub">' + ilEsc(row.warehouse || '') + ' · ' + ilEsc(row.creator) + '</div>';
            } },
            { data: 'borrower', orderable: false, render: function(data, type, row) {
                var tag = row.borrower_type === 'external' ? '<span class="il-tag ext">External</span>' : '<span class="il-tag int">Internal</span>';
                return '<span class="fw-semibold">' + ilEsc(data) + '</span>' + tag
                    + (row.borrower_company ? '<div class="il-sub">' + ilEsc(row.borrower_company) + '</div>' : '');
            } },
            { data: 'units', orderable: false, render: function(units, type, row) {
                var html = units.map(function(u) { return '<div style="font-size:12.5px">' + ilEsc(u) + '</div>'; }).join('');
                if (row.item_count > units.length) html += '<div class="il-sub">+' + (row.item_count - units.length) + ' more</div>';
                if (row.status === 'on_loan' && row.out_count < row.item_count) html += '<div class="il-sub">' + (row.item_count - row.out_count) + ' of ' + row.item_count + ' returned</div>';
                return html;
            } },
            { data: 'due_date', orderable: false, render: function(data, type, row) {
                var note = '';
                if (row.days_to_due !== null) {
                    if (row.days_to_due < 0) note = '<div class="il-due-late" style="font-size:11.5px">' + Math.abs(row.days_to_due) + ' day(s) overdue</div>';
                    else if (row.days_to_due === 0) note = '<div class="il-due-soon" style="font-size:11.5px">Due today</div>';
                    else note = '<div class="il-sub">in ' + row.days_to_due + ' day(s)</div>';
                }
                return ilEsc(row.loan_date) + ' → ' + ilEsc(data) + note;
            } },
            { data: 'status_badge', orderable: false },
            { data: 'id', orderable: false, className: 'text-center', render: function(data) {
                return '<a href="' + ilShowUrl.replace('__ID__', data) + '" class="btn-icon" title="View detail" aria-label="View detail"><i class="fa fa-eye"></i></a>';
            } }
        ]
    });
    ilTable.on('xhr.dt', function(e, settings, json) { if (json) ilSyncOverdue(json.overdue_total || 0); });

    $('#il-overdue-alert').on('click', function() {
        var $status = $('#il-toolbar [data-filter="status"]');
        $status.val($status.val() === 'overdue' ? '' : 'overdue').trigger('change');
    });

    $('#il-rm-save').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        $.post('{{ route("item-loan.reminder") }}', {
            _token: '{{ csrf_token() }}',
            enabled: $('#il-rm-enabled').is(':checked') ? 1 : 0,
            send_time: $('#il-rm-time').val(),
            days_ahead: $('#il-rm-days').val(),
            whatsapp_group_id: $('#il-rm-group').val(),
            notify_borrowers: $('#il-rm-borrowers').is(':checked') ? 1 : 0
        }).done(function(res) {
            toastr.success(res.message || 'Saved.');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('il-reminder-modal')).hide();
        }).fail(function(xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to save.';
            if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
            toastr.error(msg);
        }).always(function() { $btn.prop('disabled', false); });
    });
});
</script>
@endsection
