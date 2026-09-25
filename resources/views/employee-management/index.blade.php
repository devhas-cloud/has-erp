@extends('layouts.app')

@section('title', 'Data Karyawan')
@section('page-title', 'Data Karyawan')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Data Karyawan</h1>
        <p class="page-header-sub">Master data karyawan — profil, struktur, BPJS/pajak, dan data gaji</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i>
            <span>Tambah Karyawan</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-users me-2" style="color:var(--accent)"></i>Daftar Karyawan</span>
        <div class="d-flex align-items-center gap-2">
            <select id="filter-status" class="form-select form-select-sm" style="width:170px">
                <option value="">Semua Status</option>
                <option value="active">Active</option>
                <option value="on_leave">On Leave</option>
                <option value="terminated">Terminated</option>
            </select>
        </div>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="employee-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width:110px">No. Pegawai</th>
                        <th style="width:200px">Nama</th>
                        <th style="width:140px">Division</th>
                        <th style="width:160px">Jabatan</th>
                        <th style="width:120px">Join Date</th>
                        <th style="width:140px">Gaji Pokok</th>
                        <th style="width:110px">Status</th>
                        <th class="text-center" style="width:130px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Modal Create / Edit — Wizard 4 Langkah --}}
<div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="employeeModalTitle">Tambah Karyawan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="employee-form" autocomplete="off">
                    <input type="hidden" id="employee-edit-id" value="">

                    {{-- Stepper --}}
                    <ul class="nav nav-pills mb-3" id="employee-wizard-nav" style="font-size:12px">
                        <li class="nav-item w-25 text-center"><button type="button" class="nav-link w-100 active" data-step="1">1. Personal</button></li>
                        <li class="nav-item w-25 text-center"><button type="button" class="nav-link w-100" data-step="2" disabled>2. Kepegawaian</button></li>
                        <li class="nav-item w-25 text-center"><button type="button" class="nav-link w-100" data-step="3" disabled>3. BPJS/Pajak</button></li>
                        <li class="nav-item w-25 text-center"><button type="button" class="nav-link w-100" data-step="4" disabled>4. Gaji/Bank</button></li>
                    </ul>

                    {{-- Step 1: Personal --}}
                    <div class="wizard-step" data-step="1">
                        <div class="mb-3">
                            <label class="form-label">No. Pegawai <span style="color:var(--danger)">*</span></label>
                            <input type="text" id="employee-no" class="form-control" maxlength="20" placeholder="Contoh: EMP-0001" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span style="color:var(--danger)">*</span></label>
                            <input type="text" id="employee-name" class="form-control" maxlength="150" placeholder="Nama lengkap karyawan" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Gender</label>
                                    <select id="employee-gender" class="form-select">
                                        <option value="">— Pilih —</option>
                                        <option value="male">Laki-laki</option>
                                        <option value="female">Perempuan</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Telepon</label>
                                    <input type="text" id="employee-phone" class="form-control" maxlength="25">
                                </div>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tempat Lahir</label>
                                    <input type="text" id="employee-birth-place" class="form-control" maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Lahir</label>
                                    <input type="date" id="employee-birth-date" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" id="employee-email" class="form-control" maxlength="150">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Alamat</label>
                                    <textarea id="employee-address" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 2: Kepegawaian --}}
                    <div class="wizard-step d-none" data-step="2">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Division</label>
                                    <select id="employee-division-id" class="form-select">
                                        <option value="">— Pilih —</option>
                                        @foreach($divisions as $d)
                                        <option value="{{ $d->id }}">{{ $d->division_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jabatan</label>
                                    <select id="employee-job-title-id" class="form-select">
                                        <option value="">— Pilih —</option>
                                        @foreach($jobTitles as $jt)
                                        <option value="{{ $jt->id }}">{{ $jt->title_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Atasan Langsung (Manager)</label>
                                    <select id="employee-manager-id" class="form-select"></select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Join Date</label>
                                    <input type="date" id="employee-join-date" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 3: BPJS/Pajak --}}
                    <div class="wizard-step d-none" data-step="3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">NIK (No KTP)</label>
                                    <input type="text" id="employee-nik" class="form-control" maxlength="30">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">NPWP</label>
                                    <input type="text" id="employee-npwp-no" class="form-control" maxlength="30">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">PTKP Status</label>
                                    <select id="employee-ptkp-status" class="form-select">
                                        @foreach(['TK0','TK1','TK2','TK3','K0','K1','K2','K3'] as $ptkp)
                                        <option value="{{ $ptkp }}">{{ $ptkp }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">No. BPJS Kesehatan</label>
                                    <input type="text" id="employee-bpjs-kes" class="form-control" maxlength="30">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">No. BPJS Tenaga Kerja (JHT)</label>
                                    <input type="text" id="employee-bpjs-tk" class="form-control" maxlength="30">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 4: Gaji/Bank --}}
                    <div class="wizard-step d-none" data-step="4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Gaji Pokok <span style="color:var(--danger)">*</span></label>
                                    <input type="number" id="employee-base-salary" class="form-control" min="0" step="1000" placeholder="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Bank</label>
                                    <input type="text" id="employee-bank-name" class="form-control" maxlength="50" placeholder="Contoh: BCA">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">No. Rekening</label>
                                    <input type="text" id="employee-bank-account-no" class="form-control" maxlength="30">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Pemilik Rekening</label>
                                    <input type="text" id="employee-bank-account-name" class="form-control" maxlength="100">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" id="btn-wizard-prev"><i class="fa fa-arrow-left me-1"></i> Sebelumnya</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-wizard-next">Selanjutnya <i class="fa fa-arrow-right"></i></button>
                <button type="button" class="btn btn-success btn-sm d-none" id="btn-save-employee">
                    <i class="fa fa-save me-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let employeeModalInstance = null;
let employeeTable = null;
let managerSelect2 = null;
let currentStep = 1;

const employeeEditUrl = '{{ route("employee-management.edit", "__ID__") }}';
const employeeUpdateUrl = '{{ route("employee-management.update", "__ID__") }}';
const managerSearchUrl = '{{ route("employee-management.search-managers") }}';

const statusBadges = {
    active: '<span class="status-badge status-active">Active</span>',
    on_leave: '<span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">On Leave</span>',
    terminated: '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Terminated</span>'
};

function initEmployeeTable() {
    if (employeeTable) {
        employeeTable.destroy();
    }

    employeeTable = $('#employee-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("employee-management.data") }}',
            data: function(d) {
                d.status = $('#filter-status').val();
            }
        },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'employee_no', orderable: false, searchable: true,
                render: d => '<span style="font-family:monospace;color:var(--accent)">' + d + '</span>' },
            { data: 'name', orderable: false, searchable: true,
                render: (d, t, r) => '<a href="{{ url("employee-management") }}/' + r.id + '" style="color:var(--text-primary);text-decoration:none;"><strong>' + d + '</strong></a>' },
            { data: 'division', orderable: false, searchable: false,
                render: d => d || '<span style="color:var(--text-muted)">—</span>' },
            { data: 'job_title', orderable: false, searchable: false,
                render: d => d || '<span style="color:var(--text-muted)">—</span>' },
            { data: 'join_date', orderable: false, searchable: false,
                render: d => d || '<span style="color:var(--text-muted)">—</span>' },
            { data: 'base_salary', orderable: false, searchable: false, className: 'text-end' },
            { data: 'status', orderable: false, searchable: false,
                render: d => statusBadges[d] || d },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    btn += '<a class="btn-icon" title="Detail" href="{{ url("employee-management") }}/' + data + '"><i class="fa-solid fa-eye"></i></a>';
                    @if($canUpdate)
                    btn += '<button class="btn-icon" title="Edit" onclick="openEditModal(' + data + ')"><i class="fa-solid fa-pen"></i></button>';
                    @endif
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function setWizardStep(step) {
    currentStep = step;
    $('#employee-form .wizard-step').each(function() {
        $(this).toggleClass('d-none', $(this).data('step') !== step);
    });
    $('#employee-wizard-nav .nav-link').each(function() {
        var s = parseInt($(this).data('step'));
        $(this).toggleClass('active', s === step);
        $(this).prop('disabled', s > step);
    });
    $('#btn-wizard-prev').toggleClass('d-none', step === 1);
    $('#btn-wizard-next').toggleClass('d-none', step === 4);
    $('#btn-save-employee').toggleClass('d-none', step !== 4);
}

function resetEmployeeForm() {
    document.getElementById('employee-form').reset();
    $('#employee-form .is-invalid').removeClass('is-invalid');
    $('#employee-edit-id').val('');
    $('#employee-manager-id').val(null).trigger('change');
    currentStep = 1;
    setWizardStep(1);
    $('#employee-wizard-nav .nav-link').removeClass('active');
    $('#employee-wizard-nav .nav-link').eq(0).addClass('active');
}

function openCreateModal() {
    resetEmployeeForm();
    document.getElementById('employeeModalTitle').textContent = 'Tambah Karyawan';
    showEmployeeModal();
}

function openEditModal(id) {
    resetEmployeeForm();
    document.getElementById('employeeModalTitle').textContent = 'Edit Karyawan';

    $.get(employeeEditUrl.replace('__ID__', id), function(res) {
        var s = res.data;
        $('#employee-edit-id').val(s.id);
        $('#employee-no').val(s.employee_no || '');
        $('#employee-name').val(s.name || '');
        $('#employee-gender').val(s.gender || '');
        $('#employee-phone').val(s.phone || '');
        $('#employee-birth-place').val(s.birth_place || '');
        $('#employee-birth-date').val(s.birth_date || '');
        $('#employee-email').val(s.email || '');
        $('#employee-address').val(s.address || '');
        $('#employee-division-id').val(s.division_id || '');
        $('#employee-job-title-id').val(s.job_title_id || '');
        if (managerSelect2) {
            managerSelect2.val(null).trigger('change');
            if (s.manager_id && s.manager) {
                var option = new Option(s.manager.employee_no + ' — ' + s.manager.name, s.manager.id, true, true);
                managerSelect2.append(option).trigger('change');
            }
        }
        $('#employee-join-date').val(s.join_date || '');
        $('#employee-nik').val(s.nik || '');
        $('#employee-npwp-no').val(s.npwp_no || '');
        $('#employee-ptkp-status').val(s.ptkp_status || 'TK0');
        $('#employee-bpjs-kes').val(s.bpjs_kesehatan_no || '');
        $('#employee-bpjs-tk').val(s.bpjs_tk_no || '');
        $('#employee-base-salary').val(s.base_salary || 0);
        $('#employee-bank-name').val(s.bank_name || '');
        $('#employee-bank-account-no').val(s.bank_account_no || '');
        $('#employee-bank-account-name').val(s.bank_account_name || '');

        document.getElementById('employeeModalTitle').textContent = 'Edit Karyawan — ' + s.name;
        showEmployeeModal();
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat data karyawan.');
    });
}

function showEmployeeModal() {
    if (!employeeModalInstance) {
        employeeModalInstance = new bootstrap.Modal(document.getElementById('employeeModal'));
    }
    employeeModalInstance.show();
}

function collectPayload() {
    return {
        employee_no: $('#employee-no').val().trim(),
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
}

function saveEmployee() {
    var selfId = $('#employee-edit-id').val();
    var mgr = $('#employee-manager-id').val();
    if (selfId && mgr && String(mgr) === String(selfId)) {
        toastr.error('Karyawan tidak bisa menjadi atasan dirinya sendiri.');
        return;
    }
    if (!$('#employee-no').val().trim()) {
        toastr.error('No. pegawai wajib diisi.');
        setWizardStep(1);
        $('#employee-no').addClass('is-invalid');
        return;
    }
    if (!$('#employee-name').val().trim()) {
        toastr.error('Nama karyawan wajib diisi.');
        setWizardStep(1);
        $('#employee-name').addClass('is-invalid');
        return;
    }
    if (!$('#employee-base-salary').val()) {
        toastr.error('Gaji pokok wajib diisi. Isi 0 bila belum ditentukan.');
        setWizardStep(4);
        $('#employee-base-salary').addClass('is-invalid');
        return;
    }

    var id = $('#employee-edit-id').val();
    var isEdit = !!id;

    $('#btn-save-employee').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...');

    $.ajax({
        url: isEdit ? employeeUpdateUrl.replace('__ID__', id) : '{{ route("employee-management.store") }}',
        method: isEdit ? 'PUT' : 'POST',
        data: collectPayload(),
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(function(res) {
        toastr.success(res.message || 'Karyawan berhasil disimpan.');
        employeeModalInstance.hide();
        employeeTable.ajax.reload();
    }).fail(function(xhr) {
        var msg = 'Terjadi kesalahan saat menyimpan.';
        if (xhr.responseJSON) {
            if (xhr.responseJSON.errors) {
                var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                msg = xhr.responseJSON.errors[firstKey][0];
            } else if (xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
        }
        toastr.error(msg);
    }).always(function() {
        $('#btn-save-employee').prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initEmployeeTable();

    managerSelect2 = $('#employee-manager-id').select2({
        dropdownParent: $('#employeeModal'),
        allowClear: true,
        placeholder: '— Pilih —',
        width: '100%',
        minimumInputLength: 0,
        ajax: {
            url: managerSearchUrl,
            dataType: 'json',
            data: p => ({ q: p.term || '', except_id: $('#employee-edit-id').val() || '' }),
            processResults: d => d
        }
    });

    $('#filter-status').on('change', function() {
        employeeTable.ajax.reload();
    });

    $('#btn-wizard-next').on('click', function() {
        if (currentStep === 1 && !$('#employee-no').val().trim()) {
            toastr.error('No. pegawai wajib diisi sebelum lanjut.');
            $('#employee-no').addClass('is-invalid');
            return;
        }
        if (currentStep === 1 && !$('#employee-name').val().trim()) {
            toastr.error('Nama karyawan wajib diisi sebelum lanjut.');
            $('#employee-name').addClass('is-invalid');
            return;
        }
        setWizardStep(Math.min(currentStep + 1, 4));
    });

    $('#btn-wizard-prev').on('click', function() {
        setWizardStep(Math.max(currentStep - 1, 1));
    });

    $('#btn-save-employee').on('click', saveEmployee);
});
</script>
@endsection
