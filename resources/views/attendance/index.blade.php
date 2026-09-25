@extends('layouts.app')

@section('title', 'Absensi Karyawan')
@section('page-title', 'Absensi Karyawan')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Absensi Karyawan</h1>
        <p class="page-header-sub">Absensi multi-metode: manual, device fingerprint (import), GPS, dan face — untuk Android</p>
    </div>
    <div class="page-header-actions">
        @if($canCreate)
        <button type="button" class="btn-accent" onclick="openManualModal()">
            <i class="fa fa-fingerprint"></i><span>Absen Manual</span>
        </button>
        <button type="button" class="btn-accent" onclick="openImportModal()">
            <i class="fa fa-file-import"></i><span>Import Device</span>
        </button>
        @endif
        <a href="{{ route('attendance.office') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-building me-1"></i> Master Office &amp; Shift</a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-body-custom py-2">
        <div class="row g-2">
            <div class="col-auto"><input type="month" id="filter-month" class="form-control form-control-sm" value="{{ now()->format('Y-m') }}"></div>
            <div class="col-auto">
                <select id="filter-status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    @foreach(['pending','present','late','leave','sick','absent'] as $s)
                    <option value="{{ $s }}">{{ \Str::title($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select id="filter-method" class="form-control form-control-sm">
                    <option value="">Semua Metode</option>
                    @foreach(['fingerprint','gps','face','manual','leave_integration'] as $m)
                    <option value="{{ $m }}">{{ \Str::title($m) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-fingerprint me-2" style="color:var(--accent)"></i>Data Absensi</span>
        @if($canCreate)
        <button type="button" class="btn btn-outline-warning btn-sm" onclick="openCorrectionModal()"><i class="fa-solid fa-pen-clip me-1"></i> Ajukan Koreksi</button>
        @endif
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="attendance-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width:120px">Tanggal</th>
                        <th style="width:200px">Karyawan</th>
                        <th style="width:110px">Shift</th>
                        <th style="width:90px">Masuk</th>
                        <th style="width:90px">Keluar</th>
                        <th style="width:100px" class="text-center">Telat(mnt)</th>
                        <th style="width:120px">Metode</th>
                        <th style="width:110px">Status</th>
                        <th class="text-center" style="width:90px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

{{-- Panel Koreksi --}}
<div class="card-custom fade-in mt-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-pen-clip me-2" style="color:var(--accent)"></i>Pengajuan Koreksi Absensi</span>
        <select id="correction-status" class="form-select form-select-sm" style="width:170px">
            <option value="">Semua Status</option>
            @foreach(['pending','approved','rejected'] as $s)
            <option value="{{ $s }}">{{ \Str::title($s) }}</option>
            @endforeach
        </select>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="correction-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width:200px">Karyawan</th>
                        <th style="width:130px">Tanggal</th>
                        <th style="width:110px">Masuk</th>
                        <th style="width:110px">Keluar</th>
                        <th style="width:220px">Alasan</th>
                        <th style="width:110px">Status</th>
                        @if($canApprove)<th class="text-center" style="width:130px">Aksi</th>@endif
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Modal Koreksi Absensi --}}
<div class="modal fade" id="correctionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Ajukan Koreksi Absensi</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="corr-form" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">Karyawan <span style="color:var(--danger)">*</span></label>
                        <select id="corr-employee" class="form-select" required></select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tanggal <span style="color:var(--danger)">*</span></label>
                                <input type="date" id="corr-date" class="form-control" value="{{ now()->toDateString() }}" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Jam Masuk</label>
                                <input type="time" id="corr-in" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Jam Keluar</label>
                                <input type="time" id="corr-out" class="form-control">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Alasan koreksi <span style="color:var(--danger)">*</span></label>
                                <textarea id="corr-reason" class="form-control" rows="2" maxlength="500" required></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-warning btn-sm" id="btn-save-corr"><i class="fa fa-paper-plane me-1"></i> Kirim</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal alasan reject corrections --}}
<div class="modal fade" id="corrRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Alasan Penolakan Koreksi <span style="color:var(--danger)">*</span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <textarea id="corr-reject-note" class="form-control" rows="3" placeholder="Alasan penolakan koreksi"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-confirm-corr-reject">Tolak</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Absen Manual --}}
<div class="modal fade" id="manualModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Absen Manual</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="manual-form" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">Karyawan <span style="color:var(--danger)">*</span></label>
                        <select id="manual-employee" class="form-select" required></select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tanggal <span style="color:var(--danger)">*</span></label>
                                <input type="date" id="manual-date" class="form-control" value="{{ now()->toDateString() }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Shift</label>
                                <select id="manual-shift" class="form-select">
                                    <option value="">— Tanpa shift —</option>
                                    @foreach($shifts as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jam Masuk</label>
                                <input type="time" id="manual-clock-in" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jam Keluar</label>
                                <input type="time" id="manual-clock-out" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Status <span style="color:var(--danger)">*</span></label>
                                <select id="manual-status" class="form-select" required>
                                    <option value="present">Hadir</option>
                                    <option value="late">Terlambat</option>
                                    <option value="leave">Izin/Cuti</option>
                                    <option value="sick">Sakit</option>
                                    <option value="absent">Alpa</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Catatan</label>
                                <textarea id="manual-note" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-manual"><i class="fa fa-save me-1"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Import Device --}}
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Import Log Perangkat (CSV/Excel)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" style="font-size:13px;color:var(--text-muted)">
                    Baris: <code>NIP / EMP-xxxx</code>, <code>YYYY-MM-DD HH:MM[:SS]</code>. Baris pertama diabaikan bila berupa header.
                    Baris absen akan ber-status <strong>pending</strong> untuk di-approve.
                </p>
                <input type="file" id="import-file" class="form-control" accept=".csv,.xlsx,.xls">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-import"><i class="fa fa-upload me-1"></i> Import</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let attendanceTable = null;
let manualModalInstance = null;
let importModalInstance = null;

const approvalMap = {
    pending: '<span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">Pending</span>',
    present: '<span class="status-badge status-active">Present</span>',
    late: '<span class="status-badge" style="background:rgba(168,85,247,.12);color:#7e22ce;">Late</span>',
    leave: '<span class="status-badge" style="background:rgba(59,130,246,.12);color:#1d4ed8;">Leave</span>',
    sick: '<span class="status-badge" style="background:rgba(14,165,233,.12);color:#0369a1;">Sick</span>',
    absent: '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Absent</span>'
};

const methodIcon = {
    fingerprint: '<i class="fa-solid fa-fingerprint" title="Sidik jari"></i>',
    gps: '<i class="fa-solid fa-map-marker-alt" title="GPS"></i>',
    face: '<i class="fa-solid fa-smile" title="Face"></i>',
    manual: '<i class="fa-solid fa-pen" title="Manual"></i>',
    leave_integration: '<i class="fa-solid fa-calendar-check" title="Auto (cuti)"></i>'
};

function initAttendanceTable() {
    if (attendanceTable) {
        attendanceTable.destroy();
        attendanceTable = null;
    }

    attendanceTable = $('#attendance-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("attendance.data") }}',
            data: d => ({
                month: $('#filter-month').val(),
                status: $('#filter-status').val(),
                method: $('#filter-method').val()
            })
        },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'work_date', orderable: false, searchable: false },
            { data: 'employee', orderable: false, searchable: true,
                render: (d, t, r) => '<a href="{{ url("attendance") }}/' + r.id + '" style="color:var(--text-primary);text-decoration:none;"><span style="font-family:monospace;font-size:11px;color:var(--accent)">' + r.employee_no + '</span><br><strong>' + d + '</strong></a>' },
            { data: 'shift', orderable: false, searchable: false,
                render: d => d || '<span style="color:var(--text-muted)">—</span>' },
            { data: 'clock_in', orderable: false, searchable: false, className: 'text-center',
                render: d => d || '<span style="color:var(--text-muted)">—</span>' },
            { data: 'clock_out', orderable: false, searchable: false, className: 'text-center',
                render: d => d || '<span style="color:var(--text-muted)">—</span>' },
            { data: 'late_minutes', orderable: false, searchable: false, className: 'text-center',
                render: d => d ? '<span style="color:#b45309;font-weight:600">' + d + "</span>" : '<span style="color:var(--text-muted)">0</span>' },
            { data: 'check_in_method', orderable: false, searchable: false, className: 'text-center',
                render: d => (methodIcon[d] || d) },
            { data: 'status', orderable: false, searchable: false,
                render: d => approvalMap[d] || d },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: d => '<a class="btn-icon" title="Detail" href="{{ url("attendance") }}/' + d + '"><i class="fa-solid fa-eye"></i></a>' }
        ]
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initAttendanceTable();

    // ---------- Koreksi Absensi ----------
    const corrRejectUrlTemplate = '{{ url("attendance/corrections") }}';
    const corrTable = $('#correction-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("attendance.corrections.data") }}',
            data: d => ({ status: $('#correction-status').val() })
        },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'employee', orderable: false, searchable: true,
                render: (d,t,r) => '<span style="font-family:monospace;font-size:11px;color:var(--accent)">' + (r.employee_no || '—') + '</span><br><strong>' + d + '</strong>' },
            { data: 'work_date', orderable: false, searchable: false },
            { data: 'proposed_clock_in', orderable: false, searchable: false, className: 'text-center', render: d => d || '—' },
            { data: 'proposed_clock_out', orderable: false, searchable: false, className: 'text-center', render: d => d || '—' },
            { data: 'reason', orderable: false, searchable: true, style: 'font-size:12px' },
            { data: 'status', orderable: false, searchable: false,
                render: d => ({
                    pending: '<span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">Pending</span>',
                    approved: '<span class="status-badge status-active">Approved</span>',
                    rejected: '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Rejected</span>'
                }[d] || d)
            },
            @if($canApprove)
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: (d, t, row) => row.status === 'pending'
                    ? '<div class="d-flex justify-content-center gap-1">' +
                        '<button class="btn-icon" title="Approve" onclick="approveCorrection(' + d + ')"><i class="fa-solid fa-check"></i></button>' +
                        '<button class="btn-icon danger" title="Reject" onclick="openCorrRejectModal(' + d + ')"><i class="fa-solid fa-ban"></i></button></div>'
                    : '<span style="color:var(--text-muted)">—</span>' }
            @endif
        ]
    });

    $('#correction-status').on('change', () => corrTable.ajax.reload());

    $('#corr-employee').select2({
        dropdownParent: $('#correctionModal'),
        width: '100%',
        allowClear: true,
        placeholder: '— Pilih karyawan —',
        ajax: {
            url: '{{ route("employee-management.search-managers") }}',
            dataType: 'json',
            data: p => ({ q: p.term || '' }),
            processResults: d => d
        }
    });
});

