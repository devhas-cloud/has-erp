@extends('layouts.app')

@section('title', 'Opportunity Management')
@section('page-title', 'Opportunity Management')

@section('styles')
@include('partials.list-toolbar')
<style>
    .modal-opportunity .modal-dialog { max-width: 800px; }
    .opp-form-section {
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        margin-bottom: 16px;
        overflow: hidden;
    }
    .opp-form-section-header {
        padding: 10px 16px;
        background: #f8fafc;
        border-bottom: 1px solid var(--card-border);
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .opp-form-section-body { padding: 16px; display: none; }
    .opp-form-section.open .opp-form-section-body { display: block; }
    .opp-form-section-header .chevron { transition: transform 0.2s; font-size: 11px; color: var(--text-muted); }
    .opp-form-section.open .chevron { transform: rotate(180deg); }
    .opp-form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
    .opp-form-row .form-group { flex: 1; min-width: 200px; }
    .opp-form-row .form-group.small { flex: 0 0 160px; }
    .form-group label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--card-border);
        border-radius: var(--radius-sm);
        font-size: 13px;
        font-family: inherit;
        color: var(--text-primary);
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-soft);
        outline: none;
    }
    .form-check-inline {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 13px; font-weight: 500;
        padding: 6px 12px;
        border: 1px solid var(--card-border);
        border-radius: var(--radius-sm);
        cursor: pointer;
    }
    .form-check-inline input { width: auto; margin: 0; }
    .form-group input.is-invalid,
    .form-group select.is-invalid,
    .form-group textarea.is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
    }
    select.is-invalid + .select2-container .select2-selection {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
    }

    /* ===== Toolbar daftar (pola sama dengan Leads / Contact / Account) ===== */
    .opp-list-toolbar { border-bottom: 1px solid var(--card-border); margin-bottom: 6px; }
    .opp-btn { font-size: 12.5px; height: 32px; white-space: nowrap; display: inline-flex; align-items: center; }
    .opp-actions { display: flex; gap: 6px; margin-left: auto; }
    .opp-summary { font-size: 12px; color: var(--text-muted); padding: 0 6px 6px; }
    .opp-summary strong { color: var(--text-primary); }
    @media (max-width: 767.98px) { .opp-actions { margin-left: 0; width: 100%; justify-content: flex-end; } }
    .opp-date { color: var(--text-primary); font-weight: 600; white-space: nowrap; }
    .opp-date-rel { font-size: 11px; color: var(--text-muted); font-weight: 600; white-space: nowrap; }

