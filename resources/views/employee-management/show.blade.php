@extends('layouts.app')

@section('title', 'Karyawan — '.$employee->name)
@section('page-title', 'Detail Karyawan')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">
            <span style="font-family:monospace;color:var(--accent)">{{ $employee->employee_no }}</span>
            {{ $employee->name }}
            @if($employee->status === 'active')
            <span class="status-badge status-active">Active</span>
            @elseif($employee->status === 'on_leave')
            <span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">On Leave</span>
            @else
            <span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Terminated</span>
            @endif
        </h1>
        <p class="page-header-sub">{{ ($employee->jobTitle->title_name ?? '—').($employee->division?->division_name ? ' · '.$employee->division->division_name : '') }}</p>
    </div>
    <div class="page-header-actions">
        @if($canUpdate)
        <button type="button" class="btn-accent" onclick="openEditModal()">
            <i class="fa-solid fa-pen"></i><span>Edit</span>
        </button>
        @endif
        @if($canUpdate)
        @if($employee->status !== 'terminated')
        <button type="button" class="btn-accent-danger" onclick="terminateEmployee()">
            <i class="fa-solid fa-user-slash"></i><span>Nonaktifkan</span>
        </button>
        @else
        <button type="button" class="btn-accent" onclick="reactivateEmployee()">
            <i class="fa-solid fa-user-check"></i><span>Aktifkan</span>
        </button>
        @endif
        @endif
        <a href="{{ route('employee-management.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-body-custom py-2">
        <ul class="nav nav-pills" style="font-size:13px">
            <li class="nav-item"><button class="nav-link active" data-tab-tab="profile">Profile</button></li>
            <li class="nav-item"><button class="nav-link" data-tab-tab="family">Keluarga</button></li>
            <li class="nav-item"><button class="nav-link" data-tab-tab="attendance">Absensi</button></li>
            <li class="nav-item"><button class="nav-link" data-tab-tab="salary">Komponen Gaji</button></li>
            <li class="nav-item"><button class="nav-link" data-tab-tab="history">Riwayat</button></li>
        </ul>
    </div>
</div>

