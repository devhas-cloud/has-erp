@extends('layouts.app')

@section('title', 'Stock Opname')
@section('page-title', 'Stock Opname')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Stock Opname</h1>
        <p class="page-header-sub">Hitung fisik stok per gudang (boleh sebagian produk). Selisih menjadi penyesuaian stok setelah diposting approver.</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('stock-opname.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Stock Opname</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-clipboard-list me-2" style="color:var(--accent)"></i>Daftar Stock Opname</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="so-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Opname</th>
                        <th>Tanggal</th>
                        <th>Gudang</th>
                        <th class="text-center">Jml Baris</th>
                        <th>Dibuat Oleh</th>
                        <th>Status</th>
                        <th class="text-center" style="width:110px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const soShowUrl = '{{ route("stock-opname.show", "__ID__") }}';
const soEditUrl = '{{ route("stock-opname.edit", "__ID__") }}';
const soDeleteUrl = '{{ route("stock-opname.destroy", "__ID__") }}';
let soTable = null;

function soDelete(id) {
    Swal.fire({ title: 'Hapus Stock Opname?', text: 'Draft akan dihapus permanen.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' })
        .then(function(result) {
            if (!result.isConfirmed) return;
            $.ajax({ url: soDeleteUrl.replace('__ID__', id), method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                .done(function(res) { toastr.success(res.message || 'Dihapus.'); soTable.ajax.reload(); })
                .fail(function(xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.'); });
        });
}

$(document).ready(function() {
    soTable = $('#so-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("stock-opname.data") }}',
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'opname_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + data + '</strong>'; }
            },
            { data: 'opname_date', orderable: false, searchable: false },
            { data: 'warehouse_name', orderable: false, searchable: true },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'creator', orderable: false, searchable: false },
            { data: 'status_badge', orderable: false, searchable: false },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    btn += '<a href="' + soShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (row.status === 'draft') {
                        @if($canUpdate)
                        btn += '<a href="' + soEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Edit"><i class="fa-solid fa-pen"></i></a>';
                        @endif
                        @if($canDelete)
                        btn += '<button class="btn-icon danger" title="Hapus" onclick="soDelete(' + data + ')"><i class="fa fa-trash-can"></i></button>';
                        @endif
                    }
                    return btn + '</div>';
                }
            }
        ]
    });
});
</script>
@endsection
