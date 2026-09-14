@extends('layouts.app')

@section('title', 'PO Supplier Approval')
@section('page-title', 'PO Supplier Approval')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">PO Supplier Approval</h1>
        <p class="page-header-sub">Menandai quotation yang sudah Finish (PO dari customer terupload) boleh dilanjutkan ke proses PO barang ke supplier oleh purchasing — memerlukan approval dari 2 orang berbeda</p>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-truck-fast me-2" style="color:var(--accent)"></i>Daftar Quotation Finish</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="po-supplier-approval-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Nomor Quotation</th>
                        <th>To (Company)</th>
                        <th>TOP DP</th>
                        <th class="text-end">Total</th>
                        <th>Dibuat Oleh</th>
                        <th>Approval PO Supplier</th>
                        <th class="text-center" style="width:140px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let poSupplierApprovalTable = null;

const poSupplierApprovalApproveUrl = '{{ route("po-supplier-approval.approve", "__ID__") }}';
const quotationShowUrl = '{{ route("quotation.show", "__ID__") }}';

function escapeHtmlPsa(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function initPoSupplierApprovalTable() {
    if (poSupplierApprovalTable) {
        poSupplierApprovalTable.destroy();
    }

    poSupplierApprovalTable = $('#po-supplier-approval-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("po-supplier-approval.data") }}',
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'quotation_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + escapeHtmlPsa(data) + '</strong>'; }
            },
            { data: 'to_name', orderable: false, searchable: true,
                render: function(data) { return data ? escapeHtmlPsa(data) : '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'requires_dp', orderable: false, searchable: false, className: 'text-center' },
            { data: 'grand_total_label', orderable: false, searchable: false, className: 'text-end' },
            { data: 'creator_name', orderable: false, searchable: true },
            {
                data: 'approval_count', orderable: false, searchable: false,
                render: function(data, type, row) {
                    var color = row.ready_for_supplier_po ? '#166534' : '#92400e';
                    var bg = row.ready_for_supplier_po ? '#dcfce7' : '#fef3c7';
                    var label = row.ready_for_supplier_po ? ('Boleh PO ke Supplier (' + data + '/2)') : (data + '/2 — Menunggu Approver Kedua');
                    var html = '<span class="badge" style="background:' + bg + ';color:' + color + ';font-size:11px">' + label + '</span>';
                    if (row.approvers && row.approvers.length) {
                        html += '<div style="font-size:11px;color:var(--text-muted);margin-top:2px">';
                        row.approvers.forEach(function(a) {
                            html += '<div>' + escapeHtmlPsa(a.name) + ' — ' + escapeHtmlPsa(a.approved_at) + '</div>';
                        });
                        html += '</div>';
                    }
                    return html;
                }
            },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1 flex-wrap">';
                    btn += '<a href="' + quotationShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (row.can_approve && !row.ready_for_supplier_po && !row.already_approved_by_me) {
                        btn += '<button class="btn-icon" title="Approve PO Supplier" onclick="approvePoSupplier(' + data + ')"><i class="fa fa-check"></i></button>';
                    }
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function approvePoSupplier(id) {
    Swal.fire({
        title: 'Approve PO ke Supplier?',
        text: 'Approval Anda akan dicatat. Dibutuhkan 2 approver berbeda sebelum purchasing boleh melanjutkan PO barang ke supplier.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(poSupplierApprovalApproveUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Approval tercatat.');
                poSupplierApprovalTable.ajax.reload(null, false);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal approve.');
            });
    });
}

$(document).ready(function() {
    initPoSupplierApprovalTable();
});
</script>
@endsection
