@extends('layouts.app')

@section('title', 'Cuti — '.$leaveRequest->request_no)
@section('page-title', 'Detail Pengajuan Cuti/Izin')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">
            <span style="font-family:monospace;color:var(--accent)">{{ $leaveRequest->request_no }}</span>
            <span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">{{ \Str::title($leaveRequest->status) }}</span>
        </h1>
        <p class="page-header-sub">
            {{ $leaveRequest->employee?->employee_no }} — {{ $leaveRequest->employee?->name }}
            ({{ $leaveRequest->leaveType?->name }}, {{ $leaveRequest->days }} hari)
        </p>
    </div>
    <div class="page-header-actions">
        @if($canApprove && in_array($leaveRequest->status, ['submitted', 'revise']))
        <button type="button" class="btn-accent" onclick="decideLeave('approve')">
            <i class="fa-solid fa-check"></i><span>Approve</span>
        </button>
        <button type="button" class="btn-accent-danger" onclick="decideLeave('reject', true)">
            <i class="fa-solid fa-ban"></i><span>Reject</span>
        </button>
        <button type="button" class="btn-accent" onclick="decideLeave('revise', true)">
            <i class="fa-solid fa-pen"></i><span>Revise</span>
        </button>
        @endif
        @if($canCreate && in_array($leaveRequest->status, ['submitted', 'draft', 'revise']))
        <button type="button" class="btn-accent" onclick="cancelLeave()">
            <i class="fa-solid fa-rotate-left"></i><span>Cancel</span>
        </button>
        @endif
        <a href="{{ route('leave-request.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-custom">
            <div class="card-header-custom"><span><i class="fa-solid fa-calendar-days me-2" style="color:var(--accent)"></i>Rincian</span></div>
            <div class="card-body-custom" style="font-size:13px">
                <table class="table table-sm mb-0">
                    <tr><td style="width:40%;color:var(--text-muted)">Karyawan</td><td>{{ $leaveRequest->employee?->employee_no }} — {{ $leaveRequest->employee?->name }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Jenis</td><td>{{ $leaveRequest->leaveType?->name }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Periode</td><td>{{ $leaveRequest->start_date?->format('d-m-Y') }} s/d {{ $leaveRequest->end_date?->format('d-m-Y') }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Jumlah hari kerja</td><td><strong>{{ $leaveRequest->days }}</strong></td></tr>
                    <tr><td style="color:var(--text-muted)">Alasan</td><td>{{ $leaveRequest->reason }}</td></tr>
                    @if($leaveRequest->attachment_path)
                    <tr><td style="color:var(--text-muted)">Lampiran</td><td><a href="{{ asset('storage/'.$leaveRequest->attachment_path) }}" target="_blank">Lihat lampiran</a></td></tr>
                    @endif
                    @if($leaveRequest->decision_note)
                    <tr><td style="color:var(--text-muted)">Catatan keputusan</td><td>{{ $leaveRequest->decision_note }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
        <div class="card-custom mt-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-wallet me-2" style="color:var(--accent)"></i>Saldo Cuti {{ now()->year }}</span></div>
            <div class="card-body-custom">
                @forelse($balances as $b)
                <div class="mb-3">
                    <div class="d-flex justify-content-between" style="font-size:12.5px">
                        <span>
                            <strong>{{ $b['leave_type'] }}</strong>
                        </span>
                        <span>terpakai {{ $b['used'] }} / kuota {{ $b['entitlement'] + $b['carried_over'] }} hari</span>
                    </div>
                    @php $usedPct = $b['entitlement'] > 0 ? min(100, round($b['used'] * 100 / max(1, $b['entitlement'] + $b['carried_over']))) : 0; @endphp
                    <div class="progress" style="height:8px">
                        <div class="progress-bar" style="width:{{ $usedPct }}%"></div>
                    </div>
                    <div style="font-size:11px;color:var(--text-muted)">sisa {{ $b['remaining'] }} hari</div>
                </div>
                @empty
                <div class="p-2 text-center" style="color:var(--text-muted);font-size:12.5px">Belum ada saldo — dibentuk saat cuti pertama disetujui.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-custom">
            <div class="card-header-custom"><span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Timeline Approval</span></div>
            <div class="card-body-custom" style="font-size:12.5px">
                <div class="list-group list-group-flush">
                    @forelse($leaveRequest->logs()->with('actor')->latest()->get() as $entry)
                    <div class="list-group-item bg-transparent border-0 border-bottom py-2">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong style="font-size:13px;color:{{ $entry->action === 'approve' ? '#16a34a' : ($entry->action === 'reject' ? '#dc2626' : 'var(--text-primary)') }}">
                                    {{ \Str::ucfirst($entry->action) }}
                                </strong>
                                @if($entry->note)
                                <div style="font-size:12px;color:var(--text-muted)">{{ $entry->note }}</div>
                                @endif
                            </div>
                            <div class="text-end">
                                <div style="font-size:12px">{{ $entry->actor?->name ?? '—' }}</div>
                                <div style="font-size:11px;color:var(--text-muted)">{{ $entry->created_at->format('d-m-Y H:i') }}</div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="p-3 text-center" style="color:var(--text-muted)">Belum ada aktivitas approval.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="noteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="noteModalTitle">Catatan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <textarea id="note-area" class="form-control" rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-note-confirm">Kirim</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let noteModalInstance = null;
let currentAction = null;

const actionUrlTemplate = '{{ route("leave-request.approve",":id") }}';
const rejectActionUrlTemplate = '{{ route("leave-request.reject",":id") }}';
const reviseActionUrlTemplate = '{{ route("leave-request.revise",":id") }}';
const cancelUrl = '{{ route("leave-request.cancel", $leaveRequest->id) }}';
const id = {{ $leaveRequest->id }};

const routeMap = {
    approve: actionUrlTemplate.replace(':id', id),
    reject: rejectActionUrlTemplate.replace(':id', id),
    revise: reviseActionUrlTemplate.replace(':id', id)
};

function decideLeave(action, needsNote) {
    var title = {'approve': 'Approve Pengajuan?', 'reject': 'Reject Pengajuan?', 'revise': 'Minta Revisi Pengajuan?'}[action];

    Swal.fire({
        title: title,
        icon: action === 'approve' ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonText: action === 'approve' ? 'Ya, Approve' : ('Ya, ' + (action === 'reject' ? 'Tolak' : 'Revisi'))
    }).then(r => {
        if (!r.isConfirmed) return;
        if (needsNote) {
            $('#noteModalTitle').text(title.replace('?', ''));
            $('#note-area').val('');
            if (!noteModalInstance) noteModalInstance = new bootstrap.Modal(document.getElementById('noteModal'));
            noteModalInstance.show();
            return;
        }

        $.post(routeMap[action], { _token: '{{ csrf_token() }}' })
        .done(res => { toastr.success(res.message); setTimeout(() => location.reload(), 500); })
        .fail(err => toastr.error(err.responseJSON?.message || 'Gagal memproses.'));
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('btn-note-confirm').addEventListener('click', function() {
        var note = $('#note-area').val().trim();
        if (!note) { toastr.error('Catatan wajib diisi.'); return; }

        $.post(routeMap[currentAction], { note: note, _token: '{{ csrf_token() }}' })
        .done(res => {
            noteModalInstance.hide();
            toastr.success(res.message);
            setTimeout(() => location.reload(), 500);
        }).fail(err => toastr.error(err.responseJSON?.message || 'Gagal memproses.'));
    });
});
</script>
@endsection