</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Opportunity Management</h1>
        <p class="page-header-sub">Manage sales opportunities.</p>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span>Opportunity List</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar opp-list-toolbar" id="opp-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Search opportunity, account, stage, owner…" autocomplete="off">
                <button type="button" class="lt-clear" title="Clear search"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="account" data-searchable aria-label="Account">
                <option value="">All Accounts</option>
                @foreach($accountCompanies as $ac)<option value="{{ $ac->id }}">{{ $ac->account_name }}</option>@endforeach
            </select>
            @unless($isSales)
            <select class="form-select" data-filter="owner" data-searchable aria-label="Owner">
                <option value="">All Owners</option>
                <option value="none">No owner</option>
                @foreach($owners as $u)<option value="{{ $u->id }}">{{ $u->display_name }}</option>@endforeach
            </select>
            @endunless
            <select class="form-select" data-filter="stage" aria-label="Stage">
                <option value="">All Stages</option>
                @foreach($stages as $st)<option value="{{ $st->id }}">{{ $st->stage_name }}</option>@endforeach
            </select>
            <select class="form-select" data-filter="division_id" aria-label="Division">
                <option value="">All Divisions</option>
                @foreach($divisions as $d)<option value="{{ $d->id }}">{{ $d->division_name }}</option>@endforeach
            </select>
            <select class="form-select" id="opp-close-preset" aria-label="Close date">
                <option value="">Close Date: Any</option>
                <option value="q1">Close Date: Q1 (Jan – Mar)</option>
                <option value="q2">Close Date: Q2 (Apr – Jun)</option>
                <option value="q3">Close Date: Q3 (Jul – Sep)</option>
                <option value="q4">Close Date: Q4 (Oct – Dec)</option>
                <option value="year">Close Date: Full year</option>
                <option value="custom">Close Date: Custom range</option>
            </select>
            <select class="form-select" id="opp-year" aria-label="Close date year" style="display:none">
                @foreach($closeDateYears as $y)<option value="{{ $y }}" @selected($y === now()->year)>{{ $y }}</option>@endforeach
            </select>
            <div class="lt-date" id="opp-close-range" title="Close date" style="display:none">
                <input type="date" class="form-control" data-filter="close_date_from" aria-label="Close date from">
                <span>–</span>
                <input type="date" class="form-control" data-filter="close_date_to" aria-label="Close date to">
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
            @if($canCreate)
            <div class="opp-actions">
                <button type="button" class="btn btn-sm btn-primary opp-btn" onclick="openCreateModal()">Add Opportunity</button>
            </div>
            @endif
        </div>
        <div class="opp-summary" id="opp-summary-text" aria-live="polite">Loading…</div>
        <div class="table-responsive">
            <table id="opportunity-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Opportunity Name</th>
                        <th>Account Company</th>
                        <th>Stage</th>
                        <th>Division</th>
                        <th>Next Step</th>
                        <th>Close Date</th>
                        <th>Owner</th>
                        <th class="text-center" style="width:120px">Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
<!-- ══════════════════════════════════════════════════════════════
     MODAL: Opportunity Form
     ══════════════════════════════════════════════════════════════ -->
