@extends('layouts.app')

@section('title', 'Leads Management')
@section('page-title', 'Leads Management')

@section('styles')
@include('partials.list-toolbar')
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
    .mobile-picker { display: flex; gap: 8px; align-items: stretch; }
    .mobile-picker select { width: 92px; flex: 0 0 92px; }
    .mobile-picker input { flex: 1; }

    /* ===== Peta alamat lead (pola accounts-management) ===== */
    .lead-addr-map-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }
    .lead-addr-map-toolbar .map-coords {
        font-size: 11px;
        color: var(--text-muted);
        margin-left: auto;
    }
    .lead-addr-map {
        width: 100%;
        height: 260px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--card-border);
        background: #f1f5f9;
        z-index: 1;
    }
    .lead-addr-map-status {
        font-size: 11px;
        color: var(--text-muted);
        margin: 6px 0;
        min-height: 16px;
    }
    .lead-addr-map-status.loading { color: #2563eb; }
    .lead-addr-map-status.error { color: #b91c1c; }
    .lead-addr-map-preview {
        display: none;
        border: 1px solid var(--accent-soft);
        background: #fff;
        border-radius: var(--radius-sm);
        padding: 10px 12px;
        margin-top: 8px;
    }
    .lead-addr-map-preview-title {
        font-weight: 700;
        font-size: 12px;
        color: var(--accent);
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .lead-addr-map-preview-body {
        font-size: 12px;
        color: var(--text-primary);
        line-height: 1.5;
        margin-bottom: 8px;
        white-space: pre-line;
    }
    .lead-addr-map-preview-actions {
        display: flex;
        gap: 8px;
    }

    /* ===== Toolbar daftar (pola sama dengan Contact / Account) ===== */
    .leads-toolbar { border-bottom: 1px solid var(--card-border); margin-bottom: 6px; }
    .leads-btn { font-size: 12.5px; height: 32px; white-space: nowrap; display: inline-flex; align-items: center; }
    .leads-actions { display: flex; gap: 6px; margin-left: auto; }
    .leads-summary { font-size: 12px; color: var(--text-muted); padding: 0 6px 6px; }
    .leads-summary strong { color: var(--text-primary); }
    @media (max-width: 767.98px) { .leads-actions { margin-left: 0; width: 100%; justify-content: flex-end; } }
    .lead-date { color: var(--text-primary); font-weight: 600; white-space: nowrap; }
    .lead-date-rel { font-size: 11px; color: var(--text-muted); font-weight: 600; white-space: nowrap; }

</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Leads Management</h1>
        <p class="page-header-sub">Manage leads and prospective customers.</p>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span>Lead List</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar leads-toolbar" id="leads-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Search title, contact, account, owner…" autocomplete="off">
                <button type="button" class="lt-clear" title="Clear search"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="account" data-searchable aria-label="Account">
                <option value="">All Accounts</option>
                @foreach($accountCompanies as $ac)<option value="{{ $ac->id }}">{{ $ac->account_name }}</option>@endforeach
            </select>
            @unless($isSales)
            <select class="form-select" data-filter="assigned" data-searchable aria-label="Assigned to">
                <option value="">All Assignees</option>
                <option value="none">Unassigned</option>
                @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->display_name }}</option>@endforeach
            </select>
            @endunless
            <select class="form-select" data-filter="lead_status" aria-label="Lead status">
                <option value="">All Statuses</option>
                @foreach(['New', 'Approach', 'Qualified', 'Unqualified', 'Converted'] as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach
            </select>
            <select class="form-select" data-filter="activity" aria-label="Last activity">
                <option value="">Active Last: Any</option>
                <option value="7d">Active in last 7 days</option>
                <option value="30d">Active in last 30 days</option>
                <option value="stale">Inactive over 30 days</option>
                <option value="none">No activity yet</option>
            </select>
            <select class="form-select" id="leads-created-preset" aria-label="Created date">
                <option value="">Created: Any</option>
                <option value="q1">Created: Q1 (Jan – Mar)</option>
                <option value="q2">Created: Q2 (Apr – Jun)</option>
                <option value="q3">Created: Q3 (Jul – Sep)</option>
                <option value="q4">Created: Q4 (Oct – Dec)</option>
                <option value="year">Created: This year</option>
                <option value="custom">Created: Custom range</option>
            </select>
            <div class="lt-date" id="leads-created-range" title="Created date" style="display:none">
                <input type="date" class="form-control" data-filter="created_from" aria-label="Created from">
                <span>–</span>
                <input type="date" class="form-control" data-filter="created_to" aria-label="Created to">
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
            @if($canCreate)
            <div class="leads-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary leads-btn" onclick="openImportModal()">Import</button>
                <button type="button" class="btn btn-sm btn-primary leads-btn" onclick="openCreateModal()">Add Lead</button>
            </div>
            @endif
        </div>
        <div class="leads-summary" id="lead-summary-text" aria-live="polite">Loading…</div>
        <div class="table-responsive">
            <table id="leads-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Name</th>
                        <th>Title</th>
                        <th>Company</th>
                        <th>Mobile</th>
                        <th>Lead Status</th>
                        <th>Owner</th>
                        <th>Assigned To</th>
                        <th>Created</th>
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
                            <span>Lead Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="lead-form-section-body">
                            <div class="lead-form-row">
                                <div class="form-group small" style="display: none">
                                    <label>Lead Status <span class="text-danger">*</span></label>
                                    <select name="lead_status" id="lead-status">
                                        <option value="New">New</option>
                                        <option value="Approach">Approach</option>
                                        <option value="Qualified">Qualified</option>
                                        <option value="Unqualified">Unqualified</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Title <span class="text-danger">*</span></label>
                                    <input type="text" name="lead_title" id="lead-title-acc">
                                </div>
                                <div class="form-group">
                                    <label>Salutation <span class="text-danger">*</span></label>
                                    <select name="salutation" id="lead-salutation">
                                        <option value="">— Select —</option>
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
                                    <label>Email</label>
                                    <input type="email" name="email" id="lead-email" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <label>Mobile</label>
                                    <div class="mobile-picker">
                                        <select id="lead-mobile-country" class="form-select"></select>
                                        <input type="tel" id="lead-mobile" placeholder="8123456789" inputmode="numeric">
                                    </div>
                                    <input type="hidden" name="mobile" id="lead-mobile-full">
                                    <div class="form-text" style="font-size:11px;color:var(--text-muted)">Fill in at least one: Email or Mobile.</div>
                                </div>
                                <div class="form-group" style="display:none">
                                    <label>Phone</label>
                                    <input type="text" name="phone" id="lead-phone">
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Job Title <span class="text-danger">*</span></label>
                                    <select name="job_titles_id" id="lead-job-title">
                                        <option value="">— Select —</option>
                                        @foreach($jobTitles as $jt)
                                        <option value="{{ $jt->id }}">{{ $jt->title_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Department <span class="text-danger">*</span></label>
                                    <select name="divisions_id" id="lead-division">
                                        <option value="">— Select —</option>
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
                                        <option value="">— Select —</option>
                                        @foreach($sources as $src)
                                        <option value="{{ $src->id }}" @if(str_contains(strtolower($src->source_name), 'referral')) data-referral="1" @endif>{{ $src->source_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Preferred Contact Method</label>
                                    <select name="contact_methods_id" id="lead-contact-method">
                                        <option value="">— Select —</option>
                                        @foreach($contactMethods as $cm)
                                        <option value="{{ $cm->id }}">{{ $cm->method_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Role in Project</label>
                                    <select name="role_in_projects_id" id="lead-role">
                                        <option value="">— Select —</option>
                                        @foreach($roleInProjects as $rp)
                                        <option value="{{ $rp->id }}">{{ $rp->role_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group" id="lead-referral-group" style="display:none">
                                    <label>Referral Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name_referral" id="lead-referral" maxlength="150" placeholder="Nama pemberi referensi">
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                        <label>Follow Up Date <span class="text-danger">*</span></label>
                                        <input type="date" name="lead_follow_up_date" id="lead-follow-up">
                                </div>
                                @if($canUpdate && !$isSales)
                                <div class="form-group">
                                        <label>Assign To</label>
                                        <select name="assigned_to" id="lead-assigned">
                                            <option value="">— Select —</option>
                                            @foreach($users as $u)
                                            <option value="{{ $u->id }}">{{ $u->display_name }}</option>
                                            @endforeach
                                        </select>
                                </div>
                                @endif
                            </div>

                            <div class="lead-form-row" style="display:none;">
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
                            <div class="lead-form-row" style="display:none;">
                                <div class="form-group">
                                    <label>Unqualified Reason</label>
                                    <textarea name="unqualified_reason" id="lead-unqualified" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lead-form-section">
                        <div class="lead-form-section-header" onclick="toggleLeadSection(this)">
                            <span>Account Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="lead-form-section-body">
                            <div class="lead-form-row">

                                <div class="form-group">
                                    <label>Company</label>
                                    <select id="lead-company" style="width:100%"></select>
                                    <input type="hidden" name="account_companies_id" id="lead-company-id">
                                </div>

                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Field Type <span class="text-danger lead-field-required" style="display: none">*</span></label>
                                    <select name="types_accounts_companies_id" id="lead-field-type">
                                        <option value="">— Select —</option>
                                        @foreach($typesAccountsCompanies as $tac)
                                        <option value="{{ $tac->id }}">{{ $tac->type_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Segmentation <span class="text-danger lead-field-required" style="display: none">*</span></label>
                                    <select name="segmentation_id" id="lead-segmentation">
                                        <option value="">— Select —</option>
                                        @foreach($segmentations as $seg)
                                        <option value="{{ $seg->id }}">{{ $seg->segmentation_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group" style="display:none">
                                    <label>Account Type <span class="text-danger">*</span></label>
                                    <select name="account_types_id" id="lead-account-type">
                                        <option value="">— Select —</option>
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
                                        <option value="">— Select —</option>
                                        @foreach($businessEntities as $be)
                                        <option value="{{ $be->id }}">{{ $be->entity_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Business Value</label>
                                    <select name="business_values_id" id="lead-biz-value">
                                        <option value="">— Select —</option>
                                        @foreach($businessValues as $bv)
                                        <option value="{{ $bv->id }}">{{ $bv->value_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group" style="display: none">
                                    <label>Interaction Level</label>
                                    <select name="interaction_levels_id" id="lead-interaction">
                                        <option value="">— Select —</option>
                                        @foreach($interactionLevels as $il)
                                        <option value="{{ $il->id }}">{{ $il->level_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="lead-addr-map-group" id="lead-addr-map-group">
                                <div class="lead-addr-map-toolbar">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="leadAddrMapPickLocation()">
                                        Pick on Map
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="leadAddrMapUseMyLocation()">
                                        My Location
                                    </button>
                                    <span class="map-coords" id="lead-addr-map-coords">No point selected yet.</span>
                                </div>
                                <div class="lead-addr-map" id="lead-addr-map"></div>
                                <div class="lead-addr-map-status" id="lead-addr-map-status"></div>
                                <div class="lead-addr-map-preview" id="lead-addr-map-preview">
                                    <div class="lead-addr-map-preview-title">Address from Map</div>
                                    <div class="lead-addr-map-preview-body" id="lead-addr-map-preview-body"></div>
                                    <div class="lead-addr-map-preview-actions">
                                        <button type="button" class="btn btn-primary btn-sm" id="lead-addr-map-apply" disabled onclick="leadAddrMapApply()">
                                            Use This Address
                                        </button>
                                    </div>
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
                                    <input type="text" name="address_city" id="lead-addr-city" list="lead-city-suggestions">
                                </div>
                                <div class="form-group">
                                    <label>Province</label>
                                    <input type="text" name="address_province" id="lead-addr-province" list="lead-province-suggestions">
                                </div>
                                <div class="form-group small">
                                    <label>Zip</label>
                                    <input type="text" name="address_zip" id="lead-addr-zip">
                                </div>
                                <div class="form-group">
                                    <label>Country</label>
                                    <input type="text" name="address_country" id="lead-addr-country" list="lead-country-suggestions">
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>End User</label>
                                    <select name="end_user" id="lead-end-user">
                                        <option value="">— Select —</option>
                                        @foreach($accountCompanies as $ac)
                                        <option value="{{ $ac->id }}">{{ $ac->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lead-form-section"  style="display: none">
                        <div class="lead-form-section-header" onclick="toggleLeadSection(this)">
                            <span>Additional Information</span>
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

                        </div>
                    </div>
                    <datalist id="lead-city-suggestions">
                        @foreach($accountCities as $city)
                        <option value="{{ $city }}"></option>
                        @endforeach
                    </datalist>
                    <datalist id="lead-province-suggestions">
                        @foreach($accountProvinces as $province)
                        <option value="{{ $province }}"></option>
                        @endforeach
                    </datalist>
                    <datalist id="lead-country-suggestions">
                        @foreach($accountCountries as $country)
                        <option value="{{ $country }}"></option>
                        @endforeach
                    </datalist>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-lead">
                    Save
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
                    <p class="mb-0" style="font-size:13px;color:var(--text-muted)">
                        Download the template, fill in the data, then upload it as a CSV file.
                    </p>
                    <a href="{{ route('leads-management.template') }}" class="btn btn-sm btn-outline-success mt-2">
                        Download Template (.xlsx)
                    </a>
                </div>
                <hr>
                <form id="import-form" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label" for="import-file" style="font-size:12px;font-weight:600">CSV File</label>
                        <input type="file" name="file" id="import-file" class="form-control" accept=".csv,.txt" required>
                        <small class="text-muted">Max 5MB. Format: CSV (Save As from Excel).</small>
                    </div>
                    <div id="import-result" style="display:none;font-size:13px;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-import">
                    Upload & Import
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
const isSales = {{ $isSales ? 'true' : 'false' }};

const showUrl = '{{ route("leads-management.show", "__ID__") }}';
const fetchUrl = '{{ route("leads-management.fetch", "__ID__") }}';

const leadCountryCodes = [
    { code: '+62', flag: '🇮🇩', name: 'Indonesia' },
    { code: '+1',  flag: '🇺🇸', name: 'United States' },
    { code: '+65', flag: '🇸🇬', name: 'Singapore' },
    { code: '+60', flag: '🇲🇾', name: 'Malaysia' },
    { code: '+86', flag: '🇨🇳', name: 'China' },
    { code: '+91', flag: '🇮🇳', name: 'India' },
    { code: '+81', flag: '🇯🇵', name: 'Japan' },
    { code: '+82', flag: '🇰🇷', name: 'South Korea' },
    { code: '+44', flag: '🇬🇧', name: 'United Kingdom' },
    { code: '+33', flag: '🇫🇷', name: 'France' },
    { code: '+49', flag: '🇩🇪', name: 'Germany' },
    { code: '+39', flag: '🇮🇹', name: 'Italy' },
    { code: '+34', flag: '🇪🇸', name: 'Spain' },
    { code: '+31', flag: '🇳🇱', name: 'Netherlands' },
    { code: '+61', flag: '🇦🇺', name: 'Australia' },
    { code: '+64', flag: '🇳🇿', name: 'New Zealand' },
    { code: '+66', flag: '🇹🇭', name: 'Thailand' },
    { code: '+84', flag: '🇻🇳', name: 'Vietnam' },
    { code: '+63', flag: '🇵🇭', name: 'Philippines' },
    { code: '+55', flag: '🇧🇷', name: 'Brazil' },
];

function leadPopulateCountryCodes() {
    const $sel = $('#lead-mobile-country');
    if ($sel.children().length) {
        return;
    }
    leadCountryCodes.forEach(function(c) {
        $sel.append('<option value="' + c.code + '">' + c.flag + ' ' + c.code + '</option>');
    });
    $sel.val('+62');
    if (!$sel.hasClass('select2-hidden-accessible')) {
        $sel.select2({
            theme: 'bootstrap-5',
            dropdownAutoWidth: true,
            width: '92px',
            minimumResultsForSearch: 5,
            dropdownParent: $('#leadModal'),
        });
    }
}

function leadBuildMobile() {
    const code = $('#lead-mobile-country').val() || '+62';
    let local = ($('#lead-mobile').val() || '').replace(/\D/g, '');
    // Strip leading trunk prefix 0 for Indonesia (+62).
    if (code === '+62' && local.startsWith('0')) {
        local = local.replace(/^0+/, '');
    }
    return local ? code + local : '';
}

function leadParseMobile(full) {
    const value = (full || '').trim();
    if (!value) {
        return { code: '+62', local: '' };
    }
    let digits = value.replace(/\D/g, '');
    // Already has international prefix (doesn't start with bare 0).
    if (value.startsWith('+') || (digits.length > 1 && !value.startsWith('0'))) {
        let best = '+62';
        let bestLen = 0;
        leadCountryCodes.forEach(function(c) {
            const cd = c.code.replace(/\D/g, '');
            if (digits.startsWith(cd) && cd.length > bestLen) {
                best = c.code;
                bestLen = cd.length;
            }
        });
        const local = digits.slice(bestLen);
        return { code: best, local: local };
    }
    // Legacy local number starting with 0.
    let local = digits.replace(/^0+/, '');
    return { code: '+62', local: local };
}

function leadToggleReferral() {
    const isRef = !!($('#lead-source option:selected').attr('data-referral'));
    $('#lead-referral-group').toggle(isRef).removeClass('is-invalid');
    if (!isRef) {
        $('#lead-referral').val('');
    }
}

// Saat company sudah ada (terpilih/resulted), field account & alamat dikunci
// mengikuti data company. Saat company baru (newTag/clear) field bisa diisi.
const leadAddrReadonlyMap = [
    { id: '#lead-addr-street', list: '' },
    { id: '#lead-addr-city', list: 'lead-city-suggestions' },
    { id: '#lead-addr-province', list: 'lead-province-suggestions' },
    { id: '#lead-addr-zip', list: '' },
    { id: '#lead-addr-country', list: 'lead-country-suggestions' },
];

function leadSetAccountLocked(locked) {
    ['#lead-field-type', '#lead-segmentation', '#lead-biz-entity', '#lead-biz-value'].forEach(function(sel) {
        $(sel).prop('disabled', !!locked);
    });

    leadAddrReadonlyMap.forEach(function(f) {
        const $el = $(f.id);
        $el.prop('readonly', !!locked);
        if (locked) {
            $el.removeAttr('list');
        } else if (f.list) {
            $el.attr('list', f.list);
        }
    });

    $('#lead-addr-map-group').toggle(!locked);
    if (locked) {
        leadAddrMapReset();
    } else {
        setTimeout(function() { leadAddrMapInvalidate(); }, 150);
    }
}

// ── Peta alamat (Leaflet + OSM, sudah dimuat global di layout) ──
let leadAddrMap = null;
let leadAddrMarker = null;
let leadAddrResolved = null;
let leadAddrClickTimer = null;

function leadAddrMapEnsure() {
    if (leadAddrMap) return;
    leadAddrMap = L.map('lead-addr-map', {
        center: [-6.2088, 106.8456],
        zoom: 12,
        zoomControl: true,
    });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(leadAddrMap);
    leadAddrMap.on('click', function(e) {
        leadAddrMapOnPick(e.latlng.lat, e.latlng.lng);
    });
}

function leadAddrMapInvalidate() {
    if (leadAddrMap) leadAddrMap.invalidateSize();
}

function leadAddrMapReset() {
    if (leadAddrMarker && leadAddrMap) {
        leadAddrMap.removeLayer(leadAddrMarker);
        leadAddrMarker = null;
    }
    leadAddrResolved = null;
    if (leadAddrClickTimer) {
        clearTimeout(leadAddrClickTimer);
        leadAddrClickTimer = null;
    }
    $('#lead-addr-map-coords').text('No point selected yet.');
    $('#lead-addr-map-status').attr('class', 'lead-addr-map-status').text('');
    $('#lead-addr-map-preview').hide();
    $('#lead-addr-map-apply').prop('disabled', true);
}

function leadAddrMapPickLocation() {
    const section = $('#lead-addr-map').closest('.lead-form-section');
    if (section.length && !section.hasClass('open')) {
        section.addClass('open');
    }
    leadAddrMapEnsure();
    setTimeout(function() { leadAddrMapInvalidate(); }, 150);
    toastr.info('Click on the map to pick the address point.');
}

function leadAddrMapUseMyLocation() {
    if (!navigator.geolocation) {
        toastr.error('Geolocation is not supported by this browser.');
        return;
    }
    leadAddrMapEnsure();
    setTimeout(function() { leadAddrMapInvalidate(); }, 150);
    const status = $('#lead-addr-map-status');
    status.attr('class', 'lead-addr-map-status loading').text('Getting your location...');
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            leadAddrMap.setView([pos.coords.latitude, pos.coords.longitude], 15);
            leadAddrMapOnPick(pos.coords.latitude, pos.coords.longitude);
        },
        function() {
            status.attr('class', 'lead-addr-map-status error').text('Could not get your location. Allow location access and try again.');
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

function leadAddrMapOnPick(lat, lng) {
    if (leadAddrMarker) leadAddrMap.removeLayer(leadAddrMarker);
    leadAddrMarker = L.marker([lat, lng]).addTo(leadAddrMap);
    leadAddrMap.setView([lat, lng], 15);
    $('#lead-addr-map-coords').text(lat.toFixed(6) + ', ' + lng.toFixed(6));
    const status = $('#lead-addr-map-status');
    status.attr('class', 'lead-addr-map-status loading').text('Looking up the address for this point...');
    if (leadAddrClickTimer) clearTimeout(leadAddrClickTimer);
    leadAddrClickTimer = setTimeout(function() { leadAddrMapReverseGeocode(lat, lng); }, 600);
}

function leadAddrMapReverseGeocode(lat, lng) {
    const status = $('#lead-addr-map-status');
    const url = 'https://nominatim.openstreetmap.org/reverse?lat=' + lat + '&lon=' + lng + '&format=json&addressdetails=1&accept-language=id';
    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || data.error) {
                throw new Error(data && data.error ? data.error : 'Address not found');
            }
            const addr = data.address || {};
            const street = [addr.road, addr.house_number].filter(Boolean).join(' ') || [addr.neighbourhood, addr.suburb, addr.pedestrian].filter(Boolean).join(', ') || '';
            const city = addr.city || addr.town || addr.village || addr.municipality || addr.county || '';
            const province = addr.state || addr.region || addr.county || '';
            const postal = addr.postcode || '';
            const country = addr.country || '';

            leadAddrResolved = {
                street: street,
                city: city,
                province: province,
                postal_code: postal,
                country: country,
                display: [street, city, province, postal, country].filter(Boolean).join('\n'),
            };

            $('#lead-addr-map-preview-body').text(leadAddrResolved.display || 'Incomplete address.');
            $('#lead-addr-map-preview').show();
            status.attr('class', 'lead-addr-map-status').text('Address found. Click "Use This Address" to fill in the form.');
            $('#lead-addr-map-apply').prop('disabled', false);
        })
        .catch(function(err) {
            leadAddrResolved = null;
            status.attr('class', 'lead-addr-map-status error').text('Failed to look up the address: ' + (err.message || 'try again.'));
            $('#lead-addr-map-preview').hide();
            $('#lead-addr-map-apply').prop('disabled', true);
        });
}

function leadAddrMapApply() {
    if (!leadAddrResolved) return;
    const r = leadAddrResolved;
    if (r.street) $('#lead-addr-street').val(r.street);
    if (r.city) $('#lead-addr-city').val(r.city);
    if (r.province) $('#lead-addr-province').val(r.province);
    if (r.postal_code) $('#lead-addr-zip').val(r.postal_code);
    if (r.country) $('#lead-addr-country').val(r.country);
    toastr.success('Address filled in from the map.');
}

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
    $(".lead-field-required").hide();
    $('#lead-field-type').val('').trigger('change');
    $('#lead-segmentation').val('').trigger('change');
    $('#lead-end-user').val('').trigger('change');
    $('#lead-mobile-country').val('+62').trigger('change');
    $('#lead-mobile').val('');
    $('#lead-mobile-full').val('');
    $('#lead-referral').val('');
    $('#lead-referral-group').hide();
    leadSetAccountLocked(false);
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
        const parsedMobile = leadParseMobile(res.contact ? res.contact.mobile : '');
        $('#lead-mobile-country').val(parsedMobile.code).trigger('change');
        $('#lead-mobile').val(parsedMobile.local);
        $('#lead-mobile-full').val(res.contact ? res.contact.mobile : '');
        $('#lead-phone').val(res.contact ? res.contact.phone : '');
        $('#lead-job-title').val(res.contact ? res.contact.job_titles_id : '').trigger('change');
        $('#lead-division').val(res.contact ? res.contact.divisions_id : '').trigger('change');
        $('#lead-source').val(res.lead.source_id).trigger('change');
        $('#lead-referral').val(res.lead.name_referral || '');
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
        leadSetAccountLocked(!!res.company);

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
        layout: ListToolbar.layout,
        language: {
            processing: 'Loading…',
            lengthMenu: 'Show _MENU_ rows',
            info: 'Showing _START_–_END_ of _TOTAL_ leads',
            infoEmpty: 'No leads',
            infoFiltered: '(filtered from _MAX_)',
            zeroRecords: '<span style="color:var(--text-muted)">No leads match the search / filters.</span>',
            emptyTable: '<span style="color:var(--text-muted)">No leads yet.</span>',
            paginate: { first: '«', previous: '‹', next: '›', last: '»' }
        },
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("leads-management.data") }}',
            data: function(d) { return leadsToolbar.params(d); }
        },
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
            { data: 'mobile' },
            { data: 'status_badge' },
            { data: 'owner_name' },
            { data: 'assigned_to_name' },
            {
                data: 'created_at', orderable: true, searchable: false,
                render: function(data, type, row) {
                    if (!row.created_at_raw) return '—';
                    var days = moment().startOf('day').diff(moment(row.created_at_raw).startOf('day'), 'days');
                    var rel = days <= 0 ? 'Today' : (days === 1 ? 'Yesterday' : days + ' days ago');
                    return '<div class="lead-date">' + data + '</div><div class="lead-date-rel">' + rel + '</div>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    var html = '<div style="display:flex;gap:5px;justify-content:center">';
                    html += '<a href="' + showUrl.replace('__ID__', row.id) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    // cek jika user memiliki permission update dan status lead bukan "Converted" maka tampilkan tombol edit
                    if (leadsCanUpdate && isSales && row.lead_status !== 'Converted') {
                        html += ' <button type="button" class="btn-icon" title="Edit" onclick="openEditModal(' + row.id + ')"><i class="fa fa-pen"></i></button>';
                    }
                    // cek jika user memiliki permission update dan bukan divisi sales  dan merupakan sales manager maka tampilkan tombol edit
                    else if (leadsCanUpdate && !isSales) {
                        html += ' <button type="button" class="btn-icon" title="Edit" onclick="openEditModal(' + row.id + ')"><i class="fa fa-pen"></i></button>';
                    }

                    if (leadsCanDelete) {
                        html += ' <button type="button" class="btn btn-sm btn-ghost text-danger btn-delete-lead" title="Delete" data-id="' + row.id + '"><i class="fa fa-trash-can"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        pageLength: 10,
        lengthMenu: [10, 15, 25, 50, 100],
        // Urutan & halaman tetap saat kembali dari halaman detail; filter toolbar disimpan terpisah (leadsSaveFilters).
        stateSave: true,
        stateDuration: -1,
        stateSaveParams: function(settings, data) { data.search.search = ''; },
        // Pencarian dikembalikan dari kotak cari toolbar (dipulihkan leadsRestoreFilters).
        search: { search: $('#leads-toolbar [data-search]').val() || '' },
        stateLoadParams: function(settings, data) { data.search.search = $('#leads-toolbar [data-search]').val() || ''; },
    });
}

// --- Toolbar filter (pola sama dengan Contact / Account) ---
let leadsToolbar = null;
const LEADS_FILTER_KEY = 'leads-filters';

// Preset tanggal dibuat (kuartal / setahun) pada tahun berjalan -> isi rentang tanggal.
function leadsPresetRange(preset) {
    var year = moment().year();
    if (preset === 'year') return [moment({ year: year }).startOf('year'), moment({ year: year }).endOf('year')];
    var q = /^q([1-4])$/.exec(preset || '');
    if (!q) return null;
    var start = moment({ year: year, month: (parseInt(q[1], 10) - 1) * 3, day: 1 });
    return [start, start.clone().add(2, 'months').endOf('month')];
}

function leadsApplyPreset(preset) {
    var range = leadsPresetRange(preset);
    $('#leads-created-range').toggle(preset === 'custom');
    if (range) {
        $('[data-filter="created_from"]').val(range[0].format('YYYY-MM-DD'));
        $('[data-filter="created_to"]').val(range[1].format('YYYY-MM-DD'));
    } else if (preset !== 'custom') {
        $('[data-filter="created_from"], [data-filter="created_to"]').val('');
    }
}

// Filter tetap saat kembali dari halaman detail (per tab browser).
function leadsSaveFilters() {
    var state = { preset: $('#leads-created-preset').val(), q: $('#leads-toolbar [data-search]').val() };
    $('#leads-toolbar [data-filter]').each(function() { state[$(this).data('filter')] = $(this).val(); });
    try { sessionStorage.setItem(LEADS_FILTER_KEY, JSON.stringify(state)); } catch (e) {}
}

function leadsRestoreFilters() {
    var state = null;
    try { state = JSON.parse(sessionStorage.getItem(LEADS_FILTER_KEY) || 'null'); } catch (e) {}
    if (!state) return;
    $('#leads-toolbar [data-filter]').each(function() {
        var key = $(this).data('filter');
        if (state[key] != null && (this.tagName !== 'SELECT' || $(this).find('option[value="' + state[key] + '"]').length)) $(this).val(state[key]);
    });
    $('#leads-created-preset').val(state.preset || '');
    $('#leads-created-range').toggle(state.preset === 'custom');
    if (state.q) $('#leads-toolbar [data-search]').val(state.q);
}

$(document).on('change', '#leads-created-preset', function() {
    leadsApplyPreset($(this).val());
    $('[data-filter="created_from"]').trigger('change');
});

// Ringkasan hasil di bawah toolbar.
$('#leads-table').on('draw.dt', function(e, settings) {
    var info = new $.fn.dataTable.Api(settings).page.info();
    var preset = $('#leads-created-preset option:selected');
    var status = $('[data-filter="lead_status"]').val();
    $('#lead-summary-text').html('<strong>' + Num.format(info.recordsDisplay, 0) + '</strong> lead(s)'
        + ($('#leads-created-preset').val() ? ' · ' + $('<span>').text(preset.text()).html() : '')
        + (status ? ' · Status <strong>' + $('<span>').text(status).html() + '</strong>' : ''));
    leadsSaveFilters();
});

$(document).on('click', '#btn-save-lead', function() {
    const $btn = $(this);
    const editId = $('#lead-edit-id').val();
    const isEdit = !!editId;

    $('#lead-form .is-invalid').removeClass('is-invalid');

    const validations = [
        { field: '#lead-status', label: 'Lead Status' },
        { field: '#lead-salutation', label: 'Salutation' },
        { field: '#lead-full-name', label: 'Full Name' },
        { field: '#lead-job-title', label: 'Job Title' },
        { field: '#lead-division', label: 'Department' },
        { field: '#lead-source', label: 'Lead Source' },
        { field: '#lead-title-acc', label: 'Title' },
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
            toastr.error(v.label + ' is required.');
            $el.focus();
            return;
        }
    }


    if ($('#lead-referral-group').is(':visible') && !($('#lead-referral').val() || '').trim()) {
        $('#lead-referral').addClass('is-invalid');
        toastr.error('Referral Name is required.');
        $('#lead-referral').focus();
        return;
    }

    const companyId = $('#lead-company-id').val();
    if (companyId) {
        const companyFields = [
            { field: '#lead-field-type', label: 'Field Type' },
            { field: '#lead-segmentation', label: 'Segmentation' },
        ];

        for (let i = 0; i < companyFields.length; i++) {
            const v = companyFields[i];
            const $el = $(v.field);
            const val = $el.val() ? $el.val().trim() : '';
            if (!val) {
                $el.addClass('is-invalid');
                const section = $el.closest('.lead-form-section');
                if (section.length && !section.hasClass('open')) {
                    section.addClass('open');
                }
                toastr.error(v.label + ' is required.');
                $el.focus();
                return;
            }
        }
    }

    const leadEmail = ($('#lead-email').val() || '').trim();
    const leadMobileDigits = ($('#lead-mobile').val() || '').replace(/\D/g, '');
    if (!leadEmail && !leadMobileDigits) {
        $('#lead-email').addClass('is-invalid');
        $('#lead-mobile').addClass('is-invalid');
        toastr.error('Fill in at least one: Email or Mobile.');
        return;
    }
    if (leadEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(leadEmail)) {
        $('#lead-email').addClass('is-invalid');
        toastr.error('Format email tidak valid.');
        $('#lead-email').focus();
        return;
    }
    $('#lead-mobile-full').val(leadBuildMobile());

    const formData = new FormData(document.getElementById('lead-form'));
    formData.set('all_filed_completed', $('#lead-all-complete').is(':checked') ? '1' : '0');
    formData.set('lead_can_be_contacted', $('#lead-can-contact').is(':checked') ? '1' : '0');
    formData.set('lead_appoinment', $('#lead-appointment').is(':checked') ? '1' : '0');
    formData.set('identification', $('#lead-identification').is(':checked') ? '1' : '0');

    const url = isEdit
        ? '{{ route("leads-management.update", "__ID__") }}'.replace('__ID__', editId)
        : '{{ route("leads-management.store") }}';

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

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

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

$(document).on('change input', '#lead-form input.is-invalid, #lead-form select.is-invalid', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('change', '#lead-source', leadToggleReferral);

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
        toastr.error('Select a CSV file first.');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('_token', '{{ csrf_token() }}');

    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Importing...');

    $.ajax({
        url: '{{ route("leads-management.import") }}',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            $btn.prop('disabled', false).html('Upload & Import');

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
            $btn.prop('disabled', false).html('Upload & Import');
            var msg = xhr.responseJSON?.message || 'Failed to import the file.';
            toastr.error(msg);
        }
    });
});

$(document).on('shown.bs.modal', '#leadModal', function() {
    leadPopulateCountryCodes();
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
            $(".lead-field-required").show();
            if (c.newTag) {
                $('#lead-company-id').val('');
                $('#lead-field-type').val('').trigger('change');
                $('#lead-segmentation').val('').trigger('change');
                $("#lead-biz-entity").val('').trigger('change');
                $("#lead-biz-value").val('').trigger('change');
                $("#lead-interaction").val('').trigger('change');
                $('#lead-addr-street').val('');
                $('#lead-addr-city').val('');
                $('#lead-addr-province').val('');
                $('#lead-addr-zip').val('');
                $('#lead-addr-country').val('');
                leadSetAccountLocked(false);
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
                leadSetAccountLocked(true);
            }
        }).on('select2:clear', function() {
            $('#lead-company-id').val('');
            $(".lead-field-required").hide();
            $('#lead-field-type').val('').trigger('change');
            $('#lead-segmentation').val('').trigger('change');
            $("#lead-biz-entity").val('').trigger('change');
            $("#lead-biz-value").val('').trigger('change');
            $("#lead-interaction").val('').trigger('change');
            $('#lead-addr-street').val('');
            $('#lead-addr-city').val('');
            $('#lead-addr-province').val('');
            $('#lead-addr-zip').val('');
            $('#lead-addr-country').val('');
            leadSetAccountLocked(false);
        });
    }

    if (!$('#lead-end-user').hasClass('select2-hidden-accessible')) {
        $('#lead-end-user').select2({
            theme: 'bootstrap-5',
            placeholder: '— Select —',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#leadModal')
        });
    }
});

$(document).ready(function() {
    leadsRestoreFilters();
    leadsToolbar = ListToolbar('#leads-toolbar', function() { return leadsTable; });
    $(document).on('click', '#leads-toolbar [data-reset]', function() {
        $('#leads-created-preset').val('');
        $('#leads-created-range').hide();
    });
    initLeadsTable();
});
</script>
@endsection
