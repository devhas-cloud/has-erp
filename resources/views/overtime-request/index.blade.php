@extends('layouts.app')

@section('title', 'Pengajuan Lembur')
@section('page-title', 'Pengajuan Lembur')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Pengajuan Lembur</h1>
        <p class="page-header-sub">Jam lembur terhitung otomatis dari absensi (jam pulang shift s/d akhir jam kerja) — ERP</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i><span>Pengajuan Lembur</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-body-custom py-2">
        <div class="row g-2">
            <div class="col-auto"><input type="month" id="filter-month" class="form-control form-control-sm" value="{{ now()->format('Y-m') }}"></div>
            <div class="col-auto">
                <select id="filter-status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    @foreach(['submitted','approved','rejected','cancelled'] as $s)
                    <option value="{{ $s }}">{{ \Str::title($s) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-user-clock me-2" style="color:var(--accent)"></i>Daftar Pengajuan</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="overtime-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width:140px">No. Pengajuan</th>
                        <th style="width:200px">Karyawan</th>
                        <th style="width:120px">Tanggal</th>
                        <th style="width:150px">Shift End → Pulang</th>
                        <th style="width:100px" class="text-center">Lembur (jam)</th>
                        <th style="width:80px" class="text-center">× Mult</th>
                        <th style="width:130px" class="text-end">Est. Nominal</th>
                        <th style="width:110px">Status</th>
                        <th class="text-center" style="width:90px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Modal Pengajuan --}}
<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Pengajuan Lembur</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="ot-form" autocomplete="off">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Karyawan <span style="color:var(--danger)">*</span></label>
                                <select id="ot-employee" class="form-select" required></select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tanggal Lembur <span style="color:var(--danger)">*</span></label>
                                <input type="date" id="ot-date" class="form-control" value="{{ now()->toDateString() }}" required>
                            </div>
                        </div>
                    </div>
                    <div class="attendance-card p-2 mb-3" style="border:1px dashed var(--border);border-radius:8px;min-height:60px" id="ot-attendance">
                        <div class="text-center" style="color:var(--text-muted)">Pilih karyawan dan tanggal — data absensi akan dimuat otomatis</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Shift pulang</label>
                                <input type="text" id="ot-shift-end" class="form-control" disabled placeholder="—">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Jam pulang (end) <span style="color:var(--danger)">*</span></label>
                                <input type="time" id="ot-end-time" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Multiplier</label>
                                <input type="number" id="ot-multiplier" class="form-control" min="1" max="10" step="0.5" value="1.5">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Durasi</label>
                                <input type="text" id="ot-duration" class="form-control" value="0.00" readonly>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Estimasi nominal</label>
                                <input type="text" id="ot-estimate" class="form-control" value="Rp 0" readonly>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Alasan lembur <span style="color:var(--danger)">*</span></label>
                                <textarea id="ot-reason" class="form-control" rows="2" maxlength="500" required placeholder="Contoh: selesaikankan pengiriman device tersisa"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-ot"><i class="fa fa-save me-1"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let overtimeTable = null;
let createModalInstance = null;
let employeeSelect2 = null;

let attendanceInfo = null;
let currentDivisor = 173;
let baseSalary = 0;

const statusBadges = {
    submitted: '<span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">Submitted</span>',
    approved: '<span class="status-badge status-active">Approved</span>',
    rejected: '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Rejected</span>',
    cancelled: '<span class="status-badge" style="background:rgba(107,114,128,.15);color:#4b5563;">Cancelled</span>'
};

function initOvertimeTable() {
    if (overtimeTable) {
        overtimeTable.destroy();
        overtimeTable = null;
    }

    overtimeTable = $('#overtime-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("overtime-request.data") }}',
            data: d => ({
                month: $('#filter-month').val(),
                status: $('#filter-status').val()
            })
        },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'request_no', orderable: false, searchable: true,
                render: d => '<span style="font-family:monospace;color:var(--accent)">' + d + '</span>' },
            { data: 'employee', orderable: false, searchable: true,
                render: (d,t,r) => '<a href="{{ url("overtime-request") }}/' + r.id + '" style="text-decoration:none;color:var(--text-primary)"><span style="font-family:monospace;font-size:11px;color:var(--accent)">' + r.employee_no + '</span><br><strong>' + d + '</strong></a>' },
            { data: 'work_date', orderable: false, searchable: false },
            { data: null, orderable: false, searchable: false,
                render: (d,t,r) => ((r.shift_end || '—') + ' → ' + (r.end_time || '—')) },
            { data: 'hours', orderable: false, searchable: false, className: 'text-center',
                render: d => '<strong>' + Number(d).toFixed(2) + '</strong>' },
            { data: 'multiplier', orderable: false, searchable: false, className: 'text-center' },
            { data: 'estimated_amount', orderable: false, searchable: false, className: 'text-end' },
            { data: 'status', orderable: false, searchable: false,
                render: d => statusBadges[d] || d },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: d => '<a class="btn-icon" title="Detail" href="{{ url("overtime-request") }}/' + d + '"><i class="fa-solid fa-eye"></i></a>' }
        ]
    });
}

