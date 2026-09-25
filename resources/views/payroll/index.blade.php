@extends('layouts.app')

@section('title', 'Penggajian/Payrol')
@section('page-title', 'Penggajian/Payrol')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Penggajian / Payrol</h1>
        <p class="page-header-sub">Periode cutoff 25 → 24 — wizard draft, export Excel, PDF slip</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i><span>Buat Periode</span>
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
                    @foreach(['open','processing','closed'] as $s)
                    <option value="{{ $s }}">{{ \Str::title($s) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-file-invoice-dollar me-2" style="color:var(--accent)"></i>Daftar Periode</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="payroll-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width:200px">Periode</th>
                        <th style="width:230px">Range</th>
                        <th style="width:100px" class="text-center">Karyawan</th>
                        <th class="text-end" style="width:160px">Gross</th>
                        <th class="text-end" style="width:160px">Deduction</th>
                        <th class="text-end" style="width:160px">Net</th>
                        <th style="width:100px" class="text-center">THR</th>
                        <th style="width:110px">Status</th>
                        <th class="text-center" style="width:130px">Aksi</th>
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
                <h6 class="modal-title">Buat Periode Payroll</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="font-size:13px">
                <p>Range periode cutoff <strong>25 s/d 24</strong> akan di-auto-generate dari periode terakhir (anti-overlap).</p>
                <p style="color:var(--text-muted)">Bulan THR (Maret) otomatis ditandai <code>is_thr</code> bila periode ini mencakupnya.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-period"><i class="fa fa-save me-1"></i> Buat</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let payrollTable = null;
let createModalInstance = null;

const statusBadges = {
    open: '<span class="status-badge" style="background:rgba(107,114,128,.15);color:#4b5563;">Open</span>',
    processing: '<span class="status-badge" style="background:rgba(245,158,11,.15);color:#b45309;">Processing</span>',
    closed: '<span class="status-badge status-active">Closed</span>'
};

function initPayrollTable() {
    if (payrollTable) {
        payrollTable.destroy();
        payrollTable = null;
    }

    payrollTable = $('#payroll-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("payroll.data") }}',
            data: function(d) {
                d.status = $('#filter-status').val();
            }
        },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'name', orderable: false, searchable: true,
                render: d => '<span style="font-family:monospace;color:var(--accent)">' + d + '</span>' },
            { data: 'range', orderable: false, searchable: false },
            { data: 'employees', orderable: false, searchable: false, className: 'text-center' },
            { data: 'gross', orderable: false, searchable: false, className: 'text-end' },
            { data: 'deduction', orderable: false, searchable: false, className: 'text-end' },
            { data: 'net', orderable: false, searchable: false, className: 'text-end' },
            { data: 'is_thr', orderable: false, searchable: false, className: 'text-center',
                render: d => d ? '<span class="status-badge status-active">THR</span>' : '<span style="color:var(--text-muted)">—</span>' },
            { data: 'status', orderable: false, searchable: false,
                render: d => statusBadges[d] || d },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: d => '<a class="btn-icon" title="Detail" href="{{ url("payroll") }}/' + d + '"><i class="fa-solid fa-eye"></i></a>' }
        ]
    });
}

function openCreateModal() {
    if (!createModalInstance) createModalInstance = new bootstrap.Modal(document.getElementById('createModal'));
    createModalInstance.show();
}

$('#btn-save-period').on('click', function() {
    $.post('{{ route("payroll.store") }}', { _token: '{{ csrf_token() }}' })
    .done(res => {
        toastr.success(res.message);
        createModalInstance.hide();
        if (res.data?.id) {
            setTimeout(() => window.location.href = '{{ url("payroll") }}/' + res.data.id, 400);
        }
    })
    .fail(err => toastr.error(err.responseJSON?.message || 'Gagal membuat periode.'));
});

document.addEventListener('DOMContentLoaded', function() {
    initPayrollTable();
    document.getElementById('filter-status').addEventListener('change', () => payrollTable.ajax.reload());
});
</script>
@endsection
