@extends('layouts.app')

@section('title', 'Master Office & Shift')
@section('page-title', 'Master Absensi')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Master Office &amp; Shift</h1>
        <p class="page-header-sub">Titik geofence untuk absen GPS (Android) dan jam kerja harian</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('attendance.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-building me-2" style="color:var(--accent)"></i>Office (geofence)</span>
                @if($canCreate)
                <button type="button" class="btn btn-primary btn-sm" onclick="openOfficeModal()"><i class="fa fa-plus me-1"></i>Tambah</button>
                @endif
            </div>
            <div class="card-body-custom p-2">
                <table class="table table-custom align-middle mb-0" id="office-table" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Nama</th>
                            <th style="width:150px">Lokasi</th>
                            <th class="text-center" style="width:100px">Radius</th>
                            <th class="text-center" style="width:90px">Aktif</th>
                            @if($canUpdate || $canDelete)<th class="text-center" style="width:110px">Aksi</th>@endif
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-clock me-2" style="color:var(--accent)"></i>Shift</span>
                @if($canCreate)
                <button type="button" class="btn btn-primary btn-sm" onclick="openShiftModal()"><i class="fa fa-plus me-1"></i>Tambah</button>
                @endif
            </div>
            <div class="card-body-custom p-2">
                <table class="table table-custom align-middle mb-0" id="shift-table" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Nama</th>
                            <th style="width:110px">Masuk</th>
                            <th style="width:110px">Keluar</th>
                            <th class="text-center" style="width:110px">Toleransi (mnt)</th>
                            @if($canUpdate || $canDelete)<th class="text-center" style="width:110px">Aksi</th>@endif
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Modal Office --}}
<div class="modal fade" id="officeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="officeModalTitle">Tambah Office</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="office-form" autocomplete="off">
                    <input type="hidden" id="office-edit-id" value="">
                    <div class="mb-3">
                        <label class="form-label">Nama Office <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="office-name" class="form-control" maxlength="150" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea id="office-address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Latitude <span style="color:var(--danger)">*</span></label>
                                <input type="number" id="office-lat" class="form-control" step="0.00000001" min="-90" max="90" placeholder="e.g. -6.20000000" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Longitude <span style="color:var(--danger)">*</span></label>
                                <input type="number" id="office-lng" class="form-control" step="0.00000001" min="-180" max="180" placeholder="e.g. 106.84559999" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Radius (meter) <span style="color:var(--danger)">*</span></label>
                                <input type="number" id="office-radius" class="form-control" min="10" value="100" required>
                            </div>
                        </div>
                    </div>
                    <p style="font-size:12px;color:var(--text-muted)">
                        Kordinat dipakai untuk verifikasi satuan GPS absen Android (jarak kordinat)
                        berjangka prinsip haversine. Ambil koordinat dari Google Maps → klik kanan → Copy Lane...
                    </p>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-office"><i class="fa fa-save me-1"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Shift --}}
<div class="modal fade" id="shiftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="shiftModalTitle">Tambah Shift</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="shift-form" autocomplete="off">
                    <input type="hidden" id="shift-edit-id" value="">
                    <div class="mb-3">
                        <label class="form-label">Nama Shift <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="shift-name" class="form-control" maxlength="50" placeholder="Contoh: Pagi" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jam Masuk <span style="color:var(--danger)">*</span></label>
                                <input type="time" id="shift-clock-in" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jam Keluar <span style="color:var(--danger)">*</span></label>
                                <input type="time" id="shift-clock-out" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Toleransi (menit)</label>
                                <input type="number" id="shift-tolerance" class="form-control" min="0" value="0">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-shift"><i class="fa fa-save me-1"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
