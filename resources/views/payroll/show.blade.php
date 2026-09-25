@extends('layouts.app')

@section('title', 'Payroll — '.$period->name)
@section('page-title', 'Periode Payroll')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">
            <span style="font-family:monospace;color:var(--accent)">{{ $period->name }}</span>
            <span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">{{ \Str::title($period->status) }}</span>
            @if($period->is_thr)
            <span class="status-badge status-active">THR</span>
            @endif
        </h1>
        <p class="page-header-sub">{{ $period->start_date->format('d-m-Y') }} s/d {{ $period->end_date->format('d-m-Y') }}</p>
    </div>
    <div class="page-header-actions">
        @if($canApprove && $period->status === 'processing')
        <button type="button" class="btn-accent" onclick="finalizePeriod()">
            <i class="fa-solid fa-lock"></i><span>Process &amp; Lock</span>
        </button>
        @endif
        @if($canRead && $payslips->count())
        <button type="button" class="btn-accent" onclick="window.open('{{ route('payroll.excel', $period->id) }}')">
            <i class="fa fa-file-excel"></i><span>Excel</span>
        </button>
        @endif
        <a href="{{ route('payroll.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
</div>

{{-- Step 1 Compile --}}
@php use App\Models\PayrollPeriod; @endphp
@if(in_array($period->status, [PayrollPeriod::STATUS_OPEN, PayrollPeriod::STATUS_PROCESSING]))
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-list-check me-2" style="color:var(--accent)"></i>Step 1: Compile Data (cutoff 25–24)
            @if($period->status === PayrollPeriod::STATUS_PROCESSING)
            <span class="badge bg-warning-subtle text-dark ms-1" style="font-size:11px">draft sudah dibuat — generate ulang utk menambah/perbarui karyawan</span>
            @endif
        </span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive" style="max-height:44vh;overflow:auto">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:40px" class="text-center"><input type="checkbox" id="check-all"></th>
                        <th style="width:130px">No. Pegawai</th>
                        <th style="width:180px">Nama</th>
                        <th class="text-end" style="width:140px">Gaji Pokok</th>
                        <th class="text-end" style="width:140px">Prorata (hari kerja)</th>
                        <th class="text-center" style="width:110px">Hadir/Telat</th>
                        <th class="text-center" style="width:80px">Alpa</th>
                        <th class="text-end" style="width:140px">Angsuran jatuh tempo</th>
                        <th class="text-end" style="width:130px">Lembur approved</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($compilePreview ?? [] as $row)
                    <tr>
                        <td class="text-center"><input type="checkbox" class="emp-check" value="{{ $row['id'] }}"></td>
                        <td><span style="font-family:monospace;font-size:12px">{{ $row['employee_no'] }}</span></td>
                        <td>{{ $row['name'] }}</td>
                        <td class="text-end">Rp {{ number_format($row['base_salary'], 2, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($row['base_prorata'], 2, ',', '.') }} <span style="color:var(--text-muted);font-size:11px">/{{ $row['attendance']['working_days'] }} hari kerja</span></td>
                        <td class="text-center">{{ $row['attendance']['present_days'] + $row['attendance']['late_count'] }} ({{ $row['attendance']['late_count'] }} telat)</td>
                        <td class="text-center">{{ $row['attendance']['absent_days'] ?: '—' }}</td>
                        <td class="text-end">{{ $row['loan_installment'] ? 'Rp '.number_format($row['loan_installment']['amount'], 2, ',', '.').' (due '.$row['loan_installment']['due'].')' : '—' }}</td>
                        <td class="text-end">{{ $row['overtime_amount'] ? 'Rp '.number_format($row['overtime_amount'], 2, ',', '.') : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center" style="color:var(--text-muted)">Tidak ada karyawan aktif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="text-end p-2">
            @if($canCreate)
            <button class="btn btn-primary btn-sm" id="btn-generate-draft"><i class="fa fa-cogs me-1"></i> Generate Draft Payslips</button>
            @endif
        </div>
    </div>
</div>
@endif

@if($period->status !== PayrollPeriod::STATUS_OPEN)
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-users me-2" style="color:var(--accent)"></i>Draft Payslips ({{ $payslips->count() }})</span>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive" style="max-height:56vh;overflow:auto">
                    <table class="table table-custom align-middle mb-0" id="payslip-table">
                        <thead>
                            <tr>
                                <th style="width:120px">No. Pegawai</th>
                                <th style="width:180px">Nama</th>
                                <th class="text-end" style="width:130px">Gaji Pokok</th>
                                <th class="text-end" style="width:130px">Gross</th>
                                <th class="text-end" style="width:120px">Deduction</th>
                                <th class="text-end" style="width:130px">Net (THP)</th>
                                <th style="width:100px" class="text-center">Status</th>
                                <th class="text-center" style="width:90px">Slip</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payslips as $p)
                            <tr>
                                <td><span style="font-family:monospace">{{ $p->employee->employee_no }}</span></td>
                                <td>{{ $p->employee->name }}</td>
                                <td class="text-end">Rp {{ number_format($p->base_salary_prorata, 2, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format((float) $p->total_gross, 2, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format((float) $p->total_deduction, 2, ',', '.') }}</td>
                                <td class="text-end"><strong>Rp {{ number_format((float) $p->total_net, 2, ',', '.') }}</strong></td>
                                <td class="text-center">{{ $p->locked_at ? '<span class="status-badge status-active">Locked</span>' : '<span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">Draft</span>' }}</td>
                                <td class="text-center">
                                    <a class="btn-icon" target="_blank" href="{{ route('payroll.payslip.pdf', [$period->id, $p->id]) }}" title="Slip PDF"><i class="fa-solid fa-file-pdf"></i></a>
                                    @if($canUpdate && !$p->locked_at)
                                    <button class="btn-icon" title="Edit Komponen" onclick="openSlipModal({{ $p->id }})"><i class="fa-solid fa-pen"></i></button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center" style="color:var(--text-muted)">Belum ada payslip — generate draft.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-custom">
            <div class="card-header-custom"><span><i class="fa-solid fa-chart-line me-2" style="color:var(--accent)"></i>Ringkasan Periode</span></div>
            <div class="card-body-custom" style="font-size:13px">
                <table class="table table-sm mb-0">
                    <tr><td style="color:var(--text-muted)">Karyawan</td><td>{{ $payslips->count() }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Total Gross</td><td>Rp {{ number_format((float) $payslips->sum('total_gross'), 2, ',', '.') }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Total Deduction</td><td>Rp {{ number_format((float) $payslips->sum('total_deduction'), 2, ',', '.') }}</td></tr>
                    <tr><td style="color:var(--text-muted)">THR</td><td>Rp {{ number_format((float) $payslips->sum('total_thr'), 2, ',', '.') }}</td></tr>
                    <tr><td style="color:var(--text-muted)">Total Net</td><td><strong>Rp {{ number_format((float) $payslips->sum('total_net'), 2, ',', '.') }}</strong></td></tr>
                </table>
            </div>
        </div>
        <div class="card-custom mt-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-clipboard-list me-2" style="color:var(--accent)"></i>Riwayat</span></div>
            <div class="card-body-custom" style="font-size:12.5px">
                <div class="list-group list-group-flush" style="max-height:30vh;overflow:auto">
                    @forelse($logs as $log)
                    <div class="list-group-item bg-transparent border-0 border-bottom py-1">
                        <div><strong>{{ \Str::title($log->action) }}</strong>
                            <span style="float:right;color:var(--text-muted)">{{ $log->actor?->name ?? '—' }} · {{ $log->created_at->format('d-m-Y H:i') }}</span></div>
                        @if($log->note)<div style="color:var(--text-muted)">{{ $log->note }}</div>@endif
                    </div>
                    @empty
                    <div class="p-2 text-center" style="color:var(--text-muted)">—</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('modals')
{{-- Modal editor komponen payslip (Step 2 Draft) --}}
<div class="modal fade" id="slipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="slipModalTitle">Komponen Payslip</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive mb-3" style="max-height:36vh;overflow:auto">
                    <table id="comp-table" class="table table-sm table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:120px">Tipe</th>
                                <th>Label</th>
                                <th class="text-end" style="width:130px">Nominal</th>
                                <th class="text-center" style="width:50px">Del</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <div class="totals p-2 mb-3" id="slip-totals" style="border:1px solid var(--border);border-radius:8px;font-size:12.5px">
                    —
                </div>

                <form id="comp-form" autocomplete="off" class="row g-2">
                    <input type="hidden" id="comp-edit-id" value="">
                    <div class="col-md-4">
                        <select id="comp-type" class="form-select form-select-sm">
                            <option value="allowance">Tunjangan</option>
                            <option value="bonus">Bonus</option>
                            <option value="overtime">Lembur (manual)</option>
                            <option value="deduction">Potongan</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" id="comp-label" class="form-control form-control-sm" placeholder="Label komponen" maxlength="100">
                    </div>
                    <div class="col-md-3">
                        <input type="number" id="comp-amount" class="form-control form-control-sm" placeholder="Nominal" step="1000">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-primary" id="btn-save-comp"><i class="fa fa-save"></i></button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let slipModalInstance = null;
let currentPayslipId = null;

const payUrl = '{{ url("payroll") }}/{{ $period->id }}';

function openSlipModal(payslipId) {
    currentPayslipId = payslipId;

    $.get(payUrl + '/edit', function(res) {
        var slip = (res.data?.payslips || []).find(p => p.id === payslipId);
        if (!slip) { toastr.error('Payslip tidak ditemukan.'); return; }

        $('#slipModalTitle').text('Komponen Payslip — ' + (slip.employee?.name ?? payslipId));
        renderComponents(slip.components || [], slip.totals ?? slip);

        if (!slipModalInstance) slipModalInstance = new bootstrap.Modal(document.getElementById('slipModal'));
        slipModalInstance.show();
    }).fail(() => toastr.error('Gagal memuat payslip.'));
}

function renderComponents(components, slip) {
    let rows = '';
    components.sort((a, b) => a.sort_order - b.sort_order).forEach(c => {
        rows += '<tr>' +
            '<td><span class="badge bg-light text-dark border" style="font-size:11px">' + c.type + '</span></td>' +
            '<td>' + c.label + (c.source_type === 'manual' ? ' <i class="fa fa-edit" style="font-size:10px"></i>' : '') + '</td>' +
            '<td class="text-end">Rp ' + Number(c.amount).toLocaleString('id-ID') + '</td>' +
            '<td class="text-center">' +
                (c.source_type === 'manual'
                    ? '<button class="btn-icon danger" onclick="deleteComponent(' + c.id + ')"><i class="fa-solid fa-trash-can"></i></button>'
                    : '<span style="color:var(--text-muted)">—</span>') +
            '</td>' +
        '</tr>';
    });
    $('#comp-table tbody').html(rows || '<tr><td colspan="4" class="text-center text-muted">Belum ada komponen.</td></tr>');
    $('#slip-totals').html(
        'Gross: <strong>Rp ' + Number(slip.total_gross).toLocaleString('id-ID') + '</strong>' +
        ' · Deduction: <strong style="color:#b45309">Rp ' + Number(slip.total_deduction).toLocaleString('id-ID') + '</strong>' +
        ' · Net: <strong style="color:var(--accent)">Rp ' + Number(slip.total_net).toLocaleString('id-ID') + '<strong>');
}

function saveComponent() {
    var compId = $('#comp-edit-id').val();
    var payload = {
        label: $('#comp-label').val().trim(),
        type: $('#comp-type').val(),
        amount: $('#comp-amount').val()
    };
    if (!payload.label || !payload.amount) {
        toastr.error('Label & nominal wajib diisi.');
        return;
    }

    var url = compId
        ? payUrl + '/components/' + compId
        : payUrl + '/components';

    $.ajax({
        url: url,
        method: compId ? 'PUT' : 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        data: payload,
        dataType: 'json'
    }).done(res => {
        toastr.success(res.message);
        location.reload();
    }).fail(err => {
        toastr.error(err.responseJSON?.message || 'Gagal menyimpan komponen.');
    });
}

$('#btn-save-comp').on('click', saveComponent);

function deleteComponent(id) {
    $.ajax({
        url: payUrl + '/components/' + id,
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        dataType: 'json'
    }).done(res => {
        toastr.success(res.message);
        location.reload();
    }).fail(err => toastr.error(err.responseJSON?.message || 'Gagal menghapus.'));
}

document.addEventListener('DOMContentLoaded', function() {
    var checkAll = document.getElementById('check-all');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            document.querySelectorAll('tbody input[type=checkbox]:not(#check-all)').forEach(cb => { cb.checked = this.checked; });
        });
    }

    $('#btn-generate-draft').on('click', function() {
        var ids = $('tbody input[type=checkbox]:checked').map((i, cb) => cb.value).get();
        if (!ids.length) { toastr.error('Pilih minimal satu karyawan.'); return; }

        $('#btn-generate-draft').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyusun...');
        $.post('{{ route("payroll.generate-draft", $period->id) }}', {
            employee_ids: ids,
            _token: '{{ csrf_token() }}'
        }).done(res => {
            toastr.success(res.message);
            setTimeout(() => location.reload(), 500);
        }).fail(err => {
            toastr.error(err.responseJSON?.message || 'Gagal generate draft.');
            $('#btn-generate-draft').prop('disabled', false).html('<i class="fa fa-cogs me-1"></i> Generate Draft Payslips');
        });
    });

    function finalizePeriodBinding() {
        window.finalizePeriod = function() {
            Swal.fire({
                title: 'Process & Lock periode?',
                text: 'Semua payslip terkunci read-only.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Lock'
            }).then(r => {
                if (!r.isConfirmed) return;
                $.post('{{ route("payroll.finalize", $period->id) }}', { _token: '{{ csrf_token() }}' })
                .done(res => { toastr.success(res.message); setTimeout(() => location.reload(), 500); })
                .fail(err => toastr.error(err.responseJSON?.message || 'Gagal lock.'));
            });
        };
    }
    finalizePeriodBinding();
});
</script>
@endsection