<div class="modal fade modal-opportunity" id="opportunityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="opportunityModalTitle">Add Opportunity</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                <form id="opportunity-form" autocomplete="off">
                    <input type="hidden" id="opp-edit-id">

                    <div class="opp-form-section open">
                        <div class="opp-form-section-header" onclick="toggleOppSection(this)">
                            <span>Opportunity Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="opp-form-section-body">
                            <div class="opp-form-row">
                                <div class="form-group">
                                    <label>Opportunity Name <span class="text-danger">*</span></label>
                                    <input type="text" name="opportunity_name" id="opp-name" required>
                                </div>
                            </div>
                            <div class="opp-form-row">
                                <div class="form-group">
                                    <label>Account Name <span class="text-danger">*</span></label>
                                    <select id="opp-company" style="width:100%">
                                         <option value=""></option>
                                    </select>
                                    <input type="hidden" name="account_companies_id" id="opp-company-id">
                                </div>
                                <div class="form-group">
                                    <label>Contact Name <span class="text-danger">*</span></label>
                                    <select id="opp-contact" style="width:100%">
                                        <option value=""></option>
                                    </select>
                                    <input type="hidden" name="account_contacts_id" id="opp-contact-id">
                                </div>
                                <div class="form-group" style="display: none">
                                    <label>Type</label>
                                    <select name="type" id="opp-type">
                                        <option value="">— Select —</option>
                                        <option value="Existing Business">Existing Business</option>
                                        <option value="New Business">New Business</option>
                                    </select>
                                </div>
                            </div>
                            <div class="opp-form-row" style="display: none">
                                <div class="form-group">
                                    <label>Stage</label>
                                    <select name="stage_id" id="opp-stage">
                                        <option value="">— Select —</option>
                                        @foreach($stages as $s)
                                        <option value="{{ $s->id }}">{{ $s->stage_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Probability (%)</label>
                                    <input type="number" name="probability" id="opp-probability" value="0" min="0" max="100" required>
                                </div>
                                <div class="form-group">
                                    <label>Forecast <span class="text-danger">*</span></label>
                                    <select name="forecast_id" id="opp-forecast">
                                        <option value="">— Select —</option>
                                        @foreach($forecasts as $f)
                                        <option value="{{ $f->id }}">{{ $f->forecast_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="opp-form-row">
                                <div class="form-group" style="display: none">
                                    <label>Loss Reason</label>
                                    <select name="loss_reasons_id" id="opp-loss-reason">
                                        <option value="">— Select —</option>
                                        @foreach($lossReasons as $lr)
                                        <option value="{{ $lr->id }}">{{ $lr->reason_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Division</label>
                                    <select name="division_id" id="opp-division">
                                        <option value="">— Select —</option>
                                        @foreach($divisions as $d)
                                        <option value="{{ $d->id }}">{{ $d->division_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group" style="display: none">
                                    <label>Lead Source</label>
                                    <select name="source_id" id="opp-source">
                                        <option value="">— Select —</option>
                                        @foreach($sources as $src)
                                        <option value="{{ $src->id }}">{{ $src->source_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="opp-form-row">
                                <div class="form-group" style="display: none">
                                    <label>Close Date</label>
                                    <input type="date" name="close_date" id="opp-close-date">
                                </div>
                                <div class="form-group">
                                    <label>End User</label>
                                    <select name="end_user_id" id="opp-end-user">
                                        <option value="">— Select —</option>
                                        @foreach($accountCompanies as $ac)
                                        <option value="{{ $ac->id }}">{{ $ac->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="opp-form-row">
                                <div class="form-group">
                                    <label>Next Step</label>
                                    <textarea name="next_step" id="opp-next-step" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="opp-form-row" style="display: none">
                                <label class="form-check-inline">
                                    <input type="checkbox" name="quote_ready" id="opp-quote-ready" value="1">
                                    Quote Ready
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="opp-form-section" style="display: none">
                        <div class="opp-form-section-header" onclick="toggleOppSection(this)">
                            <span>BAT Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="opp-form-section-body">
                            <div class="opp-form-row">
                                <div class="form-group small">
                                    <label>Close Won Date</label>
                                    <input type="date" name="close_won_date" id="opp-close-won-date">
                                </div>
                            </div>
                            <div class="opp-form-row">
                                <label class="form-check-inline">
                                    <input type="checkbox" name="budget" id="opp-budget" value="1">
                                    Budget
                                </label>
                                <label class="form-check-inline">
                                    <input type="checkbox" name="authorize" id="opp-authorize" value="1">
                                    Authorize
                                </label>
                                <label class="form-check-inline">
                                    <input type="checkbox" name="timeline" id="opp-timeline" value="1">
                                    Timeline
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="opp-form-section" style="display: none">
                        <div class="opp-form-section-header" onclick="toggleOppSection(this)">
                            <span>Additional Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="opp-form-section-body">
                            <div class="opp-form-row">
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea name="description" id="opp-description" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-opportunity">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>
@endpush


@section('scripts')
<script>
let oppModalInstance = null;
let opportunityTable = null;

const oppCanUpdate = {{ $canUpdate ? 'true' : 'false' }};
const oppCanDelete = {{ $canDelete ? 'true' : 'false' }};
const oppShowUrl = '{{ url("opportunity-management") }}/';
const oppFetchUrl = '{{ route("opportunity-management.fetch", ["opportunity" => "__ID__"]) }}';

function toggleOppSection(header) {
    header.closest('.opp-form-section').classList.toggle('open');
}

function resetOpportunityForm() {
    document.getElementById('opportunity-form').reset();
    document.getElementById('opp-edit-id').value = '';
    document.querySelectorAll('.opp-form-section').forEach(function(s) {
        s.classList.remove('open');
    });
    document.querySelector('.opp-form-section').classList.add('open');
    $('#opportunity-form .is-invalid').removeClass('is-invalid');
    $('#opp-company').prop('disabled', false).val(null).trigger('change');
    $('#opp-company-id').val('');
    $('#opp-contact').val(null).trigger('change');
    $('#opp-contact-id').val('');
    $('#opp-end-user').val('').trigger('change');
}

function loadCompanyContacts(companyId, selectedContact) {
    var $contact = $('#opp-contact');
    $contact.val(null).trigger('change');

    if (!companyId) {
        $contact.find('option:not([value=""])').remove();
        return;
    }

    $.ajax({
        url: '{{ route("opportunity-management.search-contacts") }}',
        data: { company_id: companyId, q: '' },
        dataType: 'json',
        success: function(res) {
            $contact.find('option:not([value=""])').remove();
            $.each(res.results, function(i, c) {
                var opt = new Option(c.text, c.id, false, false);
                $contact.append(opt);
            });

            if (selectedContact) {
                var opt = new Option(selectedContact.full_name, selectedContact.id, true, true);
                $contact.append(opt).trigger('change');
                $('#opp-contact-id').val(selectedContact.id);
            } else {
                $contact.trigger('change');
            }
        }
    });
}

function openCreateModal() {
    resetOpportunityForm();
    document.getElementById('opportunityModalTitle').textContent = 'Add Opportunity';
    if (!oppModalInstance) {
        oppModalInstance = new bootstrap.Modal(document.getElementById('opportunityModal'));
    }
    oppModalInstance.show();
}

function openEditModal(id) {
    resetOpportunityForm();
    document.getElementById('opportunityModalTitle').textContent = 'Edit Opportunity';
    if (!oppModalInstance) {
        oppModalInstance = new bootstrap.Modal(document.getElementById('opportunityModal'));
    }

    $.get(oppFetchUrl.replace('__ID__', id), function(res) {
        var opp = res.opportunity;
        $('#opp-edit-id').val(opp.id);
        $('#opp-name').val(opp.opportunity_name);
        $('#opp-probability').val(opp.probability);
        $('#opp-type').val(opp.type || '');
        $('#opp-stage').val(opp.stage_id || '');
        $('#opp-forecast').val(opp.forecast_id || '');
        $('#opp-loss-reason').val(opp.loss_reasons_id || '');
        $('#opp-division').val(opp.division_id || '');
        $('#opp-source').val(opp.source_id || '');

        if (opp.account_company) {
            $('#opp-company').find('option:not([value=""])').remove();
            var cOption = new Option(opp.account_company.account_name, opp.account_company.id, true, true);
            $('#opp-company').append(cOption).trigger('change');
            $('#opp-company-id').val(opp.account_company.id);
        }

        // load contacts for the selected company, then set value
        if (opp.account_companies_id) {
            loadCompanyContacts(opp.account_companies_id, opp.account_contact);
        }

        if (opp.end_user) {
            $('#opp-end-user').val(opp.end_user.id).trigger('change');
        } else if (opp.end_user_id) {
            $('#opp-end-user').val(opp.end_user_id).trigger('change');
        }

        $('#opp-next-step').val(opp.next_step);
        $('#opp-description').val(opp.description);
        if (opp.close_date) {
            $('#opp-close-date').val(opp.close_date.substring(0, 10));
        }
        if (opp.close_won_date) {
            $('#opp-close-won-date').val(opp.close_won_date.substring(0, 10));
        }
        if (opp.quote_ready) { $('#opp-quote-ready').prop('checked', true); }
        if (opp.budget) { $('#opp-budget').prop('checked', true); }
        if (opp.authorize) { $('#opp-authorize').prop('checked', true); }
        if (opp.timeline) { $('#opp-timeline').prop('checked', true); }

        oppModalInstance.show();
    }).fail(function() {
        toastr.error('Failed to load opportunity data.');
    });
}

function initOpportunityTable() {
    if (opportunityTable) {
        opportunityTable.destroy();
    }

    opportunityTable = $('#opportunity-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            processing: 'Loading…',
            lengthMenu: 'Show _MENU_ rows',
            info: 'Showing _START_–_END_ of _TOTAL_ opportunities',
            infoEmpty: 'No opportunities',
            infoFiltered: '(filtered from _MAX_)',
            zeroRecords: '<span style="color:var(--text-muted)">No opportunities match the search / filters.</span>',
            emptyTable: '<span style="color:var(--text-muted)">No opportunities yet.</span>',
            paginate: { first: '«', previous: '‹', next: '›', last: '»' }
        },
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("opportunity-management.data") }}',
            data: function(d) {
                return oppToolbar.params(d);
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            {
                data: 'opportunity_name', orderable: true, searchable: true,
                render: function(data, type, row) {
                    return '<strong style="color:var(--text-primary);font-weight:600">' + (data || '—') + '</strong>';
                }
            },
            {
                data: 'account_name', orderable: true, searchable: true,
                render: function(data, type, row) {
                    var avatar = row.company_icon
                        ? '<img src="' + row.company_icon + '" class="avatar-circle" alt="" style="background:transparent">'
                        : '<div class="avatar-circle">' + (row.company_initials || '?') + '</div>';
                    return '<div style="display:flex;align-items:center;gap:10px">' +
                        avatar +
                        '<span style="color:var(--text-primary);font-weight:500">' + (data || '—') + '</span>' +
                        '</div>';
                }
            },
            {
                data: 'stage_name', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<span class="status-badge badge-success" style="background:var(--success-soft);color:var(--success)">' + (data || '—') + '</span>';
                }
            },
            {
                data: 'division_name', orderable: true, searchable: true,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'next_step', orderable: true, searchable: false },
            {
                data: 'close_date', orderable: true, searchable: false,
                render: function(data, type, row) {
                    if (!row.close_date_raw) return '—';
                    var days = moment(row.close_date_raw).startOf('day').diff(moment().startOf('day'), 'days');
                    var rel = days === 0 ? 'Today' : (days > 0 ? 'in ' + days + ' day(s)' : Math.abs(days) + ' day(s) ago');
                    return '<div class="opp-date">' + data + '</div><div class="opp-date-rel">' + rel + '</div>';
                }
            },
            { data: 'owner_name', orderable: true, searchable: true },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    var html = '<div style="display:flex;gap:5px;justify-content:center">';
                    html += '<a href="' + oppShowUrl + row.id + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (oppCanUpdate) {
                        html += ' <button type="button" class="btn-icon" title="Edit" onclick="openEditModal(' + row.id + ')"><i class="fa fa-pen"></i></button>';
                    }
                    if (oppCanDelete) {
                        html += ' <button type="button" class="btn btn-sm btn-ghost text-danger btn-delete-opportunity" title="Delete" data-id="' + row.id + '"><i class="fa fa-trash-can"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        pageLength: 10,
        lengthMenu: [10, 15, 25, 50, 100],
        // Urutan & halaman tetap saat kembali dari halaman detail; filter toolbar disimpan terpisah (oppSaveFilters).
        stateSave: true,
        stateDuration: -1,
        stateSaveParams: function(settings, data) { data.search.search = ''; },
        search: { search: $('#opp-toolbar [data-search]').val() || '' },
        stateLoadParams: function(settings, data) { data.search.search = $('#opp-toolbar [data-search]').val() || ''; },
    });
}

// --- Toolbar filter (pola sama dengan Leads / Contact / Account) ---
let oppToolbar = null;
const OPP_FILTER_KEY = 'opportunity-filters';

// Preset close date (kuartal / setahun) pada tahun terpilih -> isi rentang tanggal.
function oppPresetRange(preset) {
    var year = parseInt($('#opp-year').val(), 10) || moment().year();
    if (preset === 'year') return [moment({ year: year }).startOf('year'), moment({ year: year }).endOf('year')];
    var q = /^q([1-4])$/.exec(preset || '');
    if (!q) return null;
    var start = moment({ year: year, month: (parseInt(q[1], 10) - 1) * 3, day: 1 });
    return [start, start.clone().add(2, 'months').endOf('month')];
}

function oppApplyPreset(preset) {
    var range = oppPresetRange(preset);
    $('#opp-close-range').toggle(preset === 'custom');
    $('#opp-year').toggle(!!range);
    if (range) {
        $('[data-filter="close_date_from"]').val(range[0].format('YYYY-MM-DD'));
        $('[data-filter="close_date_to"]').val(range[1].format('YYYY-MM-DD'));
    } else if (preset !== 'custom') {
        $('[data-filter="close_date_from"], [data-filter="close_date_to"]').val('');
    }
}

// Filter tetap saat kembali dari halaman detail (per tab browser).
function oppSaveFilters() {
    var state = { preset: $('#opp-close-preset').val(), year: $('#opp-year').val(), q: $('#opp-toolbar [data-search]').val() };
    $('#opp-toolbar [data-filter]').each(function() { state[$(this).data('filter')] = $(this).val(); });
    try { sessionStorage.setItem(OPP_FILTER_KEY, JSON.stringify(state)); } catch (e) {}
}

function oppRestoreFilters() {
    var state = null;
    try { state = JSON.parse(sessionStorage.getItem(OPP_FILTER_KEY) || 'null'); } catch (e) {}
    if (!state) return;
    $('#opp-toolbar [data-filter]').each(function() {
        var key = $(this).data('filter');
        if (state[key] != null && (this.tagName !== 'SELECT' || $(this).find('option[value="' + state[key] + '"]').length)) $(this).val(state[key]);
    });
    if (state.year && $('#opp-year option[value="' + state.year + '"]').length) $('#opp-year').val(state.year);
    $('#opp-close-preset').val(state.preset || '');
    $('#opp-close-range').toggle(state.preset === 'custom');
    $('#opp-year').toggle(/^(q[1-4]|year)$/.test(state.preset || ''));
    if (state.q) $('#opp-toolbar [data-search]').val(state.q);
}

$(document).on('change', '#opp-close-preset, #opp-year', function() {
    oppApplyPreset($('#opp-close-preset').val());
    $('[data-filter="close_date_from"]').trigger('change');
});

// Ringkasan hasil di bawah toolbar.
$('#opportunity-table').on('draw.dt', function(e, settings) {
    var info = new $.fn.dataTable.Api(settings).page.info();
    var preset = $('#opp-close-preset').val();
    var stage = $('[data-filter="stage"] option:selected');
    $('#opp-summary-text').html('<strong>' + Num.format(info.recordsDisplay, 0) + '</strong> ' + (info.recordsDisplay === 1 ? 'opportunity' : 'opportunities')
        + (preset ? ' · ' + $('<span>').text($('#opp-close-preset option:selected').text() + (/^(q[1-4]|year)$/.test(preset) ? ' ' + $('#opp-year').val() : '')).html() : '')
        + ($('[data-filter="stage"]').val() ? ' · Stage <strong>' + $('<span>').text(stage.text()).html() + '</strong>' : ''));
    oppSaveFilters();
});

$(document).on('click', '#btn-save-opportunity', function() {
    const $btn = $(this);
    const editId = $('#opp-edit-id').val();
    const isEdit = !!editId;

    $('#opportunity-form .is-invalid').removeClass('is-invalid');

    const validations = [
        { field: '#opp-name', label: 'Opportunity Name' },
        { field: '#opp-company-id', label: 'Account Name', type: 'hidden', select2: '#opp-company' },
        { field: '#opp-contact-id', label: 'Contact Name', type: 'hidden', select2: '#opp-contact' },
        //{ field: '#opp-forecast', label: 'Forecast' },
    ];

    for (let i = 0; i < validations.length; i++) {
        const v = validations[i];
        const $el = $(v.field);
        const val = $el.val() ? $el.val().trim() : '';

        if (!val) {
            if (v.select2) {
                $(v.select2).next('.select2-container').find('.select2-selection').addClass('is-invalid');
            } else if (v.type === 'hidden') {
                $(v.field).addClass('is-invalid');
            } else {
                $el.addClass('is-invalid');
            }

            const section = $el.closest('.opp-form-section');
            if (section.length && !section.hasClass('open')) {
                section.addClass('open');
            }
            toastr.error(v.label + ' is required.');
            if (v.select2) {
                $(v.select2).select2('focus');
            } else {
                $el.focus();
            }
            return;
        }
    }

    const formData = new FormData(document.getElementById('opportunity-form'));
    formData.set('quote_ready', $('#opp-quote-ready').is(':checked') ? '1' : '0');
    formData.set('budget', $('#opp-budget').is(':checked') ? '1' : '0');
    formData.set('authorize', $('#opp-authorize').is(':checked') ? '1' : '0');
    formData.set('timeline', $('#opp-timeline').is(':checked') ? '1' : '0');

    const url = isEdit
        ? '{{ route("opportunity-management.update", ["opportunity" => "__ID__"]) }}'.replace('__ID__', editId)
        : '{{ route("opportunity-management.store") }}';

    formData.append('_token', '{{ csrf_token() }}');
    if (isEdit) formData.append('_method', 'PUT');

    Swal.fire({
        title: isEdit ? 'Update Opportunity?' : 'Save Opportunity?',
        text: isEdit ? 'Opportunity data will be updated.' : 'A new opportunity will be added.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Yes, update!' : 'Yes, save!',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
    }).then(function(result) {
        if (!result.isConfirmed) return;

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                toastr.success(res.message);
                if (oppModalInstance) oppModalInstance.hide();
                if (opportunityTable) opportunityTable.ajax.reload(null, false);
                $btn.prop('disabled', false).html('Save');
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('Save');
                var errors = xhr.responseJSON?.errors;
                if (errors) {
                    var first = Object.values(errors)[0];
                    toastr.error(Array.isArray(first) ? first[0] : first);
                } else {
                    toastr.error(xhr.responseJSON?.message || 'Failed to save data.');
                }
            }
        });
    });
});

$(document).on('change input', '#opportunity-form input.is-invalid, #opportunity-form select.is-invalid', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('click', '.btn-delete-opportunity', function() {
    const id = $(this).data('id');
    Swal.fire({
        title: 'Sure to delete this opportunity?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route("opportunity-management.destroy", ["opportunity" => "__ID__"]) }}'.replace('__ID__', id),
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    toastr.success(res.message);
                    if (opportunityTable) opportunityTable.ajax.reload(null, false);
                },
                error: function() {
                    toastr.error('Failed to delete data.');
                }
            });
        }
    });
});

$(document).on('shown.bs.modal', '#opportunityModal', function() {
    if (!$('#opp-company').hasClass('select2-hidden-accessible')) {
        $('#opp-company').select2({
            theme: 'bootstrap-5',
            placeholder: 'Cari perusahaan...',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#opportunityModal'),
            minimumResultsForSearch: 0,
            ajax: {
                url: '{{ route("opportunity-management.search-companies") }}',
                dataType: 'json',
                delay: 250,
                data: function(params) { return { q: params.term }; },
                processResults: function(res) { return { results: res.results }; }
            }
        }).on('select2:select', function(e) {
            $('#opp-company-id').val(e.params.data.id);
            loadCompanyContacts(e.params.data.id, null);
        }).on('select2:clear', function() {
            $('#opp-company-id').val('');
            loadCompanyContacts(null, null);
        });
    }

    if (!$('#opp-contact').hasClass('select2-hidden-accessible')) {
        $('#opp-contact').select2({
            theme: 'bootstrap-5',
            placeholder: '— Select Contact —',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#opportunityModal')
        }).on('select2:select', function(e) {
            $('#opp-contact-id').val(e.params.data.id);
        }).on('select2:clear', function() {
            $('#opp-contact-id').val('');
        });
    }

    if (!$('#opp-end-user').hasClass('select2-hidden-accessible')) {
        $('#opp-end-user').select2({
            theme: 'bootstrap-5',
            placeholder: '— Select —',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#opportunityModal')
        });
    }
});
$(document).ready(function() {
    oppRestoreFilters();
    oppToolbar = ListToolbar('#opp-toolbar', function() { return opportunityTable; });
    $(document).on('click', '#opp-toolbar [data-reset]', function() {
        $('#opp-close-preset').val('');
        $('#opp-close-range, #opp-year').hide();
    });
    initOpportunityTable();
});
</script>
@endsection