const officeStoreUrl = '{{ route("attendance.office.store") }}';
const officeUpdateUrlTemplate = '{{ route("attendance.office.update", ":id") }}';
const officeDeleteUrlTemplate = '{{ route("attendance.office.destroy", ":id") }}';
const shiftStoreUrl = '{{ route("attendance.shift.store") }}';
const shiftUpdateUrlTemplate = '{{ route("attendance.shift.update", ":id") }}';
const shiftDeleteUrlTemplate = '{{ route("attendance.shift.destroy", ":id") }}';

let officeModalInstance = null;
let shiftModalInstance = null;

const officesData = @json($offices);
const shiftsData = @json($shifts);

// ---------- Office ----------
function initOfficeTable() {
    $('#office-table').DataTable({
        destroy: true,
        data: officesData,
        columns: [
            { data: null, orderable: false, searchable: false, className: 'text-center',
                render: (d, t, r, m) => m.row + 1 },
            { data: 'name', render: d => '<strong>' + d + '</strong>' },
            { data: null, orderable: false, searchable: false,
                render: (d, t, r) => (+r.latitude).toFixed(6) + ' , ' + (+r.longitude).toFixed(6) },
            { data: 'radius_meters', orderable: false, searchable: false, className: 'text-center',
                render: d => d + ' m' },
            { data: 'is_active', orderable: false, searchable: false, className: 'text-center',
                render: d => d
                    ? '<span class="status-badge status-active">Ya</span>'
                    : '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Tidak</span>' },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                @if($canUpdate || $canDelete)
                render: function(data, t, r) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    @if($canUpdate)
                    btn += '<button class="btn-icon" title="Edit" onclick="openOfficeModal(' + data + ')"><i class="fa-solid fa-pen"></i></button>';
                    @endif
                    @if($canDelete)
                    btn += '<button class="btn-icon danger" title="Hapus" onclick="deleteOffice(' + data + ', \'' + r.name + '\')"><i class="fa-solid fa-trash-can"></i></button>';
                    @endif
                    btn += '</div>';
                    return btn;
                }
                @else
                render: () => ''
                @endif
            }
        ]
    });
}

function openOfficeModal(id) {
    document.getElementById('office-form').reset();
    $('#office-edit-id').val('');
    document.getElementById('officeModalTitle').textContent = 'Tambah Office';

    if (id) {
        var office = officesData.find(o => o.id === id);
        if (!office) return;
        $('#office-edit-id').val(office.id);
        $('#office-name').val(office.name);
        $('#office-address').val(office.address || '');
        $('#office-lat').val(office.latitude);
        $('#office-lng').val(office.longitude);
        $('#office-radius').val(office.radius_meters);
        document.getElementById('officeModalTitle').textContent = 'Edit Office';
    }

    if (!officeModalInstance) officeModalInstance = new bootstrap.Modal(document.getElementById('officeModal'));
    officeModalInstance.show();
}

