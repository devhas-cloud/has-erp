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
        <span class="notif-total">{{ $notifications->total() }} total</span>
    </div>
    <div class="card-body-custom p-0">
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
        <div class="notif-card {{ $readClass }}"
            data-id="{{ $n->id }}"
            data-type="{{ $n->type }}"
            data-task-id="{{ $n->data['task_id'] ?? '' }}"
            data-lead-id="{{ $n->data['lead_id'] ?? '' }}"
            data-activity-id="{{ $n->data['activity_id'] ?? '' }}"
            onclick="openNotifFromPage(this)">
            <div class="notif-card-left">
                <span class="notif-card-dot {{ $readClass }}"></span>
                <span class="notif-card-icon">
                    <i class="fa-solid {{ $icon }}"></i>
                </span>
            </div>
            <div class="notif-card-body">
                <div class="notif-card-title">{{ $n->title }}</div>
                <div class="notif-card-msg">{{ $n->body ?? '—' }}</div>
                <div class="notif-card-time">
                    <i class="fa-regular fa-clock"></i>
                    {{ $n->created_at->diffForHumans() }}
                </div>
            </div>
        </div>
        @empty
        <div class="empty-state">
            <i class="fa-regular fa-bell-slash"></i>
            <p>Tidak ada notifikasi</p>
        </div>
        @endforelse
    </div>
    @if ($notifications->hasPages())
    <div class="notif-pagination">
        <span class="notif-pagination-info d-sm-none">
            Menampilkan {{ $notifications->firstItem() }}–{{ $notifications->lastItem() }} dari {{ $notifications->total() }}
        </span>
        <div class="notif-pagination-links">{{ $notifications->links('vendor.pagination.bootstrap-5') }}</div>
    </div>
    @endif
</div>

<style>
.notif-total {
    font-size: 12px;
    color: var(--text-muted);
    font-weight: 500;
}

.notif-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 22px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.03);
    cursor: pointer;
    transition: all 0.2s ease;
}

.notif-card:last-child {
    border-bottom: none;
}

.notif-card:hover {
    background: linear-gradient(90deg, var(--accent-subtle), transparent);
}

.notif-card.unread {
    background: linear-gradient(90deg, var(--accent-subtle), transparent);
    border-left: 3px solid var(--accent);
}

.notif-card.unread:hover {
    background: rgba(16, 185, 129, 0.08);
}

.notif-card-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    padding-top: 2px;
}

.notif-card-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--accent);
    box-shadow: 0 0 8px var(--accent-glow);
    animation: dotPulse 2s ease-in-out infinite;
    flex-shrink: 0;
}

.notif-card-dot.read {
    background: transparent;
    box-shadow: none;
    animation: none;
}

.notif-card-icon {
    display: inline-flex;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    align-items: center;
    justify-content: center;
    background: var(--accent-soft);
    color: var(--accent);
    font-size: 13px;
    flex-shrink: 0;
}

.notif-card-body {
    flex: 1;
    min-width: 0;
}

.notif-card-title {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.3;
}

.notif-card-msg {
    font-size: 12.5px;
    color: var(--text-muted);
    margin-top: 3px;
    line-height: 1.4;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.notif-card-time {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 6px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.notif-card-time i {
    font-size: 10px;
    opacity: 0.6;
}

.notif-pagination {
    padding: 14px 22px;
    border-top: 1px solid var(--card-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.notif-pagination-info {
    font-size: 12.5px;
    color: var(--text-muted);
    font-weight: 500;
}

.notif-pagination-links {
    display: flex;
    align-items: center;
}

.notif-pagination-links nav {
    width: 100%;
}

.notif-pagination-links .pagination {
    margin: 0;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .notif-card {
        padding: 14px 16px;
        gap: 12px;
    }

    .notif-card.unread {
        border-left: 3px solid var(--accent);
    }

    .notif-card-icon {
        width: 32px;
        height: 32px;
        font-size: 11px;
        border-radius: 8px;
    }

    .notif-card-title {
        font-size: 13px;
    }

    .notif-card-msg {
        font-size: 12px;
        -webkit-line-clamp: 2;
    }

    .notif-pagination {
        flex-direction: column;
        align-items: stretch;
    }

    .notif-pagination-links nav > div.justify-content-sm-between {
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .notif-pagination-links .pagination {
        justify-content: center;
        flex-wrap: wrap;
        gap: 4px;
    }

    .notif-total {
        font-size: 11px;
    }
}

@media (max-width: 480px) {
    .notif-pagination-links .pagination .page-link {
        min-width: 32px;
        padding: 6px 10px;
        font-size: 12px;
    }

    .notif-card {
        padding: 12px 14px;
        gap: 10px;
    }

    .notif-card-left {
        gap: 8px;
    }

    .notif-card-dot {
        width: 6px;
        height: 6px;
    }

    .notif-card-icon {
        width: 28px;
        height: 28px;
        font-size: 10px;
    }

    .notif-card-title {
        font-size: 12.5px;
    }
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
                $el.find('.notif-card-dot').addClass('read');
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
            $('.notif-card').removeClass('unread').addClass('read');
            $('.notif-card-dot').addClass('read');
            toastr.success('Semua notifikasi telah ditandai dibaca');
        }).fail(function() {
            toastr.error('Gagal menandai notifikasi');
        });
    }
</script>
@endsection
