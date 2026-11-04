@extends('layouts.app')

@section('title', 'Purchase Order')
@section('page-title', 'Purchase Order')

@section('styles')
@include('partials.list-toolbar')
@endsection

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
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-file-invoice-dollar me-2" style="color:var(--accent)"></i>Daftar Purchase Order</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="po-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. PO, supplier, part number, pembuat…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" data-status-select aria-label="Status">
                <option value="">Semua Status</option>
                @foreach(\App\Models\PurchaseOrder::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}" data-label="{{ $label }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="form-select" data-filter="po_type" aria-label="Jenis PO">
                <option value="">Semua Jenis</option>
                @foreach(\App\Models\PurchaseOrder::TYPE_LABELS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            @if($suppliers->count() > 1)
            <select class="form-select" data-filter="supplier" data-searchable aria-label="Supplier">
                <option value="">Semua Supplier</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier }}">{{ $supplier }}</option>
                @endforeach
            </select>
            @endif
            <select class="form-select" data-filter="pay_status" aria-label="Status bayar">
                <option value="">Semua Status Bayar</option>
                <option value="unbilled">Belum Ditagih</option>
                <option value="unpaid">Ada Tagihan Belum Lunas</option>
                <option value="paid">Lunas</option>
            </select>
            <div class="lt-date" title="Rentang tanggal">
                <input type="date" class="form-control" data-filter="date_from" aria-label="Dari tanggal">
                <span>–</span>
                <input type="date" class="form-control" data-filter="date_to" aria-label="Sampai tanggal">
            </div>
            <select class="form-select" data-filter="mine" aria-label="Pembuat">
                <option value="">Semua Pembuat</option>
                <option value="1">Dibuat oleh Saya</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="po-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. PO</th>
                        <th>Jenis</th>
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

function initPoTable() {
    if (poTable) poTable.destroy();

    poTable = $('#po-table').DataTable({
        layout: ListToolbar.layout,
        language: ListToolbar.language,
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("purchase-order.data") }}', data: function(d) { return poToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'po_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary);font-variant-numeric:tabular-nums">' + data + '</strong>'; }
            },
            { data: 'type_badge', orderable: false, searchable: false },
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
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
    poTable.on('xhr.dt', function(e, settings, json) { poToolbar.statusCounts(json); });
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

let poToolbar = null;

$(document).ready(function() {
    poToolbar = ListToolbar('#po-toolbar', function() { return poTable; });
    initPoTable();
});
</script>
@endsection
