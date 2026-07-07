@extends('layouts.app')

@section('title', 'Detail Task')
@section('page-title', 'Detail Task')

@section('styles')
<style>
    .nav-tabs .nav-link {
        font-size: 13px; font-weight: 600;
        color: var(--text-muted); border: none;
        padding: 10px 18px; border-radius: 0;
    }
    .nav-tabs .nav-link.active {
        color: var(--accent); background: transparent;
        border-bottom: 2px solid var(--accent);
    }
    .nav-tabs .nav-link:hover:not(.active) {
        color: var(--text-primary);
        border-bottom: 2px solid var(--card-border);
    }
    .info-table td:first-child { color: var(--text-muted); width: 140px; font-size: 12.5px; }
    .info-table td:last-child { font-size: 13.5px; color: var(--text-primary); }
    .assignee-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px; background: var(--accent-soft);
        border-radius: 20px; font-size: 12px; font-weight: 600;
        color: var(--accent); margin: 2px;
    }

    /* ── Activity Feed ── */
    .activity-feed { padding: 4px 0; }
    .activity-feed .activity-form-card {
        background: #f8fafc;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        padding: 16px;
        margin-bottom: 20px;
    }
    .activity-feed .activity-form-card textarea {
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
    .activity-feed .activity-form-card textarea:focus {
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
        width: 36px; height: 36px; border-radius: 50%;
        background: var(--accent-soft); color: var(--accent);
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 13px; flex-shrink: 0;
    }
    .activity-post-body { flex: 1; min-width: 0; }
    .activity-post-header {
        display: flex; align-items: center; gap: 8px;
        margin-bottom: 4px; flex-wrap: wrap;
    }
    .activity-post-author { font-weight: 600; font-size: 13px; color: var(--text-primary); }
    .activity-post-time { font-size: 11px; color: var(--text-muted); }
    .activity-post-content {
        font-size: 13px; color: var(--text-secondary);
        line-height: 1.5; margin-bottom: 8px;
        white-space: pre-wrap; word-break: break-word;
    }
    .activity-post-attachments { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
    .activity-post-attachment {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 12px; background: #f1f5f9;
        border-radius: var(--radius-sm); font-size: 12px;
        color: var(--text-secondary); text-decoration: none;
    }
    .activity-post-attachment:hover { background: #e2e8f0; }
    .activity-post-attachment-image {
        width: 80px; height: 80px; object-fit: cover;
        border-radius: var(--radius-sm); cursor: pointer;
        border: 1px solid var(--card-border);
    }
    .activity-post-actions {
        display: flex; align-items: center; gap: 12px; margin-top: 4px;
    }
    .activity-post-actions button {
        background: none; border: none; font-size: 12px;
        font-weight: 500; color: var(--text-muted);
        cursor: pointer; padding: 2px 4px; transition: color .15s;
    }
    .activity-post-actions button:hover { color: var(--accent); }

    .activity-reply-form {
        display: flex; flex-direction: column; gap: 6px;
        margin-top: 10px; padding: 10px 14px;
        background: #f8fafc; border: 1px solid var(--card-border);
        border-radius: var(--radius-sm);
    }
    .activity-reply-form .reply-input-row {
        display: flex; gap: 8px; align-items: center;
    }
    .activity-reply-form input[type="text"] {
        flex: 1; border: 1px solid var(--card-border);
        border-radius: 20px; padding: 6px 14px;
        font-size: 12px; font-family: inherit;
    }
    .activity-reply-form input[type="text"]:focus {
        border-color: var(--accent); outline: none;
    }
    .activity-reply-form .reply-actions { display: flex; gap: 4px; align-items: center; }
    .activity-reply-form .reply-actions button {
        background: none; border: none; font-size: 12px;
        color: var(--text-muted); cursor: pointer;
        padding: 4px 6px; border-radius: var(--radius-sm);
    }
    .activity-reply-form .reply-actions button:hover {
        color: var(--accent); background: #e2e8f0;
    }
    .activity-reply-form .reply-file-name { font-size: 11px; color: var(--text-muted); margin-left: 4px; }
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
    .reply-file-chip img {
        width: 18px; height: 18px;
        border-radius: 3px;
        object-fit: cover;
    }
    .reply-file-chip .chip-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        flex: 1;
    }
    .reply-file-chip .chip-close {
        cursor: pointer;
        color: var(--text-muted);
        font-size: 10px;
        flex-shrink: 0;
    }
    .reply-file-chip .chip-close:hover { color: #dc3545; }
    .activity-reply-toggle-wrapper { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
    .activity-reply-toggle {
        background: none; border: none; font-size: 12px;
        font-weight: 600; color: var(--text-muted); cursor: pointer;
        padding: 4px 10px; border-radius: 20px; transition: all .15s;
    }
    .activity-reply-toggle:hover { color: var(--accent); background: var(--accent-soft); }
    .activity-replies { margin-top: 12px; padding-left: 48px; }
    .activity-reply { display: flex; gap: 10px; margin-bottom: 10px; }
    .activity-reply-avatar {
        width: 28px; height: 28px; border-radius: 50%;
        background: #e2e8f0; color: var(--text-muted);
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: 10px; flex-shrink: 0;
    }
    .activity-reply-body { flex: 1; }
    .activity-reply-header { display: flex; align-items: center; gap: 6px; margin-bottom: 2px; }
    .activity-reply-author { font-weight: 600; font-size: 12px; color: var(--text-primary); }
    .activity-reply-time { font-size: 10px; color: var(--text-muted); }
    .activity-reply-content {
        font-size: 12px; color: var(--text-secondary);
        line-height: 1.4; white-space: pre-wrap; word-break: break-word;
    }

    #filePreviewBody img { max-width: 100%; max-height: 80vh; border-radius: 6px; display: block; margin: 0 auto; }
    #filePreviewBody iframe { width: 100%; height: 70vh; border: none; border-radius: 4px; }
    #filePreviewModal .modal-dialog { max-width: 90vw; }
    #filePreviewModal .modal-body { padding: 8px; }

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
</style>
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Detail Task</h1>
            <p class="page-header-sub">#{{ $task->id }} — Informasi lengkap tugas</p>
        </div>
        <div class="page-header-actions">
            @if ($canUpdate && $task->creator_id === Auth::id())
                <a href="{{ route('task-planner.edit', $task->id) }}" class="btn-accent">
                    <i class="fa fa-pen"></i><span>Edit</span>
                </a>
            @endif

            <a href="{{ route('task-planner.index') }}" class="btn-ghost">
                <i class="fa fa-arrow-left"></i><span>Kembali</span>
            </a>

        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-9">
            <div class="card-custom fade-in">
                <div class="card-body-custom">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
                        <div>
                            <h5 style="margin:0;font-weight:700;font-size:18px;letter-spacing:-0.3px">{{ $task->title }}
                            </h5>
                            <div style="font-size:13px;color:var(--text-secondary);margin-top:4px">
                                Created by <strong>{{ $task->creator?->username ?? '—' }}</strong>
                            </div>
                        </div>
                        <span
                            class="status-badge
                        @if ($task->status === 'todo') status-pending
                        @elseif($task->status === 'in_progress') style="background:var(--info-soft);color:#1e40af;"
                        @elseif($task->status === 'waiting_approval') style="background:#fef3c7;color:#92400e;"
                        @else status-active @endif
                    ">
                            @if ($task->status === 'todo')
                                To Do
                            @elseif($task->status === 'in_progress')
                                In Progress
                            @elseif($task->status === 'waiting_approval')
                                Waiting Approval
                            @elseif($task->status === 'done')
                                Done
                            @endif
                        </span>
                    </div>
                    <div
                        style="display:flex;gap:24px;margin-top:16px;flex-wrap:wrap;font-size:13px;color:var(--text-muted)">
                        <span><i class="fa fa-folder me-1"></i>
                            <span style="font-weight:600">{{ $task->category?->name ?? '—' }}</span>
                        </span>
                        <span><i class="fa fa-building me-1"></i> {{ $task->whatsappGroup?->group_name ?? '—' }}</span>
                        <span><i class="fa fa-calendar-check me-1"></i> Due:
                            {{ $task->due_date->format('d M Y') }}</span>
                        @if ($task->time)
                            <span><i class="fa fa-clock me-1"></i> {{ $task->time }}</span>
                        @endif
                        @if ($task->requires_approval)
                            <span><i class="fa fa-clipboard-check me-1"></i> Requires Approval</span>
                        @endif
                    </div>
                    @if ($task->description)
                        <hr style="margin:16px 0;opacity:0.3">
                        <div style="font-size:13.5px;color:var(--text-secondary);line-height:1.7;white-space:pre-wrap">
                            {{ $task->description }}</div>
                    @endif
                </div>
            </div>

            <div class="card-custom fade-in stagger-1 mt-4">
                <div class="card-header-custom" style="padding:0 22px">
                    <ul class="nav nav-tabs" role="tablist" style="border-bottom:none;margin-bottom:-1px">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-activity"
                                type="button">
                                <i class="fa fa-chart-line me-1"></i> Activity
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-log" type="button">
                                <i class="fa fa-history me-1"></i> Log
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body-custom">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab-activity">
                            <div class="activity-feed" id="activity-feed">

                                <div id="activity-list"></div>
                                <div id="activity-loading" style="text-align:center;padding:20px;color:var(--text-muted)">
                                    <i class="fa fa-spinner fa-spin"></i> Loading...
                                </div>


                                 <div class="activity-form-card">
                                    <textarea id="activity-input" placeholder="Tulis aktivitas..." rows="2"></textarea>
                                    <div class="activity-form-actions">
                                        <div>
                                            <input type="file" id="activity-file" style="display:none" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="$('#activity-file').click()">
                                                <i class="fa fa-paperclip"></i> Attach
                                            </button>
                                            <span id="activity-file-name" style="font-size:12px;color:var(--text-muted);margin-left:8px"></span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-accent" id="btn-post-activity">
                                            <i class="fa fa-paper-plane"></i> Post
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-log">
                            <div class="empty-state">
                                <i class="fa fa-history"></i>
                                <p>Belum ada log perubahan.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card-custom fade-in stagger-1">
                <div class="card-header-custom">
                    <span><i class="fa fa-users me-2" style="color:var(--accent)"></i>Assignees</span>

                    @php
                        $isCreator = $task->creator_id === Auth::id();
                        $isAssignee = $task->assignees->contains('id', Auth::id());
                        $canTransition = $isCreator || $isAssignee;
                    @endphp
                    @if ($task->status !== 'done' && $canTransition)
                        <div style="display:flex;gap:10px;flex-wrap:wrap">
                            @if ($task->status === 'todo')
                                <button type="button" class="btn btn-sm btn-accent btn-transition"
                                    data-id="{{ $task->id }}" data-status="in_progress"
                                    style="background:#2563eb;font-size:12px">
                                    <i class="fa fa-play me-1"></i>Start Progress
                                </button>
                            @endif
                            @if ($task->status === 'in_progress')
                                <button type="button" class="btn btn-sm btn-accent btn-transition"
                                    data-id="{{ $task->id }}" data-status="done"
                                    style="background:#f59e0b;font-size:12px">
                                    <i class="fa fa-check me-1"></i>Mark as Done
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="card-body-custom">
                    @if ($task->assignees->isEmpty())
                        <div style="font-size:13px;color:var(--text-muted)">No assignees</div>
                    @else
                        @foreach ($task->assignees as $assignee)
                            <div class="assignee-badge" style="margin-bottom:6px">
                                <span>{{ strtoupper(substr($assignee->username, 0, 2)) }}</span>
                                <span>{{ $assignee->username }}</span>
                                <small
                                    style="opacity:0.7">({{ optional($assignee->hierarchyRole)->role_name ?? 'N/A' }})</small>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="card-custom fade-in stagger-2 mt-4">
                <div class="card-header-custom">
                    <span><i class="fa fa-bell me-2" style="color:var(--accent)"></i>Alert Configuration</span>
                </div>
                <div class="card-body-custom">
                    <table class="table table-sm table-borderless mb-0 info-table">
                        <tr>
                            <td>Type</td>
                            <td>
                                <strong>
                                    @if ($task->alert_type === 'none')
                                        <span style="color:var(--text-muted)">None</span>
                                    @elseif($task->alert_type === 'email')
                                        <span style="color:#1e40af">Email</span>
                                    @elseif($task->alert_type === 'whatsapp')
                                        <span style="color:#15803d">WhatsApp</span>
                                    @elseif($task->alert_type === 'both')
                                        <span style="color:#7c3aed">Email & WA</span>
                                    @endif
                                </strong>
                            </td>
                        </tr>
                        <tr>
                            <td>Target</td>
                            <td>
                                @if ($task->alert_target === 'personal')
                                    Personal (Japri)
                                @elseif($task->alert_target === 'group')
                                    Group WA Divisi
                                @elseif($task->alert_target === 'both')
                                    Personal + Group
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td>Alert Time</td>
                            <td>{{ $task->alert_time ? $task->alert_time->format('d M Y H:i') : '—' }}</td>
                        </tr>
                        <tr>
                            <td>Sent</td>
                            <td>{!! $task->is_alert_sent
                                ? '<span style="color:var(--success)"><i class="fa fa-check"></i> Yes</span>'
                                : '<span style="color:var(--text-muted)"><i class="fa fa-times"></i> No</span>' !!}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card-custom fade-in stagger-3 mt-4">
                <div class="card-header-custom">
                    <span><i class="fa fa-info-circle me-2" style="color:var(--accent)"></i>Task Details</span>
                </div>
                <div class="card-body-custom">
                    <table class="table table-sm table-borderless mb-0 info-table">
                        <tr>
                            <td>Task ID</td>
                            <td><strong>#{{ $task->id }}</strong></td>
                        </tr>
                        <tr>
                            <td>Category</td>
                            <td>
                                <span style="font-weight:600">
                                    {{ $task->category?->name ?? '—' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Division</td>
                            <td>{{ $task->division?->division_name ?? 'Global' }}</td>
                        </tr>
                        <tr>
                            <td>Created By</td>
                            <td><strong>{{ $task->creator?->username ?? '—' }}</strong></td>
                        </tr>
                        <tr>
                            <td>Approval Required</td>
                            <td>{!! $task->requires_approval
                                ? '<span style="color:#92400e">Yes</span>'
                                : '<span style="color:var(--success)">No</span>' !!}</td>
                        </tr>
                        <tr>
                            <td>Created At</td>
                            <td>{{ $task->created_at->format('d M Y H:i') }}</td>
                        </tr>
                        <tr>
                            <td>Updated At</td>
                            <td>{{ $task->updated_at->format('d M Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).on('click', '.btn-approve-task', function() {
    var id = $(this).data('id');
    var $btn = $(this);
    Swal.fire({
        title: 'Approve Task?', text: 'Status akan berubah menjadi Done.',
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Yes, approve', cancelButtonText: 'Cancel',
        confirmButtonColor: '#10b981', cancelButtonColor: '#64748b',
        reverseButtons: true,
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Approving...');
        $.ajax({
            url: '{{ route('task-planner.index') }}/' + id + '/approve',
            method: 'POST', data: { _token: '{{ csrf_token() }}' },
            success: function(res) { toastr.success(res.message); location.reload(); },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fa fa-check"></i><span>Approve</span>');
                toastr.error(xhr.responseJSON?.message || 'Gagal approve.');
            }
        });
    });
});

$(document).on('click', '.btn-transition', function() {
    var id = $(this).data('id'), status = $(this).data('status'), $btn = $(this);
    var label = status === 'in_progress' ? 'Start Progress?' : 'Mark as Done?';
    Swal.fire({
        title: label, text: 'Status task akan berubah.',
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Ya', cancelButtonText: 'Batal',
        confirmButtonColor: '#10b981', cancelButtonColor: '#64748b',
        reverseButtons: true,
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>');
        $.ajax({
            url: '{{ route('task-planner.index') }}/' + id + '/transition',
            method: 'POST', data: { status: status, _token: '{{ csrf_token() }}' },
            success: function(res) { toastr.success(res.message); location.reload(); },
            error: function(xhr) { $btn.prop('disabled', false); toastr.error(xhr.responseJSON?.message || 'Gagal.'); }
        });
    });
});

// ── Activity Feed ──
var taskId = {{ $task->id }};
var currentUserId = {{ Auth::id() }};
var activitiesBaseUrl = '{{ route('task-planner.index') }}/' + taskId + '/activities';

function loadActivities() {
    $.get(activitiesBaseUrl, function(res) {
        var data = res.data || [];
        var html = '';
        if (data.length > 0) {
            data.forEach(function(a) { html += renderActivity(a); });
        } else {
            html = '<div class="empty-state"><i class="fa fa-chart-line"></i><p>Belum ada aktivitas.</p></div>';
        }
        $('#activity-list').html(html);
        $('#activity-loading').hide();
    }).fail(function() {
        $('#activity-loading').html('<span style="color:var(--danger)">Gagal memuat aktivitas.</span>');
    });
}

function renderActivity(a) {
    var avatar = a.username ? a.username.substring(0, 2).toUpperCase() : '??';
    var time = a.created_at ? moment(a.created_at).fromNow() : '—';
    var attachmentsHtml = '';
    if (a.attachments && a.attachments.length > 0) {
        a.attachments.forEach(function(at) {
            var url = at.url || at.attachment_path;
            var name = at.name || at.attachment_name || 'File';
            if (at.type === 'image' || (at.mime_type && at.mime_type.startsWith('image/'))) {
                attachmentsHtml += '<img src="' + url + '" class="activity-post-attachment-image" onclick="openFilePreview(\'' + url + '\',\'' + name + '\')">';
            } else {
                attachmentsHtml += '<a href="#" class="activity-post-attachment" onclick="openFilePreview(\'' + url + '\',\'' + name + '\');return false"><i class="fa fa-file"></i> ' + name + '</a>';
            }
        });
    }

    // Reply quote
    var quoteHtml = '';
    if (a.reply_to) {
        quoteHtml = '<div style="padding:6px 10px;background:#f1f5f9;border-left:3px solid var(--accent);border-radius:4px;margin-bottom:8px;font-size:12px">' +
            '<div style="font-weight:700;margin-bottom:2px;font-size:11px">↩ ' + (a.reply_to.username || '?') + '</div>' +
            '<div style="opacity:0.7;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + (a.reply_to.content || '') + '</div></div>';
    }

    var isMine = a.user_id === currentUserId;
    var avatarCircle = '<div class="activity-post-avatar" style="' + (isMine ? 'background:var(--accent);color:#fff' : '') + '">' + avatar + '</div>';

    // Replies
    var replyCount = (a.replies && a.replies.length) ? a.replies.length : 0;
    var repliesSectionId = 'replies-section-' + a.id;
    var replyFormId = 'reply-form-' + a.id;
    var replyFileId = 'reply-file-' + a.id;

    var repliesHtml = '';
    if (replyCount > 0) {
        var displayStyle = replyCount > 2 ? 'style="display:none"' : '';
        repliesHtml = '<div class="activity-replies" id="' + repliesSectionId + '" ' + displayStyle + '>';
        a.replies.forEach(function(r) {
            var rAvatar = r.username ? r.username.substring(0, 2).toUpperCase() : '??';
            var rTime = r.created_at ? moment(r.created_at).fromNow() : '—';
            var rAttach = '';
            if (r.attachments && r.attachments.length > 0) {
                r.attachments.forEach(function(at) {
                    var u = at.url || at.attachment_path;
                    var n = at.name || at.attachment_name || 'File';
                    if (at.type === 'image' || (at.mime_type && at.mime_type.startsWith('image/'))) {
                        rAttach += '<img src="' + u + '" class="activity-post-attachment-image" onclick="openFilePreview(\'' + u + '\',\'' + n + '\')" style="width:60px;height:60px">';
                    } else {
                        rAttach += '<a href="#" class="activity-post-attachment" onclick="openFilePreview(\'' + u + '\',\'' + n + '\');return false"><i class="fa fa-file"></i> ' + n + '</a>';
                    }
                });
                rAttach = '<div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap">' + rAttach + '</div>';
            }
            repliesHtml += '<div class="activity-reply">' +
                '<div class="activity-reply-avatar">' + rAvatar + '</div>' +
                '<div class="activity-reply-body">' +
                '<div class="activity-reply-header"><span class="activity-reply-author">' + (r.username || '—') + '</span><span class="activity-reply-time">' + rTime + '</span></div>' +
                '<div class="activity-reply-content">' + escapeHtml(r.content || '') + '</div>' + rAttach +
                '</div></div>';
        });
        repliesHtml += '</div>';
    }

    // Reply toggle
    var replyToggle = '';
    if (replyCount > 0) {
        var toggleLabel = replyCount > 2 ? 'Lihat ' + replyCount + ' balasan' : 'Sembunyikan balasan';
        replyToggle = '<button class="activity-reply-toggle" onclick="toggleReplies(' + a.id + ')"><i class="fa fa-comments"></i> ' + toggleLabel + '</button>';
    }

    // Reply form (di BAWAH replies) — multi-file
    var replyFormHtml = '<div class="activity-reply-form" id="' + replyFormId + '" style="display:none">' +
        '<div class="reply-input-row">' +
        '<input type="text" placeholder="Tulis balasan..." id="reply-input-' + a.id + '">' +
        '<div class="reply-actions">' +
        '<button type="button" title="Lampirkan file" onclick="$(\'#' + replyFileId + '\').click()"><i class="fa fa-paperclip"></i></button>' +
        '<button type="button" title="Kirim" onclick="replyToActivity(' + a.id + ')" style="color:var(--accent)"><i class="fa fa-paper-plane"></i></button>' +
        '</div></div>' +
        '<input type="file" id="' + replyFileId + '" style="display:none" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" multiple onchange="handleReplyFiles(' + a.id + ')">' +
        '<div class="reply-file-previews" id="reply-previews-' + a.id + '"></div></div>';

    var html = '<div class="activity-post" id="activity-' + a.id + '">' +
        avatarCircle +
        '<div class="activity-post-body">' +
        '<div class="activity-post-header">' +
        '<span class="activity-post-author">' + (a.username || '—') + '</span>' +
        '<span class="activity-post-time">' + time + '</span>' +
        '</div>' +
        quoteHtml +
        '<div class="activity-post-content">' + escapeHtml(a.content || '') + '</div>' +
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

// ── Reply to Activity ──
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
        url: activitiesBaseUrl,
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

// ── File Preview Modal ──
function openFilePreview(url, name) {
    var ext = name ? name.split('.').pop().toLowerCase() : '';
    var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
    $('#filePreviewBody').empty();
    if (ext === 'pdf') {
        $('#filePreviewTitle').text(name || 'PDF Preview');
        $('#filePreviewBody').html('<iframe src="' + url + '" style="width:100%;height:85vh;border:none;border-radius:6px"></iframe>');
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
            '<p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">Klik Download untuk membuka file</p></div>'
        );
        $('#filePreviewFooter').html('<button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Tutup</button><a href="' + url + '" class="btn btn-primary btn-sm" download><i class="fa fa-download me-1"></i>Download</a>');
    }
    new bootstrap.Modal('#filePreviewModal').show();
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
    if (fileInput && fileInput.files[0]) {
        formData.append('attachments[]', fileInput.files[0]);
    }

    $.ajax({
        url: activitiesBaseUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            $('#activity-input').val('');
            $('#activity-file').val('');
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

$(document).on('change', '#activity-file', function() {
    var name = this.files[0] ? this.files[0].name : '';
    $('#activity-file-name').text(name);
});

$('button[data-bs-target="#tab-activity"]').on('shown.bs.tab', function() { loadActivities(); });

loadActivities();
</script>
@endsection

@push('modals')
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
