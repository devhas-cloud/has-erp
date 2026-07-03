@extends('layouts.app')

@section('title', 'Dashboard Task Planner')
@section('page-title', 'Dashboard Task Planner')

@section('styles')
<style>
    .dash-stat-card {
        border-radius: var(--radius);
        padding: 20px 22px;
        background: #fff;
        border: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: box-shadow 0.2s;
    }
    .dash-stat-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
    .dash-stat-icon {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }
    .dash-stat-content { flex: 1; min-width: 0; }
    .dash-stat-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }
    .dash-stat-value {
        font-size: 26px;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    .dash-stat-value.danger { color: #dc3545; }
    .dash-stat-value.success { color: #10b981; }
    .dash-stat-value.warning { color: #f59e0b; }
    .dash-stat-value.info { color: #3b82f6; }

    .category-progress {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 0;
    }
    .category-progress .cat-name {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        width: 150px;
        flex-shrink: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .category-progress .cat-bar-wrap {
        flex: 1;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        min-width: 100px;
    }
    .category-progress .cat-bar-fill {
        height: 100%;
        background: var(--accent);
        border-radius: 4px;
        transition: width 0.5s ease;
    }
    .category-progress .cat-stat {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        width: 60px;
        text-align: right;
        flex-shrink: 0;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Dashboard Task Planner</h1>
        <p class="page-header-sub">Monitoring tugas Anda secara real-time</p>
    </div>
</div>

<div class="row g-4 fade-in">
    <div class="col-lg-3 col-md-6">
        <div class="dash-stat-card">
            <div class="dash-stat-icon" style="background:var(--accent-soft);color:var(--accent)">
                <i class="fa fa-tasks"></i>
            </div>
            <div class="dash-stat-content">
                <div class="dash-stat-label">Total Tasks</div>
                <div class="dash-stat-value">{{ $totalTasks }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="dash-stat-card">
            <div class="dash-stat-icon" style="background:var(--warning-soft);color:#78350f">
                <i class="fa fa-circle"></i>
            </div>
            <div class="dash-stat-content">
                <div class="dash-stat-label">To Do</div>
                <div class="dash-stat-value warning">{{ $todoCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="dash-stat-card">
            <div class="dash-stat-icon" style="background:var(--info-soft);color:#1e40af">
                <i class="fa fa-spinner"></i>
            </div>
            <div class="dash-stat-content">
                <div class="dash-stat-label">In Progress</div>
                <div class="dash-stat-value info">{{ $inProgressCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="dash-stat-card">
            <div class="dash-stat-icon" style="background:#fee2e2;color:#dc3545">
                <i class="fa fa-exclamation-triangle"></i>
            </div>
            <div class="dash-stat-content">
                <div class="dash-stat-label">Overdue</div>
                <div class="dash-stat-value danger">{{ $overdueCount }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-2 fade-in stagger-1">
    <div class="col-lg-7">
        <div class="card-custom">
            <div class="card-header-custom">
                <span><i class="fa fa-list me-2" style="color:var(--accent)"></i>Upcoming Tasks</span>
                <a href="{{ route('task-planner.index') }}" class="btn btn-sm btn-ghost" style="font-size:11px">Lihat Semua</a>
            </div>
            <div class="card-body-custom">
                @if ($upcomingTasks->isEmpty())
                    <div class="empty-state">
                        <i class="fa fa-check-circle"></i>
                        <p>Tidak ada task yang perlu dikerjakan</p>
                    </div>
                @else
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($upcomingTasks as $task)
                                <tr>
                                    <td>
                                        <a href="{{ route('task-planner.show', $task->id) }}" style="font-weight:600;text-decoration:none;color:var(--text-primary)">
                                            {{ $task->title }}
                                        </a>
                                    </td>
                                    <td>
                                        <span style="font-size:12px;color:var(--text-muted)">{{ $task->category?->name ?? '—' }}</span>
                                    </td>
                                    <td>
                                        @php $isOverdue = $task->due_date->endOfDay()->isPast() && $task->status !== 'done'; @endphp
                                        <span class="{{ $isOverdue ? 'text-danger fw-bold' : '' }}" style="font-size:13px">
                                            {{ $task->due_date->format('d M Y') }}
                                            @if ($isOverdue)
                                                <i class="fa fa-exclamation-circle ms-1"></i>
                                            @endif
                                        </span>
                                    </td>
                                    <td>
                                        @if ($task->status === 'todo')
                                            <span class="status-badge status-pending">To Do</span>
                                        @elseif ($task->status === 'in_progress')
                                            <span class="status-badge" style="background:var(--info-soft);color:#1e40af">In Progress</span>
                                        @elseif ($task->status === 'waiting_approval')
                                            <span class="status-badge" style="background:#fef3c7;color:#92400e">Waiting Approval</span>
                                        @else
                                            <span class="status-badge status-active">Done</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('task-planner.show', $task->id) }}" class="btn btn-sm btn-ghost" style="font-size:11px">Detail</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-custom">
            <div class="card-header-custom">
                <span><i class="fa fa-chart-bar me-2" style="color:var(--accent)"></i>Tasks by Category</span>
            </div>
            <div class="card-body-custom">
                @if ($categories->isEmpty())
                    <div class="empty-state">
                        <i class="fa fa-folder-open"></i>
                        <p>Belum ada task</p>
                    </div>
                @else
                    @foreach ($categories as $cat)
                        <div class="category-progress">
                            <span class="cat-name">{{ $cat['name'] }}</span>
                            <div class="cat-bar-wrap">
                                <div class="cat-bar-fill" style="width:{{ $cat['pct'] }}%"></div>
                            </div>
                            <span class="cat-stat">{{ $cat['done'] }}/{{ $cat['total'] }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
