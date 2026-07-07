@extends('layouts.app')

@section('title', 'Detail Lead')
@section('page-title', 'Detail Lead')

@section('styles')
<style>
    /* ── Nav Tabs ── */
    .nav-tabs .nav-link {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-muted);
        border: none;
        padding: 12px 20px;
        border-radius: 0;
        transition: color .15s, border-color .15s;
    }
    .nav-tabs .nav-link.active {
        color: var(--accent);
        background: transparent;
        border-bottom: 2px solid var(--accent);
    }
    .nav-tabs .nav-link:hover:not(.active) {
        color: var(--text-primary);
        border-bottom: 2px solid var(--card-border);
    }

    /* ── Info Table ── */
    .info-table td { padding: 7px 0; vertical-align: top; line-height: 1.45; }
    .info-table td:first-child {
        color: var(--text-muted);
        width: 130px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        padding-right: 12px;
    }
    .info-table td:last-child {
        font-size: 13px;
        color: var(--text-primary);
        word-break: break-word;
    }
    .info-table tr + tr td { border-top: 1px solid var(--card-border); }

    /* ── Lead Path (SLDS-style solid pipeline) ── */
    .lead-path-wrapper {
        background: #fff;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        padding: 20px 24px 16px;
        margin-bottom: 20px;
    }
    .lead-path-wrapper__label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .8px;
        color: var(--text-muted);
        margin-bottom: 14px;
    }
    .lead-path {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .lead-path__nav {
        display: flex;
        list-style: none;
        margin: 0;
        padding: 0;
        min-width: 560px;
    }
    .lead-path__item {
        flex: 1;
        position: relative;
        clip-path: polygon(0 0, calc(100% - 16px) 0, 100% 50%, calc(100% - 16px) 100%, 0 100%, 16px 50%);
        margin-left: -16px;
        transition: background .2s, filter .2s;
    }
    .lead-path__item:first-child {
        margin-left: 0;
        clip-path: polygon(0 0, calc(100% - 16px) 0, 100% 50%, calc(100% - 16px) 100%, 0 100%);
    }
    .lead-path__item:last-child {
        clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%, 16px 50%);
    }
    .lead-path__item--active {
        background: var(--accent);
        z-index: 3;
        box-shadow: 0 2px 8px rgba(37, 99, 235, .3);
    }
    .lead-path__item--complete {
        background: #d1fae5;
        z-index: 2;
    }
    .lead-path__item--incomplete {
        background: #f1f5f9;
        z-index: 1;
    }
    .lead-path__link {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 22px 12px 28px;
        text-decoration: none;
        cursor: default;
        min-height: 44px;
    }
    .lead-path__stage {
        width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        flex-shrink: 0;
    }
    .lead-path__title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .4px;
        white-space: nowrap;
        font-weight: 500;
    }
    /* Active */
    .lead-path__item--active .lead-path__stage { color: #fff; }
    .lead-path__item--active .lead-path__title { color: #fff; font-weight: 700; }
    /* Complete */
    .lead-path__item--complete .lead-path__stage { color: #059669; }
    .lead-path__item--complete .lead-path__title { color: #065f46; font-weight: 600; }
    /* Incomplete */
    .lead-path__item--incomplete .lead-path__stage { color: #94a3b8; }
    .lead-path__item--incomplete .lead-path__title { color: #94a3b8; }

    /* ── Lead Header Card ── */
    .lead-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
    }
    .lead-header__identity {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .lead-header__name {
        margin: 0;
        font-weight: 700;
        font-size: 20px;
        letter-spacing: -.3px;
        color: var(--text-primary);
        line-height: 1.3;
    }
    .lead-header__contact {
        font-size: 13px;
        color: var(--text-secondary);
        margin-top: 2px;
    }
    .lead-header__meta {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        font-size: 13px;
        color: var(--text-muted);
        padding-top: 16px;
        border-top: 1px solid var(--card-border);
        margin-top: 16px;
    }
    .lead-header__meta i { opacity: .6; margin-right: 4px; }

    /* ── Modal styles ── */
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
        user-select: none;
    }
    .lead-form-section-body { padding: 16px; display: none; }
    .lead-form-section.open .lead-form-section-body { display: block; }
    .lead-form-section-header .chevron { transition: transform .2s; font-size: 11px; color: var(--text-muted); }
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
        letter-spacing: .5px;
        margin-bottom: 4px;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--card-border);
        border-radius: var(--radius-sm);
        font-size: 13px;
        font-family: inherit;
        color: var(--text-primary);
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-soft);
        outline: none;
    }
    .form-check-inline {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 500;
        padding: 6px 12px;
        border: 1px solid var(--card-border);
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: border-color .15s;
    }
    .form-check-inline:hover { border-color: var(--accent); }
    .form-check-inline input { width: auto; margin: 0; }
    .form-group input.is-invalid,
    .form-group select.is-invalid,
    .form-group textarea.is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 3px rgba(220,53,69,.1) !important;
    }
    select.is-invalid + .select2-container .select2-selection {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 3px rgba(220,53,69,.1) !important;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Detail Lead</h1>
        <p class="page-header-sub">Informasi lengkap data lead</p>
    </div>
    <div class="page-header-actions">
        @if($canUpdate)
        <button type="button" class="btn-accent" onclick="openEditModal({{ $lead->id }})">
            <i class="fa fa-pen"></i><span>Edit</span>
        </button>
        @endif
        <a href="{{ route('leads-management.index') }}" class="btn-ghost">
            <i class="fa fa-arrow-left"></i><span>Kembali</span>
        </a>
    </div>
</div>

@php
    $stages = ['New', 'Approach', 'Unqualified', 'Qualified'];
    $currentIdx = array_search($lead->lead_status, $stages);
    if ($currentIdx === false) $currentIdx = -1;
@endphp

<div class="row g-4">
    <!-- ═══════════════ Left Column ═══════════════ -->
    <div class="col-lg-9">

         <!-- ── Lead Header Card ── -->
        <div class="lead-path-wrapper fade-in" >
            <div class="card-body-custom">
                <div class="lead-header">
                    <div class="lead-header__identity">
                        @if($lead->accountContact?->icon)
                            <img src="{{ $lead->accountContact->icon }}" class="avatar-circle" alt="" style="background:transparent">
                        @else
                            <div class="avatar-circle" style="font-size:15px">
                                {{ strtoupper(substr($lead->accountContact?->full_name ?? '?', 0, 2)) }}
                            </div>
                        @endif
                        <div>
                            <h2 class="lead-header__name">{{ $lead->lead_title ?? '—' }}</h2>
                            <div class="lead-header__contact">
                                <strong>{{ $lead->accountContact?->full_name ?? '—' }}</strong>
                                @if($lead->accountContact?->email)
                                    &nbsp;·&nbsp; {{ $lead->accountContact->email }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lead-header__meta">
                    <span><i class="fa fa-user"></i>{{ $lead->leadOwner?->username ?? '—' }}</span>
                    <span><i class="fa fa-bullseye"></i>{{ $lead->source?->source_name ?? '—' }}</span>
                    <span><i class="fa fa-calendar"></i>Follow Up: {{ $lead->lead_follow_up_date?->format('d M Y') ?? '—' }}</span>
                    @if($lead->closed_date)
                    <span><i class="fa fa-clock"></i>Closed: {{ $lead->closed_date->format('d M Y') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- ── Lead Path Pipeline (Standalone) ── -->
        <div class="card-custom fade-in stagger-1">

            <div class="lead-path">
                <ul class="lead-path__nav">
                    @foreach($stages as $i => $stage)
                        @php
                            $isActive   = ($i === $currentIdx);
                            $isComplete = ($currentIdx >= 0 && $i < $currentIdx);
                        @endphp
                        <li class="lead-path__item
                            {{ $isActive ? 'lead-path__item--active' : '' }}
                            {{ $isComplete ? 'lead-path__item--complete' : (!$isActive ? 'lead-path__item--incomplete' : '') }}">
                            <a class="lead-path__link" tabindex="-1">
                                <span class="lead-path__stage">
                                    @if($isComplete)
                                        <i class="fa fa-check" style="font-size:10px"></i>
                                    @elseif($isActive)
                                        <i class="fa fa-circle" style="font-size:8px"></i>
                                    @else
                                        <i class="fa-regular fa-circle" style="font-size:10px"></i>
                                    @endif
                                </span>
                                <span class="lead-path__title">{{ $stage }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>



        <!-- ── Tab Section ── -->
        <div class="card-custom fade-in stagger-2 mt-4">
            <div class="card-header-custom" style="padding:0 22px">
                <ul class="nav nav-tabs" role="tablist" style="border-bottom:none;margin-bottom:-1px">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-activity" type="button" role="tab">
                            <i class="fa fa-chart-line me-1"></i> Activity
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-task" type="button" role="tab">
                            <i class="fa fa-tasks me-1"></i> Task
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-noted" type="button" role="tab">
                            <i class="fa fa-sticky-note me-1"></i> Noted
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-logs" type="button" role="tab">
                            <i class="fa fa-history me-1"></i> Logs
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body-custom">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-activity" role="tabpanel">
                        <div class="empty-state">
                            <i class="fa fa-chart-line"></i>
                            <p>Belum ada aktivitas.</p>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-task" role="tabpanel">
                        <div class="empty-state">
                            <i class="fa fa-tasks"></i>
                            <p>Belum ada task.</p>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-noted" role="tabpanel">
                        <div class="empty-state">
                            <i class="fa fa-sticky-note"></i>
                            <p>Belum ada catatan.</p>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-logs" role="tabpanel">
                        <div class="empty-state">
                            <i class="fa fa-history"></i>
                            <p>Belum ada log.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════ Right Column ═══════════════ -->
    <div class="col-lg-3">

        <!-- ── Contact Information ── -->
        <div class="card-custom fade-in stagger-1">
            <div class="card-header-custom">
                <span><i class="fa fa-user me-2" style="color:var(--accent)"></i>Contact Information</span>
            </div>
            <div class="card-body-custom" style="padding-top:12px;padding-bottom:12px">
                <table class="table table-sm table-borderless mb-0 info-table">
                    <tr><td>Full Name</td><td><strong>{{ $lead->accountContact?->full_name ?? '—' }}</strong></td></tr>
                    <tr><td>Salutation</td><td>{{ $lead->accountContact?->salutation ?? '—' }}</td></tr>
                    <tr><td>Email</td><td>{{ $lead->accountContact?->email ?? '—' }}</td></tr>
                    <tr><td>Phone</td><td>{{ $lead->accountContact?->phone ?? '—' }}</td></tr>
                    <tr><td>Mobile</td><td>{{ $lead->accountContact?->mobile ?? '—' }}</td></tr>
                    <tr><td>Job Title</td><td>{{ $lead->accountContact?->jobTitle?->title_name ?? '—' }}</td></tr>
                    <tr><td>Department</td><td>{{ $lead->accountContact?->division?->division_name ?? '—' }}</td></tr>
                    <tr><td>Contact Method</td><td>{{ $lead->accountContact?->contactMethod?->method_name ?? '—' }}</td></tr>
                    <tr><td>Role in Project</td><td>{{ $lead->accountContact?->roleInProject?->role_name ?? '—' }}</td></tr>
                </table>
            </div>
        </div>

        <!-- ── Account Information ── -->
        <div class="card-custom fade-in stagger-2 mt-4">
            <div class="card-header-custom">
                <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Account Information</span>
            </div>
            <div class="card-body-custom" style="padding-top:12px;padding-bottom:12px">
                <table class="table table-sm table-borderless mb-0 info-table">
                    <tr><td>Company</td><td><strong>{{ $lead->accountCompany?->account_name ?? '—' }}</strong></td></tr>
                    <tr><td>Field Type</td><td>{{ $lead->accountCompany?->typesAccountsCompany?->type_name ?? '—' }}</td></tr>
                    <tr><td>Segmentation</td><td>{{ $lead->accountCompany?->segmentation?->segmentation_name ?? '—' }}</td></tr>
                    <tr><td>Account Type</td><td>{{ $lead->accountCompany?->accountType?->type_name ?? '—' }}</td></tr>
                    <tr><td>Business Entity</td><td>{{ $lead->accountCompany?->businessEntity?->entity_name ?? '—' }}</td></tr>
                    <tr><td>Business Value</td><td>{{ $lead->accountCompany?->businessValue?->value_name ?? '—' }}</td></tr>
                    <tr><td>Interaction Level</td><td>{{ $lead->accountCompany?->interactionLevel?->level_name ?? '—' }}</td></tr>
                    <tr><td>End User</td><td>{{ $lead->accountCompany?->end_user ?? '—' }}</td></tr>
                    <tr><td>Address</td><td>
                        {{ collect([
                            $lead->accountCompany?->address_billing_street,
                            $lead->accountCompany?->address_billing_city,
                            $lead->accountCompany?->address_billing_province,
                            $lead->accountCompany?->address_billing_postal_code,
                            $lead->accountCompany?->address_billing_country,
                        ])->filter()->join(', ') ?: '—' }}
                    </td></tr>
                </table>
            </div>
        </div>

        <!-- ── Additional Information ── -->
        <div class="card-custom fade-in stagger-3 mt-4">
            <div class="card-header-custom">
                <span><i class="fa fa-info-circle me-2" style="color:var(--accent)"></i>Additional Info</span>
            </div>
            <div class="card-body-custom" style="padding-top:12px;padding-bottom:12px">
                <table class="table table-sm table-borderless mb-0 info-table">
                    <tr><td>Can Be Contacted</td><td>{!! $lead->lead_can_be_contacted ? '<i class="fa fa-check-circle" style="color:var(--success)"></i> Ya' : '<i class="fa fa-minus-circle" style="color:var(--text-muted)"></i> Tidak' !!}</td></tr>
                    <tr><td>Appointment</td><td>{!! $lead->lead_appoinment ? '<i class="fa fa-check-circle" style="color:var(--success)"></i> Ya' : '<i class="fa fa-minus-circle" style="color:var(--text-muted)"></i> Tidak' !!}</td></tr>
                    <tr><td>Identification</td><td>{!! $lead->identification ? '<i class="fa fa-check-circle" style="color:var(--success)"></i> Ya' : '<i class="fa fa-minus-circle" style="color:var(--text-muted)"></i> Tidak' !!}</td></tr>
                    <tr><td>All Fields Done</td><td>{!! $lead->all_filed_completed ? '<i class="fa fa-check-circle" style="color:var(--success)"></i> Ya' : '<i class="fa fa-minus-circle" style="color:var(--text-muted)"></i> Tidak' !!}</td></tr>
                    <tr><td>Close Date</td><td>{{ $lead->closed_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr><td>Unqualified Reason</td><td>{{ $lead->unqualified_reason ?? '—' }}</td></tr>
                    <tr><td>Lead Owner</td><td><strong>{{ $lead->leadOwner?->username ?? '—' }}</strong></td></tr>
                    <tr><td>Assigned To</td><td>{{ $lead->assignedTo?->username ?? '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade modal-lead" id="leadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="leadModalTitle">Edit Lead</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow-y:auto">
                <form id="lead-form" autocomplete="off">
                    <input type="hidden" id="lead-edit-id">

                    <!-- ── Lead Information ── -->
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
                                <div class="form-group" style="flex:0 0 180px">
                                    <label>Close Date</label>
                                    <input type="date" name="closed_date" id="lead-close-date">
                                </div>
                                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:8px">
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

                    <!-- ── Account Information ── -->
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
                                <div class="form-group small">
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

                    <!-- ── Additional Information ── -->
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

@section('scripts')
<script>
let leadModalInstance = null;
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

        if (res.lead.lead_can_be_contacted) $('#lead-can-contact').prop('checked', true);
        if (res.lead.lead_appoinment) $('#lead-appointment').prop('checked', true);
        if (res.lead.identification) $('#lead-identification').prop('checked', true);
        if (res.lead.lead_follow_up_date) {
            $('#lead-follow-up').val(res.lead.lead_follow_up_date.substring(0, 10));
        }
        $('#lead-assigned').val(res.lead.assigned_to).trigger('change');

        leadModalInstance.show();
    }).fail(function() {
        toastr.error('Failed to load lead data.');
    });
}

 $(document).on('click', '#btn-save-lead', function() {
    const $btn = $(this);
    const editId = $('#lead-edit-id').val();
    if (!editId) { toastr.error('Invalid lead ID.'); return; }

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
            if (section.length && !section.hasClass('open')) section.addClass('open');
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

    const url = '{{ route("leads-management.update", "__ID__") }}'.replace('__ID__', editId);
    const companyId = $('#lead-company-id').val();
    if (companyId) {
        formData.delete('account_companies_id');
        formData.set('account_companies_id', companyId);
    } else {
        const freeText = $('#lead-company').val();
        if (freeText && freeText.trim()) formData.set('company', freeText.trim());
    }

    formData.append('_token', '{{ csrf_token() }}');
    formData.append('_method', 'PUT');

    Swal.fire({
        title: 'Update Lead?',
        text: 'Lead data will be updated.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, update!',
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
                location.reload();
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

 $(document).on('shown.bs.modal', '#leadModal', function() {
    if (!$('#lead-company').hasClass('select2-hidden-accessible')) {
        $('#lead-company').select2({
            theme: 'bootstrap-5',
            placeholder: 'Ketik nama perusahaan...',
            allowClear: true,
            width: '100%',
            tags: true,
            createTag: function(params) {
                return { id: params.term, text: params.term + ' (new)', newTag: true };
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
</script>
@endsection
