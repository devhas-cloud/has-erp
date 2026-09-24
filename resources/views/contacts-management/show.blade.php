@extends('layouts.app')

@section('title', 'Detail Contact')
@section('page-title', 'Detail Contact')

@section('styles')
<style>
    .account-header-wrapper {
        background: #fff;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        padding: 20px 24px 16px;
        margin-bottom: 20px;
    }
    .account-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }
    .account-header__identity {
        display: flex;
        align-items: center;
        gap: 16px;
        min-width: 0;
    }
    .account-header__name {
        margin: 0;
        font-weight: 700;
        font-size: 20px;
        letter-spacing: -.3px;
        color: var(--text-primary);
        line-height: 1.3;
    }
    .account-header__sub {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        font-size: 13px;
        color: var(--text-secondary);
        margin-top: 2px;
    }
    .account-header__badges {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .account-header__actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid var(--card-border);
    }

    /* ── Info Table ── */
    .info-table td { padding: 8px 0; vertical-align: top; line-height: 1.45; }
    .info-table td:first-child {
        color: var(--text-muted);
        width: 160px;
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
    .info-table .info-table-group td {
        border-top: none;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .6px;
        color: var(--text-muted);
        padding-top: 14px;
    }

    .address-block {
        display: flex;
        gap: 10px;
        font-size: 13px;
        color: var(--text-primary);
        line-height: 1.6;
    }
    .address-block i { color: var(--accent); margin-top: 3px; }

    /* ── Tabs ── */
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
    .account-tab-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        margin-left: 6px;
        background: #e2e8f0;
        color: var(--text-muted);
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
        vertical-align: middle;
    }
    .nav-link.active .account-tab-badge {
        background: var(--accent-soft);
        color: var(--accent);
    }

    /* ── Related items (Lead / Opportunity) ── */
    .contact-list { padding: 0; }
    .contact-item {
        display: flex;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        text-decoration: none;
        transition: background .12s;
    }
    .contact-item:last-child { border-bottom: none; }
    .contact-item:hover { background: rgba(16,185,129,.025); }
    .contact-item:hover .contact-item__name { color: var(--accent); }
    .contact-item__avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: var(--accent-soft);
        color: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        flex-shrink: 0;
    }
    .contact-item__body { flex: 1; min-width: 0; }
    .contact-item__name {
        font-weight: 600;
        font-size: 13.5px;
        color: var(--text-primary);
        transition: color .12s;
    }
    .contact-item__job { font-size: 12px; color: var(--text-muted); margin-top: 1px; }
    .contact-item__meta {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        margin-top: 6px;
        font-size: 12px;
        color: var(--text-secondary);
    }
    .contact-item__badges {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
        flex-shrink: 0;
    }
    .contact-empty { padding: 20px 16px; }

    /* ── Modal form ── */
    .form-group { margin-bottom: 14px; }
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
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-soft);
        outline: none;
    }
    .form-group input.is-invalid,
    .form-group select.is-invalid,
    .form-group textarea.is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 3px rgba(220,53,69,.1) !important;
    }

    @media (max-width: 767.98px) {
        .account-header { flex-direction: column; align-items: flex-start; }
        .account-header__name { font-size: 17px; }
        .account-header__actions { justify-content: flex-start; }
        .contact-item { flex-wrap: wrap; }
        .contact-item__badges { flex-direction: row; align-items: flex-start; }
        .nav-tabs { flex-wrap: nowrap; overflow-x: auto; }
        .nav-tabs .nav-link { white-space: nowrap; }
    }
</style>
@endsection

@section('content')
@php
    $statusBadge = $contact->status === 'Active'
        ? '<span class="status-badge status-active">Active</span>'
        : '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d">Inactive</span>';
@endphp

<div class="page-header">
    <div>
        <h1 class="page-header-title">Detail Contact</h1>
        <p class="page-header-sub">Informasi lengkap data kontak</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ url()->previous() }}" class="btn-ghost"> <i class="fa fa-arrow-left"></i> <span>Kembali</span> </a>
    </div>
</div>

