@extends('layouts.app')

@section('title', 'Detail User')
@section('page-title', 'Detail User')

@section('styles')
<style>
    /* Profile card */
    .profile-banner {
        text-align: center;
        padding: 28px 20px 22px;
        border-bottom: 1px solid var(--card-border);
        background: linear-gradient(135deg, rgba(16,185,129,0.04) 0%, rgba(52,211,153,0.04) 100%);
    }
    .profile-avatar {
        width: 72px; height: 72px;
        border-radius: 18px;
        background: linear-gradient(135deg, var(--accent), #34d399);
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-size: 24px; font-weight: 800;
        letter-spacing: 1px;
        box-shadow: 0 6px 20px var(--accent-glow);
        margin-bottom: 14px;
    }
    .profile-name {
        font-size: 18px; font-weight: 700;
        color: var(--text-primary);
        letter-spacing: -0.3px;
    }
    .profile-email {
        font-size: 13px; color: var(--text-muted);
        margin-top: 3px; font-weight: 500;
    }

    /* Detail rows */
    .detail-rows { padding: 0; }
    .detail-row {
        display: flex;
        align-items: center;
        padding: 13px 22px;
        border-bottom: 1px solid #f1f5f9;
        gap: 12px;
    }
    .detail-row:last-child { border-bottom: none; }
    .detail-row:hover { background: rgba(16,185,129,0.015); }
    .detail-label {
        font-size: 12px; font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        min-width: 110px;
        flex-shrink: 0;
    }
    .detail-value {
        font-size: 13.5px;
        color: var(--text-primary);
        font-weight: 500;
    }
    .detail-value strong { font-weight: 700; }
    .detail-icon {
        width: 30px; height: 30px;
        border-radius: 7px;
        background: #f8fafc;
        display: flex; align-items: center; justify-content: center;
        color: var(--text-muted);
        font-size: 12px;
        flex-shrink: 0;
    }

    /* Access control read-only */
    .ac-ro-section {
        border: 1px solid var(--card-border);
        border-radius: var(--radius-lg);
        overflow: hidden;
    }
    .ac-ro-section + .ac-ro-section { margin-top: 12px; }

    .ac-ro-group-header {
        padding: 10px 18px;
        background: var(--sidebar-bg);
        color: #e2e8f0;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ac-ro-group-header i { color: var(--accent); font-size: 12px; }

    .ac-ro-table { font-size: 13px; margin: 0; }
    .ac-ro-table thead th {
        background: #f8fafc;
        color: var(--text-muted);
        font-weight: 700;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 1px solid var(--card-border);
        padding: 10px 14px;
        text-align: center;
        vertical-align: middle;
    }
    .ac-ro-table thead th:first-child { text-align: left; }
    .ac-ro-table tbody td {
        padding: 11px 14px;
        border-bottom: 1px solid #f1f5f9;
        text-align: center;
        vertical-align: middle;
    }
    .ac-ro-table tbody td:first-child { text-align: left; }
    .ac-ro-table tbody tr:last-child td { border-bottom: none; }
    .ac-ro-table tbody tr { transition: background 0.15s; }
    .ac-ro-table tbody tr:hover { background: rgba(16,185,129,0.02); }

    .ac-ro-module-name { font-weight: 600; color: var(--text-primary); font-size: 13.5px; }
    .ac-ro-module-group { font-size: 11px; color: var(--text-muted); font-weight: 500; }

    /* Permission icons */
    .perm-granted {
        width: 28px; height: 28px;
        border-radius: 6px;
        background: var(--success-soft);
        color: var(--success);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 12px;
    }
    .perm-denied {
        width: 28px; height: 28px;
        border-radius: 6px;
        background: #f8fafc;
        color: #cbd5e1;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 12px;
    }

    /* Summary stats */
    .perm-summary {
        display: flex; gap: 8px; flex-wrap: wrap;
        padding: 16px 18px;
        border-top: 1px solid var(--card-border);
        background: #f8fafc;
    }
    .perm-summary-item {
        display: flex; align-items: center; gap: 6px;
        font-size: 12px; font-weight: 600;
        color: var(--text-secondary);
        padding: 5px 12px;
        background: #fff;
        border: 1px solid var(--card-border);
        border-radius: 20px;
    }
    .perm-summary-item .count { color: var(--accent); font-weight: 800; }

    @media (max-width: 991px) {
        .profile-banner { padding: 22px 16px 18px; }
        .detail-row { padding: 11px 16px; }
    }

    @media (max-width: 576px) {
        .ac-ro-table { font-size: 12px; }
        .ac-ro-table thead th, .ac-ro-table tbody td { padding: 8px 8px; }
        .perm-granted, .perm-denied { width: 24px; height: 24px; font-size: 10px; }
        .perm-summary { flex-direction: column; }
    }
</style>
@endsection

@section('content')
{{-- <div class="breadcrumb-custom">
    <a href="">Dashboard</a>
    <i class="fa-solid fa-chevron-right bc-sep"></i>
    <a href="{{ route('user-management.index') }}">User Management</a>
    <i class="fa-solid fa-chevron-right bc-sep"></i>
    <span class="bc-current">Detail User</span>
</div> --}}

<div class="page-header">
    <div>
        <h1 class="page-header-title">Detail User</h1>
        <p class="page-header-sub">Informasi pengguna dan hak akses yang dimiliki</p>
    </div>
    <div class="page-header-actions">
        @if($canUpdate)
        <a href="{{ route('user-management.edit', $user->id) }}" class="btn-accent">
            <i class="fa-solid fa-pen"></i>
            <span>Edit</span>
        </a>
        @endif
        <a href="{{ route('user-management.index') }}" class="btn-ghost">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Kembali</span>
        </a>
    </div>
</div>

@php
    $grouped = $user->accessControls->groupBy(fn($ac) => $ac->module->group);
    $totalCreate = $user->accessControls->where('can_create', true)->count();
    $totalRead   = $user->accessControls->where('can_read', true)->count();
    $totalUpdate = $user->accessControls->where('can_update', true)->count();
    $totalDelete = $user->accessControls->where('can_delete', true)->count();
    $totalApprove = $user->accessControls->where('can_approve', true)->count();
    $totalModules = $user->accessControls->count();
@endphp

<div class="row g-4">
    <!-- Left: Profile -->
    <div class="col-lg-5">
        <div class="card-custom fade-in stagger-1">
            <div class="profile-banner">
                <div class="profile-avatar">
                    {{ strtoupper(substr($user->username, 0, 2)) }}
                </div>
                <div class="profile-name">{{ $user->username }}</div>
                <div class="profile-email">{{ $user->email }}</div>
                <div style="margin-top:10px;">
                    @if ($user->role === 'Admin')
                        <span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d">
                            <i class="fa-solid fa-shield-halved" style="font-size:10px"></i>Admin
                        </span>
                    @elseif ($user->role === 'Manager')
                        <span class="status-badge" style="background:var(--warning-soft);color:#78350f">
                            <i class="fa-solid fa-user-tie" style="font-size:10px"></i>Manager
                        </span>
                    @else
                        <span class="status-badge" style="background:var(--info-soft);color:#1e40af">
                            <i class="fa-solid fa-user" style="font-size:10px"></i>Staff
                        </span>
                    @endif
                </div>
            </div>
            <div class="detail-rows">
                <div class="detail-row">
                    <div class="detail-icon"><i class="fa-solid fa-at"></i></div>
                    <div class="detail-label">Email</div>
                    <div class="detail-value">{{ $user->email }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-icon"><i class="fa-solid fa-building"></i></div>
                    <div class="detail-label">Divisi</div>
                    <div class="detail-value">{{ $user->division?->division_name ?? '—' }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="detail-label">Role</div>
                    <div class="detail-value">
                        @if ($user->role === 'Admin')
                            <span style="color:#dc2626;font-weight:700;">Admin</span>
                        @elseif ($user->role === 'Manager')
                            <span style="color:#d97706;font-weight:700;">Manager</span>
                        @else
                            <span style="color:#2563eb;font-weight:700;">Staff</span>
                        @endif
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-icon"><i class="fa-solid fa-cube"></i></div>
                    <div class="detail-label">Modul</div>
                    <div class="detail-value"><span class="count" style="color:var(--accent);font-weight:800;">{{ $totalModules }}</span> modul</div>
                </div>
                <div class="detail-row">
                    <div class="detail-icon"><i class="fa-solid fa-calendar-plus"></i></div>
                    <div class="detail-label">Dibuat</div>
                    <div class="detail-value" style="font-size:13px;">{{ $user->created_at->format('d M Y, H:i') }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-icon"><i class="fa-solid fa-calendar-pen"></i></div>
                    <div class="detail-label">Diupdate</div>
                    <div class="detail-value" style="font-size:13px;">{{ $user->updated_at->format('d M Y, H:i') }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Access Control -->
    <div class="col-lg-7">
        <div class="card-custom fade-in stagger-2">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-shield-halved me-2" style="color:var(--accent)"></i>Access Control</span>
            </div>
            <div class="card-body-custom p-0">
                @forelse ($grouped as $group => $accesses)
                <div class="ac-ro-section {{ $loop->first ? '' : '' }}">
                    <div class="ac-ro-group-header">
                        <i class="fa-solid fa-layer-group"></i>
                        {{ $group }}
                    </div>
                    <div class="table-responsive">
                        <table class="table ac-ro-table mb-0">
                            <thead>
                                <tr>
                                    <th>Modul</th>
                                    <th style="width:60px">Create</th>
                                    <th style="width:60px">Read</th>
                                    <th style="width:60px">Update</th>
                                    <th style="width:60px">Delete</th>
                                    <th style="width:60px">Approve</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($accesses as $ac)
                                <tr>
                                    <td>
                                        <div class="ac-ro-module-name">{{ $ac->module->module_name }}</div>
                                    </td>
                                    <td>{!! $ac->can_create ? '<span class="perm-granted"><i class="fa-solid fa-check"></i></span>' : '<span class="perm-denied"><i class="fa-solid fa-minus"></i></span>' !!}</td>
                                    <td>{!! $ac->can_read ? '<span class="perm-granted"><i class="fa-solid fa-check"></i></span>' : '<span class="perm-denied"><i class="fa-solid fa-minus"></i></span>' !!}</td>
                                    <td>{!! $ac->can_update ? '<span class="perm-granted"><i class="fa-solid fa-check"></i></span>' : '<span class="perm-denied"><i class="fa-solid fa-minus"></i></span>' !!}</td>
                                    <td>{!! $ac->can_delete ? '<span class="perm-granted"><i class="fa-solid fa-check"></i></span>' : '<span class="perm-denied"><i class="fa-solid fa-minus"></i></span>' !!}</td>
                                    <td>{!! $ac->can_approve ? '<span class="perm-granted"><i class="fa-solid fa-check"></i></span>' : '<span class="perm-denied"><i class="fa-solid fa-minus"></i></span>' !!}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @empty
                <div class="empty-state">
                    <i class="fa-solid fa-shield-halved"></i>
                    <p>Tidak ada access control.</p>
                </div>
                @endforelse
            </div>

            @if ($totalModules > 0)
            <div class="perm-summary">
                <div class="perm-summary-item">
                    <i class="fa-solid fa-plus" style="font-size:10px;color:var(--success)"></i>
                    Create <span class="count">{{ $totalCreate }}</span>/{{ $totalModules }}
                </div>
                <div class="perm-summary-item">
                    <i class="fa-solid fa-eye" style="font-size:10px;color:var(--info)"></i>
                    Read <span class="count">{{ $totalRead }}</span>/{{ $totalModules }}
                </div>
                <div class="perm-summary-item">
                    <i class="fa-solid fa-pen" style="font-size:10px;color:var(--warning)"></i>
                    Update <span class="count">{{ $totalUpdate }}</span>/{{ $totalModules }}
                </div>
                <div class="perm-summary-item">
                    <i class="fa-solid fa-trash-can" style="font-size:10px;color:var(--danger)"></i>
                    Delete <span class="count">{{ $totalDelete }}</span>/{{ $totalModules }}
                </div>
                <div class="perm-summary-item">
                    <i class="fa-solid fa-circle-check" style="font-size:10px;color:var(--accent)"></i>
                    Approve <span class="count">{{ $totalApprove }}</span>/{{ $totalModules }}
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