$('#btn-save-office').on('click', function() {
    var id = $('#office-edit-id').val();
    var payload = {
        name: $('#office-name').val().trim(),
        address: $('#office-address').val().trim(),
        latitude: $('#office-lat').val(),
        longitude: $('#office-lng').val(),
        radius_meters: $('#office-radius').val()
    };
    if (!payload.name) { toastr.error('Nama office wajib diisi.'); return; }

    $.ajax({
        url: id ? officeUpdateUrlTemplate.replace(':id', id) : officeStoreUrl,
        method: id ? 'PUT' : 'POST',
        data: payload,
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(res => {
        toastr.success(res.message); officeModalInstance.hide();
        setTimeout(() => location.reload(), 500);
    }).fail(err => {
        var msg = err.responseJSON?.message || 'Gagal menyimpan office.';
        if (err.responseJSON?.errors) msg = Object.values(err.responseJSON.errors)[0][0];
        toastr.error(msg);
    });
});

function deleteOffice(id, name) {
    Swal.fire({
        title: 'Hapus Office?',
        text: '"' + name + '" akan dihapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.ajax({
            url: officeDeleteUrlTemplate.replace(':id', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(res => { toastr.success(res.message); setTimeout(() => location.reload(), 500); })
        .fail(err => toastr.error(err.responseJSON?.message || 'Gagal menghapus.'));
    });
}

// ---------- Shift ----------
function initShiftTable() {
    $('#shift-table').DataTable({
        destroy: true,
        data: shiftsData,
        columns: [
            { data: null, orderable: false, searchable: false, className: 'text-center',
                render: (d, t, r, m) => m.row + 1 },
            { data: 'name', render: d => '<strong>' + d + '</strong>' },
            { data: 'clock_in_time', orderable: false, searchable: false, render: d => d ? d.slice(0, 5) : '—' },
            { data: 'clock_out_time', orderable: false, searchable: false, render: d => d ? d.slice(0, 5) : '—' },
            { data: 'tolerance_minutes', orderable: false, searchable: false, className: 'text-center' },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                @if($canUpdate || $canDelete)
                render: function(data, t, r) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    @if($canUpdate)
                    btn += '<button class="btn-icon" title="Edit" onclick="openShiftModal(' + data + ')"><i class="fa-solid fa-pen"></i></button>';
                    @endif
                    @if($canDelete)
                    btn += '<button class="btn-icon danger" title="Hapus" onclick="deleteShift(' + data + ', \'' + r.name + '\')"><i class="fa-solid fa-trash-can"></i></button>';
                    @endif
                    btn += '</div>';
                    return btn;
                }
                @else
                render: () => ''
                @endif
            }
        ]
    });
}

function openShiftModal(id) {
    document.getElementById('shift-form').reset();
    $('#shift-edit-id').val('');
    document.getElementById('shiftModalTitle').textContent = 'Tambah Shift';

    if (id) {
        var shift = shiftsData.find(s => s.id === id);
        if (!shift) return;
        $('#shift-edit-id').val(shift.id);
        $('#shift-name').val(shift.name);
        $('#shift-clock-in').val(shift.clock_in_time ? shift.clock_in_time.slice(0, 5) : '');
        $('#shift-clock-out').val(shift.clock_out_time ? shift.clock_out_time.slice(0, 5) : '');
        $('#shift-tolerance').val(shift.tolerance_minutes);
        document.getElementById('shiftModalTitle').textContent = 'Edit Shift — ' + shift.name;
    }

    if (!shiftModalInstance) shiftModalInstance = new bootstrap.Modal(document.getElementById('shiftModal'));
    shiftModalInstance.show();
}

$('#btn-save-shift').on('click', function() {
    var id = $('#shift-edit-id').val();
    var payload = {
        name: $('#shift-name').val().trim(),
        clock_in_time: $('#shift-clock-in').val() + ':00',
        clock_out_time: $('#shift-clock-out').val() + ':00',
        tolerance_minutes: $('#shift-tolerance').val() || 0
    };
    if (!payload.name) { toastr.error('Nama shift wajib diisi.'); return; }

    $.ajax({
        url: id ? shiftUpdateUrlTemplate.replace(':id', id) : shiftStoreUrl,
        method: id ? 'PUT' : 'POST',
        data: payload,
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(res => {
        toastr.success(res.message); shiftModalInstance.hide();
        setTimeout(() => location.reload(), 500);
    }).fail(err => {
        var msg = err.responseJSON?.message || 'Gagal menyimpan shift.';
        if (err.responseJSON?.errors) msg = Object.values(err.responseJSON.errors)[0][0];
        toastr.error(msg);
    });
});

function deleteShift(id, name) {
    Swal.fire({
        title: 'Hapus Shift?',
        text: '"' + name + '" akan dihapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.ajax({
            url: shiftDeleteUrlTemplate.replace(':id', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(res => { toastr.success(res.message); setTimeout(() => location.reload(), 500); })
        .fail(err => toastr.error(err.responseJSON?.message || 'Gagal menghapus.'));
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initOfficeTable();
    initShiftTable();
});
</script>
@endsection
