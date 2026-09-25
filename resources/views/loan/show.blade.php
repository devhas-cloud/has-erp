@extends('layouts.app')

@section('title', 'Pinjaman — '.$loan->loan_no)
@section('page-title', 'Detail Peminjaman')

@section('content')
@php
    $paidCount = $installs->where('status', 'paid')->count();
    $totalCount = $installs->count();
    $progressPct = $totalCount ? round($paidCount * 100 / $totalCount) : 0;
    $unpaidTotal = $installs->where('status', 'unpaid')->sum('amount');
@endphp

<div class="page-header">
    <div>
        <h1 class="page-header-title">
            <span style="font-family:monospace;color:var(--accent)">{{ $loan->loan_no }}</span>
            <span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">{{ \Str::title($loan->status) }}</span>
        </h1>
        <p class="page-header-sub">{{ $loan->employee?->employee_no }} — {{ $loan->employee?->name }}</p>
    </div>
    <div class="page-header-actions">
        @if($canApprove && $loan->status === 'pending')
        <button type="button" class="btn-accent" onclick="decideLoan('approve')">
            <i class="fa-solid fa-check"></i><span>Approve</span>
        </button>
        <button type="button" class="btn-accent-danger" onclick="decideLoan('reject', true)">
            <i class="fa-solid fa-ban"></i><span>Reject</span>
        </button>
        @endif
        @if($canApprove && $loan->status === 'active')
        <button type="button" class="btn-accent" onclick="settleEarly()">
            <i class="fa-solid fa-money-bill-transfer"></i><span>Settle Early</span>
        </button>
        @endif
        <a href="{{ route('loan.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card-custom">
            <div class="card-header-custom"><span><i class="fa-solid fa-money-check-dollar me-2" style="color:var(--accent)"></i>Rincian</span></div>
            <div class="card-body-custom" style="font-size:13px">
                <table class="table table-sm mb-0">
                    <tr><td style="width:45%;color:var(--text-muted)">Karyawan</td><td>{{ $loan->employee?->employee_no }} — {{ $loan->employee?->name }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Nominal</td><td><strong>Rp {{ number_format((float) $loan->amount, 2, ',', '.') }}</strong></td></tr>
                    <tr><td style="color:var(--text-muted)">Tenor</td><td>{{ $loan->tenor_months }} bulan</td></tr>
                    <tr><td style="color:var(--text-muted)">Angsuran/bulan</td><td>Rp {{ number_format((float) $loan->installment_amount, 2, ',', '.') }}</td></tr>
                    @if($loan->fee_amount > 0)
                    <tr><td style="color:var(--text-muted)">Fee</td><td>Rp {{ number_format((float) $loan->fee_amount, 2, ',', '.') }}</td></tr>
                    @endif
                    <tr><td style="color:var(--text-muted)">Cair</td><td>{{ $loan->disburse_date?->format('d-m-Y') ?? '—' }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Keperluan</td><td>{{ $loan->purpose ?? '—' }}</td></tr>
                    @if($loan->decision_note)
                    <tr><td style="color:var(--text-muted)">Catatan keputusan</td><td>{{ $loan->decision_note }}</td></tr>
                    @endif
                    <tr><td style="color:var(--text-muted)">Approved</td><td>{{ $loan->approvedBy?->name ?? '—' }} {{ $loan->approved_at?->format('d-m-Y H:i') }}</td></tr>
                </table>
            </div>
        </div>
        <div class="card-custom mt-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-chart-simple me-2" style="color:var(--accent)"></i>Progress</span></div>
            <div class="card-body-custom">
                <div class="d-flex justify-content-between mb-1" style="font-size:12.5px">
                    <span>Terbayar: <strong>Rp {{ number_format((float) $paidTotal, 2, ',', '.') }}</strong></span>
                    <span style="color:var(--text-muted)">{{ $paidCount }}/{{ $totalCount }} angsuran</span>
                </div>
                @if($totalCount)
                <div class="progress" style="height:10px">
                    <div class="progress-bar" style="width:{{ $progressPct }}%"></div>
                </div>
                @if($unpaidTotal > 0)
                <div class="mt-2" style="font-size:12px;color:var(--text-muted)">Sisa untuk dibayar: <strong style="color:#b45309">Rp {{ number_format((float) $unpaidTotal, 2, ',', '.') }}</strong></div>
                @endif
                @else
                <div class="text-center p-2" style="color:var(--text-muted);font-size:12.5px">Jadwal angsuran digenerate saat loan approved.</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-custom">
            <div class="card-header-custom"><span><i class="fa-solid fa-list-ol me-2" style="color:var(--accent)"></i>Jadwal Angsuran</span></div>
            <div class="card-body-custom p-2">
                <div class="table-responsive" style="max-height:56vh;overflow:auto">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:50px" class="text-center">No</th>
                                <th style="width:130px">Jatuh tempo</th>
                                <th class="text-end" style="width:140px">Nominal</th>
                                <th class="text-center" style="width:100px">Status</th>
                                <th style="width:130px">Sumber</th>
                                <th class="text-center" style="width:110px">Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($installs as $inst)
                            <tr>
                                <td class="text-center">{{ $inst->installment_no }}</td>
                                <td>{{ $inst->due_date?->format('d-m-Y') }}</td>
                                <td class="text-end">Rp {{ number_format((float) $inst->amount, 2, ',', '.') }}</td>
                                <td class="text-center">
                                    @if($inst->status === 'paid')
                                    <span class="status-badge status-active">Paid</span>
                                    @else
                                    <span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">Unpaid</span>
                                    @endif
                                </td>
                                <td style="font-size:12px">
                                    @if($inst->paid_payslip_id)
                                    payroll #{{ $inst->paid_payslip_id }}
                                    @elseif($inst->paid_at)
                                    manual ({{ $inst->paid_at->format('d-m-Y H:i') }})
                                    @else
                                    —
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($canApprove && $inst->status === 'unpaid' && $loan->status === 'active')
                                    <button class="btn-icon" title="Bayar Manual" onclick="payInstallment({{ $inst->id }})">
                                        <i class="fa-solid fa-cash-register"></i>
                                    </button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center" style="color:var(--text-muted)">Belum ada jadwal angsuran.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const approveUrl = '{{ route("loan.approve", $loan->id) }}';
const rejectUrl = '{{ route("loan.reject", $loan->id) }}';
const settleUrl = '{{ route("loan.settle", $loan->id) }}';
const payUrlScheme = '{{ route("loan.installments.pay", ":id") }}';

function refreshPage(msg) {
    toastr.success(msg);
    setTimeout(() => location.reload(), 500);
}

function decideLoan(action, needsNote) {
    var title = action === 'approve' ? 'Approve Pinjaman?' : 'Reject Pinjaman?';

    Swal.fire({
        title: title,
        icon: action === 'approve' ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonText: action === 'approve' ? 'Ya, Approve' : 'Ya, Tolak'
    }).then(r => {
        if (!r.isConfirmed) return;
        var note = null;
        if (needsNote) {
            Swal.fire({ title: 'Alasan Penolakan', input: 'textarea', showCancelButton: true, confirmButtonText: 'Tolak' })
            .then(rr => {
                if (!rr.isConfirmed || !rr.value) { toastr.error('Alasan penolakan wajib diisi.'); return; }
                sendAction(action, { note: rr.value });
            });
            return;
        }
        sendAction(action, {});
    });
}

function sendAction(action, data) {
    var url = { approve: approveUrl, reject: rejectUrl }[action];
    $.post(url, { ...data, _token: '{{ csrf_token() }}' })
    .done(res => { toastr.success(res.message); setTimeout(() => location.reload(), 600); })
    .fail(err => toastr.error(err.responseJSON?.message || 'Gagal memproses.'));
}

function settleEarly() {
    Swal.fire({
        title: 'Settle Early?',
        text: 'Semua sisa angsuran akan ditandai lunas sekaligus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Lunaskan'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post(settleUrl, { _token: '{{ csrf_token() }}' })
        .done(res => { toastr.success(res.message); setTimeout(() => location.reload(), 600); })
        .fail(err => toastr.error(err.responseJSON?.message || 'Gagal lunaskan.'));
    });
}

function payInstallment(id) {
    Swal.fire({
        title: 'Bayar Manual angsuran?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Bayar'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post(payUrlScheme.replace(':id', id), { _token: '{{ csrf_token() }}' })
        .done(res => { toastr.success(res.message); setTimeout(() => location.reload(), 600); })
        .fail(err => toastr.error(err.responseJSON?.message || 'Gagal bayar.'));
    });
}
</script>
@endsection
