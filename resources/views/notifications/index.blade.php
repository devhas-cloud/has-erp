@extends('layouts.app')

@section('title', 'Notifikasi')
@section('page-title', 'Notifikasi')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header-title">Notifikasi</h1>
        <p class="page-header-sub">Semua notifikasi dan aktivitas anda</p>
    </div>
    <div class="page-header-actions">
        <button type="button" class="btn-ghost" onclick="markAllReadFromPage()">
            <i class="fa fa-check-double"></i>
            <span>Tandai Semua Dibaca</span>
        </button>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-regular fa-bell me-2" style="color:var(--accent)"></i>Daftar Notifikasi</span>
        <span style="font-size:12px;color:var(--text-muted);font-weight:500;">{{ $notifications->total() }} total</span>
    </div>
    <div class="card-body-custom p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:40px"></th>
                        <th style="width:40px"></th>
                        <th>Judul</th>
                        <th>Pesan</th>
                        <th style="width:160px">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notifications as $n)
                    @php
                        $icon = match($n->type) {
                            'task_assigned' => 'fa-tasks',
                            'task_status_changed' => 'fa-arrows-rotate',
                            'task_approval_required' => 'fa-clipboard-check',
                            'task_approved' => 'fa-check-circle',
                            'task_activity' => 'fa-comment',
                            'mention' => 'fa-at',
                            default => 'fa-bell',
                        };
                        $readClass = is_null($n->read_at) ? 'unread' : 'read';
                    @endphp
                    <tr class="notif-row {{ $readClass }}"
                        data-id="{{ $n->id }}"
                        data-type="{{ $n->type }}"
                        data-task-id="{{ $n->data['task_id'] ?? '' }}"
                        data-lead-id="{{ $n->data['lead_id'] ?? '' }}"
                        data-activity-id="{{ $n->data['activity_id'] ?? '' }}"
                        onclick="openNotifFromPage(this)"
                        style="cursor:pointer">
                        <td class="text-center">
                            <span class="notif-row-dot {{ $readClass }}"></span>
                        </td>
                        <td class="text-center">
                            <span class="notif-row-icon">
                                <i class="fa-solid {{ $icon }}"></i>
                            </span>
                        </td>
                        <td>
                            <div class="notif-row-title">{{ $n->title }}</div>
                        </td>
                        <td>
                            <div class="notif-row-body">{{ $n->body ?? '—' }}</div>
                        </td>
                        <td>
                            <div class="notif-row-time">{{ $n->created_at->diffForHumans() }}</div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="fa-regular fa-bell-slash"></i>
                                <p>Tidak ada notifikasi</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($notifications->hasPages())
    <div style="padding:14px 22px;border-top:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <span style="font-size:12.5px;color:var(--text-muted);font-weight:500">
            Menampilkan {{ $notifications->firstItem() }}–{{ $notifications->lastItem() }} dari {{ $notifications->total() }}
        </span>
        <div>{{ $notifications->links() }}</div>
    </div>
    @endif
</div>

<style>
.notif-row.unread {
    background: linear-gradient(90deg, var(--accent-subtle), transparent) !important;
}
.notif-row.unread:hover {
    background: rgba(16, 185, 129, 0.08) !important;
}
.notif-row-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--accent);
    box-shadow: 0 0 8px var(--accent-glow);
    animation: dotPulse 2s ease-in-out infinite;
}
.notif-row-dot.read {
    background: transparent;
    box-shadow: none;
    animation: none;
}
.notif-row-icon {
    display: inline-flex;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    align-items: center;
    justify-content: center;
    background: var(--accent-soft);
    color: var(--accent);
    font-size: 12px;
}
.notif-row-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-primary);
}
.notif-row-body {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 400px;
}
.notif-row-time {
    font-size: 11.5px;
    color: var(--text-muted);
    font-weight: 600;
    white-space: nowrap;
}
</style>

@endsection

@section('scripts')
<script>
    function openNotifFromPage(el) {
        var $el = $(el);
        var notifId = parseInt($el.data('id'));
        var type = $el.data('type') || 'default';
        var taskId = $el.data('task-id') || null;
        var leadId = $el.data('lead-id') || null;
        var activityId = $el.data('activity-id') || null;

        $.post('{{ url('/notifications') }}/' + notifId + '/read', {
            _token: '{{ csrf_token() }}'
        }, function(res) {
            var targetUrl = null;
            if (type === 'mention' && leadId) {
                targetUrl = '{{ url('leads-management') }}/' + leadId;
            } else if (taskId) {
                targetUrl = '{{ url('task-planner') }}/' + taskId;
            }

            if (targetUrl && activityId) {
                targetUrl += '#activity-' + activityId;
            }

            if (targetUrl) {
                window.location.href = targetUrl;
            } else {
                $el.removeClass('unread').addClass('read');
                $el.find('.notif-row-dot').addClass('read');
            }
        }).fail(function(err) {
            console.error('Error marking notification as read:', err);
            toastr.error('Gagal membaca notifikasi');
        });
    }

    function markAllReadFromPage() {
        $.post('{{ route('notifications.read-all') }}', {
            _token: '{{ csrf_token() }}'
        }, function() {
            $('.notif-row').removeClass('unread').addClass('read');
            $('.notif-row-dot').addClass('read');
            toastr.success('Semua notifikasi telah ditandai dibaca');
        }).fail(function() {
            toastr.error('Gagal menandai notifikasi');
        });
    }
</script>
@endsection
