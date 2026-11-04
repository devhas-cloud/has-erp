@extends('layouts.app')

@section('title', 'Penerimaan Barang')
@section('page-title', 'Penerimaan Barang')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Penerimaan Barang</h1>
        <p class="page-header-sub">Penerimaan barang dari Purchase Order / Direct Purchase — masuk gudang (stok bebas atau milik project) atau langsung pakai</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('goods-receipt.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Terima Barang</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i>Daftar Penerimaan Barang</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="gr-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Penerimaan</th>
                        <th>Dokumen Sumber</th>
                        <th>Supplier</th>
                        <th>Gudang</th>
                        <th>Tanggal</th>
                        <th class="text-center">Jml Item</th>
                        <th>Status</th>
                        <th class="text-center" style="width:150px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let grTable = null;

const grShowUrl = '{{ route("goods-receipt.show", "__ID__") }}';
const grDeleteUrl = '{{ route("goods-receipt.destroy", "__ID__") }}';

function initGrTable() {
    if (grTable) grTable.destroy();

    grTable = $('#gr-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("goods-receipt.data") }}',
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'receipt_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + data + '</strong>'; }
            },
            { data: 'source', orderable: false, searchable: true },
            { data: 'supplier_name', orderable: false, searchable: true },
            { data: 'warehouse_name', orderable: false, searchable: true },
            { data: 'received_date', orderable: false, searchable: false },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'status_badge', orderable: false, searchable: false },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    btn += '<a href="' + grShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    @if($canDelete)
                    if (row.status === 'draft') {
                        btn += '<button class="btn-icon danger" title="Hapus" onclick="deleteReceipt(' + data + ')"><i class="fa fa-trash"></i></button>';
                    }
                    @endif
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function deleteReceipt(id) {
    Swal.fire({
        title: 'Hapus Penerimaan?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: grDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Dihapus.');
            grTable.ajax.reload();
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}

$(document).ready(function() {
    initGrTable();
});
</script>
@endsection
