@extends('layouts.app')

@section('title', 'Detail Account')
@section('page-title', 'Detail Account')

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

        .account-header__desc {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-top: 6px;
            max-width: 560px;
        }

        .account-header__badges {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .account-header__meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            padding-top: 16px;
            margin-top: 16px;
            border-top: 1px solid var(--card-border);
        }

        .account-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            color: var(--text-secondary);
            background: #f8fafc;
            border: 1px solid var(--card-border);
            padding: 5px 12px;
            border-radius: 20px;
            white-space: nowrap;
            text-decoration: none;
        }

        .account-meta-chip i {
            color: var(--accent);
            opacity: .8;
        }

        .account-meta-chip a {
            color: var(--accent);
            text-decoration: none;
        }

        /* ── Info Table ── */
        .info-table td {
            padding: 8px 0;
            vertical-align: top;
            line-height: 1.45;
        }

        .info-table td:first-child {
            color: var(--text-muted);
            width: 150px;
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

        .info-table tr+tr td {
            border-top: 1px solid var(--card-border);
        }

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

        .address-block i {
            color: var(--accent);
            margin-top: 3px;
        }

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

        /* ── Related items (Contact / Lead / Opportunity) ── */
        .contact-list {
            padding: 0;
        }

        .contact-item {
            display: flex;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            text-decoration: none;
            transition: background .12s;
        }

        .contact-item:last-child {
            border-bottom: none;
        }

        .contact-item:hover {
            background: rgba(16, 185, 129, .025);
        }

        .contact-item:hover .contact-item__name {
            color: var(--accent);
        }

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

        .contact-item__body {
            flex: 1;
            min-width: 0;
        }

        .contact-item__name {
            font-weight: 600;
            font-size: 13.5px;
            color: var(--text-primary);
            transition: color .12s;
        }

        .contact-item__job {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .contact-item__meta {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 6px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .contact-item__meta a {
            color: var(--text-secondary);
            text-decoration: none;
        }

        .contact-item__meta a:hover {
            color: var(--accent);
        }

        .contact-item__badges {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            flex-shrink: 0;
        }

        .contact-empty {
            padding: 20px 16px;
        }

        /* ── Contact identity cards ── */
        .contact-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 14px;
        }

        .contact-card {
            display: flex;
            flex-direction: column;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            overflow: hidden;
            transition: box-shadow .15s, border-color .15s, transform .15s;
            background: #fff;
        }

        .contact-card:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 16px rgba(16, 185, 129, .12);
            transform: translateY(-1px);
        }

        .contact-card__top {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .contact-card:hover .contact-card__name {
            color: var(--accent);
        }

        .contact-card__avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }

        .contact-card__head {
            flex: 1;
            min-width: 0;
        }

        .contact-card__name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.3;
            transition: color .12s;
            word-break: break-word;
        }

        .contact-card__sub {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 1px;
            word-break: break-word;
        }

        .contact-card__status {
            flex-shrink: 0;
        }

        .contact-card__rows {
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }

        .contact-card__row {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 12.5px;
            color: var(--text-secondary);
            min-width: 0;
        }

        .contact-card__row i {
            width: 16px;
            text-align: center;
            color: var(--accent);
            opacity: .8;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .contact-card__row a,
        .contact-card__row span {
            flex: 1;
            min-width: 0;
            color: var(--text-secondary);
            text-decoration: none;
            white-space: normal;
            word-break: break-word;
        }

        .contact-card__row a:hover {
            color: var(--accent);
        }

        /* ── Collapsible Cards ── */
        .card-collapsible .card-header-custom {
            cursor: pointer;
        }

        .card-collapsible .card-body-custom {
            display: block;
        }

        .card-collapsible.collapsed .card-body-custom {
            display: none;
        }

        .card-collapsible .card-header-custom .chevron i {
            transition: transform .25s var(--ease);
        }

        .card-collapsible:not(.collapsed) .card-header-custom .chevron i {
            transform: rotate(180deg);
        }

        @media (max-width: 767.98px) {
            .account-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .account-header__name {
                font-size: 17px;
            }

            .account-header__meta {
                gap: 8px;
            }

            .contact-item {
                flex-wrap: wrap;
            }

            .contact-item__badges {
                flex-direction: row;
                align-items: flex-start;
            }

            .nav-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
            }

            .nav-tabs .nav-link {
                white-space: nowrap;
            }
        }
    </style>
@endsection

