@extends('layouts.app')

@section('title', 'Pengajuan (Cuti/Izin)')
@section('page-title', 'Pengajuan (Cuti/Izin)')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Pengajuan (Cuti/Izin)</h1>
        <p class="page-header-sub">Pengajuan cuti tahunan, izin, sakit, unpaid — approval sederhana + integrasi absensi</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i><span>Pengajuan</span>
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
                    @foreach(['submitted','approved','rejected','revise','cancelled'] as $s)
                    <option value="{{ $s }}">{{ \Str::title($s) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-calendar-check me-2" style="color:var(--accent)"></i>Daftar Pengajuan</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="leave-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width:140px">No. Pengajuan</th>
                        <th style="width:200px">Karyawan</th>
                        <th style="width:130px">Jenis</th>
                        <th style="width:220px">Periode</th>
                        <th style="width:90px" class="text-center">Hari</th>
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
<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Pengajuan Cuti/Izin</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="lr-form" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">Karyawan <span style="color:var(--danger)">*</span></label>
                        <select id="lr-employee" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Cuti <span style="color:var(--danger)">*</span></label>
                        <select id="lr-type" class="form-select" required>
                            @foreach($leaveTypes as $t)
                            <option value="{{ $t->id }}" data-quota="{{ $t->quota_days ?? 0 }}" data-proof="{{ $t->is_proof_required ? 1 : 0 }}">
                                {{ $t->name }}{{ $t->quota_days ? " ({$t->quota_days} hari/tahun)" : '' }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="saldo-info p-2 mb-3" id="lr-saldo" style="border:1px dashed var(--border);border-radius:8px;font-size:12.5px;color:var(--text-muted)">
                        Pilih karyawan untuk melihat saldo cuti tahunan.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Mulai <span style="color:var(--danger)">*</span></label>
                                <input type="date" id="lr-start" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sampai <span style="color:var(--danger)">*</span></label>
                                <input type="date" id="lr-end" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jumlah hari kerja</label>
                                <input type="text" id="lr-days" class="form-control" value="0" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Lampiran <span class="proof-required d-none" style="color:#b45309">(wajib: surat dokter)</span></label>
                                <input type="file" id="lr-attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Alasan <span style="color:var(--danger)">*</span></label>
                                <textarea id="lr-reason" class="form-control" rows="2" maxlength="500" required></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-lr"><i class="fa fa-save me-1"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let leaveTable = null;
let createModalInstance = null;
let employeeSelect2 = null;

let saldoData = [];
const leaveTypes = @json($leaveTypes);

const statusBadges = {
    draft: '<span class="status-badge" style="background:rgba(107,114,128,.15);color:#4b5563;">Draft</span>',
    submitted: '<span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">Submitted</span>',
    approved: '<span class="status-badge status-active">Approved</span>',
    rejected: '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Rejected</span>',
    revise: '<span class="status-badge" style="background:rgba(168,85,247,.12);color:#7e22ce;">Revise</span>',
    cancelled: '<span class="status-badge" style="background:rgba(107,114,128,.15);color:#4b5563;">Cancelled</span>'
};

function initLeaveTable() {
    if (leaveTable) {
        leaveTable.destroy();
        leaveTable = null;
    }

    leaveTable = $('#leave-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("leave-request.data") }}',
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
                render: (d,t,r) => '<a href="{{ url("leave-request") }}/' + r.id + '" style="text-decoration:none;color:var(--text-primary)"><span style="font-family:monospace;font-size:11px;color:var(--accent)">' + r.employee_no + '</span><br><strong>' + d + '</strong></a>' },
            { data: 'leave_type', orderable: false, searchable: false },
            { data: 'period', orderable: false, searchable: false },
            { data: 'days', orderable: false, searchable: false, className: 'text-center' },
            { data: 'status', orderable: false, searchable: false,
                render: d => statusBadges[d] || d },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: d => '<a class="btn-icon" title="Detail" href="{{ url("leave-request") }}/' + d + '"><i class="fa-solid fa-eye"></i></a>' }
        ]
    });
}

