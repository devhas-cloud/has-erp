@extends('layouts.app')

@section('title', 'Edit User')
@section('page-title', 'Edit User')

@section('styles')
<style>
    .form-check-input:checked {
        background-color: var(--accent);
        border-color: var(--accent);
    }
    .form-check-input:focus {
        box-shadow: 0 0 0 3px var(--accent-soft);
        border-color: var(--accent);
    }
    .form-check-input.is-invalid:focus {
        box-shadow: 0 0 0 3px rgba(239,68,68,0.12);
    }

    .field-required { color: var(--danger); font-weight: 700; margin-left: 2px; }
    .field-optional {
        font-size: 11px;
        color: var(--text-muted);
        font-weight: 500;
        margin-left: 6px;
    }

    .ac-section {
        border: 1px solid var(--card-border);
        border-radius: var(--radius-lg);
        overflow: hidden;
    }
    .ac-section + .ac-section { margin-top: 12px; }

    .ac-group-header {
        padding: 12px 18px;
        background: var(--sidebar-bg);
        color: #e2e8f0;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ac-group-header i { color: var(--accent); font-size: 13px; }

    .ac-table { font-size: 13px; margin: 0; }
    .ac-table thead th {
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
    .ac-table thead th:first-child { text-align: left; width: 30%; }
    .ac-table thead th .col-label { display: block; margin-bottom: 6px; font-weight: 700; }
    .ac-table tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid #f1f5f9;
        text-align: center;
        vertical-align: middle;
        color: var(--text-secondary);
    }
    .ac-table tbody td:first-child { text-align: left; }
    .ac-table tbody tr:last-child td { border-bottom: none; }
    .ac-table tbody tr { transition: background 0.15s; }
    .ac-table tbody tr:hover { background: rgba(16,185,129,0.02); }

    .ac-module-name { font-weight: 600; color: var(--text-primary); font-size: 13.5px; }
    .ac-module-code { font-size: 11px; color: var(--text-muted); font-weight: 500; }

    .pw-wrapper { position: relative; }
    .pw-toggle {
        position: absolute;
        right: 12px; top: 50%;
        transform: translateY(-50%);
        background: none; border: none;
        color: var(--text-muted);
        cursor: pointer;
        font-size: 14px;
        padding: 4px;
        transition: color 0.2s;
    }
    .pw-toggle:hover { color: var(--accent); }
    .pw-wrapper .form-control { padding-right: 42px; }

    .field-hint { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; font-weight: 500; }
    .invalid-feedback { font-size: 12px; font-weight: 500; }

    .form-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-top: 20px;
        border-top: 1px solid var(--card-border);
        margin-top: 28px;
    }

    /* User identity banner */
    .user-identity {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 18px;
        background: var(--accent-soft);
        border: 1px solid rgba(16,185,129,0.15);
        border-radius: var(--radius);
        margin-bottom: 24px;
    }
    .user-identity-avatar {
        width: 46px; height: 46px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--accent), #34d399);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 16px; font-weight: 800;
        flex-shrink: 0; letter-spacing: 0.5px;
        box-shadow: 0 4px 12px var(--accent-glow);
    }
    .user-identity-info { line-height: 1.3; }
    .user-identity-name { font-size: 15px; font-weight: 700; color: var(--text-primary); }
    .user-identity-meta { font-size: 12px; color: var(--text-muted); font-weight: 500; }
    .user-identity-role {
        margin-left: auto;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        flex-shrink: 0;
    }

    @media (max-width: 768px) {
        .ac-table { font-size: 12px; }
        .ac-table thead th, .ac-table tbody td { padding: 8px 8px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn-accent, .form-actions .btn-ghost { width: 100%; justify-content: center; }
        .user-identity { flex-wrap: wrap; }
        .user-identity-role { margin-left: 0; }
    }
</style>
@endsection

@section('content')
{{-- <div class="breadcrumb-custom">
    <a href="">Dashboard</a>
    <i class="fa-solid fa-chevron-right bc-sep"></i>
    <a href="{{ route('user-management.index') }}">User Management</a>
    <i class="fa-solid fa-chevron-right bc-sep"></i>
    <span class="bc-current">Edit User</span>
</div> --}}

<div class="page-header">
    <div>
        <h1 class="page-header-title">Edit User</h1>
        <p class="page-header-sub">Ubah data dan hak akses pengguna</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('user-management.index') }}" class="btn-ghost">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Kembali</span>
        </a>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-user-pen me-2" style="color:var(--accent)"></i>Data User</span>
    </div>
    <div class="card-body-custom">
        <form method="POST" action="{{ route('user-management.update', $user->id) }}" id="form-user">
            @csrf @method('PUT')

            <!-- User identity banner -->
            <div class="user-identity">
                <div class="user-identity-avatar">
                    {{ strtoupper(substr($user->username, 0, 2)) }}
                </div>
                <div class="user-identity-info">
                    <div class="user-identity-name">{{ $user->username }}</div>
                    <div class="user-identity-meta">{{ $user->email }} {{ $user->division ? '· ' . $user->division->division_name : '' }}</div>
                </div>
                @if ($user->role === 'Admin')
                    <span class="user-identity-role" style="background:var(--danger-soft);color:#7f1d1d">
                        <i class="fa-solid fa-shield-halved me-1" style="font-size:10px"></i>Admin
                    </span>
                @elseif ($user->role === 'Manager')
                    <span class="user-identity-role" style="background:var(--warning-soft);color:#78350f">
                        <i class="fa-solid fa-user-tie me-1" style="font-size:10px"></i>Manager
                    </span>
                @else
                    <span class="user-identity-role" style="background:var(--info-soft);color:#1e40af">
                        <i class="fa-solid fa-user me-1" style="font-size:10px"></i>Staff
                    </span>
                @endif
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Username<span class="field-required">*</span></label>
                    <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $user->username) }}" placeholder="Masukkan username" required>
                    @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email<span class="field-required">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" placeholder="contoh@perusahaan.com" required>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nomor Telepon</label>
                    <input type="text" name="phone_number" class="form-control @error('phone_number') is-invalid @enderror" value="{{ old('phone_number', $user->phone_number) }}" placeholder="08xxxxxxxxxx">
                    @error('phone_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password<span class="field-optional">(kosongkan jika tidak diganti)</span></label>
                    <div class="pw-wrapper">
                        <input type="password" name="password" id="inputPassword" class="form-control @error('password') is-invalid @enderror" placeholder="Kosongkan jika tidak diganti">
                        <button type="button" class="pw-toggle" id="togglePassword" aria-label="Toggle password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <div class="field-hint">Kosongkan jika password tidak ingin diubah</div>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Divisi</label>
                    <select name="division_id" class="form-select @error('division_id') is-invalid @enderror">
                        <option value="">— Pilih Divisi —</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}" {{ old('division_id', $user->division_id) == $division->id ? 'selected' : '' }}>
                                {{ $division->division_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('division_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Task Role</label>
                    <select name="task_role_id" class="form-select @error('task_role_id') is-invalid @enderror">
                        <option value="">— Pilih Task Role —</option>
                        @foreach ($taskRoles as $tr)
                            <option value="{{ $tr->id }}" {{ old('task_role_id', $user->task_role_id) == $tr->id ? 'selected' : '' }}>
                                {{ $tr->role_name }} (Lv.{{ $tr->hierarchy_level }})
                            </option>
                        @endforeach
                    </select>
                    @error('task_role_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role<span class="field-required">*</span></label>
                    <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                        <option value="Admin" {{ old('role', $user->role) == 'Admin' ? 'selected' : '' }}>Admin</option>
                        <option value="User" {{ old('role', $user->role) == 'User' ? 'selected' : '' }}>User</option>
                    </select>
                    @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <!-- Access Control -->
            <div style="margin-top:32px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
                    <div style="width:36px;height:36px;border-radius:8px;background:var(--accent-soft);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:15px;flex-shrink:0;">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <h2 style="font-size:16px;font-weight:700;color:var(--text-primary);margin:0;letter-spacing:-0.2px;">Access Control</h2>
                        <p style="font-size:12px;color:var(--text-muted);margin:2px 0 0;font-weight:500;">Hak akses saat ini sudah dimuat, sesuaikan jika diperlukan</p>
                    </div>
                </div>

                @foreach ($modules as $group => $groupModules)
                <div class="ac-section">
                    <div class="ac-group-header">
                        <i class="fa-solid fa-layer-group"></i>
                        {{ $group }}
                    </div>
                    <div class="table-responsive">
                        <table class="table ac-table mb-0">
                            <thead>
                                <tr>
                                    <th>Modul</th>
                                    <th>
                                        <span class="col-label">Create</span>
                                        <input type="checkbox" class="form-check-input check-all-col" data-col="can_create">
                                    </th>
                                    <th>
                                        <span class="col-label">Read</span>
                                        <input type="checkbox" class="form-check-input check-all-col" data-col="can_read">
                                    </th>
                                    <th>
                                        <span class="col-label">Update</span>
                                        <input type="checkbox" class="form-check-input check-all-col" data-col="can_update">
                                    </th>
                                    <th>
                                        <span class="col-label">Delete</span>
                                        <input type="checkbox" class="form-check-input check-all-col" data-col="can_delete">
                                    </th>
                                    <th>
                                        <span class="col-label">Approve</span>
                                        <input type="checkbox" class="form-check-input check-all-col" data-col="can_approve">
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($groupModules as $module)
                                    @php
                                        $perms = $userPermissions->get($module->id);
                                    @endphp
                                <tr>
                                    <td>
                                        <div class="ac-module-name">{{ $module->module_name }}</div>
                                        <div class="ac-module-code">{{ $module->module_code }}</div>
                                    </td>
                                    <td><input type="checkbox" name="modules[{{ $module->id }}][can_create]" class="form-check-input can_create" {{ $perms && $perms->can_create ? 'checked' : '' }}></td>
                                    <td><input type="checkbox" name="modules[{{ $module->id }}][can_read]" class="form-check-input can_read" {{ $perms && $perms->can_read ? 'checked' : '' }}></td>
                                    <td><input type="checkbox" name="modules[{{ $module->id }}][can_update]" class="form-check-input can_update" {{ $perms && $perms->can_update ? 'checked' : '' }}></td>
                                    <td><input type="checkbox" name="modules[{{ $module->id }}][can_delete]" class="form-check-input can_delete" {{ $perms && $perms->can_delete ? 'checked' : '' }}></td>
                                    <td><input type="checkbox" name="modules[{{ $module->id }}][can_approve]" class="form-check-input can_approve" {{ $perms && $perms->can_approve ? 'checked' : '' }}></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <button type="submit" class="btn-accent" id="btn-submit">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Update</span>
                </button>
                <a href="{{ route('user-management.index') }}" class="btn-ghost">
                    <span>Batal</span>
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    // Toggle password visibility
    $('#togglePassword').on('click', function() {
        var $input = $('#inputPassword');
        var $icon = $(this).find('i');
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Check all column in access control table
    $(document).on('change', '.check-all-col', function() {
        var col = $(this).data('col');
        var checked = this.checked;
        var $section = $(this).closest('.ac-section');
        if ($section.length) {
            $section.find('tbody input.' + col).each(function() {
                this.checked = checked;
            });
        }
    });

    // Sync header checkbox state on load
    $('.check-all-col').each(function() {
        var col = $(this).data('col');
        var $section = $(this).closest('.ac-section');
        if ($section.length) {
            var $boxes = $section.find('tbody input.' + col);
            var allChecked = $boxes.length > 0 && $boxes.filter(':not(:checked)').length === 0;
            this.checked = allChecked;
        }
    });

    // Submit confirmation
    $('#btn-submit').on('click', function(e) {
        e.preventDefault();
        var $form = $('#form-user');
        Swal.fire({
            title: 'Update Data?',
            text: 'Perubahan data user dan hak akses akan disimpan.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, update',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
        }).then(function(result) {
            if (result.isConfirmed) $form.submit();
        });
    });
});
</script>
@endsection
