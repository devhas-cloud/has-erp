@extends('layouts.app')

@section('title', 'Leads Management')
@section('page-title', 'Leads Management')

@section('styles')
<style>
    .modal-lead .modal-dialog { max-width: 800px; }
    .lead-form-section {
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        margin-bottom: 16px;
        overflow: hidden;
    }
    .lead-form-section-header {
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
    .lead-form-section-body { padding: 16px; display: none; }
    .lead-form-section.open .lead-form-section-body { display: block; }
    .lead-form-section-header .chevron { transition: transform 0.2s; font-size: 11px; color: var(--text-muted); }
    .lead-form-section.open .chevron { transform: rotate(180deg); }
    .lead-form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
    .lead-form-row .form-group { flex: 1; min-width: 200px; }
    .lead-form-row .form-group.small { flex: 0 0 160px; }
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
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Leads Management</h1>
        <p class="page-header-sub">Kelola data leads dan calon pelanggan</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        {{-- <a href="{{ route('leads-management.template') }}" class="btn btn-outline-secondary btn-sm me-2" title="Download Template">
            <i class="fa fa-download"></i>
            <span>Template</span>
        </a> --}}
        <button type="button" class="btn btn-outline-success btn-sm me-2" onclick="openImportModal()">
            <i class="fa fa-upload"></i>
            <span>Import</span>
        </button>
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i>
            <span>Add Lead</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa fa-bullhorn me-2" style="color:var(--accent)"></i>Leads List</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="leads-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Name</th>
                        <th>Title</th>
                        <th>Company</th>
                        <th>Phone</th>
                        <th>Mobile</th>
                        <th>Lead Status</th>
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
<div class="modal fade modal-lead" id="leadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="leadModalTitle">Add Lead</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                <form id="lead-form" autocomplete="off">
                    <input type="hidden" id="lead-edit-id">

                    <div class="lead-form-section open">
                        <div class="lead-form-section-header" onclick="toggleLeadSection(this)">
                            <span><i class="fa fa-user me-2" style="color:var(--accent)"></i>Lead Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="lead-form-section-body">
                            <div class="lead-form-row">
                                <div class="form-group small">
                                    <label>Lead Status <span class="text-danger">*</span></label>
                                    <select name="lead_status" id="lead-status">
                                        <option value="New">New</option>
                                        <option value="Approach">Approach</option>
                                        <option value="Qualified">Qualified</option>
                                        <option value="Unqualified">Unqualified</option>
                                    </select>
                                </div>
                                <div class="form-group small">
                                    <label>Salutation <span class="text-danger">*</span></label>
                                    <select name="salutation" id="lead-salutation">
                                        <option value="">—</option>
                                        <option value="Bapak">Bapak</option>
                                        <option value="Ibu">Ibu</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" id="lead-full-name" required>
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" id="lead-email">
                                </div>
                                <div class="form-group">
                                    <label>Mobile</label>
                                    <input type="text" name="mobile" id="lead-mobile">
                                </div>
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" name="phone" id="lead-phone">
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Job Title <span class="text-danger">*</span></label>
                                    <select name="job_titles_id" id="lead-job-title">
                                        <option value="">— Pilih —</option>
                                        @foreach($jobTitles as $jt)
                                        <option value="{{ $jt->id }}">{{ $jt->title_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Department <span class="text-danger">*</span></label>
                                    <select name="divisions_id" id="lead-division">
                                        <option value="">— Pilih —</option>
                                        @foreach($divisions as $div)
                                        <option value="{{ $div->id }}">{{ $div->division_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Lead Source <span class="text-danger">*</span></label>
                                    <select name="source_id" id="lead-source">
                                        <option value="">— Pilih —</option>
                                        @foreach($sources as $src)
                                        <option value="{{ $src->id }}">{{ $src->source_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Preferred Contact Method</label>
                                    <select name="contact_methods_id" id="lead-contact-method">
                                        <option value="">— Pilih —</option>
                                        @foreach($contactMethods as $cm)
                                        <option value="{{ $cm->id }}">{{ $cm->method_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Role in Project</label>
                                    <select name="role_in_projects_id" id="lead-role">
                                        <option value="">— Pilih —</option>
                                        @foreach($roleInProjects as $rp)
                                        <option value="{{ $rp->id }}">{{ $rp->role_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group" style="flex:0 0 180px;">
                                    <label>Close Date</label>
                                    <input type="date" name="closed_date" id="lead-close-date">
                                </div>
                                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:8px;">
                                    <label class="form-check-inline">
                                        <input type="checkbox" name="all_filed_completed" id="lead-all-complete" value="1">
                                        All Field Completed
                                    </label>
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Unqualified Reason</label>
                                    <textarea name="unqualified_reason" id="lead-unqualified" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lead-form-section">
                        <div class="lead-form-section-header" onclick="toggleLeadSection(this)">
                            <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Account Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="lead-form-section-body">
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Title <span class="text-danger">*</span></label>
                                    <input type="text" name="lead_title" id="lead-title-acc">
                                </div>
                                <div class="form-group">
                                    <label>Company</label>
                                    <select id="lead-company" style="width:100%"></select>
                                    <input type="hidden" name="account_companies_id" id="lead-company-id">
                                </div>

                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Field Type <span class="text-danger">*</span></label>
                                    <select name="types_accounts_companies_id" id="lead-field-type">
                                        <option value="">— Pilih —</option>
                                        @foreach($typesAccountsCompanies as $tac)
                                        <option value="{{ $tac->id }}">{{ $tac->type_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Segmentation <span class="text-danger">*</span></label>
                                    <select name="segmentation_id" id="lead-segmentation">
                                        <option value="">— Pilih —</option>
                                        @foreach($segmentations as $seg)
                                        <option value="{{ $seg->id }}">{{ $seg->segmentation_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Account Type <span class="text-danger">*</span></label>
                                    <select name="account_types_id" id="lead-account-type">
                                        <option value="">— Pilih —</option>
                                        @foreach($accountTypes as $at)
                                        <option value="{{ $at->id }}">{{ $at->type_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Business Entity</label>
                                    <select name="business_entities_id" id="lead-biz-entity">
                                        <option value="">— Pilih —</option>
                                        @foreach($businessEntities as $be)
                                        <option value="{{ $be->id }}">{{ $be->entity_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Business Value</label>
                                    <select name="business_values_id" id="lead-biz-value">
                                        <option value="">— Pilih —</option>
                                        @foreach($businessValues as $bv)
                                        <option value="{{ $bv->id }}">{{ $bv->value_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Interaction Level</label>
                                    <select name="interaction_levels_id" id="lead-interaction">
                                        <option value="">— Pilih —</option>
                                        @foreach($interactionLevels as $il)
                                        <option value="{{ $il->id }}">{{ $il->level_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Address Street</label>
                                    <input type="text" name="address_street" id="lead-addr-street">
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>City</label>
                                    <input type="text" name="address_city" id="lead-addr-city">
                                </div>
                                <div class="form-group">
                                    <label>Province</label>
                                    <input type="text" name="address_province" id="lead-addr-province">
                                </div>
                                <div class="form-group small">
                                    <label>Zip</label>
                                    <input type="text" name="address_zip" id="lead-addr-zip">
                                </div>
                                <div class="form-group">
                                    <label>Country</label>
                                    <input type="text" name="address_country" id="lead-addr-country">
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>End User</label>
                                    <select name="end_user" id="lead-end-user">
                                        <option value="">— Pilih —</option>
                                        @foreach($accountCompanies as $ac)
                                        <option value="{{ $ac->id }}">{{ $ac->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lead-form-section">
                        <div class="lead-form-section-header" onclick="toggleLeadSection(this)">
                            <span><i class="fa fa-info-circle me-2" style="color:var(--accent)"></i>Additional Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="lead-form-section-body">
                            <div class="lead-form-row">
                                <label class="form-check-inline">
                                    <input type="checkbox" name="lead_can_be_contacted" id="lead-can-contact" value="1">
                                    Lead Can Be Contacted
                                </label>
                                <label class="form-check-inline">
                                    <input type="checkbox" name="lead_appoinment" id="lead-appointment" value="1">
                                    Lead Appointment
                                </label>
                                <label class="form-check-inline">
                                    <input type="checkbox" name="identification" id="lead-identification" value="1">
                                    Need Identification
                                </label>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group small">
                                    <label>Follow Up Date <span class="text-danger">*</span></label>
                                    <input type="date" name="lead_follow_up_date" id="lead-follow-up">
                                </div>
                                <div class="form-group">
                                    <label>Assign To</label>
                                    <select name="assigned_to" id="lead-assigned">
                                        <option value="">— Pilih —</option>
                                        @foreach($users as $u)
                                        <option value="{{ $u->id }}">{{ $u->username }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-lead">
                    <i class="fa fa-save me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('modals')
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Import Leads</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fa fa-file-excel" style="font-size:40px;color:#217346"></i>
                    <p class="mt-2 mb-0" style="font-size:13px;color:var(--text-muted)">
                        Download template, isi data, lalu upload file CSV.
                    </p>
                    <a href="{{ route('leads-management.template') }}" class="btn btn-sm btn-outline-success mt-2">
                        <i class="fa fa-download me-1"></i> Download Template (.xlsx)
                    </a>
                </div>
                <hr>
                <form id="import-form" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:12px;font-weight:600">Pilih File CSV</label>
                        <input type="file" name="file" id="import-file" class="form-control" accept=".csv,.txt" required>
                        <small class="text-muted">Maksimal 5MB. Format: CSV (Save As dari Excel).</small>
                    </div>
                    <div id="import-result" style="display:none;font-size:13px;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-import">
                    <i class="fa fa-upload me-1"></i> Upload & Import
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let leadModalInstance = null;
let leadsTable = null;

const leadsCanUpdate = {{ $canUpdate ? 'true' : 'false' }};
const leadsCanDelete = {{ $canDelete ? 'true' : 'false' }};
const showUrl = '{{ route("leads-management.show", "__ID__") }}';
const fetchUrl = '{{ route("leads-management.fetch", "__ID__") }}';

function toggleLeadSection(header) {
    header.closest('.lead-form-section').classList.toggle('open');
}

function resetLeadForm() {
    document.getElementById('lead-form').reset();
    document.getElementById('lead-edit-id').value = '';
    document.querySelectorAll('.lead-form-section').forEach(function(s) {
        s.classList.remove('open');
    });
    document.querySelector('.lead-form-section').classList.add('open');
    $('#lead-form .is-invalid').removeClass('is-invalid');
    $('#lead-company').val('').trigger('change');
    $('#lead-company-id').val('');
    $('#lead-end-user').val('').trigger('change');
}

function openCreateModal() {
    resetLeadForm();
    document.getElementById('leadModalTitle').textContent = 'Add Lead';
    if (!leadModalInstance) {
        leadModalInstance = new bootstrap.Modal(document.getElementById('leadModal'));
    }
    leadModalInstance.show();
}

function openEditModal(id) {
    resetLeadForm();
    document.getElementById('leadModalTitle').textContent = 'Edit Lead';
    if (!leadModalInstance) {
        leadModalInstance = new bootstrap.Modal(document.getElementById('leadModal'));
    }

    $.get(fetchUrl.replace('__ID__', id), function(res) {
        $('#lead-edit-id').val(res.lead.id);

        $('#lead-status').val(res.lead.lead_status);
        $('#lead-salutation').val(res.contact ? res.contact.salutation : '').trigger('change');
        $('#lead-full-name').val(res.contact ? res.contact.full_name : '');
        $('#lead-email').val(res.contact ? res.contact.email : '');
        $('#lead-mobile').val(res.contact ? res.contact.mobile : '');
        $('#lead-phone').val(res.contact ? res.contact.phone : '');
        $('#lead-job-title').val(res.contact ? res.contact.job_titles_id : '').trigger('change');
        $('#lead-division').val(res.contact ? res.contact.divisions_id : '').trigger('change');
        $('#lead-source').val(res.lead.source_id).trigger('change');
        $('#lead-contact-method').val(res.contact ? res.contact.contact_methods_id : '').trigger('change');
        $('#lead-role').val(res.contact ? res.contact.role_in_projects_id : '').trigger('change');
        if (res.lead.closed_date) {
            $('#lead-close-date').val(res.lead.closed_date.substring(0, 10));
        }
        if (res.lead.all_filed_completed) {
            $('#lead-all-complete').prop('checked', true);
        }
        $('#lead-unqualified').val(res.lead.unqualified_reason);

        $('#lead-title-acc').val(res.lead.lead_title);
        if (res.company) {
            var option = new Option(res.company.account_name, res.company.id, true, true);
            $('#lead-company').append(option).trigger('change');
            $('#lead-company-id').val(res.company.id);
            $('#lead-field-type').val(res.company.types_accounts_companies_id).trigger('change');
            $('#lead-segmentation').val(res.company.segmentation_id).trigger('change');
            $('#lead-account-type').val(res.company.account_types_id).trigger('change');
            $('#lead-biz-entity').val(res.company.business_entities_id).trigger('change');
            $('#lead-biz-value').val(res.company.business_values_id).trigger('change');
            $('#lead-interaction').val(res.company.interaction_levels_id).trigger('change');
            $('#lead-addr-street').val(res.company.address_billing_street);
            $('#lead-addr-city').val(res.company.address_billing_city);
            $('#lead-addr-province').val(res.company.address_billing_province);
            $('#lead-addr-zip').val(res.company.address_billing_postal_code);
            $('#lead-addr-country').val(res.company.address_billing_country);
            $('#lead-end-user').val(res.company.end_user).trigger('change');
        }

        if (res.lead.lead_can_be_contacted) {
            $('#lead-can-contact').prop('checked', true);
        }
        if (res.lead.lead_appoinment) {
            $('#lead-appointment').prop('checked', true);
        }
        if (res.lead.identification) {
            $('#lead-identification').prop('checked', true);
        }
        if (res.lead.lead_follow_up_date) {
            $('#lead-follow-up').val(res.lead.lead_follow_up_date.substring(0, 10));
        }
        $('#lead-assigned').val(res.lead.assigned_to).trigger('change');

        leadModalInstance.show();
    }).fail(function() {
        toastr.error('Failed to load lead data.');
    });
}

function initLeadsTable() {
    if (leadsTable) {
        leadsTable.destroy();
    }

    leadsTable = $('#leads-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("leads-management.data") }}',
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            {
                data: 'full_name', orderable: true, searchable: true,
                render: function(data, type, row) {
                    var avatar = row.icon
                        ? '<img src="' + row.icon + '" class="avatar-circle" alt="" style="background:transparent">'
                        : '<div class="avatar-circle">' + row.initials + '</div>';
                    return '<div style="display:flex;align-items:center;gap:10px">' +
                        avatar +
                        '<strong style="color:var(--text-primary);font-weight:600">' + row.name_display + '</strong>' +
                        '</div>';
                }
            },
            { data: 'lead_title' },
            {
                data: 'account_name', orderable: true, searchable: true,
                render: function(data, type, row) {
                    var avatar = row.company_icon
                        ? '<img src="' + row.company_icon + '" class="avatar-circle" alt="" style="background:transparent">'
                        : '<div class="avatar-circle">' + row.company_initials + '</div>';
                    return '<div style="display:flex;align-items:center;gap:10px">' +
                        avatar +
                        '<span style="color:var(--text-primary);font-weight:500">' + row.company_name_display + '</span>' +
                        '</div>';
                }
            },
            { data: 'phone' },
            { data: 'mobile' },
            { data: 'status_badge' },
            { data: 'owner_name' },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    var html = '<div style="display:flex;gap:5px;justify-content:center">';
                    html += '<a href="' + showUrl.replace('__ID__', row.id) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (leadsCanUpdate) {
                        html += ' <button type="button" class="btn-icon" title="Edit" onclick="openEditModal(' + row.id + ')"><i class="fa fa-pen"></i></button>';
                    }
                    if (leadsCanDelete) {
                        html += ' <button type="button" class="btn-icon danger btn-delete-lead" title="Hapus" data-id="' + row.id + '"><i class="fa fa-trash-can"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        order: [[0, 'asc']],
        pageLength: 10,
        lengthMenu: [10, 15, 25, 50, 100],
        // language: {
        //     processing: '<i class="fa fa-spinner fa-spin"></i> Loading...',
        //     search: '',
        //     searchPlaceholder: 'Search...',
        //     lengthMenu: '_MENU_',
        //     info: 'Show _START_–_END_ of _TOTAL_',
        //     infoEmpty: 'No data available.',
        //     infoFiltered: '(filtered from _MAX_ total entries)',
        //     zeroRecords: 'No data found.',
        //     paginate: {
        //         first: '<i class="fa fa-angle-double-left"></i>',
        //         last: '<i class="fa fa-angle-double-right"></i>',
        //         previous: '<i class="fa fa-angle-left"></i>',
        //         next: '<i class="fa fa-angle-right"></i>'
        //     }
        // },
        // initComplete: function() {
        //     $('.dataTables_filter input').attr('placeholder', 'Search...');
        // }
    });
}

$(document).on('click', '#btn-save-lead', function() {
    const $btn = $(this);
    const editId = $('#lead-edit-id').val();
    const isEdit = !!editId;

    $('#lead-form .is-invalid').removeClass('is-invalid');

    const validations = [
        { field: '#lead-status', label: 'Lead Status' },
        { field: '#lead-salutation', label: 'Salutation' },
        { field: '#lead-full-name', label: 'Full Name' },
        { field: '#lead-email', label: 'Email' },
        { field: '#lead-job-title', label: 'Job Title' },
        { field: '#lead-division', label: 'Department' },
        { field: '#lead-source', label: 'Lead Source' },
        { field: '#lead-title-acc', label: 'Title' },
        { field: '#lead-field-type', label: 'Field Type' },
        { field: '#lead-segmentation', label: 'Segmentation' },
        { field: '#lead-account-type', label: 'Account Type' },
        { field: '#lead-follow-up', label: 'Follow Up Date' },
    ];

    for (let i = 0; i < validations.length; i++) {
        const v = validations[i];
        const $el = $(v.field);
        const val = $el.val() ? $el.val().trim() : '';
        if (!val) {
            $el.addClass('is-invalid');
            const section = $el.closest('.lead-form-section');
            if (section.length && !section.hasClass('open')) {
                section.addClass('open');
            }
            toastr.error(v.label + ' wajib diisi.');
            $el.focus();
            return;
        }
    }

    const formData = new FormData(document.getElementById('lead-form'));
    formData.set('all_filed_completed', $('#lead-all-complete').is(':checked') ? '1' : '0');
    formData.set('lead_can_be_contacted', $('#lead-can-contact').is(':checked') ? '1' : '0');
    formData.set('lead_appoinment', $('#lead-appointment').is(':checked') ? '1' : '0');
    formData.set('identification', $('#lead-identification').is(':checked') ? '1' : '0');

    const url = isEdit
        ? '{{ route("leads-management.update", "__ID__") }}'.replace('__ID__', editId)
        : '{{ route("leads-management.store") }}';

    const companyId = $('#lead-company-id').val();
    if (companyId) {
        formData.delete('account_companies_id');
        formData.set('account_companies_id', companyId);
    } else {
        const freeText = $('#lead-company').val();
        if (freeText && freeText.trim()) {
            formData.set('company', freeText.trim());
        }
    }

    formData.append('_token', '{{ csrf_token() }}');
    if (isEdit) formData.append('_method', 'PUT');

    Swal.fire({
        title: isEdit ? 'Update Lead?' : 'Save Lead?',
        text: isEdit ? 'Lead data will be updated.' : 'A new lead will be added.',
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
                if (leadModalInstance) leadModalInstance.hide();
                if (leadsTable) leadsTable.ajax.reload(null, false);
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

$(document).on('change input', '#lead-form input.is-invalid, #lead-form select.is-invalid', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('click', '.btn-delete-lead', function() {
    const id = $(this).data('id');
    Swal.fire({
        title: 'Sure to delete this lead?',
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
                url: '{{ route("leads-management.destroy", "__ID__") }}'.replace('__ID__', id),
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    toastr.success(res.message);
                    if (leadsTable) leadsTable.ajax.reload(null, false);
                },
                error: function() {
                    toastr.error('Failed to delete data.');
                }
            });
        }
    });
});

let importModalInstance = null;

function openImportModal() {
    document.getElementById('import-form').reset();
    document.getElementById('import-result').style.display = 'none';
    document.getElementById('import-result').innerHTML = '';
    if (!importModalInstance) {
        importModalInstance = new bootstrap.Modal(document.getElementById('importModal'));
    }
    importModalInstance.show();
}

$(document).on('click', '#btn-import', function() {
    const $btn = $(this);
    const fileInput = document.getElementById('import-file');
    const file = fileInput.files[0];

    if (!file) {
        toastr.error('Pilih file CSV terlebih dahulu.');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('_token', '{{ csrf_token() }}');

    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Importing...');

    $.ajax({
        url: '{{ route("leads-management.import") }}',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            $btn.prop('disabled', false).html('<i class="fa fa-upload me-1"></i> Upload & Import');

            const resultDiv = document.getElementById('import-result');
            resultDiv.style.display = 'block';

            let html = '<div class="alert alert-success py-2 mb-2">' + res.message + '</div>';
            if (res.result && res.result.errors && res.result.errors.length > 0) {
                html += '<div class="alert alert-warning py-2"><strong>Detail error:</strong><br>' +
                    res.result.errors.map(function(e) { return '&bull; ' + e; }).join('<br>') +
                    '</div>';
            }
            resultDiv.innerHTML = html;

            if (leadsTable) leadsTable.ajax.reload(null, false);
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html('<i class="fa fa-upload me-1"></i> Upload & Import');
            var msg = xhr.responseJSON?.message || 'Gagal import file.';
            toastr.error(msg);
        }
    });
});

$(document).on('shown.bs.modal', '#leadModal', function() {
    if (!$('#lead-company').hasClass('select2-hidden-accessible')) {
        $('#lead-company').select2({
            theme: 'bootstrap-5',
            placeholder: 'Ketik nama perusahaan...',
            allowClear: true,
            width: '100%',
            tags: true,
            createTag: function(params) {
                return {
                    id: params.term,
                    text: params.term + ' (new)',
                    newTag: true
                };
            },
            dropdownParent: $('#leadModal'),
            ajax: {
                url: '{{ route("leads-management.search-companies") }}',
                dataType: 'json',
                delay: 250,
                data: function(params) { return { q: params.term }; },
                processResults: function(res) { return { results: res.results }; }
            }
        }).on('select2:select', function(e) {
            var c = e.params.data;
            if (c.newTag) {
                $('#lead-company-id').val('');
            } else {
                $('#lead-company-id').val(c.id);
                if (c.segmentation_id) $('#lead-segmentation').val(c.segmentation_id);
                if (c.account_types_id) $('#lead-account-type').val(c.account_types_id);
                if (c.types_accounts_companies_id) $('#lead-field-type').val(c.types_accounts_companies_id);
                if (c.business_entities_id) $('#lead-biz-entity').val(c.business_entities_id);
                if (c.business_values_id) $('#lead-biz-value').val(c.business_values_id);
                if (c.interaction_levels_id) $('#lead-interaction').val(c.interaction_levels_id);
                if (c.address_billing_street) $('#lead-addr-street').val(c.address_billing_street);
                if (c.address_billing_city) $('#lead-addr-city').val(c.address_billing_city);
                if (c.address_billing_province) $('#lead-addr-province').val(c.address_billing_province);
                if (c.address_billing_postal_code) $('#lead-addr-zip').val(c.address_billing_postal_code);
                if (c.address_billing_country) $('#lead-addr-country').val(c.address_billing_country);
            }
        }).on('select2:clear', function() {
            $('#lead-company-id').val('');
        });
    }

    if (!$('#lead-end-user').hasClass('select2-hidden-accessible')) {
        $('#lead-end-user').select2({
            theme: 'bootstrap-5',
            placeholder: '— Pilih —',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#leadModal')
        });
    }
});

initLeadsTable();
</script>
@endsection
