@extends('layouts.app')

@section('title', 'Detail Lead')
@section('page-title', 'Detail Lead')

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
    <div class="col-lg-6">
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa fa-user me-2" style="color:var(--accent)"></i>Lead Information</span>
                <span class="status-badge {{ $lead->lead_status === 'New' ? 'status-pending' : ($lead->lead_status === 'Qualified' ? 'status-active' : ($lead->lead_status === 'Unqualified' ? 'status-inactive' : '')) }}"
                      style="{{ $lead->lead_status === 'Contacted' ? 'background:var(--info-soft);color:#1e40af;' : '' }}">
                    {{ $lead->lead_status }}
                </span>
            </div>
            <div class="card-body-custom">
                <table class="table table-sm table-borderless mb-0" style="font-size:13.5px;">
                    <tr><td style="color:var(--text-muted);width:140px;">Full Name</td><td><strong>{{ $lead->accountContact?->full_name ?? '—' }}</strong></td></tr>
                    <tr><td style="color:var(--text-muted);">Salutation</td><td>{{ $lead->accountContact?->salutation ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Email</td><td>{{ $lead->accountContact?->email ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Mobile</td><td>{{ $lead->accountContact?->mobile ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Job Title</td><td>{{ $lead->accountContact?->jobTitle?->title_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Department</td><td>{{ $lead->accountContact?->division?->division_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Lead Source</td><td>{{ $lead->source?->source_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Contact Method</td><td>{{ $lead->accountContact?->contactMethod?->method_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Role in Project</td><td>{{ $lead->accountContact?->roleInProject?->role_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Title</td><td>{{ $lead->lead_title ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Close Date</td><td>{{ $lead->closed_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Unqualified Reason</td><td>{{ $lead->unqualified_reason ?? '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-custom fade-in stagger-1">
            <div class="card-header-custom">
                <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Account Information</span>
            </div>
            <div class="card-body-custom">
                <table class="table table-sm table-borderless mb-0" style="font-size:13.5px;">
                    <tr><td style="color:var(--text-muted);width:140px;">Company</td><td><strong>{{ $lead->accountCompany?->account_name ?? '—' }}</strong></td></tr>
                    <tr><td style="color:var(--text-muted);">Segmentation</td><td>{{ $lead->accountCompany?->segmentation?->segmentation_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Account Type</td><td>{{ $lead->accountCompany?->accountType?->type_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Business Entity</td><td>{{ $lead->accountCompany?->businessEntity?->entity_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Business Value</td><td>{{ $lead->accountCompany?->businessValue?->value_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Interaction Level</td><td>{{ $lead->accountCompany?->interactionLevel?->level_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Address</td><td>
                        {{ $lead->accountCompany?->address_billing_street ?? '' }}
                        {{ $lead->accountCompany?->address_billing_city ?? '' }},
                        {{ $lead->accountCompany?->address_billing_province ?? '' }}
                        {{ $lead->accountCompany?->address_billing_postal_code ?? '' }},
                        {{ $lead->accountCompany?->address_billing_country ?? '' }}
                    </td></tr>
                    <tr><td style="color:var(--text-muted);">End User</td><td>{{ $lead->accountCompany?->end_user ?? '—' }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card-custom fade-in stagger-2 mt-4">
            <div class="card-header-custom">
                <span><i class="fa fa-info-circle me-2" style="color:var(--accent)"></i>Additional Information</span>
            </div>
            <div class="card-body-custom">
                <table class="table table-sm table-borderless mb-0" style="font-size:13.5px;">
                    <tr><td style="color:var(--text-muted);width:160px;">Lead Can Be Contacted</td><td>{!! $lead->lead_can_be_contacted ? '<i class="fa fa-check" style="color:var(--success)"></i>' : '<i class="fa fa-times" style="color:var(--text-muted)"></i>' !!}</td></tr>
                    <tr><td style="color:var(--text-muted);">Follow Up Date</td><td>{{ $lead->lead_follow_up_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Appointment</td><td>{!! $lead->lead_appoinment ? '<i class="fa fa-check" style="color:var(--success)"></i>' : '<i class="fa fa-times" style="color:var(--text-muted)"></i>' !!}</td></tr>
                    <tr><td style="color:var(--text-muted);">Identification</td><td>{!! $lead->identification ? '<i class="fa fa-check" style="color:var(--success)"></i>' : '<i class="fa fa-times" style="color:var(--text-muted)"></i>' !!}</td></tr>
                    <tr><td style="color:var(--text-muted);">All Field Completed</td><td>{!! $lead->all_filed_completed ? '<i class="fa fa-check" style="color:var(--success)"></i>' : '<i class="fa fa-times" style="color:var(--text-muted)"></i>' !!}</td></tr>
                    <tr><td style="color:var(--text-muted);">Lead Owner</td><td><strong>{{ $lead->leadOwner?->username ?? '—' }}</strong></td></tr>
                    <tr><td style="color:var(--text-muted);">Assigned To</td><td>{{ $lead->assignedTo?->username ?? '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