function workingDaysBetween(start, end) {
    if (!start || !end || end < start) return 0;
    var s = new Date(start), e = new Date(end), count = 0;
    while (s <= e) {
        var day = s.getDay();
        if (day !== 0 && day !== 6) count++;
        s.setDate(s.getDate() + 1);
    }
    return count;
}

function loadSaldo() {
    var emp = ($('#lr-employee').val() || [null])[0];
    if (!emp) return;

    $.get('{{ route("leave-request.balances") }}', { employee_id: emp }, function(res) {
        saldoData = res.data || [];
        var tahunan = saldoData.find(s => s.leave_type === 'Cuti Tahunan');
        $('#lr-saldo').html(tahunan
            ? '<i class="fa-solid fa-wallet me-1"></i> Saldo Cuti Tahunan <strong>' + new Date().getFullYear() + '</strong>: kuota ' + (tahunan.entitlement + tahunan.carried_over) + ' hari — terpakai <strong>' + tahunan.used + '</strong> — tersisa <strong style="color:var(--accent)">' + tahunan.remaining + '</strong> hari'
            : '<i class="fa-solid fa-arrows-rotate me-1"></i> Saldo belum diinisialisasi (akan terbentuk saat cuti pertama disetujui; kuota default mengikuti jenis cuti).');
    });
}

function openCreateModal() {
    document.getElementById('lr-form').reset();
    $('#lr-employee').val(null).trigger('change');
    $('#lr-days').val(0);
    $('#lr-start').val(new Date().toISOString().substring(0, 10));
    saldoData = [];

    if (!createModalInstance) {
        createModalInstance = new bootstrap.Modal(document.getElementById('createModal'));
    }
    createModalInstance.show();
}

$('#btn-save-lr').on('click', function() {
    var emp = ($('#lr-employee').val() || [null])[0];
    if (!emp) { toastr.error('Karyawan wajib dipilih.'); return; }
    var reason = $('#lr-reason').val().trim();
    if (!reason) { toastr.error('Alasan wajib diisi.'); return; }

    var fd = new FormData();
    fd.append('employee_id', emp);
    fd.append('leave_type_id', $('#lr-type').val());
    fd.append('start_date', $('#lr-start').val());
    fd.append('end_date', $('#lr-end').val());
    fd.append('reason', reason);
    var fileInput = document.getElementById('lr-attachment');
    if (fileInput.files[0]) fd.append('attachment', fileInput.files[0]);
    fd.append('_token', '{{ csrf_token() }}');

    $('#btn-save-lr').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Mengirim...');
    $.ajax({
        url: '{{ route("leave-request.store") }}',
        method: 'POST',
        data: fd,
        contentType: false,
        processData: false,
        dataType: 'json'
    }).done(res => {
        toastr.success(res.message);
        createModalInstance.hide();
        leaveTable.ajax.reload();
    }).fail(err => {
        var msg = err.responseJSON?.message || 'Gagal menyimpan pengajuan.';
        if (err.responseJSON?.errors) msg = Object.values(err.responseJSON.errors)[0][0];
        toastr.error(msg);
    }).always(() => {
        $('#btn-save-lr').prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan');
    });
});

document.addEventListener('DOMContentLoaded', function() {
    initLeaveTable();

    ['filter-status', 'filter-month'].forEach(id => document.getElementById(id).addEventListener('change', () => leaveTable.ajax.reload()));

    employeeSelect2 = $('#lr-employee').select2({
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
        loadSaldo();
    });

    $('#lr-type, #lr-start, #lr-end').on('change', function() {
        var days = workingDaysBetween($('#lr-start').val(), $('#lr-end').val());
        $('#lr-days').val(days);

        var opt = $('#lr-type').find(':selected');
        var quota = parseInt($(opt).data('quota') || 0);
        var proof = parseInt($(opt).data('proof') || 0);
        var tahunan = saldoData.find(s => s.leave_type === 'Cuti Tahunan');
        var sisa = tahunan ? tahunan.remaining : quota;
        if (quota && days > sisa) {
            $('#lr-saldo').html('<i class="fa-solid fa-triangle-exclamation me-1" style="color:#b45309"></i> Saldo tidak cukup: butuh ' + days + ' hari, tersisa ' + sisa + ' hari.');
        }
        $('.proof-required').toggleClass('d-none', !proof);
    });
});
</script>
@endsection
