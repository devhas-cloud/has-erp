@extends('layouts.app')

@section('title', 'Penerimaan Barang')
@section('page-title', 'Penerimaan Barang')

@section('styles')
@include('partials.list-toolbar')
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Penerimaan Barang</h1>
        <p class="page-header-sub">Barang datang dari Purchase Order dan Direct Purchase</p>
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
        <div class="list-toolbar" id="gr-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. penerimaan, PO, supplier, gudang…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="source_type" aria-label="Jenis dokumen">
                <option value="">Semua Dokumen</option>
                <option value="po">Purchase Order</option>
                <option value="dp">Direct Purchase</option>
            </select>
            <select class="form-select" data-filter="warehouse" aria-label="Gudang">
                <option value="">Semua Gudang</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>
                @endforeach
                <option value="direct">Langsung pakai</option>
            </select>
            @if($suppliers->count() > 1)
            <select class="form-select" data-filter="supplier" data-searchable aria-label="Supplier">
                <option value="">Semua Supplier</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier }}">{{ $supplier }}</option>
                @endforeach
            </select>
            @endif
            <div class="lt-date" title="Tanggal terima">
                <input type="date" class="form-control" data-filter="date_from" aria-label="Dari tanggal">
                <span>–</span>
                <input type="date" class="form-control" data-filter="date_to" aria-label="Sampai tanggal">
            </div>
            <select class="form-select" data-filter="missing_sn" aria-label="Kelengkapan SN">
                <option value="">Semua SN</option>
                <option value="1">SN belum lengkap</option>
            </select>
            <select class="form-select" data-filter="mine" aria-label="Pencatat">
                <option value="">Semua Pencatat</option>
                <option value="1">Dicatat oleh Saya</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="gr-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Penerimaan</th>
                        <th>Dokumen</th>
                        <th>Supplier</th>
                        <th>Gudang</th>
                        <th>Tanggal</th>
                        <th class="text-center">Barang</th>
                        <th>Status</th>
                        <th class="text-center" style="width:90px">Aksi</th>
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
let grToolbar = null;

const grShowUrl = '{{ route("goods-receipt.show", "__ID__") }}';
const grDeleteUrl = '{{ route("goods-receipt.destroy", "__ID__") }}';

function grEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function initGrTable() {
    if (grTable) grTable.destroy();

    grTable = $('#gr-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Tidak ada penerimaan yang cocok dengan pencarian / filter.</span>',
            emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Belum ada penerimaan barang.</span>'
        },
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("goods-receipt.data") }}', data: function(d) { return grToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'receipt_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary);font-variant-numeric:tabular-nums">' + grEsc(data) + '</strong>'; }
            },
            { data: 'source', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<span class="badge me-1" style="background:#f1f5f9;color:var(--text-secondary);font-size:10px">' + grEsc(row.source_kind) + '</span>' + grEsc(data);
                }
            },
            { data: 'supplier_name', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + grEsc(data) + '</strong>'; }
            },
            { data: 'warehouse_name', orderable: false, searchable: true,
                render: function(data, type, row) {
                    if (!data) return '<span class="badge" style="background:var(--info-soft);color:#4338ca">Langsung pakai</span>';
                    return grEsc(data) + (row.warehouse_branch ? '<div style="font-size:11.5px;color:var(--text-muted)">' + grEsc(row.warehouse_branch) + '</div>' : '');
                }
            },
            { data: 'received_date', orderable: false, searchable: false },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    return data + (row.missing_serial > 0
                        ? '<div><span class="badge" style="background:var(--warning-soft);color:#b45309;font-size:10px" title="Unit barang ber-SN yang SN-nya belum diisi">' + row.missing_serial + ' SN kosong</span></div>'
                        : '');
                }
            },
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
    grToolbar = ListToolbar('#gr-toolbar', function() { return grTable; });
    initGrTable();
});
</script>
@endsection
