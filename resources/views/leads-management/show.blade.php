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

    /* ── Activity Feed ── */
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

    /* ── Task Form (modal) ── */
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

    /* ── File Preview Modal ── */
    #filePreviewBody img { max-width: 100%; max-height: 80vh; border-radius: 6px; display: block; margin: 0 auto; }
    #filePreviewBody iframe { width: 100%; height: 85vh; border: none; border-radius: 6px; }
    #filePreviewModal .modal-dialog { max-width: 90vw; }
    #filePreviewModal .modal-body { padding: 8px; }

    /* ── Reply file previews ── */
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

    /* ── Loading States ── */
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

    /* ── Mention Suggestions ── */
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

    /* ── Highlight Flash ── */
    .highlight-flash {
        animation: highlightFlash 5s ease-out;
    }
    @keyframes highlightFlash {
        0% { background: rgba(16,185,129,0.25); }
        100% { background: transparent; }
    }

    /* ── Responsive ── */
    @media (max-width: 767.98px) {
        .lead-header { flex-direction: column; }
        .lead-header__identity { flex-wrap: wrap; }
        .lead-header__name { font-size: 17px; }
        .lead-header__meta { gap: 12px; font-size: 12px; }

        .activity-feed { max-height: 70vh; }
        .activity-post-avatar { width: 30px; height: 30px; font-size: 11px; }
        .activity-post-content { font-size: 12px; }
        .activity-post-attachment-image { width: 60px; height: 60px; }

        .info-table td:first-child { width: 100px; font-size: 11px; padding-right: 8px; }
        .info-table td:last-child { font-size: 12px; }

        .lead-path__link { padding: 8px 14px 8px 18px; min-height: 36px; }
        .lead-path__stage { width: 18px; height: 18px; font-size: 10px; }
        .lead-path__title { font-size: 10px; }

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
    <div class="col-sm-12 col-lg-9">

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
                    <div class="tab-pane fade" id="tab-task" role="tabpanel">
                        <div style="padding:12px 16px 0;display:flex;justify-content:flex-end">
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
    <div class="col-sm-12 col-lg-3">

        <!-- ── Contact Information ── -->
        <div class="card-custom fade-in stagger-1">
            <div class="card-header-custom">
                <span><i class="fa fa-user me-2" style="color:var(--accent)"></i>Contact Information</span>
            </div>
            <div class="card-body-custom" style="padding-top:12px;padding-bottom:12px">
                <div class="table-responsive">
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
        </div>

        <!-- ── Account Information ── -->
        <div class="card-custom fade-in stagger-2 mt-4">
            <div class="card-header-custom">
                <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Account Information</span>
            </div>
            <div class="card-body-custom" style="padding-top:12px;padding-bottom:12px">
                <div class="table-responsive">
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
                    <input type="hidden" id="lead-id-for-task" value="{{ $lead->id }}">

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
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-lead-task">
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

// ── Activity Feed ──
const leadId = {{ $lead->id }};
const activityFetchUrl = '/leads-management/' + leadId + '/activities';
const taskFetchUrl = '/leads-management/' + leadId + '/tasks';
const activityStoreUrl = '/leads-management/' + leadId + '/activities';

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
            setTimeout(scrollToMentionedActivity, 100);
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

// ── Reply ──
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

// ── Post Activity ──
$(document).on('click', '#btn-post-activity', function() {
    var $btn = $(this);
    var content = $('#activity-input').val().trim();
    if (!content) { toastr.error('Tulis aktivitas terlebih dahulu.'); return; }
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
    $('.activity-form-card').addClass('loading');

    var formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('content', content);
    var fileInput = $('#activity-file')[0];
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

// ── Multi-file upload for main post ──
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

// ── File Preview ──
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

// ── Task ──
function toggleTaskSection(header) {
    header.closest('.task-form-section').classList.toggle('open');
}

function openCreateTaskModal() {
    $('#task-form')[0].reset();
    document.getElementById('taskModalTitle').textContent = 'Add Task';
    $('#task-assignees').val(null).trigger('change');
    new bootstrap.Modal(document.getElementById('taskModal')).show();
}

$(document).on('click', '#btn-save-lead-task', function() {
    var $btn = $(this);
    var title = $('#task-title').val().trim();
    var category = $('#task-category-id').val();
    var dueDate = $('#task-due-date').val();
    if (!title) { toastr.error('Title wajib diisi.'); return; }
    if (!category) { toastr.error('Category wajib diisi.'); return; }
    if (!dueDate) { toastr.error('Due Date wajib diisi.'); return; }

    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Creating...');

    $.ajax({
        url: '/leads-management/' + leadId + '/tasks',
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            title: title,
            description: $('#task-description').val(),
            category_id: category,
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
                toastr.error(xhr.responseJSON?.message || 'Gagal memAdd Task.');
            }
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Add Task');
        }
    });
});

// ── Task List ──
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
                var dueLabel = t.due_date? new Date(t.due_date).toISOString().split('T')[0] : '—';
                var dueStyle = (t.status !== 'done' && t.due_date && new Date(t.due_date) < new Date(new Date().toDateString())) ? 'color:#dc3545;font-weight:600' : '';
                html += '<div class="task-item" onclick="window.location.href=\'/task-planner/' + t.id + '?back=lead-' + leadId + '\'" style="cursor:pointer">' +
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

// ── Mention Engine ──
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

// ── Init ──
loadActivities();
loadTasks();
scrollToMentionedActivity();
$(window).on('hashchange', scrollToMentionedActivity);

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
