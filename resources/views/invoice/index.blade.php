@extends('layouts.app')

@section('title', 'Invoice')
@section('page-title', 'Invoice')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Invoice</h1>
        <p class="page-header-sub">Tagihan project dengan termin pembayaran, PPN persen, status approval, dan status pembayaran.</p>
    </div>
    @if($canCreate ?? false)
    <div class="page-header-actions">
        <a href="{{ route('invoice.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Invoice</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-file-invoice-dollar me-2" style="color:var(--accent)"></i>Daftar Invoice</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="inv-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No Invoice</th>
                        <th>Tanggal</th>
                        <th>Project</th>
                        <th>Company</th>
                        <th>Jatuh Tempo</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-center">PPN</th>
                        <th class="text-end">Total</th>
                        <th>Approval</th>
                        <th>Status Bayar</th>
                        <th class="text-center" style="width:120px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let invTable = null;

const invShowUrl = '{{ route("invoice.show", "__ID__") }}';
const invEditUrl = '{{ route("invoice.edit", "__ID__") }}';
const invDeleteUrl = '{{ route("invoice.destroy", "__ID__") }}';
const invCanUpdate = {{ $canUpdate ? 'true' : 'false' }};
const invCanDelete = {{ $canDelete ? 'true' : 'false' }};

function invNumber(value) {
    return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 2 });
}

function invHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function invMoney(value) {
    return invNumber(value);
}

function initInvTable() {
    if (invTable) invTable.destroy();

    invTable = $('#inv-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("invoice.data") }}',
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'invoice_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + invHtml(data) + '</strong>'; }
            },
            { data: 'invoice_date', orderable: false, searchable: false },
            { data: 'project_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<div style="font-weight:600">' + invHtml(data) + '</div><div style="font-size:12px;color:var(--text-muted)">' + invHtml(row.project_name) + '</div>';
                }
            },
            { data: 'company_name', orderable: false, searchable: true, render: function(data) { return invHtml(data); } },
            { data: 'due_date', orderable: false, searchable: false },
            { data: 'subtotal', orderable: false, searchable: false, className: 'text-end',
                render: function(data) { return invMoney(data); }
            },
            { data: 'ppn', orderable: false, searchable: false, className: 'text-center',
                render: function(data) { return invNumber(data) + '%'; }
            },
            { data: 'total_payment', orderable: false, searchable: false, className: 'text-end',
                render: function(data) { return invMoney(data); }
            },
            { data: 'approval_badge', orderable: false, searchable: false },
            { data: 'status_badge', orderable: false, searchable: false },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var modifiable = row.approval_status === 'draft' || row.approval_status === 'rejected';
                    var btn = '<div class="d-flex justify-content-center gap-1 flex-wrap">';
                    btn += '<a href="' + invShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (invCanUpdate && modifiable) {
                        btn += '<a href="' + invEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Edit"><i class="fa fa-pen"></i></a>';
                    }
                    if (invCanDelete && modifiable) {
                        btn += '<button class="btn-icon text-danger" title="Hapus" onclick="invDelete(' + data + ')"><i class="fa fa-trash"></i></button>';
                    }
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function invDelete(id) {
    Swal.fire({
        title: 'Hapus Invoice?',
        text: 'Data invoice dan item akan dihapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: invDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Invoice dihapus.');
            invTable.ajax.reload(null, false);
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus invoice.');
        });
    });
}

$(document).ready(function() {
    initInvTable();
});
</script>
@endsection
