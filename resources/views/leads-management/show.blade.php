@extends('layouts.app')

@section('title', 'Detail Lead')
@section('page-title', 'Detail Lead')

@section('styles')
<style>
    .nav-tabs .nav-link {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-muted);
        border: none;
        padding: 10px 18px;
        border-radius: 0;
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
    .info-table td:first-child {
        color: var(--text-muted);
        width: 140px;
        font-size: 12.5px;
    }
    .info-table td:last-child {
        font-size: 13.5px;
        color: var(--text-primary);
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
        <a href="{{ route('leads-management.edit', $lead->id) }}" class="btn-accent">
            <i class="fa fa-pen"></i><span>Edit</span>
        </a>
        @endif
        <a href="{{ route('leads-management.index') }}" class="btn-ghost">
            <i class="fa fa-arrow-left"></i><span>Kembali</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Content Grid -->
    <div class="col-lg-9">
        <!-- Lead Header -->
        <div class="card-custom fade-in">
            <div class="card-body-custom">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
                    <div style="display:flex;align-items:center;gap:14px">
                        @if($lead->accountContact?->icon)
                            <img src="{{ $lead->accountContact->icon }}" class="avatar-circle" alt="" style="background:transparent">
                        @else
                            <div class="avatar-circle">{{ strtoupper(substr($lead->accountContact?->full_name ?? '?', 0, 2)) }}</div>
                        @endif
                        <div>
                            <h5 style="margin:0;font-weight:700;font-size:18px;letter-spacing:-0.3px">{{ $lead->lead_title ?? '—' }}</h5>
                            <div style="font-size:13px;color:var(--text-secondary);margin-top:2px">
                                <strong>{{ $lead->accountContact?->full_name ?? '—' }}</strong>
                            </div>
                        </div>
                    </div>
                    <span class="status-badge {{ $lead->lead_status === 'New' ? 'status-pending' : ($lead->lead_status === 'Qualified' ? 'status-active' : ($lead->lead_status === 'Unqualified' ? 'status-inactive' : '')) }}"
                          style="{{ $lead->lead_status === 'Contacted' ? 'background:var(--info-soft);color:#1e40af;' : '' }}">
                        {{ $lead->lead_status }}
                    </span>
                </div>
                <div style="display:flex;gap:24px;margin-top:16px;flex-wrap:wrap;font-size:13px;color:var(--text-muted)">
                    <span><i class="fa fa-user me-1"></i> {{ $lead->leadOwner?->username ?? '—' }}</span>
                    <span><i class="fa fa-bullseye me-1"></i> {{ $lead->source?->source_name ?? '—' }}</span>
                    <span><i class="fa fa-calendar me-1"></i> Follow Up: {{ $lead->lead_follow_up_date?->format('d M Y') ?? '—' }}</span>
                    @if($lead->closed_date)
                    <span><i class="fa fa-clock me-1"></i> Close: {{ $lead->closed_date->format('d M Y') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Tab Section -->
        <div class="card-custom fade-in stagger-1 mt-4">
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
                </div>
            </div>
        </div>
    </div>

    <!-- Right Grid -->
    <div class="col-lg-3">
        <!-- Contact Information -->
        <div class="card-custom fade-in stagger-1">
            <div class="card-header-custom">
                <span><i class="fa fa-user me-2" style="color:var(--accent)"></i>Contact Information</span>
            </div>
            <div class="card-body-custom">
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

        <!-- Account Information -->
        <div class="card-custom fade-in stagger-2 mt-4">
            <div class="card-header-custom">
                <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Account Information</span>
            </div>
            <div class="card-body-custom">
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

        <!-- Additional Information -->
        <div class="card-custom fade-in stagger-3 mt-4">
            <div class="card-header-custom">
                <span><i class="fa fa-info-circle me-2" style="color:var(--accent)"></i>Additional Info</span>
            </div>
            <div class="card-body-custom">
                <table class="table table-sm table-borderless mb-0 info-table">
                    <tr><td>Lead Can Be Contacted</td><td>{!! $lead->lead_can_be_contacted ? '<i class="fa fa-check" style="color:var(--success)"></i>' : '<i class="fa fa-times" style="color:var(--text-muted)"></i>' !!}</td></tr>
                    <tr><td>Appointment</td><td>{!! $lead->lead_appoinment ? '<i class="fa fa-check" style="color:var(--success)"></i>' : '<i class="fa fa-times" style="color:var(--text-muted)"></i>' !!}</td></tr>
                    <tr><td>Identification</td><td>{!! $lead->identification ? '<i class="fa fa-check" style="color:var(--success)"></i>' : '<i class="fa fa-times" style="color:var(--text-muted)"></i>' !!}</td></tr>
                    <tr><td>All Field Completed</td><td>{!! $lead->all_filed_completed ? '<i class="fa fa-check" style="color:var(--success)"></i>' : '<i class="fa fa-times" style="color:var(--text-muted)"></i>' !!}</td></tr>
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
