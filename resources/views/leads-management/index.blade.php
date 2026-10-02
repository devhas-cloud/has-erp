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
    .mobile-picker { display: flex; gap: 8px; align-items: stretch; }
    .mobile-picker select { width: 92px; flex: 0 0 92px; }
    .mobile-picker input { flex: 1; }

    /* ===== Toolbar: filter Created Date + Lead Status ===== */
    .lead-toolbar {
        margin: 6px 6px 14px;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    }
    .lead-toolbar-main {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px 24px;
        padding: 14px 16px;
    }
    .lead-toolbar-group { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
    /* Grup filter mengisi sisa lebar; rentang kustom turun ke baris kedua bila tidak muat, dropdown Lead Status tetap di kanan */
    .lead-toolbar-filters { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 12px; }
    .lead-status-filter { flex: 0 0 auto; }
    .lead-toolbar-label {
        font-size: 10.5px;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .lead-toolbar-label i { color: var(--accent); margin-right: 5px; }
    .lead-toolbar-controls { display: flex; align-items: center; gap: 8px 10px; flex-wrap: wrap; }

    .lead-chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .lead-chip {
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
    .lead-chip:hover {
        border-color: rgba(16, 185, 129, 0.3);
        color: var(--accent);
        background: var(--accent-soft);
    }
    .lead-chip.active {
        background: linear-gradient(135deg, var(--accent), #059669);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 2px 8px var(--accent-glow);
    }
    .lead-chip:focus-visible, .lead-summary-reset:focus-visible {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }

    .lead-range { display: flex; align-items: center; gap: 6px; }
    .lead-range .form-control { width: 148px; padding: 6px 10px; font-size: 12.5px; }
    .lead-range-sep { color: var(--text-muted); font-size: 12px; font-weight: 700; }
    .lead-range.is-custom .form-control { border-color: var(--accent); }

    .lead-status-filter .form-select { min-width: 210px; padding: 7px 34px 7px 12px; font-size: 12.5px; font-weight: 600; }

    .lead-summary {
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
    .lead-summary strong { color: var(--text-primary); font-weight: 800; }
    .lead-summary-reset {
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
    .lead-summary-reset:hover { text-decoration: underline; }

    .lead-date { color: var(--text-primary); font-weight: 600; white-space: nowrap; }
    .lead-date-rel { font-size: 11px; color: var(--text-muted); font-weight: 600; white-space: nowrap; }

    @media (max-width: 768px) {
        .lead-toolbar-main { align-items: stretch; flex-direction: column; }
        .lead-range { width: 100%; }
        .lead-range .form-control { flex: 1; width: auto; min-width: 0; }
        .lead-status-filter .form-select { width: 100%; min-width: 0; }
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
        <div class="lead-toolbar">
            <div class="lead-toolbar-main">
                <div class="lead-toolbar-filters">
                    <div class="lead-toolbar-group">
                        <span class="lead-toolbar-label"><i class="fa fa-calendar-days"></i>Created Date</span>
                        <div class="lead-toolbar-controls">
                            <div class="lead-chips" role="group" aria-label="Rentang tanggal dibuat">
                                <button type="button" class="lead-chip" data-preset="q1" title="Januari – Maret">Kuartal 1</button>
                                <button type="button" class="lead-chip" data-preset="q2" title="April – Juni">Kuartal 2</button>
                                <button type="button" class="lead-chip" data-preset="q3" title="Juli – September">Kuartal 3</button>
                                <button type="button" class="lead-chip" data-preset="q4" title="Oktober – Desember">Kuartal 4</button>
                                <button type="button" class="lead-chip" data-preset="year" title="Januari – Desember">Setahun</button>
                                <button type="button" class="lead-chip" data-preset="all" title="Tanpa filter tanggal">Semua</button>
                            </div>
                            <div class="lead-range" id="lead-range">
                                <input type="date" id="filter-created-from" class="form-control" aria-label="Dibuat dari tanggal" title="Dari tanggal">
                                <span class="lead-range-sep">–</span>
                                <input type="date" id="filter-created-to" class="form-control" aria-label="Dibuat sampai tanggal" title="Sampai tanggal">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="lead-toolbar-group lead-status-filter">
                    <label class="lead-toolbar-label" for="filter-lead-status"><i class="fa fa-layer-group"></i>Lead Status</label>
                    <select id="filter-lead-status" class="form-select">
                        <option value="">Semua Status</option>
                        @foreach(['New', 'Approach', 'Qualified', 'Unqualified', 'Converted'] as $status)
                        <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="lead-summary" aria-live="polite">
                <span id="lead-summary-text">Memuat…</span>
                <button type="button" class="lead-summary-reset" id="btn-reset-lead-filter" style="display:none">
                    <i class="fa fa-xmark me-1"></i>Hapus filter
                </button>
            </div>
        </div>
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
                            <span><i class="fa fa-user me-2" style="color:var(--accent)"></i>Lead Information</span>
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
                                        <option value="">—Pilih—</option>
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
                                    <label>Mobile  <span class="text-danger">*</span></label>
                                    <div class="mobile-picker">
                                        <select id="lead-mobile-country" class="form-select"></select>
                                        <input type="tel" id="lead-mobile" placeholder="8123456789" inputmode="numeric">
                                    </div>
                                    <input type="hidden" name="mobile" id="lead-mobile-full">
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
                                        <option value="{{ $src->id }}" @if($src->source_name === 'Referral' or $src->source_name === 'Employe Referral') data-referral="1" @endif>{{ $src->source_name }}</option>
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
                                            <option value="">— Pilih —</option>
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
                            <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Account Information</span>
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
                                        <option value="">— Pilih —</option>
                                        @foreach($typesAccountsCompanies as $tac)
                                        <option value="{{ $tac->id }}">{{ $tac->type_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Segmentation <span class="text-danger lead-field-required" style="display: none">*</span></label>
                                    <select name="segmentation_id" id="lead-segmentation">
                                        <option value="">— Pilih —</option>
                                        @foreach($segmentations as $seg)
                                        <option value="{{ $seg->id }}">{{ $seg->segmentation_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group" style="display:none">
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
                                <div class="form-group" style="display: none">
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

                    <div class="lead-form-section"  style="display: none">
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
        ajax: {
            url: '{{ route("leads-management.data") }}',
            data: function(d) {
                d.created_from = $('#filter-created-from').val();
                d.created_to = $('#filter-created-to').val();
                d.lead_status = $('#filter-lead-status').val();
            }
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
            { data: 'phone' },
            { data: 'mobile' },
            { data: 'status_badge' },
            { data: 'owner_name' },
            { data: 'assigned_to_name' },
            {
                data: 'created_at', orderable: true, searchable: false,
                render: function(data, type, row) {
                    if (!row.created_at_raw) return '—';
                    var days = moment().startOf('day').diff(moment(row.created_at_raw).startOf('day'), 'days');
                    var rel = days <= 0 ? 'Hari ini' : (days === 1 ? 'Kemarin' : days + ' hari lalu');
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
                        html += ' <button type="button" class="btn-icon danger btn-delete-lead" title="Hapus" data-id="' + row.id + '"><i class="fa fa-trash-can"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        order: [[9, 'desc']],
        pageLength: 10,
        lengthMenu: [10, 15, 25, 50, 100],
        // Filter, urutan, pencarian & halaman tetap saat kembali dari halaman detail (per tab browser)
        stateSave: true,
        stateDuration: -1,
        stateSaveParams: function(settings, data) {
            data.leadFilter = {
                preset: leadActivePreset,
                from: $('#filter-created-from').val(),
                to: $('#filter-created-to').val(),
                status: $('#filter-lead-status').val(),
            };
        },
        stateLoadParams: function(settings, data) {
            var saved = data.leadFilter;
            if (!saved) return;
            setLeadStatusFilter(saved.status);
            if (isCreatedPreset(saved.preset)) {
                applyCreatedPreset(saved.preset);
            } else {
                setCreatedRange(saved.from, saved.to);
            }
        },
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

// --- Filter Created Date (kuartal + rentang kustom) ---
const LEAD_DATE_FMT = 'YYYY-MM-DD';
// Filter bawaan saat halaman pertama dibuka: semua tanggal, urut terbaru. Ganti ke 'q' + moment().quarter() untuk kuartal berjalan.
const LEAD_DEFAULT_PRESET = 'all';
let leadActivePreset = 'all';

function isCreatedPreset(preset) {
    return /^(q[1-4]|year|all)$/.test(preset || '');
}

// Rentang tanggal sebuah preset pada tahun berjalan
function createdPresetRange(preset) {
    var year = moment().year();
    if (preset === 'year') {
        return [moment({ year: year }).startOf('year'), moment({ year: year }).endOf('year')];
    }
    var q = /^q([1-4])$/.exec(preset);
    if (!q) return null;
    var start = moment({ year: year, month: (parseInt(q[1], 10) - 1) * 3, day: 1 });
    return [start, start.clone().add(2, 'months').endOf('month')];
}

function setCreatedRange(from, to, preset) {
    from = from || '';
    to = to || '';
    $('#filter-created-from').val(from);
    $('#filter-created-to').val(to);

    leadActivePreset = preset || ((from || to) ? 'custom' : 'all');
    $('.lead-chip[data-preset]').each(function() {
        var active = $(this).data('preset') === leadActivePreset;
        $(this).toggleClass('active', active).attr('aria-pressed', active ? 'true' : 'false');
    });
    $('#lead-range').toggleClass('is-custom', leadActivePreset === 'custom');
}

function applyCreatedPreset(preset) {
    var range = createdPresetRange(preset);
    if (range) {
        setCreatedRange(range[0].format(LEAD_DATE_FMT), range[1].format(LEAD_DATE_FMT), preset);
    } else {
        setCreatedRange('', '', 'all');
    }
}

function createdRangeLabel() {
    var from = $('#filter-created-from').val();
    var to = $('#filter-created-to').val();
    var fmt = function(d) { return moment(d).format('DD MMM YYYY'); };

    var q = /^q([1-4])$/.exec(leadActivePreset);
    if (q) return 'Kuartal ' + q[1] + ' ' + moment().year() + ' (' + moment(from).format('DD MMM') + ' – ' + fmt(to) + ')';
    if (leadActivePreset === 'year') return 'Tahun ' + moment().year();

    if (from && to) return from === to ? fmt(from) : fmt(from) + ' – ' + fmt(to);
    if (from) return 'mulai ' + fmt(from);
    if (to) return 'sampai ' + fmt(to);
    return '';
}

$(document).on('click', '.lead-chip[data-preset]', function() {
    applyCreatedPreset($(this).data('preset'));
    if (leadsTable) leadsTable.ajax.reload();
});

$('#filter-created-from, #filter-created-to').on('change', function() {
    var from = $('#filter-created-from').val();
    var to = $('#filter-created-to').val();

    // Rentang terbalik → tukar otomatis, jangan dibuang
    if (from && to && from > to) {
        var tmp = from; from = to; to = tmp;
    }
    setCreatedRange(from, to);
    if (leadsTable) leadsTable.ajax.reload();
});

// --- Filter Lead Status (dropdown; kosong = semua status) ---
function setLeadStatusFilter(status) {
    status = status || '';
    // Status tersimpan yang sudah tidak ada di pilihan → kembali ke "Semua Status"
    $('#filter-lead-status').val($('#filter-lead-status option[value="' + status + '"]').length ? status : '');
}

$('#filter-lead-status').on('change', function() {
    if (leadsTable) leadsTable.ajax.reload();
});

$('#btn-reset-lead-filter').on('click', function() {
    applyCreatedPreset('all');
    setLeadStatusFilter('');
    if (leadsTable) leadsTable.ajax.reload();
});

// Ringkasan hasil di bawah toolbar
$('#leads-table').on('draw.dt', function(e, settings) {
    var api = new $.fn.dataTable.Api(settings);
    var info = api.page.info();
    var label = createdRangeLabel();
    var status = $('#filter-lead-status').val();

    $('#lead-summary-text').html(
        '<strong>' + info.recordsDisplay.toLocaleString('id-ID') + '</strong> lead' +
        (label ? ' · Created Date <strong>' + label + '</strong>' : ' · semua tanggal') +
        (status ? ' · Status <strong>' + $('<span>').text(status).html() + '</strong>' : ' · semua status')
    );
    $('#btn-reset-lead-filter').toggle(!!(label || status));
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
        { field: '#lead-email', label: 'Email' },
        { field: '#lead-mobile', label: 'Mobile' },
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
            toastr.error(v.label + ' wajib diisi.');
            $el.focus();
            return;
        }
    }


    if ($('#lead-referral-group').is(':visible') && !($('#lead-referral').val() || '').trim()) {
        $('#lead-referral').addClass('is-invalid');
        toastr.error('Referral Name wajib diisi.');
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
                toastr.error(v.label + ' wajib diisi.');
                $el.focus();
                return;
            }
        }
    }

    if (!$('#lead-mobile').val() || !$('#lead-mobile').val().replace(/\D/g, '')) {
        $('#lead-mobile').addClass('is-invalid');
        toastr.error('Mobile wajib diisi.');
        $('#lead-mobile').focus();
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

applyCreatedPreset(LEAD_DEFAULT_PRESET); // ditimpa oleh filter tersimpan (stateLoadParams) bila ada
initLeadsTable();
</script>
@endsection
