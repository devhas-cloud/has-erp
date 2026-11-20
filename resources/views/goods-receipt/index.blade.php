@extends('layouts.app')

@section('title', 'Penerimaan Barang')
@section('page-title', 'Penerimaan Barang')

@section('styles')
@include('partials.list-toolbar')
<style>
    #gr-table th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    .gr-mix { display: flex; flex-wrap: wrap; gap: 3px; justify-content: center; }
    .gr-chip { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 999px; white-space: nowrap; }
    .gr-chip-material { background: #e0f2fe; color: #075985; }
    .gr-chip-serial { background: #fef3c7; color: #92400e; }
    .gr-chip-direct { background: #ede9fe; color: #5b21b6; }
    .gr-chip-missing { background: #fff7ed; color: #b45309; border: 1px solid #fed7aa; }
    .gr-sub { font-size: 11.5px; color: var(--text-muted); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Penerimaan Barang</h1>
        <p class="page-header-sub">Barang datang dari Purchase Order dan Direct Purchase. <strong>Material</strong> menambah saldo gudang (harga rata-rata); <strong>barang ber-SN</strong> tercatat per unit, SN boleh menyusul.</p>
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
            <select class="form-select" data-filter="kind" aria-label="Jenis barang">
                <option value="">Semua Jenis Barang</option>
                <option value="material">Berisi Material</option>
                <option value="serial">Berisi Barang ber-SN</option>
            </select>
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
                        <th class="text-center">Isi Penerimaan</th>
                        <th class="text-end">Nilai Masuk Gudang</th>
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
const grStockDetailUrl = '{{ route("stock-detail.index") }}';

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
                render: function(data, type, row) {
                    return '<a href="' + grShowUrl.replace('__ID__', row.id) + '" style="font-weight:700;color:var(--text-primary);font-variant-numeric:tabular-nums">' + grEsc(data) + '</a>'
                        + (row.creator ? '<div class="gr-sub">' + grEsc(row.creator) + '</div>' : '');
                }
            },
            { data: 'source', orderable: false, searchable: true,
                render: function(data, type, row) {
                    var label = '<span class="badge me-1" style="background:#f1f5f9;color:var(--text-secondary);font-size:10px">' + grEsc(row.source_kind) + '</span>';
                    return label + (row.source_url ? '<a href="' + row.source_url + '" target="_blank">' + grEsc(data) + '</a>' : grEsc(data));
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
                    var chips = '';
                    if (row.material_lines) chips += '<span class="gr-chip gr-chip-material" title="Menambah saldo material di gudang">' + row.material_lines + ' material</span>';
                    if (row.serial_units) chips += '<span class="gr-chip gr-chip-serial" title="Unit barang ber-SN (1 baris stok per unit)">' + row.serial_units + ' unit SN</span>';
                    if (row.direct_lines) chips += '<span class="gr-chip gr-chip-direct" title="Tidak masuk stok">' + row.direct_lines + ' langsung pakai</span>';
                    if (row.missing_serial > 0) {
                        chips += '<a class="gr-chip gr-chip-missing" href="' + grShowUrl.replace('__ID__', row.id) + '" title="Unit barang ber-SN yang SN-nya belum diisi"><i class="fa fa-triangle-exclamation me-1"></i>' + row.missing_serial + ' SN kosong</a>';
                    }
                    return '<div class="fw-semibold">' + data + ' baris</div><div class="gr-mix">' + chips + '</div>';
                }
            },
            { data: 'stock_value', orderable: false, searchable: false, className: 'text-end',
                render: function(data, type, row) {
                    if (!row.material_lines && !row.serial_units) return '<span class="gr-sub">—</span>';
                    return '<span style="font-variant-numeric:tabular-nums;font-weight:600">' + Num.format(data, 2) + '</span>';
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
