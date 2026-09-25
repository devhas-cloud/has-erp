@extends('layouts.app')

@section('title', 'Peminjaman')
@section('page-title', 'Peminjaman (Kas Bon)')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Peminjaman (Kas Bon)</h1>
        <p class="page-header-sub">Pinjaman karyawan + jadwal angsuran bulanan — dipotong otomatis di payroll</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i><span>Pengajuan Pinjaman</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-body-custom py-2">
        <div class="row g-2">
            <div class="col-auto">
                <select id="filter-status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    @foreach(['pending','active','paid_off','rejected','settled'] as $s)
                    <option value="{{ $s }}">{{ \Str::title($s) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-hand-holding-usd me-2" style="color:var(--accent)"></i>Daftar Pinjaman</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="loan-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width:130px">No. Pinjaman</th>
                        <th style="width:200px">Karyawan</th>
                        <th style="width:140px">Total</th>
                        <th style="width:90px" class="text-center">Tenor</th>
                        <th style="width:140px">Angsuran/bln</th>
                        <th style="width:110px" class="text-center">Progress</th>
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
                <h6 class="modal-title" id="loanModalTitle">Pengajuan Pinjaman</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="loan-form" autocomplete="off">
                    <input type="hidden" id="loan-edit-id" value="">
                    <div class="mb-3">
                        <label class="form-label">Karyawan <span style="color:var(--danger)">*</span></label>
                        <select id="lo-employee" class="form-select" required></select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nominal Pinjaman <span style="color:var(--danger)">*</span></label>
                                <input type="number" id="lo-amount" class="form-control" min="1" step="10000" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tenor (bulan) <span style="color:var(--danger)">*</span></label>
                                <input type="number" id="lo-tenor" class="form-control" min="1" max="36" value="6" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Angsuran/bulan (auto)</label>
                                <input type="number" id="lo-installment" class="form-control" min="1" step="10000" placeholder="amount ÷ tenor">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Fee (opsional)</label>
                                <input type="number" id="lo-fee" class="form-control" min="0" step="10000" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tanggal cair (opsional)</label>
                                <input type="date" id="lo-disburse" class="form-control">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Keperluan</label>
                                <textarea id="lo-purpose" class="form-control" rows="2" maxlength="500"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-loan"><i class="fa fa-save me-1"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let loanTable = null;
let createModalInstance = null;

const loanUpdateUrl = '{{ route("loan.update", ":id") }}';

const statusBadges = {
    pending: '<span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">Pending</span>',
    approved: '<span class="status-badge" style="background:rgba(59,130,246,.12);color:#1d4ed8;">Approved</span>',
    active: '<span class="status-badge status-active">Active</span>',
    paid_off: '<span class="status-badge" style="background:rgba(22,163,74,.12);color:#16a34a;">Paid Off</span>',
    rejected: '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Rejected</span>',
    settled: '<span class="status-badge" style="background:rgba(59,130,246,.12);color:#1d4ed8;">Settled</span>'
};

function initLoanTable() {
    if (loanTable) {
        loanTable.destroy();
        loanTable = null;
    }

    loanTable = $('#loan-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("loan.data") }}',
            data: function(d) {
                d.status = $('#filter-status').val();
            }
        },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'loan_no', orderable: false, searchable: true,
                render: d => '<span style="font-family:monospace;color:var(--accent)">' + d + '</span>' },
            { data: 'employee', orderable: false, searchable: true,
                render: (d,t,r) => '<a href="{{ url("loan") }}/' + r.id + '" style="text-decoration:none;color:var(--text-primary)"><span style="font-family:monospace;font-size:11px;color:var(--accent)">' + r.employee_no + '</span><br><strong>' + d + '</strong></a>' },
            { data: 'amount', orderable: false, searchable: false, className: 'text-end' },
            { data: 'tenor', orderable: false, searchable: false, className: 'text-center',
                render: d => d + ' bln' },
            { data: 'installment_amount', orderable: false, searchable: false, className: 'text-end' },
            { data: 'progress', orderable: false, searchable: false, className: 'text-center',
                render: d => d || '<span style="color:var(--text-muted)">—</span>' },
            { data: 'status', orderable: false, searchable: false,
                render: d => statusBadges[d] || d },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, t, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    btn += '<a class="btn-icon" title="Detail" href="{{ url("loan") }}/' + data + '"><i class="fa-solid fa-eye"></i></a>';
                    @if($canUpdate)
                    if (row.status === 'pending') {
                        btn += '<button class="btn-icon" title="Edit" onclick="openEditModal(' + data + ')"><i class="fa-solid fa-pen"></i></button>';
                    }
                    @endif
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function openCreateModal() {
    document.getElementById('loan-form').reset();
    $('#loan-edit-id').val('');
    $('#lo-employee').val(null).trigger('change').prop('disabled', false);
    $('#lo-fee').val(0);
    $('#loanModalTitle').text('Pengajuan Pinjaman');

    if (!createModalInstance) {
        createModalInstance = new bootstrap.Modal(document.getElementById('createModal'));
    }
    createModalInstance.show();
}

function openEditModal(id) {
    var row = loanTable.rows().data().toArray().find(r => r.id === id);
    if (!row) { toastr.error('Data pinjaman tidak ditemukan.'); return; }

    document.getElementById('loan-form').reset();
    $('#loan-edit-id').val(row.id);
    $('#loanModalTitle').text('Edit Pinjaman — ' + row.loan_no);

    // isi karyawan (inject option agar select2 menampilkan)
    $('#lo-employee').val(null).trigger('change');
    $('#lo-employee').append(new Option(row.employee_no + ' — ' + row.employee, row.employee_id, true, true)).trigger('change');
    $('#lo-employee').prop('disabled', true);

    $('#lo-amount').val(row.amount ? parseFloat(row.amount.replace(/,/g, '')) : '');
    $('#lo-tenor').val(row.tenor);
    $('#lo-installment').val(row.installment_amount ? parseFloat(row.installment_amount.replace(/,/g, '')) : '');
    $('#lo-fee').val(row.fee_amount ? parseFloat(row.fee_amount.replace(/,/g, '')) : 0);
    $('#lo-disburse').val(row.disburse_date || '');
    $('#lo-purpose').val(row.purpose || '');

    if (!createModalInstance) {
        createModalInstance = new bootstrap.Modal(document.getElementById('createModal'));
    }
    createModalInstance.show();
}

$('#btn-save-loan').on('click', function() {
    var editId = $('#loan-edit-id').val();
    var emp = $('#lo-employee').val();
    if (!emp) { toastr.error('Karyawan wajib dipilih.'); return; }
    if (!$('#lo-amount').val() || $('#lo-amount').val() <= 0) { toastr.error('Nominal pinjaman tidak valid.'); return; }
    if (!$('#lo-tenor').val()) { toastr.error('Tenor wajib diisi.'); return; }

    var payload = {
        amount: $('#lo-amount').val(),
        tenor_months: $('#lo-tenor').val(),
        installment_amount: $('#lo-installment').val() || null,
        fee_amount: $('#lo-fee').val() || 0,
        disburse_date: $('#lo-disburse').val() || null,
        purpose: $('#lo-purpose').val().trim() || null
    };
    if (!editId) {
        payload.employee_id = emp;
    }

    $('#btn-save-loan').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...');
    $.ajax({
        url: editId ? loanUpdateUrl.replace(':id', editId) : '{{ route("loan.store") }}',
        method: editId ? 'PUT' : 'POST',
        data: payload,
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(res => {
        toastr.success(res.message);
        createModalInstance.hide();
        loanTable.ajax.reload();
    }).fail(err => {
        var msg = err.responseJSON?.message || 'Gagal menyimpan pengajuan.';
        if (err.responseJSON?.errors) msg = Object.values(err.responseJSON.errors)[0][0];
        toastr.error(msg);
    }).always(() => {
        $('#btn-save-loan').prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan');
    });
});

document.addEventListener('DOMContentLoaded', function() {
    initLoanTable();

    document.getElementById('filter-status').addEventListener('change', () => loanTable.ajax.reload());

    $('#lo-employee').select2({
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
    });

    // auto hitung installment
    $('#lo-amount, #lo-tenor').on('change keyup', function() {
        var amount = parseFloat($('#lo-amount').val()) || 0;
        var tenor = parseInt($('#lo-tenor').val()) || 1;
        $('#lo-installment').attr('placeholder', 'amount ÷ tenor = ' + (amount / tenor).toLocaleString('id-ID', { maximumFractionDigits: 0 }));
    });

    // reset mode edit saat modal ditutup
    $('#createModal').on('hidden.bs.modal', function() {
        $('#lo-employee').prop('disabled', false);
    });
});
</script>
@endsection
