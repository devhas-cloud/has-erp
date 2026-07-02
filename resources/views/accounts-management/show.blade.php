@extends('layouts.app')

@section('title', 'Detail Account')
@section('page-title', 'Detail Account')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Detail Account</h1>
        <p class="page-header-sub">Informasi lengkap data perusahaan</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('accounts-management.index') }}" class="btn-ghost">
            <i class="fa fa-arrow-left"></i><span>Kembali</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Company Information</span>
            </div>
            <div class="card-body-custom">
                <table class="table table-sm table-borderless mb-0" style="font-size:13.5px;">
                    <tr><td style="color:var(--text-muted);width:160px;">Account Name</td><td><strong>{{ $account->account_name ?? '—' }}</strong></td></tr>
                    <tr><td style="color:var(--text-muted);">Field Type</td><td>{{ $account->typesAccountsCompany?->type_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Account Source</td><td>{{ $account->source?->source_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Website</td><td>{{ $account->website ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Description</td><td>{{ $account->description ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Segmentation</td><td>{{ $account->segmentation?->segmentation_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Business Entity</td><td>{{ $account->businessEntity?->entity_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Business Value</td><td>{{ $account->businessValue?->value_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Interaction Level</td><td>{{ $account->interactionLevel?->level_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">End User</td><td>{{ $account->end_user ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Parent Account</td><td>{{ $account->parentAccount?->account_name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Phone</td><td>{{ $account->phone ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Owner</td><td><strong>{{ $account->accountOwner?->username ?? '—' }}</strong></td></tr>
                    <tr><td style="color:var(--text-muted);">Status</td><td>{{ $account->status ?? '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-custom fade-in stagger-1">
            <div class="card-header-custom">
                <span><i class="fa fa-file-invoice me-2" style="color:var(--accent)"></i>Billing Address</span>
            </div>
            <div class="card-body-custom">
                <table class="table table-sm table-borderless mb-0" style="font-size:13.5px;">
                    <tr><td style="color:var(--text-muted);width:140px;">Street</td><td>{{ $account->address_billing_street ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">City</td><td>{{ $account->address_billing_city ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Province</td><td>{{ $account->address_billing_province ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Postal Code</td><td>{{ $account->address_billing_postal_code ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Country</td><td>{{ $account->address_billing_country ?? '—' }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card-custom fade-in stagger-2 mt-4">
            <div class="card-header-custom">
                <span><i class="fa fa-truck me-2" style="color:var(--accent)"></i>Shipping Address</span>
            </div>
            <div class="card-body-custom">
                <table class="table table-sm table-borderless mb-0" style="font-size:13.5px;">
                    <tr><td style="color:var(--text-muted);width:140px;">Street</td><td>{{ $account->address_shipping_street ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">City</td><td>{{ $account->address_shipping_city ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Province</td><td>{{ $account->address_shipping_province ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Postal Code</td><td>{{ $account->address_shipping_postal_code ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted);">Country</td><td>{{ $account->address_shipping_country ?? '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