let correctionModalInstance = null;
let corrRejectModalInstance = null;
let currentCorrectionId = null;

function openCorrectionModal() {
    document.getElementById('corr-form').reset();
    $('#corr-employee').val(null).trigger('change');
    $('#corr-date').val(new Date().toISOString().substring(0, 10));

    if (!correctionModalInstance) correctionModalInstance = new bootstrap.Modal(document.getElementById('correctionModal'));
    correctionModalInstance.show();
}

$('#btn-save-corr').on('click', function() {
    var emp = ($('#corr-employee').val() || [null])[0];
    if (!emp) { toastr.error('Karyawan wajib dipilih.'); return; }

    var payload = {
        employee_id: emp,
        work_date: $('#corr-date').val(),
        proposed_clock_in: $('#corr-in').val() || null,
        proposed_clock_out: $('#corr-out').val() || null,
        reason: $('#corr-reason').val().trim()
    };
    if (!payload.reason) { toastr.error('Alasan koreksi wajib diisi.'); return; }

    $('#btn-save-corr').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Mengirim...');
    $.post('{{ route("attendance.corrections.store") }}', payload)
    .done(res => {
        toastr.success(res.message);
        correctionModalInstance.hide();
        $('#correction-table').DataTable().ajax.reload();
    })
    .fail(err => {
        var msg = err.responseJSON?.message || 'Gagal mengirim koreksi.';
        if (err.responseJSON?.errors) msg = Object.values(err.responseJSON.errors)[0][0];
        toastr.error(msg);
    })
    .always(() => {
        $('#btn-save-corr').prop('disabled', false).html('<i class="fa fa-paper-plane me-1"></i> Kirim');
    });
});

