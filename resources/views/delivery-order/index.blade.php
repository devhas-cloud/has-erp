@extends('layouts.app')

@section('title', 'Delivery Order')
@section('page-title', 'Delivery Order')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Delivery Order</h1>
        <p class="page-header-sub">Surat jalan pengiriman barang milik project ke customer — simpan draft, lalu posting oleh gudang saat barang berangkat</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('delivery-order.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Delivery Order</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-truck me-2" style="color:var(--accent)"></i>Daftar Delivery Order</span>
        <select id="do-status-filter" class="form-select form-select-sm" style="width:auto">
            <option value="">Semua status</option>
            @foreach(\App\Models\DeliveryOrder::STATUS_LABELS as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="do-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. DO</th>
                        <th>Tanggal Kirim</th>
                        <th>Project</th>
                        <th>Penerima</th>
                        <th>Gudang</th>
                        <th class="text-center">Jml Item</th>
                        <th>Status</th>
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
const doShowUrl = '{{ route("delivery-order.show", "__ID__") }}';
const doEditUrl = '{{ route("delivery-order.edit", "__ID__") }}';
const doPdfUrl = '{{ route("delivery-order.pdf", "__ID__") }}';
const doDeleteUrl = '{{ route("delivery-order.destroy", "__ID__") }}';
let doTable = null;

function doEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function doDelete(id) {
    Swal.fire({ title: 'Hapus Delivery Order?', text: 'Draft akan dihapus permanen.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' })
        .then(function(result) {
            if (!result.isConfirmed) return;
            $.ajax({ url: doDeleteUrl.replace('__ID__', id), method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                .done(function(res) { toastr.success(res.message || 'Dihapus.'); doTable.ajax.reload(); })
                .fail(function(xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.'); });
        });
}

$(document).ready(function() {
    doTable = $('#do-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("delivery-order.data") }}', data: function(d) { d.status = $('#do-status-filter').val(); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'do_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + doEsc(data) + '</strong>'; }
            },
            { data: 'delivery_date', orderable: false, searchable: false },
            { data: 'project', orderable: false, searchable: true, render: function(data) { return doEsc(data); } },
            { data: 'recipient_name', orderable: false, searchable: true, render: function(data) { return doEsc(data); } },
            { data: 'warehouse_name', orderable: false, searchable: false, render: function(data) { return doEsc(data); } },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'status_badge', orderable: false, searchable: false },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    btn += '<a href="' + doShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    btn += '<a href="' + doPdfUrl.replace('__ID__', data) + '" target="_blank" class="btn-icon" title="Surat Jalan"><i class="fa fa-file-pdf"></i></a>';
                    if (row.status === 'draft') {
                        @if($canUpdate)
                        btn += '<a href="' + doEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Edit"><i class="fa-solid fa-pen"></i></a>';
                        @endif
                        @if($canDelete)
                        btn += '<button class="btn-icon danger" title="Hapus" onclick="doDelete(' + data + ')"><i class="fa fa-trash-can"></i></button>';
                        @endif
                    }
                    return btn + '</div>';
                }
            }
        ]
    });
    $('#do-status-filter').on('change', function() { doTable.ajax.reload(); });
});
</script>
@endsection
