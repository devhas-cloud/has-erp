@extends('layouts.app')

@section('title', 'User Management')
@section('page-title', 'User Management')

@section('content')
{{-- <div class="breadcrumb-custom">
    <a href="">Dashboard</a>
    <i class="fa-solid fa-chevron-right bc-sep"></i>
    <span class="bc-current">User Management</span>
</div> --}}

<div class="page-header">
    <div>
        <h1 class="page-header-title">User Management</h1>
        <p class="page-header-sub">Kelola data pengguna yang memiliki akses ke sistem</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('user-management.create') }}" class="btn-accent">
            <i class="fa-solid fa-plus"></i>
            <span>Tambah User</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-users me-2" style="color:var(--accent)"></i>Daftar User</span>
        <span style="font-size:12px;color:var(--text-muted);font-weight:500;">{{ $users->total() }} total data</span>
    </div>
    <div class="card-body-custom p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Divisi</th>
                        <th>Role</th>
                        <th>Dibuat</th>
                        <th class="text-center" style="width:140px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                    <tr>
                        <td style="color:var(--text-muted);font-weight:500">{{ $loop->iteration + $users->firstItem() - 1 }}</td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--accent),#34d399);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;flex-shrink:0;letter-spacing:.3px">
                                    {{ strtoupper(substr($user->username, 0, 2)) }}
                                </div>
                                <strong style="color:var(--text-primary);font-weight:600">{{ $user->username }}</strong>
                            </div>
                        </td>
                        <td style="color:var(--text-secondary)">{{ $user->email }}</td>
                        <td>{{ $user->division?->division_name ?? '<span style="color:var(--text-muted)">—</span>' }}</td>
                        <td>
                            @if ($user->role === 'Admin')
                                <span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d">
                                    <i class="fa-solid fa-shield-halved" style="font-size:10px"></i>
                                    Admin
                                </span>
                            @elseif ($user->role === 'Manager')
                                <span class="status-badge" style="background:var(--warning-soft);color:#78350f">
                                    <i class="fa-solid fa-user-tie" style="font-size:10px"></i>
                                    Manager
                                </span>
                            @else
                                <span class="status-badge" style="background:var(--info-soft);color:#1e40af">
                                    <i class="fa-solid fa-user" style="font-size:10px"></i>
                                    Staff
                                </span>
                            @endif
                        </td>
                        <td style="color:var(--text-muted);font-size:13px">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="text-center">
                            <div style="display:flex;gap:5px;justify-content:center">
                                <a href="{{ route('user-management.show', $user->id) }}" class="btn-icon" title="Detail">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                @if($canUpdate)
                                <a href="{{ route('user-management.edit', $user->id) }}" class="btn-icon" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                @endif
                                @if($canDelete)
                                <form action="{{ route('user-management.destroy', $user->id) }}" method="POST" style="display:inline">
                                    @csrf @method('DELETE')
                                    <button type="button" class="btn-icon danger btn-delete" title="Hapus">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="fa-solid fa-users-slash"></i>
                                <p>Belum ada data user.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($users->hasPages())
    <div style="padding:14px 22px;border-top:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <span style="font-size:12.5px;color:var(--text-muted);font-weight:500">
            Menampilkan {{ $users->firstItem() }}–{{ $users->lastItem() }} dari {{ $users->total() }}
        </span>
        <div>{{ $users->links() }}</div>
    </div>
    @endif
</div>
@endsection