<div class="account-header-wrapper fade-in">
    <div class="account-header">
        <div class="account-header__identity">
            @if($contact->icon)
                <img src="{{ $contact->icon }}" class="avatar-circle" alt="" style="background:transparent">
            @else
                <div class="avatar-circle" style="font-size:15px">
                    {{ strtoupper(substr($contact->full_name ?? '?', 0, 2)) }}
                </div>
            @endif
            <div>
                <h2 class="account-header__name">
                    {{ trim(($contact->salutation ? $contact->salutation.' ' : '').($contact->full_name ?? '—')) }}
                </h2>
                <div class="account-header__sub">
                    @if($contact->jobTitle?->title_name)
                        <span><strong>{{ $contact->jobTitle->title_name }}</strong></span>
                    @endif
                    @if($contact->accountCompany?->account_name)
                        <a href="{{ route('accounts-management.show', ['accounts_management' => $contact->accountCompany->id]) }}"
                           style="color:var(--accent);text-decoration:none">
                            <i class="fa fa-building"></i> {{ $contact->accountCompany->account_name }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
        <div class="account-header__badges">
            {!! $statusBadge !!}
            @if($contact->division?->division_name)
                <span class="badge" style="background:var(--info-soft);color:#1e40af;font-size:12px;padding:5px 12px">
                    {{ $contact->division->division_name }}
                </span>
            @endif
            @if($contact->contactMethod?->method_name)
                <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:12px;padding:5px 12px">
                    {{ $contact->contactMethod->method_name }}
                </span>
            @endif
        </div>
    </div>

    <div class="account-header__actions">
        <span class="status-badge" style="background:#f1f5f9;color:var(--text-muted)">
            <i class="fa fa-user"></i> Owner: {{ $contact->contactOwner?->username ?? '—' }}
        </span>
        @if($contact->assignedTo?->username)
            <span class="status-badge" style="background:#f1f5f9;color:var(--text-muted)">
                <i class="fa fa-user-check"></i> Assigned: {{ $contact->assignedTo->username }}
            </span>
        @endif
        <span style="margin-left:auto"></span>

        @if($canCreate)
            <button type="button" class="btn btn-sm btn-accent" onclick="openNewLeadModal()">
                <i class="fa fa-bolt"></i> New Lead
            </button>
            @if($contact->account_companies_id)
                <button type="button" class="btn btn-sm btn-accent" onclick="openNewOpportunityModal()">
                    <i class="fa fa-bullseye"></i> New Opportunity
                </button>
            @endif
        @endif
    </div>
</div>

<div class="card-custom fade-in stagger-1">
    <div class="card-header-custom" style="padding:0 22px">
        <ul class="nav nav-tabs" role="tablist" style="border-bottom:none;margin-bottom:-1px">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-contact" type="button" role="tab">
                    <i class="fa fa-user me-1"></i> Contact Information
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-lead" type="button" role="tab">
                    <i class="fa fa-bolt me-1"></i> Leads
                    <span class="account-tab-badge">{{ $contact->leads->count() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-opportunity" type="button" role="tab">
                    <i class="fa fa-bullseye me-1"></i> Opportunity
                    <span class="account-tab-badge">{{ $opportunities->count() }}</span>
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body-custom">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-contact" role="tabpanel">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card-custom" style="box-shadow:none">
                            <div class="card-header-custom">
                                <span><i class="fa fa-user me-2" style="color:var(--accent)"></i>Contact Information</span>
                            </div>
                            <div class="card-body-custom">
                                <table class="table table-sm table-borderless mb-0 info-table">
                                    <tr class="info-table-group"><td colspan="2">General</td></tr>
                                    <tr><td>Full Name</td><td><strong>{{ trim(($contact->salutation ? $contact->salutation.' ' : '').($contact->full_name ?? '—')) }}</strong></td></tr>
                                    <tr><td>Email</td><td>@if($contact->email)<a href="mailto:{{ $contact->email }}" style="color:var(--accent)">{{ $contact->email }}</a>@else — @endif</td></tr>
                                    <tr><td>Phone</td><td>{{ $contact->phone ?? '—' }}</td></tr>
                                    <tr><td>Mobile</td><td>{{ $contact->mobile ?? '—' }}</td></tr>
                                    <tr class="info-table-group"><td colspan="2">Classification</td></tr>
                                    <tr><td>Job Title</td><td>{{ $contact->jobTitle?->title_name ?? '—' }}</td></tr>
                                    <tr><td>Contact Source</td><td>{{ $contact->source?->source_name ?? '—' }}</td></tr>
                                    <tr><td>Department</td><td>{{ $contact->division?->division_name ?? '—' }}</td></tr>
                                    <tr><td>Preferred Contact Method</td><td>{{ $contact->contactMethod?->method_name ?? '—' }}</td></tr>
                                    <tr><td>Role in Project</td><td>{{ $contact->roleInProject?->role_name ?? '—' }}</td></tr>
                                    <tr class="info-table-group"><td colspan="2">Ownership</td></tr>
                                    <tr><td>Owner</td><td><strong>{{ $contact->contactOwner?->username ?? '—' }}</strong></td></tr>
                                    <tr><td>Assigned To</td><td>{{ $contact->assignedTo?->username ?? '—' }}</td></tr>
                                    <tr><td>Status</td><td>{!! $statusBadge !!}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card-custom" style="box-shadow:none">
                            <div class="card-header-custom">
                                <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Company Information</span>
                            </div>
                            <div class="card-body-custom">
                                <table class="table table-sm table-borderless mb-0 info-table">
                                    <tr><td>Account Name</td><td><strong>{{ $contact->accountCompany?->account_name ?? '—' }}</strong></td></tr>
                                    <tr><td>Phone</td><td>{{ $contact->accountCompany?->phone ?? '—' }}</td></tr>
                                </table>
                            </div>
                        </div>

                        <div class="card-custom mt-4" style="box-shadow:none">
                            <div class="card-header-custom">
                                <span><i class="fa fa-location-dot me-2" style="color:var(--accent)"></i>Address</span>
                            </div>
                            <div class="card-body-custom">
                                @php
                                    $address = collect([
                                        $contact->accountCompany?->address_billing_street,
                                        $contact->accountCompany?->address_billing_city,
                                        $contact->accountCompany?->address_billing_province,
                                        $contact->accountCompany?->address_billing_postal_code,
                                        $contact->accountCompany?->address_billing_country,
                                    ])->filter()->join(', ');
                                @endphp
                                @if($address)
                                    <div class="address-block"><i class="fa fa-location-dot"></i><span>{{ $address }}</span></div>
                                @else
                                    <div class="address-block"><span style="color:var(--text-muted)">—</span></div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-lead" role="tabpanel">
                @if($contact->leads->isEmpty())
                    <div class="empty-state contact-empty">
                        <i class="fa fa-bolt"></i>
                        <p>Belum ada lead pada kontak ini.</p>
                    </div>
                @else
                    <div class="contact-list">
                        @foreach($contact->leads as $lead)
                            @php
                                $leadColor = match ($lead->lead_status) {
                                    'Qualified' => '<span class="status-badge status-active">Qualified</span>',
                                    'Approach' => '<span class="status-badge" style="background:var(--info-soft);color:#1e40af">Approach</span>',
                                    'Unqualified' => '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d">Unqualified</span>',
                                    default => '<span class="status-badge" style="background:#e2e8f0;color:#475569">New</span>',
                                };
                            @endphp
                            <a class="contact-item" href="{{ route('leads-management.show', ['leads_management' => $lead->id]) }}">
                                <div class="contact-item__avatar" style="background:var(--info-soft);color:#1e40af">
                                    <i class="fa fa-bolt"></i>
                                </div>
                                <div class="contact-item__body">
                                    <div class="contact-item__name">{{ $lead->lead_title ?? '—' }}</div>
                                    <div class="contact-item__job">
                                        @if($lead->source?->source_name)
                                            <i class="fa fa-tag"></i> {{ $lead->source->source_name }}
                                        @endif
                                    </div>
                                    <div class="contact-item__meta">
                                        @if($lead->leadOwner?->username)
                                            <span><i class="fa fa-user"></i> {{ $lead->leadOwner->username }}</span>
                                        @endif
                                        @if($lead->lead_follow_up_date)
                                            <span><i class="fa fa-calendar"></i> Follow up: {{ $lead->lead_follow_up_date->format('d M Y') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="contact-item__badges">{!! $leadColor !!}</div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="tab-pane fade" id="tab-opportunity" role="tabpanel">
                @if($opportunities->isEmpty())
                    <div class="empty-state contact-empty">
                        <i class="fa fa-bullseye"></i>
                        <p>Belum ada opportunity pada kontak ini.</p>
                    </div>
                @else
                    <div class="contact-list">
                        @foreach($opportunities as $opp)
                            <a class="contact-item" href="{{ route('opportunity-management.show', ['opportunity' => $opp->id]) }}">
                                <div class="contact-item__avatar" style="background:var(--accent-soft)">
                                    <i class="fa fa-bullseye"></i>
                                </div>
                                <div class="contact-item__body">
                                    <div class="contact-item__name">{{ $opp->opportunity_name ?? '—' }}</div>
                                    <div class="contact-item__job">
                                        @if($opp->stage?->stage_name)
                                            {{ $opp->stage->stage_name }}
                                            <span style="color:var(--text-muted)">&middot; {{ $opp->stage->probability ?? 0 }}%</span>
                                        @endif
                                        @if($opp->forecast?->forecast_name)
                                            <span style="color:var(--text-muted)">&middot; {{ $opp->forecast->forecast_name }}</span>
                                        @endif
                                    </div>
                                    <div class="contact-item__meta">
                                        @if($opp->owner?->username)
                                            <span><i class="fa fa-user"></i> {{ $opp->owner->username }}</span>
                                        @endif
                                        @if($opp->close_date)
                                            <span><i class="fa fa-calendar"></i> {{ $opp->close_date->format('d M Y') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="contact-item__badges">
                                    @if($opp->stage?->stage_name)
                                        <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:11px">
                                            {{ $opp->stage->stage_name }}
                                        </span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('modals')
<div class="modal fade" id="newLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa fa-bolt me-1" style="color:var(--accent)"></i> New Lead</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Lead Title <span class="text-danger">*</span></label>
                    <input type="text" id="nl-title" placeholder="Judul lead / kebutuhan">
                </div>
                <div class="form-group" style="display: none">
                    <label>Lead Status <span class="text-danger">*</span></label>
                    <select id="nl-status">
                        <option value="New">New</option>
                        <option value="Approach">Approach</option>
                        <option value="Qualified">Qualified</option>
                        <option value="Unqualified">Unqualified</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Source <span class="text-danger">*</span></label>
                    <select id="nl-source">
                        <option value="">— Pilih —</option>
                        @foreach($sources as $src)
                        <option value="{{ $src->id }}" @if(str_contains(strtolower($src->source_name), 'referral')) data-referral="1" @endif>
                            {{ $src->source_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" id="nl-referral-group" style="display:none">
                    <label>Name Referral <span class="text-danger">*</span></label>
                    <input type="text" id="nl-referral" placeholder="Nama referral (wajib untuk sumber Referral)">
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Wajib diisi untuk sumber Referral / Employe Referral.</div>
                </div>
                <div class="form-group">
                    <label>Follow Up Date <span class="text-danger">*</span></label>
                    <input type="date" id="nl-follow-up">
                </div>

                @if($canUpdate && !$isSales)
                <div class="form-group">
                    <label>Assigned To</label>
                    <select id="nl-assigned">
                        <option value="">— Pilih —</option>
                        @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->username }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-new-lead">
                    <i class="fa fa-save me-1"></i> Save Lead
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newOpportunityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa fa-bullseye me-1" style="color:var(--accent)"></i> New Opportunity</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Opportunity Name <span class="text-danger">*</span></label>
                    <input type="text" id="no-name" placeholder="Nama opportunity">
                </div>
                <div class="form-group">
                    <label>End User</label>
                    <select id="no-end-user" style="width:100%"></select>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Opsional — ketik nama account untuk mencari.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-new-opportunity">
                    <i class="fa fa-save me-1"></i> Save Opportunity
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@endsection

@section('scripts')
<script>
const contactStoreLeadUrl = '{{ route('contact-management.leads.store', ['contact_management' => '__ID__']) }}';
const contactId = {{ $contact->id }};

function openNewLeadModal() {
    $('#newLeadModal .is-invalid').removeClass('is-invalid');
    $('#newLeadModal input[type="text"], #newLeadModal input[type="date"], #newLeadModal select').val('');
    $('#nl-status').val('New');
    $('#nl-referral-group').hide();
    new bootstrap.Modal('#newLeadModal').show();
}

$(document).on('change', '#nl-source', function() {
    const isRef = $(this).find(':selected').data('referral') ? true : false;
    $('#nl-referral-group').toggle(isRef);
    if (!isRef) $('#nl-referral').val('');
    $('#nl-referral').removeClass('is-invalid');
});

function openNewOpportunityModal() {
    $('#no-name').val('');
    $('#no-end-user').val('').trigger('change');
    $('#newOpportunityModal .is-invalid').removeClass('is-invalid');
    new bootstrap.Modal('#newOpportunityModal').show();
}

$(document).on('shown.bs.modal', '#newOpportunityModal', function() {
    if ($('#no-end-user').hasClass('select2-hidden-accessible')) return;
    $('#no-end-user').select2({
        theme: 'bootstrap-5',
        placeholder: 'Ketik nama account...',
        allowClear: true,
        width: '100%',
        dropdownParent: $('#newOpportunityModal'),
        minimumInputLength: 1,
        ajax: {
            url: '{{ route('opportunity-management.search-companies') }}',
            dataType: 'json',
            delay: 250,
            data: function(p) { return { q: p.term }; },
            processResults: function(res) { return { results: res.results }; }
        }
    });
});

$(document).on('click', '#btn-save-new-lead', function() {
    const $btn = $(this);
    $('#newLeadModal .is-invalid').removeClass('is-invalid');
    let first = null;
    if (!$('#nl-title').val().trim()) { $('#nl-title').addClass('is-invalid'); first = first || 'Lead Title wajib diisi.'; }
    if (!$('#nl-status').val()) { $('#nl-status').addClass('is-invalid'); first = first || 'Lead Status wajib dipilih.'; }
    if (!$('#nl-source').val()) { $('#nl-source').addClass('is-invalid'); first = first || 'Source wajib dipilih.'; }
    if (!$('#nl-follow-up').val()) { $('#nl-follow-up').addClass('is-invalid'); first = first || 'Follow Up Date wajib diisi.'; }
    const isRef = $('#nl-source').find(':selected').data('referral') ? true : false;
    if (isRef && !$('#nl-referral').val().trim()) { $('#nl-referral').addClass('is-invalid'); first = first || 'Name Referral wajib diisi untuk sumber Referral / Employe Referral.'; }
    if (first) { toastr.error(first); return; }

    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Saving...');
    const payload = {
        _token: '{{ csrf_token() }}',
        lead_title: $('#nl-title').val().trim(),
        lead_status: $('#nl-status').val(),
        source_id: $('#nl-source').val(),
        name_referral: $('#nl-referral').val().trim(),
        lead_follow_up_date: $('#nl-follow-up').val()
    };
    const nlAssigned = $('#nl-assigned').val();
    if (nlAssigned) payload.assigned_to = nlAssigned;
    $.ajax({
        url: contactStoreLeadUrl.replace('__ID__', contactId),
        type: 'POST',
        data: payload,
        success: function(res) {
            bootstrap.Modal.getInstance('#newLeadModal').hide();
            toastr.success(res.message);
            setTimeout(function() { location.reload(); }, 600);
        },
        error: function(xhr) {
            const errs = xhr.responseJSON?.errors;
            toastr.error(errs ? Object.values(errs)[0][0] : (xhr.responseJSON?.message || 'Gagal menyimpan lead.'));
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save Lead');
        }
    });
});

$(document).on('click', '#btn-save-new-opportunity', function() {
    const $btn = $(this);
    $('#newOpportunityModal .is-invalid').removeClass('is-invalid');
    if (!$('#no-name').val().trim()) {
        $('#no-name').addClass('is-invalid');
        toastr.error('Opportunity Name wajib diisi.');
        return;
    }

    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Saving...');
    const payload = {
        _token: '{{ csrf_token() }}',
        opportunity_name: $('#no-name').val().trim(),
        account_companies_id: {{ $contact->account_companies_id ?? 'null' }},
        account_contacts_id: {{ $contact->id }}
    };
    const noEndUser = $('#no-end-user').val();
    if (noEndUser) payload.end_user_id = noEndUser;
    $.ajax({
        url: '{{ route('opportunity-management.store') }}',
        type: 'POST',
        data: payload,
        success: function(res) {
            bootstrap.Modal.getInstance('#newOpportunityModal').hide();
            toastr.success(res.message);
            setTimeout(function() { location.reload(); }, 600);
        },
        error: function(xhr) {
            const errs = xhr.responseJSON?.errors;
            toastr.error(errs ? Object.values(errs)[0][0] : (xhr.responseJSON?.message || 'Gagal menyimpan opportunity.'));
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save Opportunity');
        }
    });
});
</script>
@endsection
