@extends('layouts.app')

@section('title', 'Absensi — '.$attendance->employee?->name)
@section('page-title', 'Detail Absensi')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Detail Absensi</h1>
        <p class="page-header-sub">
            <span style="font-family:monospace;color:var(--accent)">{{ $attendance->employee?->employee_no }}</span>
            {{ $attendance->employee?->name }} ·
            {{ $attendance->work_date?->format('d-m-Y') }}
        </p>
    </div>
    <div class="page-header-actions">
        @if($canApprove && $attendance->status === 'pending')
        <button type="button" class="btn-accent" onclick="approveAttendance({{ $attendance->id }})">
            <i class="fa-solid fa-check"></i><span>Approve</span>
        </button>
        <button type="button" class="btn-accent-danger" onclick="openRejectModal()">
            <i class="fa-solid fa-ban"></i><span>Reject</span>
        </button>
        @endif
        <a href="{{ route('attendance.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-custom">
            <div class="card-header-custom"><span><i class="fa-solid fa-clock me-2" style="color:var(--accent)"></i>Waktu Kerja</span></div>
            <div class="card-body-custom" style="font-size:13px">
                <table class="table table-sm mb-0">
                    <tr><td style="width:45%;color:var(--text-muted)">Tanggal</td><td>{{ $attendance->work_date?->format('d-m-Y') }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Shift</td><td>{{ $attendance->shift?->name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Masuk</td><td>{{ $attendance->clock_in?->format('H:i') ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Keluar</td><td>{{ $attendance->clock_out?->format('H:i') ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Telat</td><td>{{ $attendance->late_minutes ?: '0' }} menit</td></tr>
                    <tr><td style="color:var(--text-muted)">Status</td><td>
                        @if($attendance->status === 'pending')
                        <span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">Pending</span>
                        @elseif($attendance->status === 'present')
                        <span class="status-badge status-active">Present</span>
                        @else
                        <span class="badge" style="background:rgba(59,130,246,.12);color:#1d4ed8;">{{ \Str::title($attendance->status) }}</span>
                        @endif
                    </td></tr>
                    <tr><td style="color:var(--text-muted)">Catatan</td><td>{{ $attendance->note ?? '—' }}</td></tr>
                </table>
            </div>
        </div>
        <div class="card-custom mt-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-fingerprint me-2" style="color:var(--accent)"></i>Metode Absensi</span></div>
            <div class="card-body-custom" style="font-size:13px">
                <table class="table table-sm mb-0">
                    <tr><td style="width:40%;color:var(--text-muted)">Metode</td><td>{{ \Str::ucfirst($attendance->check_in_method) }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Sumber</td><td>{{ \Str::ucfirst($attendance->source ?? '-') }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Office</td><td>{{ $attendance->office?->name ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Kordinat</td><td>{{ $attendance->check_in_lat ? $attendance->check_in_lat.', '.$attendance->check_in_lng : '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Face Match</td><td>{{ is_null($attendance->face_matched) ? '—' : ($attendance->face_matched ? 'Ya' : 'Tidak') }}</td></tr>
                    @if($attendance->photo_path)
                    <tr><td style="color:var(--text-muted)">Selfie</td><td>
                        <a href="{{ asset('storage/'.$attendance->photo_path) }}" target="_blank" class="text-decoration-none">Lihat foto</a>
                    </td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        @if($attendance->check_in_lat)
        <div class="card-custom mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-map-pin me-2" style="color:var(--accent)"></i>Lokasi Check-in</span></div>
            <div class="card-body-custom p-0">
                <iframe style="width:100%;height:320px;border:0"
                    src="https://www.google.com/maps?q={{ $attendance->check_in_lat }},{{ $attendance->check_in_lng }}&z=16&output=embed"
                    loading="lazy"></iframe>
            </div>
        </div>
        @endif
        <div class="card-custom">
            <div class="card-header-custom"><span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Timeline Approval</span></div>
            <div class="card-body-custom">
                <div class="list-group list-group-flush">
                    @forelse($approvals as $apr)
                    <div class="list-group-item bg-transparent border-0 border-bottom py-2">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong style="font-size:13px;color:{{ $apr->action === 'approve' ? '#16a34a' : '#dc2626' }}">
                                    {{ \Str::ucfirst($apr->action) }}
                                </strong>
                                @if($apr->note)
                                <div style="font-size:12px;color:var(--text-muted)">{{ $apr->note }}</div>
                                @endif
                            </div>
                            <div class="text-end">
                                <div style="font-size:12px">{{ $apr->actor?->name }}</div>
                                <div style="font-size:11px;color:var(--text-muted)">{{ $apr->created_at->format('d-m-Y H:i') }}</div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="p-3 text-center" style="color:var(--text-muted);font-size:13px">Belum ada aktivitas approval.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Alasan Penolakan <span style="color:var(--danger)">*</span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <textarea id="reject-note" class="form-control" rows="3" placeholder="Alasan penolakan absensi"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-confirm-reject">Tolak</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
const approveUrlTemplate = '{{ route("attendance.approve", ":id") }}';
const rejectUrlTemplate = '{{ route("attendance.reject", ":id") }}';
const rejectUrl = rejectUrlTemplate.replace(':id', '{{ $attendance->id }}');
const approveUrl = approveUrlTemplate.replace(':id', '{{ $attendance->id }}');

function approveAttendance() {
    Swal.fire({
        title: 'Approve Absensi?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.ajax({ url: approveUrl, method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
        .done(res => { toastr.success(res.message); setTimeout(() => location.reload(), 500); })
        .fail(err => toastr.error(err.responseJSON?.message || 'Gagal approve.'));
    });
}

let rejectModalInstance = null;

function openRejectModal() {
    document.getElementById('reject-note').value = '';
    if (!rejectModalInstance) {
        rejectModalInstance = new bootstrap.Modal(document.getElementById('rejectModal'));
    }
    rejectModalInstance.show();
}

document.querySelector('#btn-confirm-reject')?.addEventListener('click', function() {
    var note = $('#reject-note').val().trim();
    if (!note) { toastr.error('Alasan penolakan wajib diisi.'); return; }

    $.post(rejectUrl, { note: note, _token: '{{ csrf_token() }}' })
    .done(res => {
        rejectModalInstance.hide();
        toastr.success(res.message);
        setTimeout(() => location.reload(), 500);
    })
    .fail(err => toastr.error(err.responseJSON?.message || 'Gagal reject.'));
});
</script>
@endsection
