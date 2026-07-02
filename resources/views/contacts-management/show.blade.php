@extends('layouts.app')

@section('title', 'Detail Contact')
@section('page-title', 'Detail Contact')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Detail Contact</h1>
        <p class="page-header-sub">Informasi lengkap data kontak</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('contact-management.index') }}" class="btn-ghost">
            <i class="fa fa-arrow-left"></i><span>Kembali</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa fa-user me-2" style="color:var(--accent)"></i>Contact Information</span>
            </div>
            <div class="card-body-custom">
                <table class="table table-sm table-borderless mb-0" style="font-size:13.5px;">
                    <tr><td style="color:var(--text-muted);width:160px;">Full Name</td><td><strong>{{ $contact->full_name ?? '—' }}</strong></td></tr>
                    <tr><td style="color:var(--text-muted);">Salutation</td><td>{{ $contact->salutation ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Email</td><td>{{ $contact->email ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Phone</td><td>{{ $contact->phone ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Mobile</td><td>{{ $contact->mobile ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Job Title</td><td>{{ $contact->jobTitle?->title_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Contact Source</td><td>{{ $contact->source?->source_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Department</td><td>{{ $contact->division?->division_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Preferred Contact Method</td><td>{{ $contact->contactMethod?->method_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Role in Project</td><td>{{ $contact->roleInProject?->role_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Owner</td><td><strong>{{ $contact->contactOwner?->username ?? '—' }}</strong></td></tr>
                    <tr><td style="color:var(--text-muted);">Status</td><td>{{ $contact->status ?? '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-custom fade-in stagger-1">
            <div class="card-header-custom">
                <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Company Information</span>
            </div>
            <div class="card-body-custom">
                <table class="table table-sm table-borderless mb-0" style="font-size:13.5px;">
                    <tr><td style="color:var(--text-muted);width:140px;">Account Name</td><td><strong>{{ $contact->accountCompany?->account_name ?? '—' }}</strong></td></tr>
                    <tr><td style="color:var(--text-muted);">Phone</td><td>{{ $contact->accountCompany?->phone ?? '—' }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card-custom fade-in stagger-2 mt-4">
            <div class="card-header-custom">
                <span><i class="fa fa-map-marker-alt me-2" style="color:var(--accent)"></i>Address Information</span>
            </div>
            <div class="card-body-custom">
                <table class="table table-sm table-borderless mb-0" style="font-size:13.5px;">
                    <tr><td style="color:var(--text-muted);width:140px;">Address Street</td><td>{{ $contact->address_street ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">City</td><td>{{ $contact->address_city ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Province</td><td>{{ $contact->address_province ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Postal Code</td><td>{{ $contact->address_postal_code ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Country</td><td>{{ $contact->address_country ?? '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
