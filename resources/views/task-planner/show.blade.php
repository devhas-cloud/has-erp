@extends('layouts.app')

@section('title', 'Detail Task')
@section('page-title', 'Detail Task')

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

        .assignee-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: var(--accent-soft);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: var(--accent);
            margin: 2px;
        }

        .chat-area {
            display: flex;
            flex-direction: column;
            height: 520px;
            background: #f9fafb;
        }
        .chat-feed {
            flex: 1;
            overflow-y: auto;
            padding: 16px 16px 8px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .chat-feed::-webkit-scrollbar { width: 5px; }
        .chat-feed::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }

        .chat-date-sep {
            text-align: center;
            margin: 14px 0 10px;
            position: relative;
        }
        .chat-date-sep .date-line { position: absolute; left: 0; right: 0; top: 50%; border-top: 1px solid #e5e7eb; }
        .chat-date-sep .date-label {
            position: relative;
            display: inline-block;
            background: #f9fafb;
            padding: 0 12px;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .chat-msg { display: flex; gap: 8px; max-width: 85%; margin-bottom: 2px; align-items: flex-end; }
        .chat-msg.mine { align-self: flex-end; flex-direction: row-reverse; }
        .chat-msg.theirs { align-self: flex-start; }

        .chat-avatar-sm {
            width: 30px; height: 30px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; color: #fff; flex-shrink: 0;
            margin-bottom: 2px;
        }
        .chat-msg.mine .chat-avatar-sm { display: none; }
        .chat-msg.mine + .chat-msg.mine .chat-avatar-sm { display: none; }
        .chat-msg.mine:first-child .chat-avatar-sm,
        .chat-msg.theirs + .chat-msg.mine .chat-avatar-sm { display: flex; }

        .chat-bubble-wrap { display: flex; flex-direction: column; }
        .chat-msg.mine .chat-bubble-wrap { align-items: flex-end; }
        .chat-msg.theirs .chat-bubble-wrap { align-items: flex-start; }
        .chat-sender {
            font-size: 10.5px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 1px;
            padding: 0 6px;
        }
        .chat-msg.theirs:first-child .chat-sender,
        .chat-msg.theirs + .chat-msg.mine + .chat-msg.theirs .chat-sender { display: block; }
        .chat-msg.theirs + .chat-msg.theirs .chat-sender { display: none; }

        .chat-bubble {
            padding: 9px 14px;
            font-size: 13.5px;
            line-height: 1.55;
            word-break: break-word;
            white-space: pre-wrap;
            max-width: 100%;
            position: relative;
        }
        .chat-msg.mine .chat-bubble {
            background: var(--accent);
            color: #fff;
            border-radius: 16px 4px 16px 16px;
        }
        .chat-msg.theirs .chat-bubble {
            background: #fff;
            color: var(--text-primary);
            border-radius: 4px 16px 16px 16px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .chat-bubble-time {
            font-size: 10px;
            margin-top: 1px;
            padding: 0 6px;
            opacity: 0.5;
        }
        .chat-msg.mine .chat-bubble-time { text-align: right; }
        .chat-msg.theirs .chat-bubble-time { text-align: left; }

        .chat-img { margin-top: 4px; }
        .chat-img img {
            max-width: 260px; max-height: 200px; border-radius: 10px;
            cursor: pointer; display: block;
        }
        .chat-msg.mine .chat-img img { border: 2px solid rgba(255,255,255,0.3); }
        .chat-msg.theirs .chat-img img { border: 1px solid #e5e7eb; }
        .chat-file-link {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 12px; background: rgba(255,255,255,0.15);
            border-radius: 8px; font-size: 12px; text-decoration: none;
            margin-top: 4px;
        }
        .chat-msg.mine .chat-file-link { color: #fff; background: rgba(255,255,255,0.2); }
        .chat-msg.theirs .chat-file-link {
            color: var(--text-primary); background: #f1f5f9; border: 1px solid #e5e7eb;
        }

        .chat-composer-bar {
            display: flex; gap: 8px; align-items: flex-end;
            padding: 10px 14px;
            background: #fff;
            border-top: 1px solid #e5e7eb;
        }
        .chat-composer-bar textarea {
            flex: 1; border: 1px solid #e5e7eb; border-radius: 20px;
            padding: 8px 14px; font-size: 13.5px; font-family: inherit;
            resize: none; min-height: 38px; max-height: 120px; overflow-y: auto;
            line-height: 1.4; color: var(--text-primary);
        }
        .chat-composer-bar textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .chat-composer-bar textarea::placeholder { color: #9ca3af; }

        .chat-attach-btn {
            width: 38px; height: 38px; border-radius: 50%; border: none;
            background: #f1f5f9; color: var(--text-muted); cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-size: 15px;
            flex-shrink: 0; transition: all 0.15s;
        }
        .chat-attach-btn:hover { background: #e5e7eb; color: var(--accent); }
        .chat-send-btn {
            width: 38px; height: 38px; border-radius: 50%; border: none;
            background: var(--accent); color: #fff; cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-size: 14px;
            flex-shrink: 0; transition: all 0.15s;
        }
        .chat-send-btn:hover { filter: brightness(1.1); }
        .chat-send-btn:disabled { opacity: 0.4; cursor: not-allowed; filter: none; }

        .chat-file-chip-bar {
            display: flex; align-items: center; gap: 6px;
            padding: 4px 10px 4px 4px;
            background: #f1f5f9; border-radius: 16px; font-size: 11px;
            margin-bottom: 6px; max-width: 220px;
        }
        .chat-file-chip-bar img { width: 24px; height: 24px; border-radius: 5px; object-fit: cover; }
        .chat-file-chip-bar .chip-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
        .chat-file-chip-bar .chip-remove { cursor: pointer; color: #9ca3af; flex-shrink: 0; }
        .chat-file-chip-bar .chip-remove:hover { color: #dc3545; }

        .chat-empty {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            height: 100%; text-align: center; color: var(--text-muted); gap: 8px;
        }
        .chat-empty i { font-size: 44px; opacity: 0.15; }
        .chat-empty p { font-size: 14px; margin: 0; font-weight: 500; }
        .chat-empty .sub { font-size: 12px; opacity: 0.5; }

        .chat-skeleton { display: flex; gap: 8px; padding: 6px 0; align-items: flex-end; }
        .chat-skeleton.mine { flex-direction: row-reverse; align-self: flex-end; }
        .chat-skeleton .skel-bubble {
            height: 40px; width: 180px; border-radius: 16px 4px 16px 16px;
            background: #e5e7eb; animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; }
        }

        #filePreviewBody img { max-width: 100%; max-height: 75vh; border-radius: 6px; display: block; margin: 0 auto; }
        #filePreviewBody iframe { width: 100%; height: 70vh; border: none; border-radius: 4px; }
        #filePreviewModal .modal-dialog { max-width: 90vw; }
        #filePreviewModal .modal-body { padding: 8px; }
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
                        <span><i class="fa fa-building me-1"></i> {{ $task->division?->division_name ?? 'Global' }}</span>
                        <span><i class="fa fa-calendar me-1"></i> Start: {{ $task->start_date->format('d M Y H:i') }}</span>
                        <span><i class="fa fa-calendar-check me-1"></i> Due:
                            {{ $task->due_date->format('d M Y H:i') }}</span>
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
                            <div class="chat-area" id="chat-area">
                                <div class="chat-feed" id="activity-feed"></div>
                                <div class="chat-composer-bar">
                                    <button type="button" class="chat-attach-btn" id="btn-attach-file" title="Lampirkan">
                                        <i class="fa fa-paperclip"></i>
                                    </button>
                                    <textarea id="activity-content" placeholder="Tulis pesan..." rows="1"></textarea>
                                    <button type="button" class="chat-send-btn" id="btn-post-activity" title="Kirim">
                                        <i class="fa fa-paper-plane"></i>
                                    </button>
                                    <input type="file" id="activity-file-input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip" multiple style="display:none">
                                </div>
                                <div id="chat-file-chip" style="display:none;padding:0 14px 6px;background:#fff"></div>
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

                    @php $isCreator = $task->creator_id === Auth::id(); @endphp
                    @if ($task->status !== 'done')
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
                title: 'Approve Task?',
                text: 'Status akan berubah menjadi Done.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, approve',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
            }).then(function(result) {
                if (result.isConfirmed) {
                    $btn.prop('disabled', true).html(
                        '<i class="fa fa-spinner fa-spin me-1"></i> Approving...');
                    $.ajax({
                        url: '{{ route('task-planner.index') }}/' + id + '/approve',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(res) {
                            toastr.success(res.message);
                            location.reload();
                        },
                        error: function(xhr) {
                            $btn.prop('disabled', false).html(
                                '<i class="fa fa-check"></i><span>Approve</span>');
                            toastr.error(xhr.responseJSON?.message || 'Gagal approve.');
                        }
                    });
                }
            });
        });

        $(document).on('click', '.btn-transition', function() {
            var id = $(this).data('id');
            var status = $(this).data('status');
            var $btn = $(this);
            var label = status === 'in_progress' ? 'Start Progress?' : 'Mark as Done?';
            Swal.fire({
                title: label,
                text: 'Status task akan berubah.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
            }).then(function(result) {
                if (result.isConfirmed) {
                    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>');
                    $.ajax({
                        url: '{{ route('task-planner.index') }}/' + id + '/transition',
                        method: 'POST',
                        data: {
                            status: status,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(res) {
                            toastr.success(res.message);
                            location.reload();
                        },
                        error: function(xhr) {
                            $btn.prop('disabled', false);
                            toastr.error(xhr.responseJSON?.message || 'Gagal.');
                        }
                    });
                }
            });
        });

        var currentUserId = {{ Auth::id() }};
        var taskId = {{ $task->id }};

        function formatDateLabel(dateStr) {
            var d = new Date(dateStr);
            var now = new Date();
            var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            var yesterday = new Date(today.getTime() - 86400000);
            var msgDate = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            if (msgDate.getTime() === today.getTime()) return 'Hari ini';
            if (msgDate.getTime() === yesterday.getTime()) return 'Kemarin';
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
        }

        function formatTimeLabel(ts) {
            var d = new Date(ts);
            return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
        }

        function loadActivities() {
            var $feed = $('#activity-feed');
            $feed.html(
                '<div class="chat-skeleton"><div class="skel-bubble"></div></div>' +
                '<div class="chat-skeleton mine"><div class="skel-bubble" style="width:120px;border-radius:16px 16px 4px 16px"></div></div>' +
                '<div class="chat-skeleton"><div class="skel-bubble" style="width:200px"></div></div>'
            );

            $.get('{{ route("task-planner.index") }}/' + taskId + '/activities', function(res) {
                var data = res.data || [];

                if (data.length === 0) {
                    $feed.html(
                        '<div class="chat-empty">' +
                        '<i class="fa fa-comments"></i>' +
                        '<p>Belum ada percakapan</p>' +
                        '<p class="sub">Kirim pesan pertama untuk memulai</p>' +
                        '</div>'
                    );
                    return;
                }

                var html = '';
                var lastDate = '';
                var lastUserId = null;

                data.forEach(function(a) {
                    var aDate = a.created_at ? a.created_at.split('T')[0] : '';
                    if (aDate !== lastDate) {
                        html += '<div class="chat-date-sep"><span class="date-line"></span><span class="date-label">' + formatDateLabel(aDate) + '</span></div>';
                        lastDate = aDate;
                        lastUserId = null;
                    }

                    var isMine = a.user_id === currentUserId;
                    var cls = isMine ? 'mine' : 'theirs';
                    var hue = a.user_id ? (a.user_id * 137) % 360 : 210;
                    var bgColor = 'hsl(' + hue + ', 55%, 50%)';

                    var showAvatar = !isMine && lastUserId !== a.user_id;
                    var showName = !isMine && lastUserId !== a.user_id;

                    html += '<div class="chat-msg ' + cls + '">';

                    if (showAvatar) {
                        html += '<div class="chat-avatar-sm" style="background:' + bgColor + '">' + (a.initials || '?') + '</div>';
                    } else if (!isMine) {
                        html += '<div style="width:30px;flex-shrink:0"></div>';
                    }

                    html += '<div class="chat-bubble-wrap">';
                    if (showName) {
                        html += '<div class="chat-sender">' + (a.username || '?') + '</div>';
                    }
                    html += '<div class="chat-bubble">';
                    if (a.attachments && a.attachments.length > 0) {
                        a.attachments.forEach(function(att) {
                            if (att.type === 'image') {
                                html += '<div class="chat-img"><img src="' + att.url + '" onclick="openLightbox(\'' + att.url + '\')" loading="lazy"></div>';
                            } else {
                                html += '<a href="#" class="chat-file-link" onclick="openFileLightbox(\'' + att.url + '\', \'' + (att.name || 'File') + '\');return false;" style="display:block;margin-top:2px"><i class="fa fa-file me-1"></i>' + (att.name || 'File') + '</a>';
                            }
                        });
                    }
                    if (a.content) {
                        if (a.attachments && a.attachments.length > 0) {
                            html += '<div style="margin-top:6px">' + a.content + '</div>';
                        } else {
                            html += a.content;
                        }
                    }
                    html += '</div>';
                    html += '<div class="chat-bubble-time">' + formatTimeLabel(a.created_at || a.timestamp) + '</div>';
                    html += '</div>';
                    html += '</div>';

                    lastUserId = isMine ? null : a.user_id;
                });

                $feed.html(html);
                setTimeout(scrollChatBottom, 100);
            }).fail(function() {
                $feed.html('<div class="chat-empty"><i class="fa fa-exclamation-triangle"></i><p>Gagal memuat.</p></div>');
            });
        }

        function scrollChatBottom() {
            var $feed = $('#activity-feed');
            $feed.scrollTop($feed[0].scrollHeight);
        }

        var $textarea = $('#activity-content');
        $textarea.on('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        $textarea.on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                $('#btn-post-activity').click();
            }
        });

        $('#btn-attach-file').on('click', function() { $('#activity-file-input').click(); });

        var selectedFiles = [];

        $('#activity-file-input').on('change', function() {
            var newFiles = Array.from(this.files);
            newFiles.forEach(function(file) {
                if (selectedFiles.length >= 10) { toastr.error('Maksimum 10 file.'); return; }
                if (selectedFiles.some(function(f) { return f.name === file.name && f.size === file.size; })) return;
                selectedFiles.push(file);
            });
            $(this).val('');
            renderFilePreview();
        });

        function renderFilePreview() {
            var $chip = $('#chat-file-chip');
            if (selectedFiles.length === 0) { $chip.hide().empty(); return; }
            var html = '';
            selectedFiles.forEach(function(file, i) {
                html += '<div class="chat-file-chip-bar">';
                if (file.type.startsWith('image/')) {
                    var reader = new FileReader();
                    reader.onload = (function(idx) { return function(e) { $('.chip-img-' + idx).attr('src', e.target.result); }; })(i);
                    reader.readAsDataURL(file);
                    html += '<img class="chip-img-' + i + '" src="" style="width:24px;height:24px;border-radius:5px;background:#e5e7eb">';
                } else {
                    html += '<span style="width:24px;height:24px;border-radius:5px;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0"><i class="fa fa-file"></i></span>';
                }
                html += '<span class="chip-name">' + file.name + '</span>';
                html += '<span class="chip-remove" data-idx="' + i + '"><i class="fa fa-times"></i></span>';
                html += '</div>';
            });
            $chip.html(html).show();
        }

        $(document).on('click', '.chip-remove', function() {
            var idx = parseInt($(this).data('idx'));
            selectedFiles.splice(idx, 1);
            $('#activity-file-input').val('');
            renderFilePreview();
        });

        $('#btn-post-activity').on('click', function() {
            var content = $textarea.val().trim();
            if (!content && selectedFiles.length === 0) {
                $textarea.focus();
                return;
            }
            var $btn = $(this).prop('disabled', true);

            var formData = new FormData();
            formData.append('content', content);
            formData.append('_token', '{{ csrf_token() }}');
            selectedFiles.forEach(function(file) {
                formData.append('attachments[]', file);
            });

            $.ajax({
                url: '{{ route("task-planner.index") }}/' + taskId + '/activities',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    $textarea.val('').css('height', 'auto');
                    selectedFiles = [];
                    $('#chat-file-chip').hide().empty();
                    loadActivities();
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Gagal mengirim.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });

        $('button[data-bs-target="#tab-activity"]').on('shown.bs.tab', function() { loadActivities(); });

        loadActivities();

        function openLightbox(url) {
            $('#filePreviewTitle').text('Image Preview');
            $('#filePreviewBody').html('<img src="' + url + '" alt="">');
            $('#filePreviewFooter').empty();
            new bootstrap.Modal('#filePreviewModal').show();
        }

        function openFileLightbox(url, name) {
            var ext = name.split('.').pop().toLowerCase();
            var previewable = ['pdf', 'txt', 'html', 'htm', 'csv', 'json', 'xml', 'svg'];
            if (previewable.indexOf(ext) !== -1) {
                window.open(url, '_blank');
                return;
            }
            $('#filePreviewTitle').text(name);
            $('#filePreviewBody').html(
                '<div style="padding:40px 20px;text-align:center">' +
                '<i class="fa fa-file" style="font-size:56px;color:var(--text-muted);display:block;margin-bottom:16px"></i>' +
                '<p style="font-size:15px;font-weight:600;color:var(--text-primary)">' + name + '</p>' +
                '<p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">File tidak dapat ditampilkan di browser</p>' +
                '</div>'
            );
            $('#filePreviewFooter').html('<button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Tutup</button> <a href="' + url + '" class="btn btn-primary btn-sm" download><i class="fa fa-download me-1"></i>Download</a>');
            new bootstrap.Modal('#filePreviewModal').show();
        }
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