function approveCorrection(id) {
    Swal.fire({
        title: 'Approve koreksi?',
        text: 'Record absensi karyawan akan diperbarui mengikuti jam koreksi.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post('{{ url("attendance/corrections") }}/' + id + '/approve', { _token: '{{ csrf_token() }}' })
        .done(res => {
            toastr.success(res.message);
            $('#correction-table').DataTable().ajax.reload();
            $('#attendance-table').DataTable().ajax.reload();
        })
        .fail(err => toastr.error(err.responseJSON?.message || 'Gagal approve.'));
    });
}

function openCorrRejectModal(id) {
    currentCorrectionId = id;
    $('#corr-reject-note').val('');
    if (typeof corrRejectModalInstance === 'undefined' || !window.corrRejectModalInstance) {
        window.corrRejectModalInstance = new bootstrap.Modal(document.getElementById('corrRejectModal'));
    }
    corrRejectModalInstance.show();
}

$('#btn-confirm-corr-reject').on('click', function() {
    var note = $('#corr-reject-note').val().trim();
    if (!note) { toastr.error('Alasan penolakan wajib diisi.'); return; }

    $.post('{{ url("attendance/corrections") }}/' + currentCorrectionId + '/reject', {
        note: note,
        _token: '{{ csrf_token() }}'
    }).done(res => {
        corrRejectModalInstance.hide();
        toastr.success(res.message);
        $('#correction-table').DataTable().ajax.reload();
    }).fail(err => toastr.error(err.responseJSON?.message || 'Gagal menolak.'));
});


    ['filter-status', 'filter-method'].forEach(id => {
        document.getElementById(id).addEventListener('change', () => attendanceTable.ajax.reload());
    });
    document.getElementById('filter-month').addEventListener('change', () => attendanceTable.ajax.reload());

    $('#manual-employee').select2({
        dropdownParent: $('#manualModal'),
        allowClear: true,
        width: '100%',
        placeholder: '— Pilih karyawan —',
        ajax: {
            url: '{{ route("employee-management.search-managers") }}',
            dataType: 'json',
            data: p => ({ q: p.term || '' }),
            processResults: d => d
        }
    });
});

