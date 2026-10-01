@extends('layouts.app')

@section('title', 'Profil Saya')
@section('page-title', 'Profil Saya')

@section('styles')
<style>
    .field-required { color: var(--danger); font-weight: 700; margin-left: 2px; }
    .field-hint { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; font-weight: 500; }
    .invalid-feedback { font-size: 12px; font-weight: 500; }

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

    .form-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-top: 20px;
        border-top: 1px solid var(--card-border);
        margin-top: 28px;
    }

    /* Photo block */
    .profile-photo {
        display: flex;
        align-items: center;
        gap: 18px;
        padding: 16px 18px;
        background: var(--accent-soft);
        border: 1px solid rgba(16,185,129,0.15);
        border-radius: var(--radius);
        margin-bottom: 24px;
    }
    .profile-photo-avatar {
        width: 84px; height: 84px;
        border-radius: 20px;
        background: linear-gradient(135deg, var(--accent), #34d399);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 28px; font-weight: 800;
        flex-shrink: 0; letter-spacing: 0.5px;
        box-shadow: 0 4px 12px var(--accent-glow);
        overflow: hidden;
    }
    .profile-photo-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .profile-photo-info { line-height: 1.3; min-width: 0; }
    .profile-photo-name { font-size: 16px; font-weight: 700; color: var(--text-primary); }
    .profile-photo-meta { font-size: 12px; color: var(--text-muted); font-weight: 500; }
    .profile-photo-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .profile-photo-actions .btn-ghost { padding: 6px 12px; font-size: 12px; }
    .btn-ghost.danger:hover {
        border-color: rgba(239, 68, 68, 0.25);
        color: var(--danger);
        background: var(--danger-soft);
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.1);
    }

    /* Read-only account info */
    .account-info { list-style: none; margin: 0; padding: 0; }
    .account-info li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 11px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }
    .account-info li:last-child { border-bottom: none; padding-bottom: 0; }
    .account-info li:first-child { padding-top: 0; }
    .account-info-label { color: var(--text-muted); font-weight: 600; }
    .account-info-value { color: var(--text-primary); font-weight: 700; text-align: right; word-break: break-word; }

    @media (max-width: 768px) {
        .profile-photo { flex-direction: column; align-items: flex-start; }
        .form-actions { flex-direction: column; }
        .form-actions .btn-accent, .form-actions .btn-ghost { width: 100%; justify-content: center; }
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Profil Saya</h1>
        <p class="page-header-sub">Kelola nama, foto, kontak, dan password akun Anda</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-user-pen me-2" style="color:var(--accent)"></i>Informasi Profil</span>
            </div>
            <div class="card-body-custom">
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" id="form-profile">
                    @csrf @method('PUT')

                    <div class="profile-photo">
                        <div class="profile-photo-avatar" id="photoPreview" data-initials="{{ strtoupper(substr($user->display_name, 0, 2)) }}">
                            @if ($user->avatar_url)
                                <img src="{{ $user->avatar_url }}" alt="Foto profil">
                            @else
                                {{ strtoupper(substr($user->display_name, 0, 2)) }}
                            @endif
                        </div>
                        <div class="profile-photo-info">
                            <div class="profile-photo-name">{{ $user->display_name }}</div>
                            <div class="profile-photo-meta">{{ $user->email }}</div>
                            <div class="profile-photo-actions">
                                <label for="inputPhoto" class="btn-ghost mb-0">
                                    <i class="fa-solid fa-camera"></i>
                                    <span>{{ $user->avatar_url ? 'Ganti Foto' : 'Pilih Foto' }}</span>
                                </label>
                                @if ($user->avatar_url)
                                    <button type="button" class="btn-ghost danger" id="btnRemovePhoto">
                                        <i class="fa-solid fa-trash"></i>
                                        <span>Hapus Foto</span>
                                    </button>
                                @endif
                            </div>
                            <input type="file" name="photo" id="inputPhoto" class="d-none @error('photo') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                            <div class="field-hint">JPG, PNG, atau WEBP. Maksimal 2 MB. Foto tersimpan setelah klik Simpan Perubahan.</div>
                            @error('photo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Lengkap<span class="field-required">*</span></label>
                            <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name', $user->full_name) }}" placeholder="Nama lengkap Anda" required>
                            @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-accent">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Simpan Perubahan</span>
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('profile.photo.destroy') }}" id="form-remove-photo" class="d-none">
                    @csrf @method('DELETE')
                </form>
            </div>
        </div>

        <div class="card-custom fade-in stagger-1 mt-4" id="card-password">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-key me-2" style="color:var(--accent)"></i>Ubah Password</span>
            </div>
            <div class="card-body-custom">
                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Password Saat Ini<span class="field-required">*</span></label>
                            <div class="pw-wrapper">
                                <input type="password" name="current_password" class="form-control @error('current_password', 'password') is-invalid @enderror" placeholder="Masukkan password saat ini" autocomplete="current-password" required>
                                <button type="button" class="pw-toggle" aria-label="Toggle password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                            @error('current_password', 'password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password Baru<span class="field-required">*</span></label>
                            <div class="pw-wrapper">
                                <input type="password" name="password" class="form-control @error('password', 'password') is-invalid @enderror" placeholder="Minimal 6 karakter" autocomplete="new-password" minlength="6" required>
                                <button type="button" class="pw-toggle" aria-label="Toggle password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                            @error('password', 'password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Konfirmasi Password Baru<span class="field-required">*</span></label>
                            <div class="pw-wrapper">
                                <input type="password" name="password_confirmation" class="form-control" placeholder="Ulangi password baru" autocomplete="new-password" minlength="6" required>
                                <button type="button" class="pw-toggle" aria-label="Toggle password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-accent">
                            <i class="fa-solid fa-lock"></i>
                            <span>Ubah Password</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-custom fade-in stagger-2">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-id-badge me-2" style="color:var(--accent)"></i>Informasi Akun</span>
            </div>
            <div class="card-body-custom">
                <ul class="account-info">
                    <li>
                        <span class="account-info-label">Username</span>
                        <span class="account-info-value">{{ $user->username }}</span>
                    </li>
                    <li>
                        <span class="account-info-label">Role</span>
                        <span class="account-info-value">{{ $user->role }}</span>
                    </li>
                    <li>
                        <span class="account-info-label">Divisi</span>
                        <span class="account-info-value">{{ $user->division?->division_name ?? '—' }}</span>
                    </li>
                    <li>
                        <span class="account-info-label">Task Role</span>
                        <span class="account-info-value">{{ $user->hierarchyRole?->role_name ?? '—' }}</span>
                    </li>
                    <li>
                        <span class="account-info-label">Bergabung</span>
                        <span class="account-info-value">{{ $user->created_at?->format('d M Y') ?? '—' }}</span>
                    </li>
                </ul>
                <div class="field-hint" style="margin-top:14px">
                    <i class="fa-solid fa-circle-info me-1"></i>Username, role, divisi, dan hak akses hanya dapat diubah oleh Admin.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // --- Photo preview ---
    $('#inputPhoto').on('change', function() {
        var file = this.files && this.files[0];
        if (!file) return;

        if (['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) === -1) {
            toastr.error('Foto harus berformat JPG, PNG, atau WEBP.');
            this.value = '';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            toastr.error('Ukuran foto maksimal 2 MB.');
            this.value = '';
            return;
        }

        var img = document.createElement('img');
        img.alt = 'Foto profil';
        img.src = URL.createObjectURL(file);
        $('#photoPreview').empty().append(img);
    });

    // --- Remove photo ---
    $('#btnRemovePhoto').on('click', function() {
        Swal.fire({
            title: 'Hapus foto profil?',
            text: 'Avatar akan kembali menampilkan inisial nama Anda.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) $('#form-remove-photo').submit();
        });
    });

    // --- Password toggle ---
    $('.pw-toggle').on('click', function() {
        var $input = $(this).siblings('input');
        var show = $input.attr('type') === 'password';
        $input.attr('type', show ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye', !show).toggleClass('fa-eye-slash', show);
    });

    @if ($errors->password->any())
        document.getElementById('card-password').scrollIntoView({ block: 'center' });
    @endif
</script>
@endsection
