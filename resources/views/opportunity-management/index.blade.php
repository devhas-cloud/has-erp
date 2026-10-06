@extends('layouts.app')

@section('title', 'Opportunity Management')
@section('page-title', 'Opportunity Management')

@section('styles')
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

    /* ===== Toolbar: filter Close Date + urutkan ===== */
    .opp-toolbar {
        margin: 6px 6px 14px;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    }
    .opp-toolbar-main {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px 24px;
        padding: 14px 16px;
    }
    .opp-toolbar-group { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
    /* Grup filter mengisi sisa lebar; rentang kustom turun ke baris kedua bila tidak muat, dropdown Stage tetap di kanan */
    .opp-toolbar-filters { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 12px; }
    .opp-stage-filter { flex: 0 0 auto; }
    .opp-toolbar-label {
        font-size: 10.5px;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .opp-toolbar-label i { color: var(--accent); margin-right: 5px; }
    .opp-toolbar-controls { display: flex; align-items: center; gap: 8px 10px; flex-wrap: wrap; }

    .opp-chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .opp-chip {
        padding: 7px 13px;
        border-radius: 20px;
        border: 1px solid var(--card-border);
        background: var(--card);
        color: var(--text-secondary);
        font-size: 12px;
        font-weight: 700;
        font-family: inherit;
        line-height: 1.2;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s var(--ease);
    }
    .opp-chip:hover {
        border-color: rgba(16, 185, 129, 0.3);
        color: var(--accent);
        background: var(--accent-soft);
    }
    .opp-chip.active {
        background: linear-gradient(135deg, var(--accent), #059669);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 2px 8px var(--accent-glow);
    }
    .opp-chip:focus-visible, .opp-summary-reset:focus-visible {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }

    .opp-toolbar .opp-year { width: auto; min-width: 92px; padding: 6px 30px 6px 12px; font-size: 12.5px; font-weight: 700; }
    .opp-range { display: flex; align-items: center; gap: 6px; }
    .opp-range .form-control { width: 148px; padding: 6px 10px; font-size: 12.5px; }
    .opp-range-sep { color: var(--text-muted); font-size: 12px; font-weight: 700; }
    .opp-range.is-custom .form-control { border-color: var(--accent); }

    .opp-stage-filter .form-select { min-width: 210px; padding: 7px 34px 7px 12px; font-size: 12.5px; font-weight: 600; }

    .opp-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        padding: 9px 16px;
        border-top: 1px solid var(--card-border);
        font-size: 12.5px;
        color: var(--text-secondary);
        font-weight: 500;
    }
    .opp-summary strong { color: var(--text-primary); font-weight: 800; }
    .opp-summary-reset {
        border: none;
        background: none;
        padding: 2px 4px;
        color: var(--danger);
        font-size: 12px;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        border-radius: 6px;
    }
    .opp-summary-reset:hover { text-decoration: underline; }

    .opp-date { color: var(--text-primary); font-weight: 600; white-space: nowrap; }
    .opp-date-rel { font-size: 11px; color: var(--text-muted); font-weight: 600; white-space: nowrap; }

    @media (max-width: 768px) {
        .opp-toolbar-main { align-items: stretch; flex-direction: column; }
        .opp-range { width: 100%; }
        .opp-range .form-control { flex: 1; width: auto; min-width: 0; }
        .opp-stage-filter .form-select { width: 100%; min-width: 0; }
        .opp-toolbar .opp-year { width: 100%; }
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Opportunity Management</h1>
        <p class="page-header-sub">Kelola data opportunity dan peluang penjualan</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i>
            <span>Add Opportunity</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa fa-bullseye me-2" style="color:var(--accent)"></i>Opportunity List</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="opp-toolbar">
            <div class="opp-toolbar-main">
                <div class="opp-toolbar-filters">
                <div class="opp-toolbar-group">
                    <span class="opp-toolbar-label"><i class="fa fa-calendar-days"></i>Close Date</span>
                    <div class="opp-toolbar-controls">

                        <div class="opp-chips" role="group" aria-label="Rentang close date">
                            <button type="button" class="opp-chip" data-preset="q1" title="Januari – Maret">Kuartal 1</button>
                            <button type="button" class="opp-chip" data-preset="q2" title="April – Juni">Kuartal 2</button>
                            <button type="button" class="opp-chip" data-preset="q3" title="Juli – September">Kuartal 3</button>
                            <button type="button" class="opp-chip" data-preset="q4" title="Oktober – Desember">Kuartal 4</button>
                            <button type="button" class="opp-chip" data-preset="year" title="Januari – Desember">Setahun</button>
                            <button type="button" class="opp-chip" data-preset="all" title="Tanpa filter tanggal">Semua</button>
                        </div>
                        <div class="opp-range" id="opp-range">
                            <input type="date" id="filter-close-from" class="form-control" aria-label="Close date dari" title="Dari tanggal">
                            <span class="opp-range-sep">–</span>
                            <input type="date" id="filter-close-to" class="form-control" aria-label="Close date sampai" title="Sampai tanggal">
                        </div>
                    </div>
                </div>
                </div>
                <div class="opp-toolbar-group opp-stage-filter">
                    <label class="opp-toolbar-label" for="filter-stage"><i class="fa fa-layer-group"></i>Stage</label>
                    <select id="filter-stage" class="form-select">
                        <option value="">Semua Stage</option>
                        @foreach($stages as $s)
                        <option value="{{ $s->id }}">{{ $s->stage_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="opp-summary" aria-live="polite">
                <span id="opp-summary-text">Memuat…</span>
                <button type="button" class="opp-summary-reset" id="btn-reset-close-filter" style="display:none">
                    <i class="fa fa-xmark me-1"></i>Hapus filter
                </button>
            </div>
        </div>
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
                            <span><i class="fa fa-bullseye me-2" style="color:var(--accent)"></i>Opportunity Information</span>
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
                                        <option value="">— Pilih —</option>
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
                                        <option value="">— Pilih —</option>
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
                                        <option value="">— Pilih —</option>
                                        @foreach($lossReasons as $lr)
                                        <option value="{{ $lr->id }}">{{ $lr->reason_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Division</label>
                                    <select name="division_id" id="opp-division">
                                        <option value="">— Pilih —</option>
                                        @foreach($divisions as $d)
                                        <option value="{{ $d->id }}">{{ $d->division_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group" style="display: none">
                                    <label>Lead Source</label>
                                    <select name="source_id" id="opp-source">
                                        <option value="">— Pilih —</option>
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
                                        <option value="">— Pilih —</option>
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
                            <span><i class="fa fa-check-circle me-2" style="color:var(--accent)"></i>BAT Information</span>
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
                            <span><i class="fa fa-info-circle me-2" style="color:var(--accent)"></i>Additional Information</span>
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
                    <i class="fa fa-save me-1"></i> Save
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
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("opportunity-management.data") }}',
            data: function(d) {
                d.close_date_from = $('#filter-close-from').val();
                d.close_date_to = $('#filter-close-to').val();
                d.stage_ids = selectedStageIds();
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
                    var initials = row.company_initials || '?';
                    return '<div style="display:flex;align-items:center;gap:10px">' +
                        '<div class="avatar-circle">' + initials + '</div>' +
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
                    var rel = days === 0 ? 'Hari ini' : (days > 0 ? days + ' hari lagi' : Math.abs(days) + ' hari lalu');
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
                        html += ' <button type="button" class="btn-icon danger btn-delete-opportunity" title="Hapus" data-id="' + row.id + '"><i class="fa fa-trash-can"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        order: [[1, 'asc']],
        pageLength: 10,
        lengthMenu: [10, 15, 25, 50, 100],
        // Filter, urutan, pencarian & halaman tetap saat kembali dari halaman detail (per tab browser)
        stateSave: true,
        stateDuration: -1,
        stateSaveParams: function(settings, data) {
            data.oppClose = {
                v: OPP_FILTER_VERSION,
                preset: oppActivePreset,
                year: $('#opp-year').val(),
                from: $('#filter-close-from').val(),
                to: $('#filter-close-to').val(),
                stages: selectedStageIds(),
            };
        },
        stateLoadParams: function(settings, data) {
            var saved = data.oppClose;
            // Filter tersimpan dari versi lama (bawaan kuartal berjalan) diabaikan
            if (!saved || saved.v !== OPP_FILTER_VERSION) return;
            setSelectedStages(saved.stages || []);
            if (isClosePreset(saved.preset)) {
                if (saved.year && $('#opp-year option[value="' + saved.year + '"]').length) {
                    $('#opp-year').val(saved.year);
                }
                applyClosePreset(saved.preset);
            } else {
                setCloseRange(saved.from, saved.to);
            }
        },
    });
}

// --- Filter Close Date (preset + rentang kustom) ---
const OPP_DATE_FMT = 'YYYY-MM-DD';
// Filter bawaan saat halaman pertama dibuka: semua tanggal, supaya seluruh opportunity langsung tampil.
// Ganti ke 'q' + moment().quarter() bila ingin bawaan kuartal berjalan.
const OPP_DEFAULT_PRESET = 'all';
// Naikkan angka ini bila bawaan filter berubah, agar filter tersimpan versi lama tidak dipakai lagi.
const OPP_FILTER_VERSION = 2;
let oppActivePreset = 'all';

function isClosePreset(preset) {
    return /^(q[1-4]|year|all)$/.test(preset || '');
}

// Tahun acuan kuartal: dropdown #opp-year bila ada, kalau tidak tahun berjalan
function closeYear() {
    return parseInt($('#opp-year').val(), 10) || moment().year();
}

// Rentang tanggal sebuah preset pada tahun acuan
function closePresetRange(preset) {
    var year = closeYear();
    if (preset === 'year') {
        return [moment({ year: year }).startOf('year'), moment({ year: year }).endOf('year')];
    }
    var q = /^q([1-4])$/.exec(preset);
    if (!q) return null;
    var start = moment({ year: year, month: (parseInt(q[1], 10) - 1) * 3, day: 1 });
    return [start, start.clone().add(2, 'months').endOf('month')];
}

function setCloseRange(from, to, preset) {
    from = from || '';
    to = to || '';
    $('#filter-close-from').val(from);
    $('#filter-close-to').val(to);

    oppActivePreset = preset || ((from || to) ? 'custom' : 'all');
    $('.opp-chip[data-preset]').each(function() {
        var active = $(this).data('preset') === oppActivePreset;
        $(this).toggleClass('active', active).attr('aria-pressed', active ? 'true' : 'false');
    });
    $('#opp-range').toggleClass('is-custom', oppActivePreset === 'custom');
}

function applyClosePreset(preset) {
    var range = closePresetRange(preset);
    if (range) {
        setCloseRange(range[0].format(OPP_DATE_FMT), range[1].format(OPP_DATE_FMT), preset);
    } else {
        setCloseRange('', '', 'all');
    }
}

function closeRangeLabel() {
    var from = $('#filter-close-from').val();
    var to = $('#filter-close-to').val();
    var fmt = function(d) { return moment(d).format('DD MMM YYYY'); };

    var q = /^q([1-4])$/.exec(oppActivePreset);
    if (q) return 'Kuartal ' + q[1] + ' ' + closeYear() + ' (' + moment(from).format('DD MMM') + ' – ' + fmt(to) + ')';
    if (oppActivePreset === 'year') return 'Tahun ' + closeYear();

    if (from && to) return from === to ? fmt(from) : fmt(from) + ' – ' + fmt(to);
    if (from) return 'mulai ' + fmt(from);
    if (to) return 'sampai ' + fmt(to);
    return '';
}

$(document).on('click', '.opp-chip[data-preset]', function() {
    applyClosePreset($(this).data('preset'));
    if (opportunityTable) opportunityTable.ajax.reload();
});

// Ganti tahun: kuartal/setahun yang sedang aktif ikut pindah tahun; "Semua" & rentang kustom tidak diubah
$('#opp-year').on('change', function() {
    if (!closePresetRange(oppActivePreset)) return;
    applyClosePreset(oppActivePreset);
    if (opportunityTable) opportunityTable.ajax.reload();
});

$('#filter-close-from, #filter-close-to').on('change', function() {
    var from = $('#filter-close-from').val();
    var to = $('#filter-close-to').val();

    // Rentang terbalik → tukar otomatis, jangan dibuang
    if (from && to && from > to) {
        var tmp = from; from = to; to = tmp;
    }
    setCloseRange(from, to);
    if (opportunityTable) opportunityTable.ajax.reload();
});

// --- Filter Stage (dropdown; kosong = semua stage) ---
function selectedStageIds() {
    var stage = $('#filter-stage').val();
    return stage ? [stage] : [];
}

function setSelectedStages(ids) {
    var stage = String((ids || [])[0] || '');
    // Id tersimpan yang stage-nya sudah tidak ada → kembali ke "Semua Stage"
    $('#filter-stage').val($('#filter-stage option[value="' + stage + '"]').length ? stage : '');
}

function stageLabel() {
    return $('#filter-stage').val() ? $('#filter-stage option:selected').text().trim() : '';
}

$('#filter-stage').on('change', function() {
    if (opportunityTable) opportunityTable.ajax.reload();
});

$('#btn-reset-close-filter').on('click', function() {
    applyClosePreset('all');
    setSelectedStages([]);
    if (opportunityTable) opportunityTable.ajax.reload();
});

// Ringkasan hasil di bawah toolbar
$('#opportunity-table').on('draw.dt', function(e, settings) {
    var api = new $.fn.dataTable.Api(settings);
    var info = api.page.info();
    var label = closeRangeLabel();
    var stages = stageLabel();

    $('#opp-summary-text').html(
        '<strong>' + info.recordsDisplay.toLocaleString('id-ID') + '</strong> opportunity' +
        (label ? ' · Close Date <strong>' + label + '</strong>' : ' · semua tanggal') +
        (stages ? ' · Stage <strong>' + $('<span>').text(stages).html() + '</strong>' : ' · semua stage')
    );
    $('#btn-reset-close-filter').toggle(!!(label || stages));
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
            toastr.error(v.label + ' wajib diisi.');
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

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Saving...');

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
                $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save');
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save');
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
            placeholder: '— Pilih Contact —',
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
            placeholder: '— Pilih —',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#opportunityModal')
        });
    }
});
applyClosePreset(OPP_DEFAULT_PRESET); // ditimpa oleh filter tersimpan (stateLoadParams) bila ada
initOpportunityTable();
</script>
@endsection
