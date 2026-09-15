@extends('layouts.app')

@section('title', 'Purchase Order')
@section('page-title', 'Purchase Order')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Purchase Order</h1>
        <p class="page-header-sub">PO ke supplier, disusun dari item Permintaan Barang yang sudah Approved — bisa menggabungkan beberapa Permintaan Barang lintas divisi dalam satu PO</p>
    </div>
    @if($canCreate ?? false)
    <div class="page-header-actions">
        <a href="{{ route('purchase-order.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Purchase Order</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-file-invoice-dollar me-2" style="color:var(--accent)"></i>Daftar Purchase Order</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="po-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. PO</th>
                        <th>Supplier</th>
                        <th>Tanggal</th>
                        <th class="text-center">Jml Item</th>
                        <th class="text-end">Total</th>
                        <th>Dibuat Oleh</th>
                        <th>Status</th>
                        <th class="text-center" style="width:160px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let poTable = null;

const poShowUrl = '{{ route("purchase-order.show", "__ID__") }}';
const poEditUrl = '{{ route("purchase-order.edit", "__ID__") }}';
const poDeleteUrl = '{{ route("purchase-order.destroy", "__ID__") }}';
const poSubmitUrl = '{{ route("purchase-order.submit", "__ID__") }}';
const poApproveUrl = '{{ route("purchase-order.approve", "__ID__") }}';

function initPoTable() {
    if (poTable) poTable.destroy();

    poTable = $('#po-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("purchase-order.data") }}',
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'po_number', orderable: false, searchable: true },
            { data: 'supplier_name', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + data + '</strong>'; }
            },
            { data: 'date', orderable: false, searchable: false },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'grand_total_label', orderable: false, searchable: false, className: 'text-end' },
            { data: 'creator_name', orderable: false, searchable: true },
            { data: 'status_badge', orderable: false, searchable: false },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1 flex-wrap">';
                    btn += '<a href="' + poShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (row.status === 'draft' && row.is_creator) {
                        btn += '<a href="' + poEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Edit"><i class="fa fa-pen"></i></a>';
                        btn += '<button class="btn-icon" title="Submit Approval" onclick="submitPo(' + data + ')"><i class="fa fa-paper-plane"></i></button>';
                        btn += '<button class="btn-icon text-danger" title="Hapus" onclick="deletePo(' + data + ')"><i class="fa fa-trash"></i></button>';
                    }
                    if (row.can_approve) {
                        btn += '<button class="btn-icon" title="Approve" onclick="approvePo(' + data + ')"><i class="fa fa-check"></i></button>';
                    }
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function submitPo(id) {
    Swal.fire({
        title: 'Submit Approval?',
        text: 'Purchase order akan dikirim untuk approval.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Submit',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(poSubmitUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Dikirim untuk approval.');
                poTable.ajax.reload();
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal submit.');
            });
    });
}

function approvePo(id) {
    Swal.fire({
        title: 'Approve Purchase Order?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(poApproveUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Disetujui.');
                poTable.ajax.reload();
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal approve.');
            });
    });
}

function deletePo(id) {
    Swal.fire({
        title: 'Hapus Purchase Order?',
        text: 'Purchase order #' + id + ' akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: poDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Dihapus.');
            poTable.ajax.reload();
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}

$(document).ready(function() {
    initPoTable();
});
</script>
@endsection