{{-- ======================= TAB PROFILE ======================= --}}
<div class="emp-tab" id="emp-tab-profile">
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card-custom">
                <div class="card-header-custom"><span><i class="fa-solid fa-id-card me-2" style="color:var(--accent)"></i>Data Pribadi</span></div>
                <div class="card-body-custom" style="font-size:13px">
                    <table class="table table-sm mb-0">
                        <tr><td style="width:40%;color:var(--text-muted)">Gender</td><td>{{ $employee->gender === 'male' ? 'Laki-laki' : ($employee->gender === 'female' ? 'Perempuan' : '—') }}</td></tr>
                        <tr><td style="color:var(--text-muted)">Tempat Lahir</td><td>{{ $employee->birth_place ?? '—' }}</td></tr>
                        <tr><td style="color:var(--text-muted)">Tanggal Lahir</td><td>{{ $employee->birth_date?->format('d-m-Y') ?? '—' }}</td></tr>
                        <tr><td style="color:var(--text-muted)">Telepon</td><td>{{ $employee->phone ?? '—' }}</td></tr>
                        <tr><td style="color:var(--text-muted)">Email</td><td>{{ $employee->email ?? '—' }}</td></tr>
                        <tr><td style="color:var(--text-muted)">Alamat</td><td>{{ $employee->address ?? '—' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-custom">
                <div class="card-header-custom"><span><i class="fa-solid fa-briefcase me-2" style="color:var(--accent)"></i>Kepegawaian</span></div>
                <div class="card-body-custom" style="font-size:13px">
                    <table class="table table-sm mb-0">
                        <tr><td style="width:40%;color:var(--text-muted)">No. Pegawai</td><td><span style="font-family:monospace">{{ $employee->employee_no }}</span></td></tr>
                        <tr><td style="color:var(--text-muted)">Division</td><td>{{ $employee->division->division_name ?? '—' }}</td></tr>
                        <tr><td style="color:var(--text-muted)">Jabatan</td><td>{{ $employee->jobTitle->title_name ?? '—' }}</td></tr>
                        <tr><td style="color:var(--text-muted)">Atasan</td><td>{{ $employee->manager?->name ?? '—' }}</td></tr>
                        <tr><td style="color:var(--text-muted)">Join Date</td><td>{{ $employee->join_date?->format('d-m-Y') ?? '—' }}</td></tr>
                        <tr><td style="color:var(--text-muted)">Resign Date</td><td>{{ $employee->resign_date?->format('d-m-Y') ?? '—' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-custom mb-3">
                <div class="card-header-custom"><span><i class="fa-solid fa-file-medical me-2" style="color:var(--accent)"></i>BPJS &amp; Pajak</span></div>
                <div class="card-body-custom" style="font-size:13px">
                    <table class="table table-sm mb-0">
                        <tr><td style="width:50%;color:var(--text-muted)">NIK</td><td>{{ $employee->nik ?? '—' }}</td></tr>
                        <tr><td style="color:var(--text-muted)">NPWP</td><td>{{ $employee->npwp_no ?? '—' }}</td></tr>
                        <tr><td style="color:var(--text-muted)">PTKP</td><td>{{ $employee->ptkp_status }}</td></tr>
                        <tr><td style="color:var(--text-muted)">BPJS Kes.</td><td>{{ $employee->bpjs_kesehatan_no ?? '—' }}</td></tr>
                        <tr><td style="color:var(--text-muted)">BPJS TK</td><td>{{ $employee->bpjs_tk_no ?? '—' }}</td></tr>
                    </table>
                </div>
            </div>
            <div class="card-custom">
                <div class="card-header-custom"><span><i class="fa-solid fa-money-bill-wave me-2" style="color:var(--accent)"></i>Gaji &amp; Bank</span></div>
                <div class="card-body-custom" style="font-size:13px">
                    <table class="table table-sm mb-0">
                        <tr><td style="width:50%;color:var(--text-muted)">Gaji Pokok</td><td><strong>Rp {{ number_format((float) $employee->base_salary, 2, ',', '.') }}</strong></td></tr>
                        <tr><td style="color:var(--text-muted)">Bank</td><td>{{ $employee->bank_name ?? '—' }}</td></tr>
                        <tr><td style="color:var(--text-muted)">Rekening</td><td>{{ $employee->bank_account_no ?? '—' }}</td></tr>
                        <tr><td style="color:var(--text-muted)">Nama Rek.</td><td>{{ $employee->bank_account_name ?? '—' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ======================= TAB KELUARGA ======================= --}}
<div class="emp-tab d-none" id="emp-tab-family">
    <div class="card-custom">
        <div class="card-header-custom">
            <span><i class="fa-solid fa-people-roof me-2" style="color:var(--accent)"></i>Data Keluarga</span>
            @if($canCreate)
            <button type="button" class="btn btn-primary btn-sm" onclick="openFamilyModal()"><i class="fa fa-plus me-1"></i>Tambah</button>
            @endif
        </div>
        <div class="card-body-custom p-2">
            <table class="table table-custom align-middle mb-0" id="family-table" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Nama</th>
                        <th>Hubungan</th>
                        <th>Tanggal Lahir</th>
                        <th>Pekerjaan</th>
                        @if($canUpdate || $canDelete)<th class="text-center" style="width:110px">Aksi</th>@endif
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

{{-- ======================= TAB ABSENSI ======================= --}}
<div class="emp-tab d-none" id="emp-tab-attendance">
    <div class="card-custom">
        <div class="card-header-custom">
            <span><i class="fa-solid fa-fingerprint me-2" style="color:var(--accent)"></i>Absensi (read-only)</span>
            <form method="GET" action="{{ route('employee-management.show', $employee->id) }}">
                <input type="hidden" name="tab" value="attendance">
                <div class="d-flex align-items-center gap-2">
                    <input type="month" name="att_month" class="form-control form-control-sm" value="{{ $attendanceMonth }}" onchange="this.form.submit()">
                </div>
            </form>
        </div>
        <div class="card-body-custom p-2">
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:130px">Tanggal</th>
                            <th style="width:120px">Shift</th>
                            <th style="width:110px">Masuk</th>
                            <th style="width:110px">Keluar</th>
                            <th style="width:90px" class="text-center">Telat (mnt)</th>
                            <th style="width:120px">Metode</th>
                            <th style="width:120px">Status</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $att)
                        <tr>
                            <td>{{ $att->work_date?->isoFormat('ddd, DD MMM YYYY') }}</td>
                            <td>{{ $att->shift->name ?? '—' }}</td>
                            <td>{{ $att->clock_in?->format('H:i') ?? '—' }}</td>
                            <td>{{ $att->clock_out?->format('H:i') ?? '—' }}</td>
                            <td class="text-center">{{ $att->late_minutes ?: '—' }}</td>
                            <td><span class="badge bg-light text-dark border" style="font-size:11px">{{ $att->check_in_method }}</span></td>
                            <td><span class="badge" style="font-size:11px;background:rgba(59,130,246,.12);color:#1d4ed8;">{{ $att->status }}</span></td>
                            <td>{{ $att->note ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center" style="color:var(--text-muted)">Belum ada data absensi pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ======================= TAB RIWAYAT ======================= --}}
<div class="emp-tab d-none" id="emp-tab-history">
    <div class="card-custom">
        <div class="card-header-custom"><span><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--accent)"></i>Riwayat Perubahan</span></div>
        <div class="card-body-custom">
            <div class="list-group list-group-flush" style="max-height:60vh;overflow:auto">
                @forelse($logs as $log)
                <div class="list-group-item bg-transparent border-0 border-bottom py-2">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong style="font-size:13px">{{ \Str::title($log->action) }}</strong>
                            <div style="font-size:12px;color:var(--text-muted)">{{ $log->description }}</div>
                        </div>
                        <div class="text-end">
                            <div style="font-size:12px">{{ $log->user?->name ?? '—' }}</div>
                            <div style="font-size:11px;color:var(--text-muted)">{{ $log->created_at->format('d-m-Y H:i') }}</div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="p-3 text-center tiny" style="color:var(--text-muted)">Belum ada riwayat.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ======================= TAB KOMPONEN GAJI ======================= --}}
<div class="emp-tab d-none" id="emp-tab-salary">
    <div class="card-custom">
        <div class="card-header-custom">
            <span><i class="fa-solid fa-coins me-2" style="color:var(--accent)"></i>Override Komponen Gaji (per karyawan)</span>
            @if($canCreate)
            <button type="button" class="btn btn-primary btn-sm" onclick="openSalaryModal()"><i class="fa fa-plus me-1"></i>Tambah Override</button>
            @endif
        </div>
        <div class="card-body-custom p-2">
            <table id="salary-component-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px" class="text-center">#</th>
                        <th style="width:200px">Komponen</th>
                        <th class="text-center" style="width:110px">Type</th>
                        <th class="text-center" style="width:110px">Frekuensi</th>
                        <th class="text-end" style="width:130px">Default</th>
                        <th class="text-end" style="width:130px">Override</th>
                        <th class="text-center" style="width:120px">Aktif sejak</th>
                        <th class="text-center" style="width:100px">Status</th>
                        @if($canUpdate || $canDelete)<th class="text-center" style="width:110px">Aksi</th>@endif
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Modal Edit Profile (form sama dgn wizard, tanpa step) --}}
<div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Edit Karyawan — {{ $employee->name }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="employee-form" autocomplete="off">
                    <ul class="nav nav-tabs mb-3" style="font-size:12px">
                        <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#e-form-personal">Personal</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#e-form-job">Kepegawaian</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#e-form-tax">BPJS/Pajak</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#e-form-salary">Gaji/Bank</button></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="e-form-personal">
                            <div class="mb-3"><label class="form-label">Nama Lengkap <span style="color:var(--danger)">*</span></label>
                                <input type="text" id="employee-name" class="form-control" value="{{ $employee->name }}" maxlength="150" required></div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">Gender</label>
                                        <select id="employee-gender" class="form-select">
                                            <option value="">— Pilih —</option>
                                            <option value="male" @selected($employee->gender === 'male')>Laki-laki</option>
                                            <option value="female" @selected($employee->gender === 'female')>Perempuan</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">Telepon</label>
                                        <input type="text" id="employee-phone" class="form-control" value="{{ $employee->phone }}" maxlength="25"></div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">Tempat Lahir</label>
                                        <input type="text" id="employee-birth-place" class="form-control" value="{{ $employee->birth_place }}" maxlength="100"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">Tanggal Lahir</label>
                                        <input type="date" id="employee-birth-date" class="form-control" value="{{ $employee->birth_date?->toDateString() }}"></div>
                                </div>
                            </div>
                            <div class="mb-3"><label class="form-label">Email</label>
                                <input type="email" id="employee-email" class="form-control" value="{{ $employee->email }}" maxlength="150"></div>
                            <div class="mb-3"><label class="form-label">Alamat</label>
                                <textarea id="employee-address" class="form-control" rows="2">{{ $employee->address }}</textarea></div>
                        </div>
                        <div class="tab-pane fade" id="e-form-job">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">Division</label>
                                        <select id="employee-division-id" class="form-select">
                                            <option value="">— Pilih —</option>
                                            @foreach($divisions as $d)
                                            <option value="{{ $d->id }}" @selected($employee->division_id === $d->id)>{{ $d->division_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">Jabatan</label>
                                        <select id="employee-job-title-id" class="form-select">
                                            <option value="">— Pilih —</option>
                                            @foreach($jobTitles as $jt)
                                            <option value="{{ $jt->id }}" @selected($employee->job_title_id === $jt->id)>{{ $jt->title_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">Atasan Langsung</label>
                                        <select id="employee-manager-id" class="form-select">
                                            @if($employee->manager)
                                            <option value="{{ $employee->manager->id }}" selected>{{ $employee->manager->employee_no }} — {{ $employee->manager->name }}</option>
                                            @endif
                                        </select></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">Join Date</label>
                                        <input type="date" id="employee-join-date" class="form-control" value="{{ $employee->join_date?->toDateString() }}"></div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="e-form-tax">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">NIK</label>
                                        <input type="text" id="employee-nik" class="form-control" value="{{ $employee->nik }}" maxlength="30"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">NPWP</label>
                                        <input type="text" id="employee-npwp-no" class="form-control" value="{{ $employee->npwp_no }}" maxlength="30"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">PTKP Status</label>
                                        <select id="employee-ptkp-status" class="form-select">
                                            @foreach(['TK0','TK1','TK2','TK3','K0','K1','K2','K3'] as $ptkp)
                                            <option value="{{ $ptkp }}" @selected($employee->ptkp_status === $ptkp)>{{ $ptkp }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">No. BPJS Kesehatan</label>
                                        <input type="text" id="employee-bpjs-kes" class="form-control" value="{{ $employee->bpjs_kesehatan_no }}" maxlength="30"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">No. BPJS Tenaga Kerja (JHT)</label>
                                        <input type="text" id="employee-bpjs-tk" class="form-control" value="{{ $employee->bpjs_tk_no }}" maxlength="30"></div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="e-form-salary">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">Gaji Pokok <span style="color:var(--danger)">*</span></label>
                                        <input type="number" id="employee-base-salary" class="form-control" value="{{ $employee->base_salary }}" min="0" step="1000" required></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">Bank</label>
                                        <input type="text" id="employee-bank-name" class="form-control" value="{{ $employee->bank_name }}" maxlength="50"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">No. Rekening</label>
                                        <input type="text" id="employee-bank-account-no" class="form-control" value="{{ $employee->bank_account_no }}" maxlength="30"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">Nama Pemilik Rekening</label>
                                        <input type="text" id="employee-bank-account-name" class="form-control" value="{{ $employee->bank_account_name }}" maxlength="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success btn-sm" id="btn-save-employee">
                    <i class="fa fa-save me-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Salary Override --}}
<div class="modal fade" id="salaryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="salaryModalTitle">Tambah Override Komponen</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="salary-form" autocomplete="off">
                    <input type="hidden" id="sed-edit-id" value="">
                    <div class="mb-3">
                        <label class="form-label">Komponen <span style="color:var(--danger)">*</span></label>
                        <select id="sed-component" class="form-select" required></select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Override Amount (opsional)</label>
                                <input type="number" id="sed-amount" class="form-control" min="0" step="10000" placeholder="kosong = nilai default master">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Aktif sejak</label>
                                <input type="date" id="sed-active-since" class="form-control">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-sed"><i class="fa fa-save me-1"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Keluarga --}}
<div class="modal fade" id="familyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="familyModalTitle">Tambah Keluarga</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="family-form" autocomplete="off">
                    <input type="hidden" id="family-edit-id" value="">
                    <div class="mb-3"><label class="form-label">Nama <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="family-name" class="form-control" maxlength="150" required></div>
                    <div class="mb-3"><label class="form-label">Hubungan <span style="color:var(--danger)">*</span></label>
                        <select id="family-relation" class="form-select" required>
                            <option value="spouse">Pasangan</option>
                            <option value="child">Anak</option>
                            <option value="parent">Orang Tua</option>
                        </select></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3"><label class="form-label">Tanggal Lahir</label>
                                <input type="date" id="family-birth-date" class="form-control"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3"><label class="form-label">Pekerjaan</label>
                                <input type="text" id="family-occupation" class="form-control" maxlength="100"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-family"><i class="fa fa-save me-1"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
const employeeId = {{ $employee->id }};
const employeeUpdateUrl = '{{ route("employee-management.update", ["employee_management" => ":id"]) }}'.replace('/employee-management/:id', '/employee-management/' + {{ $employee->id }});
const managerSearchUrl = '{{ route("employee-management.search-managers") }}';
const terminateUrl = '{{ route("employee-management.terminate", $employee->id) }}';
const reactivateUrl = '{{ route("employee-management.reactivate", $employee->id) }}';
const familyListUrl = '{{ route("employee-management.families", $employee->id) }}';
const familyStoreUrl = '{{ route("employee-management.families.store", $employee->id) }}';
const familyUpdateUrlTemplate = '{{ route("employee-management.families.update", [$employee->id, ":fid"]) }}';
const familyDeleteUrlTemplate = '{{ route("employee-management.families.destroy", [$employee->id, ":fid"]) }}';

let employeeModalInstance = null;
let familyModalInstance = null;
let managerSelect2 = null;

// --- tabs
document.querySelectorAll('[data-tab-tab]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('[data-tab-tab]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.emp-tab').forEach(p => p.classList.add('d-none'));
        document.getElementById('emp-tab-' + btn.dataset.tabTab).classList.remove('d-none');
    });
});

// buka tab dari query param
(function() {
    var params = new URLSearchParams(window.location.search);
    var tab = params.get('tab');
    if (tab) {
        var target = document.querySelector('[data-tab-tab="' + tab + '"]');
        if (target) { target.click(); }
    }
})();

// --- Profil edit
function openEditModal() {
    if (!employeeModalInstance) {
        employeeModalInstance = new bootstrap.Modal(document.getElementById('employeeModal'));
    }
    employeeModalInstance.show();
}

document.addEventListener('DOMContentLoaded', function() {
    managerSelect2 = $('#employee-manager-id').select2({
        dropdownParent: $('#employeeModal'),
        allowClear: true,
        placeholder: '— Pilih —',
        width: '100%'
    }).on('select2:select select2:clear', function(e) {
        var newId = e.params?.data?.id || null;
        if (newId !== null && String(newId) === String(employeeId)) {
            $(this).val(null).trigger('change');
            toastr.error('Karyawan tidak bisa menjadi atasan dirinya sendiri.');
        }
    });

    $('#btn-save-employee').on('click', function() {
        if (!$('#employee-name').val().trim()) {
            toastr.error('Nama karyawan wajib diisi.');
            return;
        }
        var payload = {
            name: $('#employee-name').val().trim(),
            gender: $('#employee-gender').val() || null,
            birth_date: $('#employee-birth-date').val() || null,
            birth_place: $('#employee-birth-place').val().trim(),
            address: $('#employee-address').val().trim(),
            phone: $('#employee-phone').val().trim(),
            email: $('#employee-email').val().trim(),
            division_id: $('#employee-division-id').val() || null,
            job_title_id: $('#employee-job-title-id').val() || null,
            manager_id: $('#employee-manager-id').val() || null,
            join_date: $('#employee-join-date').val() || null,
            nik: $('#employee-nik').val().trim(),
            npwp_no: $('#employee-npwp-no').val().trim(),
            ptkp_status: $('#employee-ptkp-status').val(),
            bpjs_kesehatan_no: $('#employee-bpjs-kes').val().trim(),
            bpjs_tk_no: $('#employee-bpjs-tk').val().trim(),
            base_salary: $('#employee-base-salary').val() || 0,
            bank_name: $('#employee-bank-name').val().trim(),
            bank_account_no: $('#employee-bank-account-no').val().trim(),
            bank_account_name: $('#employee-bank-account-name').val().trim()
        };
        $('#btn-save-employee').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...');
        $.ajax({
            url: employeeUpdateUrl,
            method: 'PUT',
            data: payload,
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Data karyawan diperbarui.');
            employeeModalInstance.hide();
            setTimeout(() => location.reload(), 600);
        }).fail(function(xhr) {
            var msg = xhr.responseJSON?.message || 'Terjadi kesalahan.';
            if (xhr.responseJSON?.errors) {
                msg = Object.values(xhr.responseJSON.errors)[0][0];
            }
            toastr.error(msg);
        }).always(function() {
            $('#btn-save-employee').prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan');
        });
    });
});

// --- Terminate / Activate

// --- Komponen Gaji (tab salary)
const salaryComponentsUrl = '{{ route("employee-management.salary-components", $employee->id) }}';
const salaryComponentsStoreUrl = '{{ route("employee-management.salary-components.store", $employee->id) }}';
const salaryComponentsUpdateUrlTemplate = '{{ route("employee-management.salary-components.update", [$employee->id, ":id"]) }}';
const salaryComponentsDeleteUrlTemplate = '{{ route("employee-management.salary-components.destroy", [$employee->id, ":id"]) }}';

let salaryData = [];
let salaryMasters = [];
let salaryTableInited = false;
let salaryModalInstance = null;
let salaryComponentSelect2 = null;

function initSalaryTable() {
    if (salaryTableInited) return;
    salaryTableInited = true;

    $('#salary-component-table').DataTable({
        destroy: true,
        data: [],
        columns: [
            { data: null, orderable: false, searchable: false, className: 'text-center',
                render: (d, t, r, m) => m.row + 1 },
            { data: null, searchable: false,
                render: d => d.component ? '<strong>' + d.component.code + '</strong> — ' + d.component.name : '—' },
            { data: null, searchable: false, className: 'text-center',
                render: d => d.component ? (d.component.type || '—') : '—' },
            { data: null, searchable: false, className: 'text-center',
                render: d => d.component ? (d.component.frequency || '—') : '—' },
            { data: null, searchable: false, className: 'text-end',
                render: d => d.component ? 'Rp ' + Number(d.component.default_amount).toLocaleString('id-ID') : '—' },
            { data: 'override_amount', searchable: false, className: 'text-end',
                render: d => d ? 'Rp ' + Number(d).toLocaleString('id-ID') : '<span style="color:var(--text-muted)">—</span>' },
            { data: 'active_since', searchable: false, className: 'text-center', render: d => d || '—' },
            { data: 'is_active', searchable: false, className: 'text-center',
                render: d => d
                    ? '<span class="status-badge status-active">Aktif</span>'
                    : '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Inaktif</span>' },
            @if($canUpdate || $canDelete)
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    @if($canUpdate)
                    btn += '<button class="btn-icon" title="Edit" onclick="openSalaryModal(' + data + ')"><i class="fa-solid fa-pen"></i></button>';
                    @endif
                    @if($canDelete)
                    btn += '<button class="btn-icon danger" title="Hapus" onclick="deleteSalaryOverride(' + data + ')"><i class="fa-solid fa-trash-can"></i></button>';
                    @endif
                    btn += '</div>';
                    return btn;
                }
            }
            @endif
        ]
    });
}

function loadSalaryComponents() {
    $.get(salaryComponentsUrl, function(res) {
        salaryData = res.data || [];
        salaryMasters = res.masters || [];

        $('#salary-component-table').DataTable({
            destroy: true,
            data: salaryData,
            columns: [
                { data: null, orderable: false, searchable: false, className: 'text-center',
                    render: (d, t, r, m) => m.row + 1 },
                { data: null, searchable: false, orderable: false,
                    render: d => d.component ? '<strong>' + d.component.code + '</strong><br><span style="font-size:11.5px;color:var(--text-muted)">' + d.component.name + '</span>' : '—' },
                { data: null, searchable: false, orderable: false, className: 'text-center',
                    render: d => d.component ? d.component.type : '—' },
                { data: null, searchable: false, orderable: false, className: 'text-center',
                    render: d => d.component ? d.component.frequency : '—' },
                { data: null, searchable: false, orderable: false, className: 'text-end',
                    render: d => d.component ? 'Rp ' + Number(d.component.default_amount).toLocaleString('id-ID') : '—' },
                { data: 'override_amount', searchable: false, orderable: false, className: 'text-end',
                    render: d => d ? 'Rp ' + Number(d).toLocaleString('id-ID') : '<span style="color:var(--text-muted)">—</span>' },
                { data: 'active_since', searchable: false, orderable: false, className: 'text-center', render: d => d || '—' },
                { data: 'is_active', searchable: false, orderable: false, className: 'text-center',
                    render: d => d
                        ? '<span class="status-badge status-active">Aktif</span>'
                        : '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Inaktif</span>' },
                @if($canUpdate || $canDelete)
                { data: 'id', orderable: false, searchable: false, className: 'text-center',
                    render: function(data, t, row) {
                        var btn = '<div class="d-flex justify-content-center gap-1">';
                        @if($canUpdate)
                        btn += '<button class="btn-icon" title="Edit" onclick="openSalaryModal(' + data + ')"><i class="fa-solid fa-pen"></i></button>';
                        @endif
                        @if($canDelete)
                        btn += '<button class="btn-icon danger" title="Hapus" onclick="deleteSalaryOverride(' + data + ')"><i class="fa-solid fa-trash-can"></i></button>';
                        @endif
                        btn += '</div>';
                        return btn;
                    }
                }
                @endif
            ]
        });
    });
}

function openSalaryModal(id) {
    document.getElementById('salary-form').reset();
    $('#sed-edit-id').val('');

    // populate master select
    var select = $('#sed-component');
    select.empty();
    salaryMasters.forEach(m => select.append(new Option(m.text + ' (default Rp ' + Number(m.default_amount).toLocaleString('id-ID') + ')', m.id)));
    select.val(null).trigger('change');

    if (id) {
        var row = salaryData.find(s => s.id === id);
        if (!row) return;
        $('#salaryModalTitle').text('Edit Override Komponen');
        $('#sed-edit-id').val(row.id);
        select.val(row.component?.id).trigger('change');
        $('#sed-amount').val(row.override_amount || '');
        $('#sed-active-since').val(row.active_since || '');
    }

    if (!salaryModalInstance) salaryModalInstance = new bootstrap.Modal(document.getElementById('salaryModal'));
    salaryModalInstance.show();
}

$('#btn-save-sed').on('click', function() {
    var id = $('#sed-edit-id').val();
    var payload = {
        salary_component_id: $('#sed-component').val(),
        override_amount: $('#sed-amount').val() || null,
        active_since: $('#sed-active-since').val() || null
    };
    if (!payload.salary_component_id) { toastr.error('Komponen wajib dipilih.'); return; }

    $.ajax({
        url: id ? salaryComponentsUpdateUrlTemplate.replace(':id', id) : salaryComponentsStoreUrl,
        method: id ? 'PUT' : 'POST',
        data: payload,
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(res => {
        toastr.success(res.message);
        salaryModalInstance.hide();
        loadSalaryComponents();
    }).fail(err => {
        var msg = err.responseJSON?.message || 'Gagal menyimpan override.';
        if (err.responseJSON?.errors) msg = Object.values(err.responseJSON.errors)[0][0];
        toastr.error(msg);
    });
});

function deleteSalaryOverride(id) {
    Swal.fire({
        title: 'Hapus override?',
        text: 'Komponen kembali ke nilai default master.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.ajax({
            url: salaryComponentsDeleteUrlTemplate.replace(':id', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(res => { toastr.success(res.message); loadSalaryComponents(); });
    });
}

document.querySelector('[data-tab-tab="salary"]')?.addEventListener('click', function() {
    loadSalaryComponents();
});

function terminateEmployee() {
    Swal.fire({
        title: 'Akhir Kepegawaian?',
        text: 'Status karyawan akan berubah menjadi terminated. Data histori tetap aman.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Nonaktifkan',
        cancelButtonText: 'Batal'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.ajax({ url: terminateUrl, method: 'PUT', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
        .done(res => { toastr.success(res.message); setTimeout(() => location.reload(), 500); })
        .fail(err => toastr.error(err.responseJSON?.message || 'Gagal menonaktifkan.'));
    });
}

function reactivateEmployee() {
    Swal.fire({
        title: 'Aktifkan kembali?',
        text: 'Status karyawan akan kembali menjadi active.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Aktifkan',
        cancelButtonText: 'Batal'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.ajax({ url: reactivateUrl, method: 'PUT', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
        .done(res => { toastr.success(res.message); setTimeout(() => location.reload(), 500); })
        .fail(err => toastr.error(err.responseJSON?.message || 'Gagal aktivasi.'));
    });
}

// --- Keluarga
const relationLabel = { spouse: 'Pasangan', child: 'Anak', parent: 'Orang Tua' };

function initFamilyTable() {
    $('#family-table').DataTable({
        destroy: true,
        data: [],
        serverSide: false,
        ajax: {
            url: familyListUrl,
            dataSrc: 'data'
        },
        order: [],
        columns: [
            { data: null, orderable: false, searchable: false, className: 'text-center',
                render: (d, t, r, meta) => meta.row + 1 },
            { data: 'name', render: d => '<strong>' + d + '</strong>' },
            { data: 'relation', render: d => relationLabel[d] || d },
            { data: 'birth_date', orderable: false, searchable: false, render: d => d || '—' },
            { data: 'occupation', orderable: false, searchable: false, render: d => d || '—' },
            @if($canUpdate || $canDelete)
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, t, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    @if($canUpdate)
                    btn += '<button class="btn-icon" title="Edit" onclick="openFamilyModal(' + data + ')"><i class="fa-solid fa-pen"></i></button>';
                    @endif
                    @if($canDelete)
                    btn += '<button class="btn-icon danger" title="Hapus" onclick="deleteFamily(' + data + ', \'' + row.name + '\')"><i class="fa-solid fa-trash-can"></i></button>';
                    @endif
                    btn += '</div>';
                    return btn;
                }
            }
            @endif
        ]
    });
}

function openFamilyModal(id) {
    document.getElementById('family-form').reset();
    $('#family-edit-id').val('');
    $('#family-form .is-invalid').removeClass('is-invalid');

    if (id) {
        $.get(familyListUrl, function(res) {
            var family = res.data.find(f => f.id === id);
            if (!family) return;
            $('#familyModalTitle').text('Edit Keluarga — ' + family.name);
            $('#family-edit-id').val(family.id);
            $('#family-name').val(family.name || '');
            $('#family-relation').val(family.relation || 'spouse');
            $('#family-birth-date').val(family.birth_date || '');
            $('#family-occupation').val(family.occupation || '');
        });
    }

    if (!familyModalInstance) {
        familyModalInstance = new bootstrap.Modal(document.getElementById('familyModal'));
    }
    familyModalInstance.show();
}

$('#btn-save-family').on('click', function() {
    var id = $('#family-edit-id').val();
    var payload = {
        name: $('#family-name').val().trim(),
        relation: $('#family-relation').val(),
        birth_date: $('#family-birth-date').val() || null,
        occupation: $('#family-occupation').val().trim()
    };
    if (!payload.name) { toastr.error('Nama wajib diisi.'); return; }

    $.ajax({
        url: id ? familyUpdateUrlTemplate.replace(':fid', id) : familyStoreUrl,
        method: id ? 'PUT' : 'POST',
        data: payload,
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(res => {
        toastr.success(res.message || 'Tersimpan.');
        familyModalInstance.hide();
        $('#family-table').DataTable().ajax.reload();
    }).fail(err => {
        toastr.error(err.responseJSON?.message || 'Gagal menyimpan.');
    });
});

function deleteFamily(id, name) {
    Swal.fire({
        title: 'Hapus Keluarga?',
        text: '"' + name + '" akan dihapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.ajax({
            url: familyDeleteUrlTemplate.replace(':fid', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(res => {
            toastr.success(res.message || 'Terhapus.');
            $('#family-table').DataTable().ajax.reload();
        }).fail(err => toastr.error(err.responseJSON?.message || 'Gagal menghapus.'));
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('[data-tab-tab="family"]').addEventListener('click', function() {
        initFamilyTable();
    });
});
</script>
@endsection
