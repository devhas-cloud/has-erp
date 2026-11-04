@extends('layouts.app')

@section('title', 'Direct Purchase')
@section('page-title', 'Direct Purchase')

@section('styles')
@include('partials.list-toolbar')
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Direct Purchase</h1>
        <p class="page-header-sub">Pembelian langsung tanpa Permintaan Barang / PO — untuk stok, project, atau langsung pakai</p>
    </div>
    @if($canCreate ?? false)
    <div class="page-header-actions">
        <a href="{{ route('direct-purchase.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Direct Purchase</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-basket-shopping me-2" style="color:var(--accent)"></i>Daftar Direct Purchase</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="dp-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. dokumen, supplier, part number, pembuat…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" data-status-select aria-label="Status">
                <option value="">Semua Status</option>
                @foreach(\App\Models\DirectPurchase::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}" data-label="{{ $label }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="form-select" data-filter="purpose" aria-label="Tujuan">
                <option value="">Semua Tujuan</option>
                @foreach(\App\Models\DirectPurchase::PURPOSE_LABELS as $key => $label)
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
            <table id="dp-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Dokumen</th>
                        <th>Supplier</th>
                        <th>Tanggal</th>
                        <th>Tujuan</th>
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
let dpTable = null;

const dpShowUrl = '{{ route("direct-purchase.show", "__ID__") }}';
const dpEditUrl = '{{ route("direct-purchase.edit", "__ID__") }}';
const dpDeleteUrl = '{{ route("direct-purchase.destroy", "__ID__") }}';
const dpSubmitUrl = '{{ route("direct-purchase.submit", "__ID__") }}';
const dpApproveUrl = '{{ route("direct-purchase.approve", "__ID__") }}';
const dpCanApprove = {{ $canApprove ? 'true' : 'false' }};

function initDpTable() {
    if (dpTable) dpTable.destroy();

    dpTable = $('#dp-table').DataTable({
        layout: ListToolbar.layout,
        language: ListToolbar.language,
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("direct-purchase.data") }}', data: function(d) { return dpToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'dp_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary);font-variant-numeric:tabular-nums">' + data + '</strong>'; }
            },
            { data: 'supplier_name', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + data + '</strong>'; }
            },
            { data: 'date', orderable: false, searchable: false },
            { data: 'purpose', orderable: false, searchable: false,
                render: function(data, type, row) {
                    var colors = { stock: 'background:var(--accent-soft);color:var(--accent)', project: 'background:#fef3c7;color:#92400e', direct: 'background:var(--info-soft);color:#1e40af' };
                    return '<span class="badge" style="' + (colors[data] || '') + '">' + (row.purpose_label || data) + '</span>';
                }
            },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'grand_total_label', orderable: false, searchable: false, className: 'text-end' },
            { data: 'creator_name', orderable: false, searchable: true },
            { data: 'status_badge', orderable: false, searchable: false },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1 flex-wrap">';
                    btn += '<a href="' + dpShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (row.status === 'draft' && row.is_creator) {
                        btn += '<a href="' + dpEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Edit"><i class="fa fa-pen"></i></a>';
                        btn += '<button class="btn-icon" title="Submit Approval" onclick="submitDp(' + data + ')"><i class="fa fa-paper-plane"></i></button>';
                        btn += '<button class="btn-icon text-danger" title="Hapus" onclick="deleteDp(' + data + ')"><i class="fa fa-trash"></i></button>';
                    }
                    if (dpCanApprove && row.status === 'waiting_approval') {
                        btn += '<button class="btn-icon" title="Approve" onclick="approveDp(' + data + ')"><i class="fa fa-check"></i></button>';
                    }
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
    dpTable.on('xhr.dt', function(e, settings, json) { dpToolbar.statusCounts(json); });
}

function submitDp(id) {
    Swal.fire({
        title: 'Submit Approval?',
        text: 'Direct purchase akan dikirim untuk approval.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Submit',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(dpSubmitUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Dikirim untuk approval.');
                dpTable.ajax.reload();
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal submit.');
            });
    });
}

function approveDp(id) {
    Swal.fire({
        title: 'Approve Direct Purchase?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(dpApproveUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Disetujui.');
                dpTable.ajax.reload();
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal approve.');
            });
    });
}

function deleteDp(id) {
    Swal.fire({
        title: 'Hapus Direct Purchase?',
        text: 'Direct purchase #' + id + ' akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: dpDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Dihapus.');
            dpTable.ajax.reload();
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}

let dpToolbar = null;

$(document).ready(function() {
    dpToolbar = ListToolbar('#dp-toolbar', function() { return dpTable; });
    initDpTable();
});
</script>
@endsection
