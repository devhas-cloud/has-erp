@extends('layouts.app')

@section('title', 'Lembur — '.$overtime->request_no)
@section('page-title', 'Detail Pengajuan Lembur')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">
            <span style="font-family:monospace;color:var(--accent)">{{ $overtime->request_no }}</span>
            @if($overtime->status === 'submitted')
            <span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">Submitted</span>
            @elseif($overtime->status === 'approved')
            <span class="status-badge status-active">Approved</span>
            @else
            <span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">{{ \Str::title($overtime->status) }}</span>
            @endif
        </h1>
        <p class="page-header-sub">{{ $overtime->employee?->name }} · {{ $overtime->work_date?->format('d-m-Y') }}</p>
    </div>
    <div class="page-header-actions">
        @if($canApprove && $overtime->status === 'submitted')
        <button type="button" class="btn-accent" onclick="openApproveModal()">
            <i class="fa-solid fa-check"></i><span>Approve</span>
        </button>
        <button type="button" class="btn-accent-danger" onclick="openRejectModal()">
            <i class="fa-solid fa-ban"></i><span>Reject</span>
        </button>
        @endif
        @if($canCreate && $overtime->status === 'submitted')
        <button type="button" class="btn-accent" onclick="cancelOvertime()">
            <i class="fa-solid fa-rotate-left"></i><span>Cancel</span>
        </button>
        @endif
        <a href="{{ route('overtime-request.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card-custom">
            <div class="card-header-custom"><span><i class="fa-solid fa-clock me-2" style="color:var(--accent)"></i>Rincian Lembur</span></div>
            <div class="card-body-custom" style="font-size:13px">
                <table class="table table-sm mb-0">
                    <tr><td style="width:45%;color:var(--text-muted)">Karyawan</td><td>{{ $overtime->employee?->employee_no }} — {{ $overtime->employee?->name }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Tanggal</td><td>{{ $overtime->work_date?->format('d-m-Y') }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Shift pulang</td><td>{{ $overtime->shift_end_time?->format('H:i') ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Absensi (pulang)</td><td>{{ $overtime->attendance?->clock_out?->format('H:i') ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Lembur</td><td>{{ $overtime->start_time?->format('H:i') }} → {{ $overtime->end_time?->format('H:i') }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Durasi</td><td><strong>{{ number_format((float) $overtime->hours, 2) }} jam</strong></td></tr>
                    <tr><td style="color:var(--text-muted)">Multiplier</td><td>× {{ number_format((float) $overtime->multiplier, 2) }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Estimasi (per ÷ {{ $divisor }})</td><td>
                        <strong style="color:var(--accent)">Rp {{ number_format((float) $overtime->estimated_amount, 2, ',', '.') }}</strong>
                    </td></tr>
                    <tr><td style="color:var(--text-muted)">Alasan</td><td>{{ $overtime->reason ?? '—' }}</td></tr>
                    @if($overtime->decision_note)
                    <tr><td style="color:var(--text-muted)">Catatan keputusan</td><td>{{ $overtime->decision_note }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
        <div class="card-custom mt-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-wallet me-2" style="color:var(--accent)"></i>Dasar Perhitungan</span></div>
            <div class="card-body-custom" style="font-size:12.5px;color:var(--text-muted)">
                Upah lembur = (gaji pokok ÷ {{ $divisor }}) × jam × multiplier.
                Gaji pokok Rp {{ number_format((float) $overtime->employee?->base_salary ?? 0, 2, ',', '.') }};
                per jam = Rp {{ number_format((float) ($overtime->employee?->base_salary ?? 0) / $divisor, 2, ',', '.') }}.
                Masuk ke payroll sebagai baris <code>payslip_components</code> "Upah Lembur" saat periode 25–24 diproses.
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-custom">
            <div class="card-header-custom"><span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Status &amp; Histori</span></div>
            <div class="card-body-custom" style="font-size:12.5px">
                <div class="row g-2 mb-3">
                    <div class="col-sm-4">
                        <div class="p-2" style="border:1px solid var(--border);border-radius:8px">
                            <div style="color:var(--text-muted)">Diajukan oleh</div>
                            <div>{{ $overtime->submittedBy?->name ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-2" style="border:1px solid var(--border);border-radius:8px">
                            <div style="color:var(--text-muted)">Approval</div>
                            <div>{{ $overtime->approvedBy?->name ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-2" style="border:1px solid var(--border);border-radius:8px">
                            <div style="color:var(--text-muted)">Waktu approval</div>
                            <div>{{ $overtime->approved_at?->format('d-m-Y H:i') ?? '—' }}</div>
                        </div>
                    </div>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($overtime->logs()->with('user')->latest()->get() as $log)
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
                    <div class="p-3 text-center" style="color:var(--text-muted)">Belum ada aktivitas.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Modal approve dengan koreksi multiplier/end time --}}
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Approve — {{ $overtime->request_no }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Koreksi jam pulang (end time)</label>
                    <input type="time" id="ap-end-time" class="form-control" value="{{ $overtime->end_time?->format('H:i') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Koreksi multiplier</label>
                    <input type="number" id="ap-multiplier" class="form-control" min="1" max="10" step="0.5" value="{{ $overtime->multiplier }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Catatan (opsional)</label>
                    <textarea id="ap-note" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success btn-sm" id="btn-confirm-approve"><i class="fa fa-check"></i> Approve</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal reject --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Alasan Penolakan <span style="color:var(--danger)">*</span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <textarea id="rej-note" class="form-control" rows="3" placeholder="Alasan penolakan lembur"></textarea>
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
const approveUrl = '{{ route("overtime-request.approve", $overtime->id) }}';
const rejectUrl = '{{ route("overtime-request.reject", $overtime->id) }}';
const cancelUrl = '{{ route("overtime-request.cancel", $overtime->id) }}';

let approveModalInstance = null;
let rejectModalInstance = null;

function openApproveModal() {
    document.getElementById('ap-note').value = '';
    if (!approveModalInstance) approveModalInstance = new bootstrap.Modal(document.getElementById('approveModal'));
    approveModalInstance.show();
}

document.querySelector('#btn-confirm-approve')?.addEventListener('click', function() {
    $.post(approveUrl, {
        end_time: $('#ap-end-time').val() || null,
        multiplier: $('#ap-multiplier').val(),
        note: $('#ap-note').val().trim() || null,
        _token: '{{ csrf_token() }}'
    }).done(res => {
        approveModalInstance.hide();
        toastr.success(res.message);
        setTimeout(() => location.reload(), 500);
    }).fail(err => toastr.error(err.responseJSON?.message || 'Gagal approve.'));
});

function openRejectModal() {
    document.getElementById('rej-note').value = '';
    if (!rejectModalInstance) rejectModalInstance = new bootstrap.Modal(document.getElementById('rejectModal'));
    rejectModalInstance.show();
}

document.querySelector('#btn-confirm-reject')?.addEventListener('click', function() {
    var note = $('#rej-note').val().trim();
    if (!note) { toastr.error('Alasan penolakan wajib diisi.'); return; }

    $.post(rejectUrl, { note: note, _token: '{{ csrf_token() }}' })
    .done(res => {
        rejectModalInstance.hide();
        toastr.success(res.message);
        setTimeout(() => location.reload(), 500);
    })
    .fail(err => toastr.error(err.responseJSON?.message || 'Gagal reject.'));
});

function cancelOvertime() {
    Swal.fire({
        title: 'Batalkan pengajuan?',
        text: 'Pengajuan lembur ini akan dibatalkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Batalkan'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post(cancelUrl, { _token: '{{ csrf_token() }}' })
        .done(res => { toastr.success(res.message); setTimeout(() => location.reload(), 500); })
        .fail(err => toastr.error(err.responseJSON?.message || 'Gagal membatalkan.'));
    });
}
</script>
@endsection