@section('content')
    @php
        $statusBadge =
            $account->status === 'Active'
                ? '<span class="status-badge status-active">Active</span>'
                : '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d">Inactive</span>';
    @endphp

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

    <div class="account-header-wrapper fade-in">
        <div class="account-header">
            <div class="account-header__identity">
                @if ($account->icon)
                    <img src="{{ $account->icon }}" class="avatar-circle" alt="" style="background:transparent">
                @else
                    <div class="avatar-circle" style="font-size:15px">
                        {{ strtoupper(substr($account->account_name ?? '?', 0, 2)) }}
                    </div>
                @endif
                <div>
                    <h2 class="account-header__name">{{ $account->account_name ?? '—' }}.
                        {{ $account->typesAccountsCompany?->type_name }}   {!! $statusBadge !!}</h2>

                    <div class="account-header__sub">
                        <span class="badge"
                            style="background:var(--accent-soft);color:var(--accent);font-size:12px;padding:5px 12px">
                            {{ $account->segmentation?->segmentation_name ?? '—' }}
                        </span>
                        @if ($account->childAccounts->isNotEmpty())
                            <span style="color:var(--text-muted)">&middot; {{ $account->childAccounts->count() }} Child
                                Account(s)</span>
                        @endif
                    </div>
                    @if ($account->description)
                        <div class="account-header__desc">{{ $account->description }}</div>
                    @endif
                </div>
            </div>
            <div class="account-header__badges">

            </div>
        </div>

        <div class="account-header__meta">
            @if ($account->phone)
                <a class="account-meta-chip" href="tel:{{ $account->phone }}"><i class="fa fa-phone"></i>
                    {{ $account->phone }}</a>
            @endif
            @if ($account->website)
                <a class="account-meta-chip" href="{{ $account->website }}" target="_blank" rel="noopener"><i
                        class="fa fa-globe"></i> {{ $account->website }}</a>
            @endif
            @if ($account->end_user)
                <span class="account-meta-chip"><i class="fa fa-user-tag"></i> End User: {{ $account->end_user }}</span>
            @endif
            @if ($account->parentAccount?->account_name)
                <span class="account-meta-chip"><i class="fa fa-sitemap"></i> Parent:
                    {{ $account->parentAccount->account_name }}</span>
            @endif
        </div>
    </div>

    <div class="card-custom fade-in stagger-1">
        <div class="card-header-custom" style="padding:0 22px">
            <ul class="nav nav-tabs" role="tablist" style="border-bottom:none;margin-bottom:-1px">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-company" type="button"
                        role="tab">
                        <i class="fa fa-building me-1"></i> Company Information
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-contact" type="button"
                        role="tab">
                        <i class="fa fa-users me-1"></i> Contact
                        <span class="account-tab-badge">{{ $account->contacts->count() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-lead" type="button" role="tab">
                        <i class="fa fa-bolt me-1"></i> Lead
                        <span class="account-tab-badge">{{ $account->leads->count() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-opportunity" type="button"
                        role="tab">
                        <i class="fa fa-bullseye me-1"></i> Opportunity
                        <span class="account-tab-badge">{{ $account->opportunities->count() }}</span>
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body-custom">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-company" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card-custom" style="box-shadow:none">
                                <div class="card-header-custom">
                                    <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Company
                                        Information</span>
                                </div>
                                <div class="card-body-custom">
                                    <table class="table table-sm table-borderless mb-0 info-table">
                                        <tr class="info-table-group">
                                            <td colspan="2">General</td>
                                        </tr>
                                        <tr>
                                            <td>Account Name</td>
                                            <td><strong>{{ $account->account_name ?? '—' }}</strong></td>
                                        </tr>
                                        <tr>
                                            <td>Website</td>
                                            <td>{{ $account->website ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td>Phone</td>
                                            <td>{{ $account->phone ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td>Description</td>
                                            <td>{{ $account->description ?? '—' }}</td>
                                        </tr>
                                        <tr class="info-table-group">
                                            <td colspan="2">Classification</td>
                                        </tr>
                                        <tr>
                                            <td>Field Type</td>
                                            <td>{{ $account->typesAccountsCompany?->type_name ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td>Account Source</td>
                                            <td>{{ $account->source?->source_name ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td>Segmentation</td>
                                            <td>{{ $account->segmentation?->segmentation_name ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td>Business Entity</td>
                                            <td>{{ $account->businessEntity?->entity_name ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td>Business Value</td>
                                            <td>{{ $account->businessValue?->value_name ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td>Interaction Level</td>
                                            <td>{{ $account->interactionLevel?->level_name ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td>End User</td>
                                            <td>{{ $account->end_user ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td>Parent Account</td>
                                            <td>{{ $account->parentAccount?->account_name ?? '—' }}</td>
                                        </tr>
                                        <tr class="info-table-group">
                                            <td colspan="2">Ownership</td>
                                        </tr>
                                        <tr>
                                            <td>Owner</td>
                                            <td><strong>{{ $account->accountOwner?->username ?? '—' }}</strong></td>
                                        </tr>
                                        <tr>
                                            <td>Status</td>
                                            <td>{!! $statusBadge !!}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card-custom card-collapsible collapsed" style="box-shadow:none">
                                <div class="card-header-custom" onclick="toggleCard(this)" style="cursor:pointer">
                                    <span><i class="fa fa-file-invoice me-2" style="color:var(--accent)"></i>Billing
                                        Address</span>
                                    <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                                </div>
                                <div class="card-body-custom">
                                    @php
                                        $billing = collect([
                                            $account->address_billing_street,
                                            $account->address_billing_city,
                                            $account->address_billing_province,
                                            $account->address_billing_postal_code,
                                            $account->address_billing_country,
                                        ])
                                            ->filter()
                                            ->join(', ');
                                    @endphp
                                    @if ($billing)
                                        <div class="address-block"><i
                                                class="fa fa-location-dot"></i><span>{{ $billing }}</span></div>
                                    @else
                                        <div class="empty-state" style="padding:18px"><i class="fa fa-location-dot"></i>
                                            <p>Belum ada alamat billing.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="card-custom card-collapsible collapsed mt-4" style="box-shadow:none">
                                <div class="card-header-custom" onclick="toggleCard(this)" style="cursor:pointer">
                                    <span><i class="fa fa-truck me-2" style="color:var(--accent)"></i>Shipping
                                        Address</span>
                                    <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                                </div>
                                <div class="card-body-custom">
                                    @php
                                        $shipping = collect([
                                            $account->address_shipping_street,
                                            $account->address_shipping_city,
                                            $account->address_shipping_province,
                                            $account->address_shipping_postal_code,
                                            $account->address_shipping_country,
                                        ])
                                            ->filter()
                                            ->join(', ');
                                    @endphp
                                    @if ($shipping)
                                        <div class="address-block"><i
                                                class="fa fa-location-dot"></i><span>{{ $shipping }}</span></div>
                                    @else
                                        <div class="empty-state" style="padding:18px"><i class="fa fa-location-dot"></i>
                                            <p>Belum ada alamat shipping.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-contact" role="tabpanel">
                    @if ($account->contacts->isEmpty())
                        <div class="empty-state contact-empty">
                            <i class="fa fa-address-book"></i>
                            <p>Belum ada kontak pada account ini.</p>
                        </div>
                    @else
                        <div class="contact-list" style="padding:16px">
                            <div class="contact-card-grid">
                                @foreach ($account->contacts as $c)
                                    <div class="contact-card"
                                        data-href="{{ route('contact-management.show', ['contact_management' => $c->id]) }}">
                                        <div class="contact-card__top">
                                            <div class="contact-card__avatar">
                                                {{ strtoupper(substr($c->full_name ?? '?', 0, 2)) }}</div>
                                            <div class="contact-card__head">
                                                <div class="contact-card__name">
                                                    {{ trim(($c->salutation ? $c->salutation . ' ' : '') . ($c->full_name ?? '—')) }}
                                                </div>
                                                <div class="contact-card__sub">
                                                    {{ $c->jobTitle?->title_name ?? '—' }}@if ($c->division?->division_name)
                                                        <span style="color:var(--text-muted)">&middot;
                                                            {{ $c->division->division_name }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="contact-card__status">
                                                @if ($c->status === 'Active')
                                                    <span class="status-badge status-active">Active</span>
                                                @else
                                                    <span class="status-badge"
                                                        style="background:var(--danger-soft);color:#7f1d1d">Inactive</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="contact-card__rows">
                                            @if ($c->email)
                                                <div class="contact-card__row">
                                                    <i class="fa fa-envelope"></i>
                                                    <a href="mailto:{{ $c->email }}">{{ $c->email }}</a>
                                                </div>
                                            @endif
                                            @if ($c->phone)
                                                <div class="contact-card__row">
                                                    <i class="fa fa-phone"></i>
                                                    <a href="tel:{{ $c->phone }}">{{ $c->phone }}</a>
                                                </div>
                                            @endif
                                            @if ($c->mobile)
                                                <div class="contact-card__row">
                                                    <i class="fa fa-mobile"></i>
                                                    <a href="tel:{{ $c->mobile }}">{{ $c->mobile }}</a>
                                                </div>
                                            @endif
                                            @if ($c->contactMethod?->method_name || $c->contactOwner?->username)
                                                <div class="contact-card__row">
                                                    <i class="fa fa-address-book"></i>
                                                    <span>
                                                        @if ($c->contactMethod?->method_name)
                                                            {{ $c->contactMethod->method_name }}
                                                        @endif
                                                        @if ($c->contactOwner?->username)
                                                            <span style="color:var(--text-muted)">&middot;
                                                                {{ $c->contactOwner->username }}</span>
                                                        @endif
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="tab-pane fade" id="tab-lead" role="tabpanel">
                    @if ($account->leads->isEmpty())
                        <div class="empty-state contact-empty">
                            <i class="fa fa-bolt"></i>
                            <p>Belum ada lead pada account ini.</p>
                        </div>
                    @else
                        <div class="contact-list" style="padding:0">
                            @foreach ($account->leads as $lead)
                                @php
                                    $leadColor = match ($lead->lead_status) {
                                        'Qualified' => '<span class="status-badge status-active">Qualified</span>',
                                        'Approach'
                                            => '<span class="status-badge" style="background:var(--info-soft);color:#1e40af">Approach</span>',
                                        'Unqualified'
                                            => '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d">Unqualified</span>',
                                        default
                                            => '<span class="status-badge" style="background:#e2e8f0;color:#475569">New</span>',
                                    };
                                @endphp
                                <a class="contact-item"
                                    href="{{ route('leads-management.show', ['leads_management' => $lead->id]) }}">
                                    <div class="contact-item__avatar" style="background:var(--info-soft);color:#1e40af">
                                        <i class="fa fa-bolt"></i>
                                    </div>
                                    <div class="contact-item__body">
                                        <div class="contact-item__name">{{ $lead->lead_title ?? '—' }}</div>
                                        <div class="contact-item__job">
                                            @if ($lead->accountContact?->full_name)
                                                <i class="fa fa-user"></i> {{ $lead->accountContact->full_name }}
                                            @endif
                                            @if ($lead->source?->source_name)
                                                <span style="color:var(--text-muted)">&middot;
                                                    {{ $lead->source->source_name }}</span>
                                            @endif
                                        </div>
                                        <div class="contact-item__meta">
                                            @if ($lead->leadOwner?->username)
                                                <span><i class="fa fa-user"></i> {{ $lead->leadOwner->username }}</span>
                                            @endif
                                            @if ($lead->closed_date)
                                                <span><i class="fa fa-calendar"></i>
                                                    {{ $lead->closed_date->format('d M Y') }}</span>
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
                    @if ($account->opportunities->isEmpty())
                        <div class="empty-state contact-empty">
                            <i class="fa fa-bullseye"></i>
                            <p>Belum ada opportunity pada account ini.</p>
                        </div>
                    @else
                        <div class="contact-list" style="padding:0">
                            @foreach ($account->opportunities as $opp)
                                <a class="contact-item"
                                    href="{{ route('opportunity-management.show', ['opportunity' => $opp->id]) }}">
                                    <div class="contact-item__avatar" style="background:var(--accent-soft)">
                                        <i class="fa fa-bullseye"></i>
                                    </div>
                                    <div class="contact-item__body">
                                        <div class="contact-item__name">{{ $opp->opportunity_name ?? '—' }}</div>
                                        <div class="contact-item__job">
                                            @if ($opp->stage?->stage_name)
                                                {{ $opp->stage->stage_name }}
                                                <span style="color:var(--text-muted)">&middot;
                                                    {{ $opp->stage->probability ?? 0 }}%</span>
                                            @endif
                                            @if ($opp->forecast?->forecast_name)
                                                <span style="color:var(--text-muted)">&middot;
                                                    {{ $opp->forecast->forecast_name }}</span>
                                            @endif
                                        </div>
                                        <div class="contact-item__meta">
                                            @if ($opp->owner?->username)
                                                <span><i class="fa fa-user"></i> {{ $opp->owner->username }}</span>
                                            @endif
                                            @if ($opp->close_date)
                                                <span><i class="fa fa-calendar"></i>
                                                    {{ $opp->close_date->format('d M Y') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="contact-item__badges">
                                        @if ($opp->stage?->stage_name)
                                            <span class="badge"
                                                style="background:var(--accent-soft);color:var(--accent);font-size:11px">
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
@endsection

@section('scripts')
    <script>
        function toggleCard(el) {
            $(el).closest('.card-collapsible').toggleClass('collapsed');
        }

        $(document).on('click', '.contact-card', function(e) {
            if (e.target.closest('a')) return;
            const href = $(this).data('href');
            if (href) window.location.href = href;
        });
    </script>
@endsection
