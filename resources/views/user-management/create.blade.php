@extends('layouts.app')

@section('title', 'Tambah User')
@section('page-title', 'Tambah User')

@section('styles')
<style>
    /* Custom checkbox accent */
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

    /* Required asterisk */
    .field-required { color: var(--danger); font-weight: 700; margin-left: 2px; }

    /* Access control section */
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

    .ac-table {
        font-size: 13px;
        margin: 0;
    }
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
    .ac-table thead th:first-child {
        text-align: left;
        width: 30%;
    }
    .ac-table thead th .col-label {
        display: block;
        margin-bottom: 6px;
        font-weight: 700;
    }
    .ac-table tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid #f1f5f9;
        text-align: center;
        vertical-align: middle;
        color: var(--text-secondary);
    }
    .ac-table tbody td:first-child {
        text-align: left;
    }
    .ac-table tbody tr:last-child td { border-bottom: none; }
    .ac-table tbody tr { transition: background 0.15s; }
    .ac-table tbody tr:hover { background: rgba(16,185,129,0.02); }

    .ac-module-name {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 13.5px;
    }
    .ac-module-code {
        font-size: 11px;
        color: var(--text-muted);
        font-weight: 500;
    }

    /* Password toggle */
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

    /* Form field hints */
    .field-hint {
        font-size: 11.5px;
        color: var(--text-muted);
        margin-top: 4px;
        font-weight: 500;
    }

    /* Invalid feedback override */
    .invalid-feedback { font-size: 12px; font-weight: 500; }

    /* Action bar */
    .form-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-top: 20px;
        border-top: 1px solid var(--card-border);
        margin-top: 28px;
    }

    @media (max-width: 768px) {
        .ac-table { font-size: 12px; }
        .ac-table thead th, .ac-table tbody td { padding: 8px 8px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn-accent, .form-actions .btn-ghost { width: 100%; justify-content: center; }
    }
</style>
@endsection

@section('content')
{{-- <div class="breadcrumb-custom">
    <a href="">Dashboard</a>
    <i class="fa-solid fa-chevron-right bc-sep"></i>
    <a href="{{ route('user-management.index') }}">User Management</a>
    <i class="fa-solid fa-chevron-right bc-sep"></i>
    <span class="bc-current">Tambah User</span>
</div> --}}

<div class="page-header">
    <div>
        <h1 class="page-header-title">Tambah User</h1>
        <p class="page-header-sub">Isi data berikut untuk menambahkan pengguna baru ke sistem</p>
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
        <span><i class="fa-solid fa-user-plus me-2" style="color:var(--accent)"></i>Data User</span>
    </div>
    <div class="card-body-custom">
        <form method="POST" action="{{ route('user-management.store') }}" id="form-user">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Username<span class="field-required">*</span></label>
                    <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}" placeholder="Masukkan username" required autofocus>
                    @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email<span class="field-required">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="contoh@perusahaan.com" required>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password<span class="field-required">*</span></label>
                    <div class="pw-wrapper">
                        <input type="password" name="password" id="inputPassword" class="form-control @error('password') is-invalid @enderror" placeholder="Minimal 8 karakter" required>
                        <button type="button" class="pw-toggle" id="togglePassword" aria-label="Toggle password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <div class="field-hint">Minimal 8 karakter, gunakan huruf dan angka</div>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Divisi</label>
                    <select name="division_id" class="form-select @error('division_id') is-invalid @enderror">
                        <option value="">— Pilih Divisi —</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}" {{ old('division_id') == $division->id ? 'selected' : '' }}>
                                {{ $division->division_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('division_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role<span class="field-required">*</span></label>
                    <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                        <option value="">— Pilih Role —</option>
                        <option value="Admin" {{ old('role') == 'Admin' ? 'selected' : '' }}>Admin</option>
                        <option value="Manager" {{ old('role') == 'Manager' ? 'selected' : '' }}>Manager</option>
                        <option value="Staff" {{ old('role') == 'Staff' ? 'selected' : '' }}>Staff</option>
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
                        <p style="font-size:12px;color:var(--text-muted);margin:2px 0 0;font-weight:500;">Tentukan hak akses untuk setiap modul</p>
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
                                        <input type="checkbox" class="form-check-input check-all-col" data-col="can_read" checked>
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
                                <tr>
                                    <td>
                                        <div class="ac-module-name">{{ $module->module_name }}</div>
                                        <div class="ac-module-code">{{ $module->module_code }}</div>
                                    </td>
                                    <td><input type="checkbox" name="modules[{{ $module->id }}][can_create]" class="form-check-input can_create"></td>
                                    <td><input type="checkbox" name="modules[{{ $module->id }}][can_read]" class="form-check-input can_read" checked></td>
                                    <td><input type="checkbox" name="modules[{{ $module->id }}][can_update]" class="form-check-input can_update"></td>
                                    <td><input type="checkbox" name="modules[{{ $module->id }}][can_delete]" class="form-check-input can_delete"></td>
                                    <td><input type="checkbox" name="modules[{{ $module->id }}][can_approve]" class="form-check-input can_approve"></td>
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
                    <span>Simpan</span>
                </button>
                <a href="{{ route('user-management.index') }}" class="btn-ghost">
                    <span>Batal</span>
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Toggle password visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const input = document.getElementById('inputPassword');
    const icon = this.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});

// Check all column in access control table
document.querySelectorAll('.check-all-col').forEach(function(cb) {
    cb.addEventListener('change', function() {
        const col = this.dataset.col;
        this.closest('table').querySelectorAll('tbody .' + col).forEach(function(r) {
            r.checked = cb.checked;
        });
    });
});

// Submit confirmation
document.getElementById('btn-submit').addEventListener('click', function(e) {
    e.preventDefault();
    const form = document.getElementById('form-user');
    Swal.fire({
        title: 'Simpan Data?',
        text: 'Pastikan data user dan hak akses sudah benar.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, simpan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b',
    }).then(function(result) {
        if (result.isConfirmed) form.submit();
    });
});
</script>
@endpush