function recalcEstimate() {
    var shiftEnd = $('#ot-shift-end').val(); // 'HH:MM'
    var endTime = $('#ot-end-time').val();
    var mult = parseFloat($('#ot-multiplier').val()) || 1.5;

    var toMin = t => {
        if (!t) return null;
        var parts = t.split(':');
        return parseInt(parts[0]) * 60 + parseInt(parts[1] || 0);
    };

    var mins = null;
    if (shiftEnd && endTime) {
        var se = toMin(shiftEnd), ee = toMin(endTime);
        mins = ee - se;
        if (mins < 0) mins += 24 * 60; // shift malam lintas tengah malam (konsisten dgn server)
    }

    var hours = mins ? mins / 60 : 0;
    $('#ot-duration').val(hours.toFixed(2));
    $('#ot-estimate').val('Rp ' + ((baseSalary / currentDivisor) * hours * mult).toLocaleString('id-ID', { maximumFractionDigits: 0 }));
}

function loadAttendance() {
    var emp = ($('#ot-employee').val() || [null])[0];
    var date = $('#ot-date').val();

    if (!emp || !date) return;

    $.get('{{ route("overtime-request.fetch-attendance") }}', { employee_id: emp, work_date: date }, function(res) {
        attendanceInfo = res.attendance;
        baseSalary = res.base_salary || 0;
        currentDivisor = res.hour_divisor || 173;

        var shiftEnd = attendanceInfo?.shift_end_time || '';
        var clockOut = attendanceInfo?.clock_out || '';

        $('#ot-shift-end').val(shiftEnd);
        $('#ot-end-time').val(clockOut || shiftEnd);

        if (attendanceInfo) {
            $('#ot-attendance').html(
                '<div class="d-flex justify-content-between" style="font-size:12.5px">' +
                    '<span><i class="fa-solid fa-fingerprint me-1"></i>Absensi ' + attendanceInfo.shift_name + ': masuk ' + (attendanceInfo.clock_in || '—') + ', pulang <strong>' + (attendanceInfo.clock_out || '—') + '</strong></span>' +
                    (attendanceInfo.shift_end ? '<span>shift pulang: ' + attendanceInfo.shift_end + '</span>' : '') +
                '</div>');
        } else {
            $('#ot-attendance').html('<div class="text-center" style="color:#b45309;font-size:12.5px"><i class="fa-solid fa-triangle-exclamation me-1"></i>Absensi belum ada — hanya Admin yang dapat membuat pengajuan manual.</div>');
        }
        recalcEstimate();

        if (res.default_multiplier) {
            $('#ot-multiplier').val(res.default_multiplier);
        }
    });
}

function openCreateModal() {
    document.getElementById('ot-form').reset();
    $('#ot-employee').val(null).trigger('change');
    $('#ot-attendance').html('<div class="text-center" style="color:var(--text-muted)">Pilih karyawan dan tanggal — data absensi akan dimuat otomatis</div>');
    $('#ot-date').val(new Date().toISOString().substring(0, 10));

    if (!createModalInstance) {
        createModalInstance = new bootstrap.Modal(document.getElementById('createModal'));
    }
    createModalInstance.show();
}

$('#btn-save-ot').on('click', function() {
    var emp = ($('#ot-employee').val() || [null])[0];
    if (!emp) { toastr.error('Karyawan wajib dipilih.'); return; }

    var payload = {
        employee_id: emp,
        work_date: $('#ot-date').val(),
        shift_end_time: $('#ot-shift-end').val() || null,
        end_time: $('#ot-end-time').val() || null,
        multiplier: $('#ot-multiplier').val(),
        reason: $('#ot-reason').val().trim()
    };
    if (!payload.reason) { toastr.error('Alasan lembur wajib diisi.'); return; }

    var bid = '#btn-save-ot';
    $(bid).prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...');
    $.post('{{ route("overtime-request.store") }}', payload)
    .done(res => {
        toastr.success(res.message);
        createModalInstance.hide();
        overtimeTable.ajax.reload();
    }).fail(err => {
        var msg = err.responseJSON?.message || 'Gagal menyimpan pengajuan.';
        if (err.responseJSON?.errors) msg = Object.values(err.responseJSON.errors)[0][0];
        toastr.error(msg);
    }).always(() => {
        $(bid).prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan');
    });
});

document.addEventListener('DOMContentLoaded', function() {
    initOvertimeTable();

    ['filter-status', 'filter-month'].forEach(id => document.getElementById(id).addEventListener('change', () => overtimeTable.ajax.reload()));

    employeeSelect2 = $('#ot-employee').select2({
        dropdownParent: $('#createModal'),
        width: '100%',
        allowClear: true,
        placeholder: '— Pilih karyawan —',
        ajax: {
            url: '{{ route("employee-management.search-managers") }}',
            dataType: 'json',
            data: p => ({ q: p.term || '' }),
            processResults: d => d
        }
    }).on('select2:select', function() {
        loadAttendance();
    });

    $('#ot-date').on('change', function() {
        if ($('#ot-employee').val()) loadAttendance();
    });
    $('#ot-multiplier, #ot-end-time').on('change keyup', recalcEstimate);
});
</script>
@endsection
