@extends('layouts.app')

@section('title', 'Detail Opportunity')
@section('page-title', 'Detail Opportunity')

@section('styles')
<style>
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

    .opportunity-header-wrapper {
        background: #fff;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        padding: 20px 24px 16px;
        margin-bottom: 20px;
    }

    .opp-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
    }
    .opp-header__identity {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .opp-header__name {
        margin: 0;
        font-weight: 700;
        font-size: 20px;
        letter-spacing: -.3px;
        color: var(--text-primary);
        line-height: 1.3;
    }
    .opp-header__contact {
        font-size: 13px;
        color: var(--text-secondary);
        margin-top: 2px;
    }
    .opp-header__badges {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .opp-header__meta {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        font-size: 13px;
        color: var(--text-muted);
        padding-top: 16px;
        border-top: 1px solid var(--card-border);
        margin-top: 16px;
    }
    .opp-header__meta i { opacity: .6; margin-right: 4px; }

    .modal-opportunity .modal-dialog { max-width: 800px; }
    .opp-form-section {
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        margin-bottom: 16px;
        overflow: hidden;
    }
    .opp-form-section-header {
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
    .opp-form-section-body { padding: 16px; display: none; }
    .opp-form-section.open .opp-form-section-body { display: block; }
    .opp-form-section-header .chevron { transition: transform .2s; font-size: 11px; color: var(--text-muted); }
    .opp-form-section.open .chevron { transform: rotate(180deg); }
    .opp-form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
    .opp-form-row .form-group { flex: 1; min-width: 200px; }
    .opp-form-row .form-group.small { flex: 0 0 160px; }
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

    /* ── Select2 UI Polishing ── */
    .modal-opportunity .select2-container { width: 100% !important; }
    .modal-opportunity .select2-container--bootstrap-5 .select2-selection {
        min-height: 36px;
        border: 1px solid var(--card-border);
        border-radius: var(--radius-sm);
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .modal-opportunity .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        line-height: 34px;
        padding-left: 12px;
        font-size: 13px;
        color: var(--text-primary);
    }
    .modal-opportunity .select2-container--bootstrap-5 .select2-selection--single .select2-selection__placeholder {
        font-size: 13px;
        color: var(--text-muted);
    }
    .modal-opportunity .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
        height: 34px;
    }
    .modal-opportunity .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-soft);
    }
    .select2-container--open .select2-dropdown--below {
        margin-top: 2px;
        border-radius: var(--radius-sm);
        border-color: var(--card-border);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    }
    .select2-dropdown { z-index: 1060; }
    .select2-container--bootstrap-5 .select2-results__option {
        font-size: 13px;
        padding: 8px 12px;
    }
    .select2-container--bootstrap-5 .select2-results__option--highlighted.select2-results__option--selectable {
        background: var(--accent-soft) !important;
        color: var(--accent) !important;
    }
    .select2-container--bootstrap-5 .select2-results__option--selected {
        background: #f1f5f9 !important;
        color: var(--text-primary) !important;
    }
    .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
        font-size: 13px;
        padding: 8px 12px;
        border: 1px solid var(--card-border);
        border-radius: var(--radius-sm);
    }
    .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field:focus {
        border-color: var(--accent);
        outline: none;
    }
    .select2-container--bootstrap-5 .select2-selection__clear {
        font-size: 16px;
        color: var(--text-muted);
        padding: 0 8px;
    }
    .select2-container--bootstrap-5 .select2-selection__clear:hover {
        color: #dc3545;
    }

    .activity-feed { padding: 4px 0; max-height: 600px; overflow-y: auto; }
    .activity-form-card {
        background: #f8fafc;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        padding: 16px;
        margin-bottom: 20px;
        position: relative;
    }
    .activity-form-card textarea {
        width: 100%;
        border: 1px solid var(--card-border);
        border-radius: var(--radius-sm);
        padding: 10px 12px;
        font-size: 13px;
        font-family: inherit;
        resize: vertical;
        min-height: 60px;
        transition: border-color .15s;
    }
    .activity-form-card textarea:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-soft);
        outline: none;
    }
    .activity-form-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: 10px;
    }
    .activity-form-actions .btn { font-size: 12px; }

    .activity-post {
        display: flex;
        gap: 12px;
        padding: 16px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .activity-post:last-child { border-bottom: none; }
    .activity-post-avatar {
        width: 36px;
        height: 36px;
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
    .activity-post-body { flex: 1; min-width: 0; }
    .activity-post-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
        flex-wrap: wrap;
    }
    .activity-post-author { font-weight: 600; font-size: 13px; color: var(--text-primary); }
    .activity-post-time { font-size: 11px; color: var(--text-muted); }
    .activity-post-content { font-size: 13px; color: var(--text-secondary); line-height: 1.5; margin-bottom: 8px; white-space: pre-wrap; word-break: break-word; }
    .activity-post-attachments { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
    .activity-post-attachment {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: #f1f5f9;
        border-radius: var(--radius-sm);
        font-size: 12px;
        color: var(--text-secondary);
        text-decoration: none;
    }
    .activity-post-attachment:hover { background: #e2e8f0; }
    .activity-post-attachment-image {
        width: 80px; height: 80px;
        object-fit: cover;
        border-radius: var(--radius-sm);
        cursor: pointer;
        border: 1px solid var(--card-border);
    }
    .activity-post-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 4px;
    }
    .activity-post-actions button {
        background: none;
        border: none;
        font-size: 12px;
        font-weight: 500;
        color: var(--text-muted);
        cursor: pointer;
        padding: 2px 4px;
        transition: color .15s;
    }
    .activity-post-actions button:hover { color: var(--accent); }

    .activity-replies { margin-top: 12px; padding-left: 48px; }
    .activity-reply { display: flex; gap: 10px; margin-bottom: 10px; }
    .activity-reply-avatar {
        width: 28px; height: 28px;
        border-radius: 50%;
        background: #e2e8f0;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 10px;
        flex-shrink: 0;
    }
    .activity-reply-body { flex: 1; }
    .activity-reply-header { display: flex; align-items: center; gap: 6px; margin-bottom: 2px; }
    .activity-reply-author { font-weight: 600; font-size: 12px; color: var(--text-primary); }
    .activity-reply-time { font-size: 10px; color: var(--text-muted); }
    .activity-reply-content { font-size: 12px; color: var(--text-secondary); line-height: 1.4; white-space: pre-wrap; word-break: break-word; }

    .activity-reply-form {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-top: 10px;
        padding: 10px 14px;
        background: #f8fafc;
        border: 1px solid var(--card-border);
        border-radius: var(--radius-sm);
        position: relative;
    }
    .activity-reply-form .reply-input-row {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .activity-reply-form input[type="text"] {
        flex: 1;
        border: 1px solid var(--card-border);
        border-radius: 20px;
        padding: 6px 14px;
        font-size: 12px;
        font-family: inherit;
    }
    .activity-reply-form input[type="text"]:focus {
        border-color: var(--accent);
        outline: none;
    }
    .activity-reply-form .reply-actions {
        display: flex;
        gap: 4px;
        align-items: center;
    }
    .activity-reply-form .reply-actions button {
        background: none;
        border: none;
        font-size: 12px;
        color: var(--text-muted);
        cursor: pointer;
        padding: 4px 6px;
        border-radius: var(--radius-sm);
    }
    .activity-reply-form .reply-actions button:hover {
        color: var(--accent);
        background: #e2e8f0;
    }
    .activity-reply-form .reply-file-name {
        font-size: 11px;
        color: var(--text-muted);
        margin-left: 4px;
    }

    .activity-reply-toggle-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 4px;
    }
    .activity-reply-toggle {
        background: none;
        border: none;
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
        padding: 4px 10px;
        border-radius: 20px;
        transition: all .15s;
    }
    .activity-reply-toggle:hover {
        color: var(--accent);
        background: var(--accent-soft);
    }

    .task-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        transition: background .1s;
    }
    .task-item:last-child { border-bottom: none; }
    .task-item:hover { background: rgba(16,185,129,.02); cursor: pointer; }
    .task-item-icon {
        width: 32px; height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }
    .task-item-body { flex: 1; min-width: 0; }
    .task-item-title { font-weight: 600; font-size: 13.5px; color: var(--text-primary); margin-bottom: 2px; }
    .task-item-meta { font-size: 12px; color: var(--text-muted); }
    .task-item-status { font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 20px; flex-shrink: 0; }

    .task-form-section {
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        margin-bottom: 16px;
        overflow: hidden;
    }
    .task-form-section-header {
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
    .task-form-section-body { padding: 16px; display: none; }
    .task-form-section.open .task-form-section-body { display: block; }
    .task-form-section-header .chevron { transition: transform .2s; font-size: 11px; color: var(--text-muted); }
    .task-form-section.open .chevron { transform: rotate(180deg); }
    .task-form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
    .task-form-row .form-group { flex: 1; min-width: 200px; }
    .task-form-row .form-group.small { flex: 0 0 160px; }

    #filePreviewBody img { max-width: 100%; max-height: 80vh; border-radius: 6px; display: block; margin: 0 auto; }
    #filePreviewBody iframe { width: 100%; height: 85vh; border: none; border-radius: 6px; }
    #filePreviewModal .modal-dialog { max-width: 90vw; }
    #filePreviewModal .modal-body { padding: 8px; }

    .reply-file-previews {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 4px;
    }
    .reply-file-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px 3px 6px;
        background: #fff;
        border: 1px solid var(--card-border);
        border-radius: 14px;
        font-size: 11px;
        max-width: 180px;
    }
    .reply-file-chip img { width: 18px; height: 18px; border-radius: 3px; object-fit: cover; }
    .reply-file-chip .chip-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
    .reply-file-chip .chip-close { cursor: pointer; color: var(--text-muted); font-size: 10px; flex-shrink: 0; }
    .reply-file-chip .chip-close:hover { color: #dc3545; }

    .activity-form-card.loading {
        opacity: 0.6;
        pointer-events: none;
        position: relative;
    }
    .activity-form-card.loading::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(255,255,255,0.4);
        border-radius: var(--radius);
        z-index: 1;
    }
    .activity-reply-form.loading {
        opacity: 0.6;
        pointer-events: none;
    }

    .mention-suggestions {
        position: static;
        background: #fff;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        box-shadow: 0 6px 20px rgba(0,0,0,.12);
        max-height: 220px;
        overflow-y: auto;
        width: 260px;
        margin-top: 4px;
        display: none;
    }
    .mention-suggestion-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 14px;
        cursor: pointer;
        font-size: 13px;
        border-bottom: 1px solid #f1f5f9;
    }
    .mention-suggestion-item:last-child { border-bottom: none; }
    .mention-suggestion-item:hover,
    .mention-suggestion-item.active {
        background: var(--accent-soft);
    }
    .mention-suggestion-avatar {
        width: 30px; height: 30px;
        border-radius: 50%;
        background: var(--accent-soft);
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 11px;
        color: var(--accent); flex-shrink: 0;
    }
    .mention-suggestion-name {
        font-weight: 600; color: var(--text-primary);
    }
    .mention-tag {
        color: var(--accent);
        background: var(--accent-soft);
        padding: 1px 5px;
        border-radius: 4px;
        font-weight: 600;
        display: inline;
    }

    .highlight-flash {
        animation: highlightFlash 5s ease-out;
    }
    @keyframes highlightFlash {
        0% { background: rgba(16,185,129,0.25); }
        100% { background: transparent; }
    }

    @media (max-width: 767.98px) {
        .opp-header { flex-direction: column; }
        .opp-header__identity { flex-wrap: wrap; }
        .opp-header__name { font-size: 17px; }
        .opp-header__meta { gap: 12px; font-size: 12px; }

        .activity-feed { max-height: 70vh; }
        .activity-post-avatar { width: 30px; height: 30px; font-size: 11px; }
        .activity-post-content { font-size: 12px; }
        .activity-post-attachment-image { width: 60px; height: 60px; }

        .info-table td:first-child { width: 100px; font-size: 11px; padding-right: 8px; }
        .info-table td:last-child { font-size: 12px; }

        .mention-suggestions { width: 100%; max-width: 260px; }

        .activity-form-actions { flex-direction: column; align-items: stretch; }
        .activity-form-actions .btn { width: 100%; justify-content: center; }
        .activity-reply-form .reply-input-row { flex-direction: column; }
        .activity-reply-form input[type="text"] { width: 100%; }
        .activity-replies { padding-left: 24px; }

        .task-item { flex-wrap: wrap; }
        .task-item-status { margin-left: auto; }

        .page-header { flex-direction: column; gap: 12px; }
        .page-header-actions { width: 100%; }
        .page-header-actions .btn-accent,
        .page-header-actions .btn-ghost { flex: 1; justify-content: center; }
    }

    /* ── Collapsible Cards ── */
    .card-collapsible .card-header-custom { cursor: pointer; }
    .card-collapsible .card-body-custom { display: block; }
    .card-collapsible.collapsed .card-body-custom { display: none; }
    .card-collapsible .card-header-custom .chevron i { transition: transform .25s var(--ease); }
    .card-collapsible:not(.collapsed) .card-header-custom .chevron i { transform: rotate(180deg); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Detail Opportunity</h1>
        <p class="page-header-sub">Informasi lengkap data opportunity #{{ $opportunity->id }}</p>
    </div>
    <div class="page-header-actions">
        @if($canUpdate)
        <button type="button" class="btn-accent" onclick="openEditModal({{ $opportunity->id }})">
            <i class="fa fa-pen"></i><span>Edit</span>
        </button>
        @endif
        <a href="{{ route('opportunity-management.index') }}" class="btn-ghost">
            <i class="fa fa-arrow-left"></i><span>Kembali</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-sm-12 col-lg-9">

        <div class="opportunity-header-wrapper fade-in">
            <div class="card-body-custom">
                <div class="opp-header">
                    <div class="opp-header__identity">
                        @if($opportunity->accountCompany?->icon)
                            <img src="{{ $opportunity->accountCompany->icon }}" class="avatar-circle" alt="" style="background:transparent">
                        @else
                            <div class="avatar-circle" style="font-size:15px">
                                {{ strtoupper(substr($opportunity->accountCompany?->account_name ?? '?', 0, 2)) }}
                            </div>
                        @endif
                        <div>
                            <h2 class="opp-header__name">{{ $opportunity->opportunity_name ?? '—' }}</h2>
                            <div class="opp-header__contact">
                                <strong>{{ $opportunity->owner?->username ?? '—' }}</strong>
                                @if($opportunity->accountCompany?->account_name)
                                    &nbsp;·&nbsp; {{ $opportunity->accountCompany->account_name }}
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="opp-header__badges">
                        <span class="badge badge-soft-primary" style="font-size:13px;padding:6px 14px">
                            {{ $opportunity->stage?->stage_name ?? '—' }}
                        </span>
                        <span style="font-size:20px;font-weight:700;color:var(--text-primary)">
                            {{ $opportunity->probability ?? 0 }}%
                        </span>
                    </div>
                </div>

                <div class="opp-header__meta">
                    <span><i class="fa fa-calendar-check"></i>Close Won: {{ $opportunity->close_won_date?->format('d M Y') ?? '—' }}</span>
                    <span><i class="fa fa-chart-bar"></i>Forecast: {{ $opportunity->forecast?->forecast_name ?? '—' }}</span>
                    <span>{!! $opportunity->budget ? '<i class="fa fa-check-circle" style="color:var(--success)"></i>' : '<i class="fa-regular fa-circle" style="color:var(--text-muted)"></i>' !!} Budget</span>
                    <span>{!! $opportunity->authorize ? '<i class="fa fa-check-circle" style="color:var(--success)"></i>' : '<i class="fa-regular fa-circle" style="color:var(--text-muted)"></i>' !!} Authorize</span>
                    <span>{!! $opportunity->timeline ? '<i class="fa fa-check-circle" style="color:var(--success)"></i>' : '<i class="fa-regular fa-circle" style="color:var(--text-muted)"></i>' !!} Timeline</span>
                    <span>{!! $opportunity->quote_ready ? '<i class="fa fa-check-circle" style="color:var(--success)"></i>' : '<i class="fa-regular fa-circle" style="color:var(--text-muted)"></i>' !!} Quote Ready</span>
                </div>
            </div>
        </div>



        <div class="card-custom fade-in stagger-2 mt-4">
            <div class="card-header-custom" style="padding:0 22px">
                <ul class="nav nav-tabs" role="tablist" style="border-bottom:none;margin-bottom:-1px">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-activity" type="button" role="tab">
                            <i class="fa fa-chart-line me-1"></i> Activity
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
                        <div class="activity-feed" id="activity-feed">
                            <div id="activity-list"></div>
                            <div id="activity-loading" style="text-align:center;padding:20px;color:var(--text-muted)">
                                <i class="fa fa-spinner fa-spin"></i> Loading...
                            </div>
                        </div>
                        <div class="activity-form-card">
                            <textarea id="activity-input" placeholder="Tulis aktivitas..." rows="2"></textarea>
                            <div class="mention-suggestions" id="mention-suggestions"></div>
                            <div class="activity-form-actions">
                                <div>
                                    <input type="file" id="activity-file" style="display:none" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" multiple>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="$('#activity-file').click()">
                                        <i class="fa fa-paperclip"></i> Attach
                                    </button>
                                    <span id="activity-file-name" style="font-size:12px;color:var(--text-muted);margin-left:8px"></span>
                                    <div class="reply-file-previews" id="activity-file-previews"></div>
                                </div>
                                <button type="button" class="btn btn-sm btn-accent" id="btn-post-activity">
                                    <i class="fa fa-paper-plane"></i> Post
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-noted" role="tabpanel">
                        <div class="empty-state">
                            <i class="fa fa-sticky-note"></i>
                            <p>Belum ada catatan.</p>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-logs" role="tabpanel">
                        @php
                            $opportunityLogs = $opportunity->logs()->with('user')->orderByDesc('created_at')->get();
                        @endphp
                        @if ($opportunityLogs->isEmpty())
                            <div class="empty-state">
                                <i class="fa fa-history"></i>
                                <p>Belum ada log.</p>
                            </div>
                        @else
                            <div style="padding:4px 0">
                                @foreach ($opportunityLogs as $log)
                                    <div class="activity-post" style="padding:16px 22px">
                                        <div class="activity-post-avatar">{{ strtoupper(substr($log->user?->username ?? 'S', 0, 2)) }}</div>
                                        <div class="activity-post-body">
                                            <div class="activity-post-header">
                                                <span class="activity-post-author">{{ $log->user?->username ?? 'System' }}</span>
                                                <span class="activity-post-time">{{ $log->created_at->diffForHumans() }}</span>
                                            </div>
                                            <div class="activity-post-content">{{ $log->description }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-12 col-lg-3">

        <!-- ── Task Card ── -->
        <div class="card-custom fade-in stagger-1 card-collapsible">
            <div class="card-header-custom" onclick="toggleCard(this)" style="cursor:pointer">
                <span><i class="fa fa-tasks me-2" style="color:var(--accent)"></i>Task</span>
                <span class="chevron"><i class="fa fa-chevron-down"></i></span>
            </div>
            <div class="card-body-custom">
                <div style="display:flex;justify-content:flex-end;margin-bottom:8px">
                    <button type="button" class="btn btn-sm btn-accent" style="height: 25px;" onclick="openCreateTaskModal()">
                        <i class="fa fa-plus"></i> Add Task
                    </button>
                </div>
                <div id="task-list">
                    <div style="text-align:center;padding:20px;color:var(--text-muted)">
                        <i class="fa fa-spinner fa-spin"></i> Loading...
                    </div>
                </div>
            </div>
        </div>
        <br>

        <div class="card-custom fade-in stagger-1 card-collapsible collapsed">
            <div class="card-header-custom" onclick="toggleCard(this)" style="cursor:pointer">
                <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Account Company</span>
                <span class="chevron"><i class="fa fa-chevron-down"></i></span>
            </div>
            <div class="card-body-custom" style="padding-top:12px;padding-bottom:12px">
                <div class="table-responsive">
                <table class="table table-sm table-borderless mb-0 info-table">
                    <tr><td>Company</td><td><strong>{{ $opportunity->accountCompany?->account_name ?? '—' }}</strong></td></tr>
                    <tr><td>Field Type</td><td>{{ $opportunity->accountCompany?->typesAccountsCompany?->type_name ?? '—' }}</td></tr>
                    <tr><td>Segmentation</td><td>{{ $opportunity->accountCompany?->segmentation?->segmentation_name ?? '—' }}</td></tr>
                    <tr><td>Account Type</td><td>{{ $opportunity->accountCompany?->accountType?->type_name ?? '—' }}</td></tr>
                    <tr><td>Business Entity</td><td>{{ $opportunity->accountCompany?->businessEntity?->entity_name ?? '—' }}</td></tr>
                    <tr><td>Business Value</td><td>{{ $opportunity->accountCompany?->businessValue?->value_name ?? '—' }}</td></tr>
                    <tr><td>Interaction Level</td><td>{{ $opportunity->accountCompany?->interactionLevel?->level_name ?? '—' }}</td></tr>
                    <tr><td>Address</td><td>
                        {{ collect([
                            $opportunity->accountCompany?->address_billing_street,
                            $opportunity->accountCompany?->address_billing_city,
                            $opportunity->accountCompany?->address_billing_province,
                            $opportunity->accountCompany?->address_billing_postal_code,
                            $opportunity->accountCompany?->address_billing_country,
                        ])->filter()->join(', ') ?: '—' }}
                    </td></tr>
                </table>
                </div>
            </div>
        </div>



        <div class="card-custom fade-in stagger-1 mt-4 card-collapsible collapsed">
            <div class="card-header-custom" onclick="toggleCard(this)" style="cursor:pointer">
                <span><i class="fa fa-user me-2" style="color:var(--accent)"></i>Account Contact</span>
                <span class="chevron"><i class="fa fa-chevron-down"></i></span>
            </div>
            <div class="card-body-custom" style="padding-top:12px;padding-bottom:12px">
                <div class="table-responsive">
                <table class="table table-sm table-borderless mb-0 info-table">
                    <tr><td>Full Name</td><td><strong>{{ $opportunity->accountContact?->full_name ?? '—' }}</strong></td></tr>
                    <tr><td>Salutation</td><td>{{ $opportunity->accountContact?->salutation ?? '—' }}</td></tr>
                    <tr><td>Email</td><td>{{ $opportunity->accountContact?->email ?? '—' }}</td></tr>
                    <tr><td>Phone</td><td>{{ $opportunity->accountContact?->phone ?? '—' }}</td></tr>
                    <tr><td>Mobile</td><td>{{ $opportunity->accountContact?->mobile ?? '—' }}</td></tr>
                    <tr><td>Job Title</td><td>{{ $opportunity->accountContact?->jobTitle?->title_name ?? '—' }}</td></tr>
                    <tr><td>Department</td><td>{{ $opportunity->accountContact?->division?->division_name ?? '—' }}</td></tr>
                    <tr><td>Contact Method</td><td>{{ $opportunity->accountContact?->contactMethod?->method_name ?? '—' }}</td></tr>
                    <tr><td>Role in Project</td><td>{{ $opportunity->accountContact?->roleInProject?->role_name ?? '—' }}</td></tr>
                </table>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in stagger-3 mt-4 card-collapsible collapsed">
            <div class="card-header-custom" onclick="toggleCard(this)" style="cursor:pointer">
                <span><i class="fa fa-info-circle me-2" style="color:var(--accent)"></i>Opportunity Details</span>
                <span class="chevron"><i class="fa fa-chevron-down"></i></span>
            </div>
            <div class="card-body-custom" style="padding-top:12px;padding-bottom:12px">
                <table class="table table-sm table-borderless mb-0 info-table">
                    <tr><td>Stage</td><td><strong>{{ $opportunity->stage?->stage_name ?? '—' }}</strong></td></tr>
                    <tr><td>Forecast</td><td>{{ $opportunity->forecast?->forecast_name ?? '—' }}</td></tr>
                    <tr><td>Loss Reason</td><td>{{ $opportunity->lossReason?->reason_name ?? '—' }}</td></tr>
                    <tr><td>Probability</td><td><strong>{{ $opportunity->probability ?? '0' }}%</strong></td></tr>
                    <tr><td>Division</td><td>{{ $opportunity->division?->division_name ?? '—' }}</td></tr>
                    <tr><td>Source</td><td>{{ $opportunity->source?->source_name ?? '—' }}</td></tr>
                    <tr><td>Lead</td><td>{{ $opportunity->lead?->lead_title ?? '—' }}</td></tr>
                    <tr><td>Next Step</td><td>{{ $opportunity->next_step ?? '—' }}</td></tr>
                    <tr><td>Description</td><td>{{ $opportunity->description ?? '—' }}</td></tr>
                    <tr><td>Budget</td><td>{!! $opportunity->budget ? '<i class="fa fa-check-circle" style="color:var(--success)"></i> Ya' : '<i class="fa fa-minus-circle" style="color:var(--text-muted)"></i> Tidak' !!}</td></tr>
                    <tr><td>Authorize</td><td>{!! $opportunity->authorize ? '<i class="fa fa-check-circle" style="color:var(--success)"></i> Ya' : '<i class="fa fa-minus-circle" style="color:var(--text-muted)"></i> Tidak' !!}</td></tr>
                    <tr><td>Timeline</td><td>{!! $opportunity->timeline ? '<i class="fa fa-check-circle" style="color:var(--success)"></i> Ya' : '<i class="fa fa-minus-circle" style="color:var(--text-muted)"></i> Tidak' !!}</td></tr>
                    <tr><td>Quote Ready</td><td>{!! $opportunity->quote_ready ? '<i class="fa fa-check-circle" style="color:var(--success)"></i> Ya' : '<i class="fa fa-minus-circle" style="color:var(--text-muted)"></i> Tidak' !!}</td></tr>
                    <tr><td>Close Won Date</td><td>{{ $opportunity->close_won_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr><td>End User</td><td>{{ $opportunity->endUser?->account_name ?? '—' }}</td></tr>
                    <tr><td>Owner</td><td><strong>{{ $opportunity->owner?->username ?? '—' }}</strong></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade modal-opportunity" id="opportunityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="opportunityModalTitle">Edit Opportunity</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow-y:auto">
                <form id="opportunity-form" autocomplete="off">
                    <input type="hidden" id="opp-edit-id">

                    <div class="opp-form-section open">
                        <div class="opp-form-section-header" onclick="toggleOppSection(this)">
                            <span><i class="fa fa-bullseye me-2" style="color:var(--accent)"></i>Opportunity Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="opp-form-section-body">
                            <div class="opp-form-row">
                                <div class="form-group">
                                    <label>Opportunity Name <span class="text-danger">*</span></label>
                                    <input type="text" name="opportunity_name" id="opp-name" required>
                                </div>
                            </div>
                            <div class="opp-form-row">
                                <div class="form-group">
                                    <label>Account Name <span class="text-danger">*</span></label>
                                    <select id="opp-company" style="width:100%"></select>
                                    <input type="hidden" name="account_companies_id" id="opp-company-id">
                                </div>
                                <div class="form-group">
                                    <label>Contact Name</label>
                                    <select id="opp-contact" style="width:100%">
                                        <option value=""></option>
                                    </select>
                                    <input type="hidden" name="account_contacts_id" id="opp-contact-id">
                                </div>
                                <div class="form-group small">
                                    <label>Type</label>
                                    <select name="type" id="opp-type">
                                        <option value="">— Select —</option>
                                        <option value="Existing Business">Existing Business</option>
                                        <option value="New Business">New Business</option>
                                    </select>
                                </div>
                            </div>
                            <div class="opp-form-row">
                                <div class="form-group">
                                    <label>Stage</label>
                                    <select name="stage_id" id="opp-stage">
                                        <option value="">— Pilih —</option>
                                        @foreach($stages as $s)
                                        <option value="{{ $s->id }}">{{ $s->stage_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Probability (%)</label>
                                    <input type="number" name="probability" id="opp-probability" value="0" min="0" max="100" required>
                                </div>
                                <div class="form-group small">
                                    <label>Forecast <span class="text-danger">*</span></label>
                                    <select name="forecast_id" id="opp-forecast">
                                        <option value="">— Pilih —</option>
                                        @foreach($forecasts as $f)
                                        <option value="{{ $f->id }}">{{ $f->forecast_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="opp-form-row">
                                <div class="form-group">
                                    <label>Loss Reason</label>
                                    <select name="loss_reasons_id" id="opp-loss-reason">
                                        <option value="">— Pilih —</option>
                                        @foreach($lossReasons as $lr)
                                        <option value="{{ $lr->id }}">{{ $lr->reason_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Division</label>
                                    <select name="division_id" id="opp-division">
                                        <option value="">— Pilih —</option>
                                        @foreach($divisions as $d)
                                        <option value="{{ $d->id }}">{{ $d->division_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Lead Source</label>
                                    <select name="source_id" id="opp-source">
                                        <option value="">— Pilih —</option>
                                        @foreach($sources as $src)
                                        <option value="{{ $src->id }}">{{ $src->source_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="opp-form-row">
                                <div class="form-group small">
                                    <label>Close Date</label>
                                    <input type="date" name="close_date" id="opp-close-date">
                                </div>
                                <div class="form-group small">
                                    <label>End User</label>
                                    <select name="end_user_id" id="opp-end-user">
                                        <option value="">— Pilih —</option>
                                        @foreach($accountCompanies as $ac)
                                        <option value="{{ $ac->id }}">{{ $ac->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="opp-form-row">
                                <div class="form-group">
                                    <label>Next Step</label>
                                    <textarea name="next_step" id="opp-next-step" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="opp-form-row">
                                <label class="form-check-inline">
                                    <input type="checkbox" name="quote_ready" id="opp-quote-ready" value="1">
                                    Quote Ready
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="opp-form-section">
                        <div class="opp-form-section-header" onclick="toggleOppSection(this)">
                            <span><i class="fa fa-check-circle me-2" style="color:var(--accent)"></i>BAT Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="opp-form-section-body">
                            <div class="opp-form-row">
                                <div class="form-group small">
                                    <label>Close Won Date</label>
                                    <input type="date" name="close_won_date" id="opp-close-won-date">
                                </div>
                            </div>
                            <div class="opp-form-row">
                                <label class="form-check-inline">
                                    <input type="checkbox" name="budget" id="opp-budget" value="1">
                                    Budget
                                </label>
                                <label class="form-check-inline">
                                    <input type="checkbox" name="authorize" id="opp-authorize" value="1">
                                    Authorize
                                </label>
                                <label class="form-check-inline">
                                    <input type="checkbox" name="timeline" id="opp-timeline" value="1">
                                    Timeline
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="opp-form-section">
                        <div class="opp-form-section-header" onclick="toggleOppSection(this)">
                            <span><i class="fa fa-info-circle me-2" style="color:var(--accent)"></i>Additional Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="opp-form-section-body">
                            <div class="opp-form-row">
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea name="description" id="opp-description" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-opportunity">
                    <i class="fa fa-save me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('modals')
<div class="modal fade modal-task" id="taskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="taskModalTitle">Add Task</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow-y:auto">
                <form id="task-form" autocomplete="off">
                    <input type="hidden" id="opportunity-id-for-task" value="{{ $opportunity->id }}">

                    <div class="task-form-section open">
                        <div class="task-form-section-header" onclick="toggleTaskSection(this)">
                            <span><i class="fa fa-info-circle me-2" style="color:var(--accent)"></i>Task Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="task-form-section-body">
                            <div class="task-form-row">
                                <div class="form-group">
                                    <label>Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" id="task-title" placeholder="Enter task title">
                                </div>
                            </div>
                            <div class="task-form-row">
                                <div class="form-group" style="flex:2">
                                    <label>Description</label>
                                    <textarea name="description" id="task-description" rows="3" placeholder="Task description..."></textarea>
                                </div>
                            </div>
                            <div class="task-form-row">
                                <div class="form-group">
                                    <label>Category <span class="text-danger">*</span></label>
                                    <select name="category_id" id="task-category-id">
                                        <option value="">— Select Category —</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}">
                                                {{ $cat->division_id ? '[' . optional($cat->division)->division_name . '] ' : '[Global] ' }}
                                                {{ $cat->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Due Date <span class="text-danger">*</span></label>
                                    <input type="date" name="due_date" id="task-due-date">
                                </div>
                                <div class="form-group">
                                    <label>Time</label>
                                    <input type="time" name="time" id="task-time">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="task-form-section open">
                        <div class="task-form-section-header" onclick="toggleTaskSection(this)">
                            <span><i class="fa fa-users me-2" style="color:var(--accent)"></i>Assign To</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="task-form-section-body">

                            <div class="task-form-row" id="task-handling-division-container" style="display:none">
                                <div class="form-group">
                                    <label>Divisi</label>
                                    <select name="handling_division_id" id="task-handling-division" style="width:100%">
                                        <option value="">— Pilih Divisi —</option>
                                        @foreach ($divisions as $d)
                                            <option value="{{ $d->id }}">{{ $d->division_name }}</option>
                                        @endforeach
                                    </select>
                                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Anggota tim divisi akan otomatis menjadi assignee task.</div>
                                </div>
                            </div>

                            <div class="task-form-row">
                                <div class="form-group">
                                    <label>Assign To</label>
                                    <select name="assignees[]" id="task-assignees" multiple style="width:100%"></select>
                                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Leave empty to assign to yourself. Filtered by your delegation hierarchy.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="task-form-section" style="display:none">
                        <div class="task-form-section-header" onclick="toggleTaskSection(this)">
                            <span><i class="fa fa-bell me-2" style="color:var(--accent)"></i>Alert Settings</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="task-form-section-body">
                            <div class="task-form-row">
                                <div class="form-group small">
                                    <label>Alert Type</label>
                                    <select name="alert_type" id="task-alert-type">
                                        <option value="none">None</option>
                                        <option value="whatsapp">WhatsApp</option>
                                    </select>
                                </div>
                                <div class="form-group small">
                                    <label>Alert Target</label>
                                    <select name="alert_target" id="task-alert-target">
                                        <option value="personal">Personal (Japri)</option>
                                        <option value="group">Group WA</option>
                                    </select>
                                </div>
                                <div class="form-group small">
                                    <label>Alert Time</label>
                                    <input type="datetime-local" name="alert_time" id="task-alert-time">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-opportunity-task">
                    <i class="fa fa-save me-1"></i> Add Task
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="filePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="filePreviewTitle">File Preview</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-2" id="filePreviewBody"></div>
            <div class="modal-footer" id="filePreviewFooter"></div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let oppModalInstance = null;
const oppFetchUrl = '{{ route("opportunity-management.fetch", ["opportunity" => "__ID__"]) }}';

function toggleOppSection(header) {
    header.closest('.opp-form-section').classList.toggle('open');
}

function resetOpportunityForm() {
    document.getElementById('opportunity-form').reset();
    document.getElementById('opp-edit-id').value = '';
    document.querySelectorAll('.opp-form-section').forEach(function(s) {
        s.classList.remove('open');
    });
    document.querySelector('.opp-form-section').classList.add('open');
    $('#opportunity-form .is-invalid').removeClass('is-invalid');
    $('#opp-company').val('').trigger('change');
    $('#opp-company-id').val('');
    $('#opp-contact').empty().append('<option value=""></option>').trigger('change');
    $('#opp-contact-id').val('');
    $('#opp-end-user').val('').trigger('change');
}

function loadOppContacts(companyId, selectedContact) {
    var $contact = $('#opp-contact');
    $contact.val(null).trigger('change');

    if (!companyId) {
        $contact.find('option:not([value=""])').remove();
        return;
    }

    $.ajax({
        url: '{{ route("opportunity-management.search-contacts") }}',
        data: { company_id: companyId, q: '' },
        dataType: 'json',
        success: function(res) {
            $contact.find('option:not([value=""])').remove();
            $.each(res.results, function(i, c) {
                var opt = new Option(c.text, c.id, false, false);
                $contact.append(opt);
            });

            if (selectedContact) {
                var opt = new Option(selectedContact.full_name, selectedContact.id, true, true);
                $contact.append(opt).trigger('change');
                $('#opp-contact-id').val(selectedContact.id);
            } else {
                $contact.trigger('change');
            }
        }
    });
}

function openEditModal(id) {
    resetOpportunityForm();
    document.getElementById('opportunityModalTitle').textContent = 'Edit Opportunity';
    if (!oppModalInstance) {
        oppModalInstance = new bootstrap.Modal(document.getElementById('opportunityModal'));
    }

    $.get(oppFetchUrl.replace('__ID__', id), function(res) {
        var opp = res.opportunity;
        $('#opp-edit-id').val(opp.id);
        $('#opp-name').val(opp.opportunity_name);
        $('#opp-probability').val(opp.probability);
        $('#opp-type').val(opp.type || '');

        if (opp.account_company) {
            $('#opp-company').find('option:not([value=""])').remove();
            var cOption = new Option(opp.account_company.account_name, opp.account_company.id, true, true);
            $('#opp-company').append(cOption).trigger('change');
            $('#opp-company-id').val(opp.account_company.id);
        } else if (opp.account_companies_id) {
            $('#opp-company-id').val(opp.account_companies_id);
        }

        // load contacts for selected company, then pre-select
        var companyId = opp.account_company ? opp.account_company.id : (opp.account_companies_id || null);
        loadOppContacts(companyId, opp.account_contact);

        $('#opp-stage').val(opp.stage_id || '');
        $('#opp-forecast').val(opp.forecast_id || '');
        $('#opp-loss-reason').val(opp.loss_reasons_id || '');
        $('#opp-division').val(opp.division_id || '');
        $('#opp-source').val(opp.source_id || '');
        $('#opp-end-user').val(opp.end_user_id || '');

        $('#opp-next-step').val(opp.next_step);
        $('#opp-description').val(opp.description);
        if (opp.close_date) {
            $('#opp-close-date').val(opp.close_date.substring(0, 10));
        }
        if (opp.close_won_date) {
            $('#opp-close-won-date').val(opp.close_won_date.substring(0, 10));
        }
        if (opp.quote_ready) { $('#opp-quote-ready').prop('checked', true); }
        if (opp.budget) { $('#opp-budget').prop('checked', true); }
        if (opp.authorize) { $('#opp-authorize').prop('checked', true); }
        if (opp.timeline) { $('#opp-timeline').prop('checked', true); }

        oppModalInstance.show();
    }).fail(function() {
        toastr.error('Failed to load opportunity data.');
    });
}

$(document).on('click', '#btn-save-opportunity', function() {
    const $btn = $(this);
    const editId = $('#opp-edit-id').val();
    if (!editId) { toastr.error('Invalid opportunity ID.'); return; }

    $('#opportunity-form .is-invalid').removeClass('is-invalid');

    if (!$('#opp-name').val() || !$('#opp-name').val().trim()) {
        $('#opp-name').addClass('is-invalid');
        toastr.error('Opportunity Name wajib diisi.');
        $('#opp-name').focus();
        return;
    }
    if (!$('#opp-company-id').val()) {
        $('#opp-company').next('.select2-container').find('.select2-selection').addClass('is-invalid');
        toastr.error('Account Company wajib dipilih.');
        return;
    }
    if (!$('#opp-forecast').val()) {
        $('#opp-forecast').addClass('is-invalid');
        toastr.error('Forecast wajib dipilih.');
        return;
    }

    const formData = new FormData(document.getElementById('opportunity-form'));
    formData.set('quote_ready', $('#opp-quote-ready').is(':checked') ? '1' : '0');
    formData.set('budget', $('#opp-budget').is(':checked') ? '1' : '0');
    formData.set('authorize', $('#opp-authorize').is(':checked') ? '1' : '0');
    formData.set('timeline', $('#opp-timeline').is(':checked') ? '1' : '0');

    const url = '{{ route("opportunity-management.update", ["opportunity" => "__ID__"]) }}'.replace('__ID__', editId);

    formData.append('_token', '{{ csrf_token() }}');
    formData.append('_method', 'PUT');

    Swal.fire({
        title: 'Update Opportunity?',
        text: 'Opportunity data will be updated.',
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
                if (oppModalInstance) oppModalInstance.hide();
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

$(document).on('change input', '#opportunity-form input.is-invalid, #opportunity-form select.is-invalid', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('shown.bs.modal', '#opportunityModal', function() {
    if (!$('#opp-company').hasClass('select2-hidden-accessible')) {
        $('#opp-company').select2({
            theme: 'bootstrap-5',
            placeholder: 'Ketik nama perusahaan...',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#opportunityModal'),
            minimumResultsForSearch: 0,
            ajax: {
                url: '{{ route("opportunity-management.search-companies") }}',
                dataType: 'json',
                delay: 250,
                data: function(params) { return { q: params.term }; },
                processResults: function(res) { return { results: res.results }; }
            }
        }).on('select2:select', function(e) {
            $('#opp-company-id').val(e.params.data.id);
            loadOppContacts(e.params.data.id, null);
        }).on('select2:clear', function() {
            $('#opp-company-id').val('');
            loadOppContacts(null, null);
        });
    }

    if (!$('#opp-contact').hasClass('select2-hidden-accessible')) {
        $('#opp-contact').select2({
            theme: 'bootstrap-5',
            placeholder: '— Pilih Contact —',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#opportunityModal')
        }).on('select2:select', function(e) {
            $('#opp-contact-id').val(e.params.data.id);
        }).on('select2:clear', function() {
            $('#opp-contact-id').val('');
        });
    }

    if (!$('#opp-end-user').hasClass('select2-hidden-accessible')) {
        $('#opp-end-user').select2({
            theme: 'bootstrap-5',
            placeholder: '— Pilih —',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#opportunityModal')
        });
    }
});

$(document).on('shown.bs.modal', '#taskModal', function() {
    if (!$('#task-assignees').hasClass('select2-hidden-accessible')) {
        $('#task-assignees').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search and select assignees...',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#taskModal'),
            ajax: {
                url: '/users/search',
                dataType: 'json',
                delay: 300,
                data: function(params) { return { q: params.term }; },
                processResults: function(res) { return { results: res.results.map(function(u) { return { id: u.id, text: u.username }; }) }; }
            },
            minimumInputLength: 1
        });
    }
});

const categoryHandlerMap = @json($categoryHandlerMap);
const fetchDivisionHandlersUrl = '{{ route('task-planner.fetch-division-handlers') }}';

$(document).on('change', '#task-category-id', function() {
    var enabled = categoryHandlerMap[$(this).val()] ? true : false;
    $('#task-handling-division-container').toggle(enabled);
    if (!enabled) {
        $('#task-handling-division').val('');
    }
});

$(document).on('change', '#task-handling-division', function() {
    var divisionId = $(this).val();
    if (!divisionId) return;
    var $assignees = $('#task-assignees');
    $.ajax({
        url: fetchDivisionHandlersUrl,
        data: { division_id: divisionId },
        dataType: 'json',
        success: function(res) {
            $assignees.empty();
            (res.results || []).forEach(function(u) {
                $assignees.append(new Option(u.text, u.id, true, true));
            });
            $assignees.trigger('change');
        },
        error: function() {
            toastr.error('Gagal memuat anggota divisi.');
        }
    });
});

const opportunityId = {{ $opportunity->id }};
const activityFetchUrl = '{{ url("opportunity-management") }}/' + opportunityId + '/activities';
const activityStoreUrl = '{{ url("opportunity-management") }}/' + opportunityId + '/activities';
const taskFetchUrl = '{{ route("opportunity-management.tasks.fetch", ["opportunity" => "__ID__"]) }}'.replace('__ID__', opportunityId);
const taskStoreUrl = '{{ route("opportunity-management.tasks.store", ["opportunity" => "__ID__"]) }}'.replace('__ID__', opportunityId);

function loadActivities() {
    $.get(activityFetchUrl, function(res) {
        var html = '';
        if (res.data && res.data.length > 0) {
            res.data.forEach(function(a) { html += renderActivity(a); });
        } else {
            html = '<div class="empty-state"><i class="fa fa-chart-line"></i><p>Belum ada aktivitas.</p></div>';
        }
        $('#activity-list').html(html);
        $('#activity-loading').hide();
        if (window.location.hash?.startsWith('#activity-') || sessionStorage.getItem('mention_target')) {
            setTimeout(scrollToMentionedActivity, 500);
        }
    }).fail(function() {
        $('#activity-loading').html('<span style="color:var(--danger)">Gagal memuat aktivitas.</span>');
    });
}

function renderActivity(a) {
    var avatar = a.user ? (a.user.username || '?').substring(0, 2).toUpperCase() : '??';
    var time = a.created_at ? moment(a.created_at).fromNow() : '—';
    var attachmentsHtml = '';
    if (a.attachments && a.attachments.length > 0) {
        a.attachments.forEach(function(at) {
            var url = at.attachment_url || at.attachment_path;
            var name = at.attachment_name || 'File';
            if (at.attachment_type === 'image') {
                attachmentsHtml += '<img src="' + url + '" class="activity-post-attachment-image" onclick="openFilePreview(\'' + url + '\',\'' + name + '\')">';
            } else {
                attachmentsHtml += '<a href="#" class="activity-post-attachment" onclick="openFilePreview(\'' + url + '\',\'' + name + '\');return false"><i class="fa fa-file"></i> ' + name + '</a>';
            }
        });
    }
    var taskBadge = '';
    if (a.task) {
        taskBadge = '<span class="status-badge status-active" style="font-size:10px;padding:1px 8px;margin-left:8px"><i class="fa fa-tasks" style="font-size:9px"></i> Task #' + a.task.id + '</span>';
    }

    var replyCount = (a.replies && a.replies.length) ? a.replies.length : 0;
    var repliesSectionId = 'replies-section-' + a.id;
    var replyFormId = 'reply-form-' + a.id;
    var replyFileId = 'reply-file-' + a.id;

    var repliesHtml = '';
    if (replyCount > 0) {
        var displayStyle = replyCount > 2 ? 'style="display:none"' : '';
        repliesHtml = '<div class="activity-replies" id="' + repliesSectionId + '" ' + displayStyle + '>';
        a.replies.forEach(function(r) {
            var rAvatar = r.user ? (r.user.username || '?').substring(0, 2).toUpperCase() : '??';
            var rTime = r.created_at ? moment(r.created_at).fromNow() : '—';
            var rAttach = '';
            if (r.attachments && r.attachments.length > 0) {
                r.attachments.forEach(function(at) {
                    var u = at.attachment_url || at.attachment_path;
                    var n = at.attachment_name || 'File';
                    if (at.attachment_type === 'image') {
                        rAttach += '<img src="' + u + '" class="activity-post-attachment-image" onclick="openFilePreview(\'' + u + '\',\'' + n + '\')" style="width:60px;height:60px;display:inline-block;margin-right:4px">';
                    } else {
                        rAttach += '<a href="#" class="activity-post-attachment" onclick="openFilePreview(\'' + u + '\',\'' + n + '\');return false" style="display:inline-flex;margin-right:4px"><i class="fa fa-file"></i> ' + n + '</a>';
                    }
                });
                rAttach = '<div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap">' + rAttach + '</div>';
            }
            repliesHtml += '<div class="activity-reply" id="activity-' + r.id + '">' +
                '<div class="activity-reply-avatar">' + rAvatar + '</div>' +
                '<div class="activity-reply-body">' +
                '<div class="activity-reply-header"><span class="activity-reply-author">' + (r.user ? r.user.username : '—') + '</span><span class="activity-reply-time">' + rTime + '</span></div>' +
                '<div class="activity-reply-content">' + renderMentions(r.content || '') + '</div>' + rAttach +
                '</div></div>';
        });
        repliesHtml += '</div>';
    }

    var replyToggle = '';
    if (replyCount > 0) {
        var toggleLabel = replyCount > 2 ? 'Lihat ' + replyCount + ' balasan' : 'Sembunyikan balasan';
        replyToggle = '<button class="activity-reply-toggle" onclick="toggleReplies(' + a.id + ')"><i class="fa fa-comments"></i> ' + toggleLabel + '</button>';
    }

    var replyFormHtml = '<div class="activity-reply-form" id="' + replyFormId + '" style="display:none">' +
        '<div class="reply-input-row">' +
        '<input type="text" placeholder="Tulis balasan..." id="reply-input-' + a.id + '" onkeydown="if(event.key==\'Enter\'&&!(typeof mentionActive!==\'undefined\'&&mentionActive)){event.preventDefault();replyToActivity(' + a.id + ')}">' +
        '<div class="reply-actions">' +
        '<button type="button" title="Lampirkan file" onclick="$(\'#' + replyFileId + '\').click()"><i class="fa fa-paperclip"></i></button>' +
        '<button type="button" title="Kirim" onclick="replyToActivity(' + a.id + ')" style="color:var(--accent)"><i class="fa fa-paper-plane"></i></button>' +
        '</div></div>' +
        '<input type="file" id="' + replyFileId + '" style="display:none" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" multiple onchange="handleReplyFiles(' + a.id + ')">' +
        '<div class="reply-file-previews" id="reply-previews-' + a.id + '"></div>' +
        '<div class="mention-suggestions reply-mention-suggestions" id="mention-suggestions-reply-' + a.id + '"></div></div>';

    var html = '<div class="activity-post" id="activity-' + a.id + '">' +
        '<div class="activity-post-avatar">' + avatar + '</div>' +
        '<div class="activity-post-body">' +
        '<div class="activity-post-header">' +
        '<span class="activity-post-author">' + (a.user ? a.user.username : '—') + '</span>' +
        '<span class="activity-post-time">' + time + '</span>' +
        taskBadge +
        '</div>' +
        '<div class="activity-post-content">' + renderMentions(a.content || '') + '</div>' +
        (attachmentsHtml ? '<div class="activity-post-attachments">' + attachmentsHtml + '</div>' : '') +
        '<div class="activity-post-actions">' +
        '<button onclick="$(\'#' + replyFormId + '\').toggle();if($(\'#' + replyFormId + '\').is(\':visible\'))$(\'#reply-input-' + a.id + '\').focus()"><i class="fa fa-reply"></i> Balas</button>' +
        replyToggle +
        '</div>' +
        repliesHtml +
        replyFormHtml +
        '</div></div>';
    return html;
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

var replyFileState = {};

function handleReplyFiles(activityId) {
    var input = $('#reply-file-' + activityId)[0];
    if (!input || !input.files.length) return;
    if (!replyFileState[activityId]) replyFileState[activityId] = [];
    Array.from(input.files).forEach(function(file) {
        if (replyFileState[activityId].length >= 10) { toastr.error('Maksimum 10 file.'); return; }
        var exists = replyFileState[activityId].some(function(f) { return f.name === file.name && f.size === file.size; });
        if (!exists) replyFileState[activityId].push(file);
    });
    input.value = '';
    renderReplyPreviews(activityId);
}

function renderReplyPreviews(activityId) {
    var files = replyFileState[activityId] || [];
    var $container = $('#reply-previews-' + activityId);
    if (files.length === 0) { $container.empty(); return; }
    var html = '';
    files.forEach(function(file, idx) {
        var thumb = '';
        if (file.type.startsWith('image/')) {
            (function(i) {
                var reader = new FileReader();
                reader.onload = function(e) { $('.reply-chip-img-' + activityId + '-' + i).attr('src', e.target.result); };
                reader.readAsDataURL(file);
            })(idx);
            thumb = '<img class="reply-chip-img-' + activityId + '-' + idx + '" src="">';
        } else {
            thumb = '<i class="fa fa-file"></i>';
        }
        html += '<div class="reply-file-chip">' + thumb + '<span class="chip-name">' + escapeHtml(file.name) + '</span><span class="chip-close" onclick="removeReplyFile(' + activityId + ',' + idx + ')">&times;</span></div>';
    });
    $container.html(html);
}

function removeReplyFile(activityId, idx) {
    if (replyFileState[activityId]) { replyFileState[activityId].splice(idx, 1); renderReplyPreviews(activityId); }
}

function toggleReplies(activityId) {
    var section = $('#replies-section-' + activityId);
    var count = section.find('.activity-reply').length;
    section.toggle();
    var btns = $('.activity-reply-toggle');
    btns.each(function() {
        if ($(this).attr('onclick') && $(this).attr('onclick').indexOf('toggleReplies(' + activityId + ')') !== -1) {
            $(this).html(section.is(':visible') ? '<i class="fa fa-comments"></i> Sembunyikan balasan' : '<i class="fa fa-comments"></i> Lihat ' + count + ' balasan');
        }
    });
}

function replyToActivity(activityId) {
    var input = $('#reply-input-' + activityId);
    var content = input.val().trim();
    var files = replyFileState[activityId] || [];
    if (!content && files.length === 0) return;

    var $form = $('#reply-form-' + activityId);
    $form.addClass('loading');
    var $sendBtn = $form.find('.reply-actions button[onclick*="replyToActivity"]');
    $sendBtn.html('<i class="fa fa-spinner fa-spin"></i>');

    var formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('content', content || '');
    formData.append('reply_to_id', activityId);
    files.forEach(function(file) { formData.append('attachments[]', file); });

    $.ajax({
        url: activityStoreUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function() {
            input.val('');
            replyFileState[activityId] = [];
            $('#reply-previews-' + activityId).empty();
            loadActivities();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Gagal mengirim balasan.');
        },
        complete: function() {
            $form.removeClass('loading');
            $sendBtn.html('<i class="fa fa-paper-plane"></i>');
        }
    });
}

$(document).on('click', '#btn-post-activity', function() {
    var $btn = $(this);
    var content = $('#activity-input').val().trim();
    if (!content) { toastr.error('Tulis aktivitas terlebih dahulu.'); return; }
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
    $('.activity-form-card').addClass('loading');

    var formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('content', content);
    if (activitySelectedFiles.length > 0) {
        activitySelectedFiles.forEach(function(file) {
            formData.append('attachments[]', file);
        });
    }

    $.ajax({
        url: activityStoreUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            $('#activity-input').val('');
            activitySelectedFiles = [];
            $('#activity-file-previews').empty();
            $('#activity-file-name').text('');
            loadActivities();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Gagal posting.');
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Post');
            $('.activity-form-card').removeClass('loading');
        }
    });
});

var activitySelectedFiles = [];

$(document).on('change', '#activity-file', function() {
    var files = Array.from(this.files);
    files.forEach(function(file) {
        if (activitySelectedFiles.length >= 10) { toastr.error('Maksimum 10 file.'); return; }
        var exists = activitySelectedFiles.some(function(f) { return f.name === file.name && f.size === file.size; });
        if (!exists) activitySelectedFiles.push(file);
    });
    this.value = '';
    renderActivityFilePreviews();
});

function renderActivityFilePreviews() {
    var $container = $('#activity-file-previews');
    if (activitySelectedFiles.length === 0) { $container.empty(); $('#activity-file-name').text(''); return; }
    var html = '';
    activitySelectedFiles.forEach(function(file, idx) {
        var thumb = '';
        if (file.type.startsWith('image/')) {
            (function(i) {
                var reader = new FileReader();
                reader.onload = function(e) { $('.act-chip-img-' + i).attr('src', e.target.result); };
                reader.readAsDataURL(file);
            })(idx);
            thumb = '<img class="act-chip-img-' + idx + '" src="">';
        } else {
            thumb = '<i class="fa fa-file"></i>';
        }
        html += '<div class="reply-file-chip">' + thumb + '<span class="chip-name">' + escapeHtml(file.name) + '</span><span class="chip-close" onclick="removeActivityFile(' + idx + ')">&times;</span></div>';
    });
    $container.html(html);
    $('#activity-file-name').text(activitySelectedFiles.length + ' file(s) selected');
}

function removeActivityFile(idx) {
    activitySelectedFiles.splice(idx, 1);
    renderActivityFilePreviews();
}

function openFilePreview(url, name) {
    var ext = name ? name.split('.').pop().toLowerCase() : '';
    var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
    var videoExts = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
    $('#filePreviewBody').empty();
    if (ext === 'pdf') {
        $('#filePreviewTitle').text(name || 'PDF Preview');
        $('#filePreviewBody').html('<iframe src="' + url + '" style="width:100%;height:85vh;border:none;border-radius:6px"></iframe>');
        $('#filePreviewFooter').html('<button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Tutup</button><a href="' + url + '" class="btn btn-primary btn-sm" download><i class="fa fa-download me-1"></i>Download</a>');
    } else if (videoExts.indexOf(ext) !== -1) {
        $('#filePreviewTitle').text(name || 'Video Preview');
        $('#filePreviewBody').html('<video src="' + url + '" controls style="max-width:100%;max-height:80vh;border-radius:6px;display:block;margin:0 auto"></video>');
        $('#filePreviewFooter').html('<button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Tutup</button><a href="' + url + '" class="btn btn-primary btn-sm" download><i class="fa fa-download me-1"></i>Download</a>');
    } else if (imageExts.indexOf(ext) !== -1) {
        $('#filePreviewTitle').text(name || 'Image Preview');
        $('#filePreviewBody').html('<img src="' + url + '" alt="' + name + '" style="max-width:100%;max-height:80vh;border-radius:6px;display:block;margin:0 auto">');
        $('#filePreviewFooter').html('<button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Tutup</button><a href="' + url + '" class="btn btn-primary btn-sm" download><i class="fa fa-download me-1"></i>Download</a>');
    } else {
        $('#filePreviewTitle').text(name || 'File Preview');
        $('#filePreviewBody').html(
            '<div style="padding:40px 20px;text-align:center">' +
            '<i class="fa fa-file" style="font-size:56px;color:var(--text-muted);display:block;margin-bottom:16px"></i>' +
            '<p style="font-size:15px;font-weight:600;color:var(--text-primary)">' + (name || 'File') + '</p>' +
            '<p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">File akan diunduh secara otomatis</p></div>'
        );
        $('#filePreviewFooter').html('<button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Tutup</button><a href="' + url + '" class="btn btn-primary btn-sm" download><i class="fa fa-download me-1"></i>Download</a>');
    }
    new bootstrap.Modal('#filePreviewModal').show();
}

function toggleTaskSection(header) {
    header.closest('.task-form-section').classList.toggle('open');
}

function toggleCard(el) {
    $(el).closest('.card-collapsible').toggleClass('collapsed');
}

function openCreateTaskModal() {
    $('#task-form')[0].reset();
    document.getElementById('taskModalTitle').textContent = 'Add Task';
    $('#task-assignees').val(null).trigger('change');
    $('#task-handling-division-container').hide();
    $('#task-handling-division').val('');
    new bootstrap.Modal(document.getElementById('taskModal')).show();
}

$(document).on('click', '#btn-save-opportunity-task', function() {
    var $btn = $(this);
    var title = $('#task-title').val().trim();
    var category = $('#task-category-id').val();
    var dueDate = $('#task-due-date').val();
    if (!title) { toastr.error('Title wajib diisi.'); return; }
    if (!category) { toastr.error('Category wajib diisi.'); return; }
    if (!dueDate) { toastr.error('Due Date wajib diisi.'); return; }

    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Creating...');

    $.ajax({
        url: taskStoreUrl,
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            title: title,
            description: $('#task-description').val(),
            category_id: category,
            handling_division_id: $('#task-handling-division').val(),
            due_date: dueDate,
            time: $('#task-time').val(),
            assignees: $('#task-assignees').val(),
            alert_type: $('#task-alert-type').val(),
            alert_target: $('#task-alert-target').val(),
            alert_time: $('#task-alert-time').val()
        },
        success: function(res) {
            toastr.success(res.message);
            bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
            loadActivities();
            loadTasks();
        },
        error: function(xhr) {
            var errors = xhr.responseJSON?.errors;
            if (errors) {
                var first = Object.values(errors)[0];
                toastr.error(Array.isArray(first) ? first[0] : first);
            } else {
                toastr.error(xhr.responseJSON?.message || 'Gagal menambah task.');
            }
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Add Task');
        }
    });
});

function loadTasks() {
    $.get(taskFetchUrl, function(res) {
        var html = '';
        if (res.length > 0) {
            res.forEach(function(t) {
                var iconBg = t.status === 'done' ? 'background:#d1fae5;color:#059669' : t.status === 'in_progress' ? 'background:#dbeafe;color:#2563eb' : 'background:#f1f5f9;color:var(--text-muted)';
                var statusClass = t.status === 'done' ? 'status-active' : t.status === 'in_progress' ? 'status-pending' : 'status-inactive';
                var statusLabel = t.status === 'todo' ? 'To Do' : t.status === 'in_progress' ? 'In Progress' : t.status === 'waiting_approval' ? 'Waiting' : 'Done';
                var assigneeName = (t.assignees && t.assignees.length > 0) ? t.assignees.map(function(a) { return a.username; }).join(', ') : '—';
                var creatorName = t.creator ? t.creator.username : '—';
                var categoryName = t.category ? t.category.name : '—';
                var dueLabel = t.due_date ? new Date(t.due_date).toISOString().split('T')[0] : '—';
                var dueStyle = (t.status !== 'done' && t.due_date && new Date(t.due_date) < new Date(new Date().toDateString())) ? 'color:#dc3545;font-weight:600' : '';
                html += '<div class="task-item" onclick="window.location.href=\'/task-planner/' + t.id + '?back=opportunity-' + opportunityId + '\'" style="cursor:pointer">' +
                    '<div class="task-item-icon" style="' + iconBg + '"><i class="fa fa-tasks"></i></div>' +
                    '<div class="task-item-body">' +
                    '<div class="task-item-title">' + escapeHtml(t.title) + '</div>' +
                    '<div class="task-item-meta"><i class="fa fa-user"></i> ' + escapeHtml(assigneeName) + ' · <i class="fa fa-user-pen"></i> ' + escapeHtml(creatorName) + '</div>' +
                    '<div class="task-item-meta" style="margin-top:2px"><i class="fa fa-folder"></i> ' + escapeHtml(categoryName) + ' · <i class="fa fa-calendar" style="' + dueStyle + '"></i> <span style="' + dueStyle + '">' + dueLabel + '</span></div>' +
                    '</div>' +
                    '<span class="task-item-status ' + statusClass + '">' + statusLabel + '</span>' +
                    '</div>';
            });
        } else {
            html = '<div class="empty-state"><i class="fa fa-tasks"></i><p>Belum ada task.</p></div>';
        }
        $('#task-list').html(html);
    }).fail(function() {
        $('#task-list').html('<div style="text-align:center;padding:20px;color:var(--danger)">Gagal memuat task.</div>');
    });
}

var mentionActive = false;
var mentionStart = -1;
var mentionQuery = '';
var mentionResults = [];
var mentionIndex = 0;
var mentionTextarea = null;

$(document).on('keydown keyup', '#activity-input, .activity-reply-form input[type="text"]', function(e) {
    mentionTextarea = this;
    if (e.type === 'keydown' && mentionActive) {
        if (['ArrowDown', 'ArrowUp', 'Enter', 'Escape', 'Tab'].includes(e.key)) {
            e.preventDefault(); e.stopImmediatePropagation();
            if (e.key === 'ArrowDown') { mentionIndex = (mentionIndex + 1) % mentionResults.length; updateMentionActive(); return; }
            if (e.key === 'ArrowUp') { mentionIndex = (mentionIndex - 1 + mentionResults.length) % mentionResults.length; updateMentionActive(); return; }
            if (e.key === 'Enter') { if (mentionResults[mentionIndex]) selectMention(mentionResults[mentionIndex].username); return; }
            if (e.key === 'Escape') { mentionActive = false; $('.mention-suggestions').hide(); return; }
            if (e.key === 'Tab') { if (mentionResults[0]) selectMention(mentionResults[0].username); return; }
        }
    }
    if (e.type === 'keyup' && !['ArrowDown','ArrowUp','Enter','Escape','Tab'].includes(e.key)) { handleMentionTrigger.call(this); }
});

function handleMentionTrigger() {
    var cursorPos = this.selectionStart;
    var text = this.value;
    var atIdx = text.lastIndexOf('@', cursorPos - 1);
    if (atIdx !== -1 && (atIdx === 0 || text[atIdx - 1] === ' ' || text[atIdx - 1] === '\n')) {
        var query = text.substring(atIdx + 1, cursorPos);
        if (!query.includes(' ') && query.length >= 1) {
            mentionActive = true;
            mentionStart = atIdx;
            mentionQuery = query;
            searchMentions(query, this);
            return;
        }
    }
    mentionActive = false;
    $('.mention-suggestions').hide();
    mentionIndex = 0;
}

function searchMentions(query, input) {
    $.get('/users/search', { q: query }, function(res) {
        if (res.results && res.results.length > 0) {
            mentionResults = res.results;
            mentionIndex = 0;
            var html = '';
            res.results.forEach(function(user, i) {
                var initials = user.initials || '?';
                html += '<div class="mention-suggestion-item" onclick="selectMention(\'' + user.username + '\')" onmouseenter="mentionIndex=' + i + ';updateMentionActive()">' +
                    '<div class="mention-suggestion-avatar">' + initials + '</div>' +
                    '<span class="mention-suggestion-name">' + user.username + '</span></div>';
            });
            var dropdownId = $(input).attr('id') === 'activity-input' ? '#mention-suggestions' : '#mention-suggestions-reply-' + $(input).attr('id').replace('reply-input-', '');
            $(dropdownId).html(html).show();
        } else {
            $('.mention-suggestions').hide();
        }
    });
}

function updateMentionActive() {
    var dropdownId = $(mentionTextarea).attr('id') === 'activity-input' ? '#mention-suggestions' : '#mention-suggestions-reply-' + $(mentionTextarea).attr('id').replace('reply-input-', '');
    $(dropdownId + ' .mention-suggestion-item').removeClass('active').eq(mentionIndex).addClass('active');
}

function selectMention(username) {
    var $ta = $(mentionTextarea);
    var text = $ta.val();
    var cursorPos = $ta[0].selectionStart;
    var atIdx = text.lastIndexOf('@', cursorPos - 1);
    if (atIdx === -1 || (atIdx > 0 && text[atIdx - 1] !== ' ' && text[atIdx - 1] !== '\n')) atIdx = mentionStart;
    if (atIdx === -1) { mentionActive = false; return; }
    var before = text.substring(0, atIdx);
    var after = text.substring(cursorPos);
    $ta.val(before + '@' + username + ' ' + after);
    $('.mention-suggestions').hide();
    mentionActive = false;
    mentionStart = -1;
    $ta.focus();
}

function renderMentions(text) {
    return escapeHtml(text || '').replace(/(^|\s)@([a-zA-Z0-9_\.]+)/g, '$1<span class="mention-tag">@$2</span>');
}

loadActivities();
loadTasks();
scrollToMentionedActivity();
$(window).on('hashchange', function() {
    if (window.location.hash && window.location.hash.startsWith('#activity-')) {
        loadActivities();
    } else {
        scrollToMentionedActivity();
    }
});

function scrollToMentionedActivity() {
    if (window.location.hash && window.location.hash.startsWith('#activity-')) {
        scrollToTarget(window.location.hash.replace('#activity-', ''));
        return;
    }
    var targetId = sessionStorage.getItem('mention_target');
    if (targetId) {
        sessionStorage.removeItem('mention_target');
        scrollToTarget(targetId);
    }
}

function scrollToTarget(id) {
    var attempts = 0;
    var checkExist = setInterval(function() {
        var el = document.getElementById('activity-' + id);
        if (!el) el = document.querySelector('[id="activity-' + id + '"]');
        if (el) {
            clearInterval(checkExist);
            var repliesSection = el.closest('.activity-replies');
            if (repliesSection && repliesSection.style.display === 'none') {
                repliesSection.style.display = 'block';
                var toggleBtn = el.closest('.activity-post-body').querySelector('.activity-reply-toggle');
                if (toggleBtn) {
                    var count = repliesSection.querySelectorAll('.activity-reply').length;
                    toggleBtn.innerHTML = '<i class="fa fa-comments"></i> Sembunyikan balasan';
                }
            }
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('highlight-flash');
            setTimeout(function() { el.classList.remove('highlight-flash'); }, 2200);
        }
        if (++attempts > 50) clearInterval(checkExist);
    }, 150);
}
</script>
@endsection