function openManualModal() {
    document.getElementById('manual-form').reset();
    $('#manual-form .is-invalid').removeClass('is-invalid');
    $('#manual-employee').val(null).trigger('change');
    $('#manual-date').val(new Date().toISOString().substring(0, 10));

    if (!manualModalInstance) {
        manualModalInstance = new bootstrap.Modal(document.getElementById('manualModal'));
    }
    manualModalInstance.show();
}

$('#btn-save-manual').on('click', function() {
    var emp = ($('#manual-employee').val() || [null])[0];
    if (!emp) { toastr.error('Karyawan wajib dipilih.'); return; }

    var payload = {
        employee_id: emp,
        work_date: $('#manual-date').val(),
        shift_id: $('#manual-shift').val() || null,
        clock_in: $('#manual-clock-in').val() || null,
        clock_out: $('#manual-clock-out').val() || null,
        status: $('#manual-status').val(),
        note: $('#manual-note').val().trim() || null
    };

    $('#btn-save-manual').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...');
    $.ajax({
        url: '{{ route("attendance.manual") }}',
        method: 'POST',
        data: payload,
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(res => {
        toastr.success(res.message);
        manualModalInstance.hide();
        attendanceTable.ajax.reload();
    }).fail(err => {
        var msg = err.responseJSON?.message || 'Gagal menyimpan absen manual.';
        if (err.responseJSON?.errors) msg = Object.values(err.responseJSON.errors)[0][0];
        toastr.error(msg);
    }).always(() => {
        $('#btn-save-manual').prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan');
    });
});

function openImportModal() {
    document.getElementById('import-file').value = '';
    if (!importModalInstance) {
        importModalInstance = new bootstrap.Modal(document.getElementById('importModal'));
    }
    importModalInstance.show();
}

$('#btn-import').on('click', function() {
    var file = document.getElementById('import-file').files[0];
    if (!file) { toastr.error('Pilih berkas terlebih dahulu.'); return; }

    var fd = new FormData();
    fd.append('file', file);
    fd.append('_token', '{{ csrf_token() }}');

    $('#btn-import').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Mengunggah...');
    $.ajax({
        url: '{{ route("attendance.import-device") }}',
        method: 'POST',
        data: fd,
        contentType: false,
        processData: false,
        dataType: 'json'
    }).done(res => {
        toastr.success(res.message);
        importModalInstance.hide();
        attendanceTable.ajax.reload();
    }).fail(err => {
        toastr.error(err.responseJSON?.message || 'Gagal mengimpor.');
    }).always(() => {
        $('#btn-import').prop('disabled', false).html('<i class="fa fa-upload me-1"></i> Import');
    });
});
</script>
@endsection
