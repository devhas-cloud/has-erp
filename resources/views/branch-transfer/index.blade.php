@extends('layouts.app')

@section('title', 'Branch Transfer')
@section('page-title', 'Branch Transfer')

@section('styles')
@include('partials.list-toolbar')
<style>
    .bt-route { display: flex; align-items: center; gap: 8px; }
    .bt-route .bt-node { min-width: 0; }
    .bt-route .bt-branch { font-weight: 600; font-size: 13px; }
    .bt-route .bt-wh { font-size: 11.5px; color: var(--text-muted); }
    .bt-route .bt-arrow { color: var(--accent); flex-shrink: 0; }
    .bt-dir { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 999px; white-space: nowrap; }
    .bt-dir-out { background: #e0f2fe; color: #075985; }
    .bt-dir-in { background: #d1fae5; color: #065f46; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Branch Transfer</h1>
        <p class="page-header-sub">Pengiriman barang antar branch dengan surat jalan. Stok tercatat dalam pengiriman sampai diperiksa dan diterima branch tujuan.</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('branch-transfer.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Pengiriman</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-truck-fast me-2" style="color:var(--accent)"></i>Daftar Branch Transfer</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="bt-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. dokumen, barang, SN, ekspedisi, kendaraan…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" aria-label="Status">
                <option value="">Semua Status</option>
                @foreach(\App\Models\BranchTransfer::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            {{-- @if($userBranchId)
            <select class="form-select" data-filter="direction" aria-label="Arah">
                <option value="">Masuk &amp; Keluar</option>
                <option value="out">Keluar (dikirim branch saya)</option>
                <option value="in">Masuk (menuju branch saya)</option>
            </select>
            @endif --}}
            @if($branches->count() > 1)
            {{-- <select class="form-select" data-filter="from_branch" aria-label="Branch asal">
                <option value="">Semua Branch Asal</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}">Dari: {{ $b->name }}</option>
                @endforeach
            </select> --}}
            <select class="form-select" data-filter="to_branch" aria-label="Branch tujuan">
                <option value="">Semua Branch Tujuan</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}">Ke: {{ $b->name }}</option>
                @endforeach
            </select>
            @endif
            @if($warehouses->count() > 1)
            <select class="form-select" data-filter="warehouse" data-searchable aria-label="Gudang">
                <option value="">Semua Gudang</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>
                @endforeach
            </select>
            @endif
            <div class="lt-date" title="Tanggal kirim">
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
            <table id="bt-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Dokumen</th>
                        <th>Tgl Kirim</th>
                        <th>Rute</th>
                        <th>Ekspedisi</th>
                        <th class="text-center">Barang</th>
                        <th>Status</th>
                        <th class="text-center" style="width:70px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let btTable = null;
let btToolbar = null;
const btShowUrl = '{{ route("branch-transfer.show", "__ID__") }}';

function btEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    btToolbar = ListToolbar('#bt-toolbar', function() { return btTable; });
    btTable = $('#bt-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Tidak ada Branch Transfer yang cocok dengan pencarian / filter.</span>',
            emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Belum ada Branch Transfer.</span>'
        },
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("branch-transfer.data") }}', data: function(d) { return btToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'transfer_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<a href="' + btShowUrl.replace('__ID__', row.id) + '" style="font-weight:700;color:var(--text-primary);font-variant-numeric:tabular-nums">' + btEsc(data) + '</a>'
                        + (row.direction ? ' <span class="bt-dir bt-dir-' + row.direction + '">' + (row.direction === 'out' ? 'Keluar' : 'Masuk') + '</span>' : '')
                        + '<div style="font-size:11.5px;color:var(--text-muted)">' + btEsc(row.creator) + '</div>';
                } },
            { data: 'transfer_date', orderable: false, searchable: false },
            { data: 'from', orderable: false, searchable: false,
                render: function(data, type, row) {
                    return '<div class="bt-route">'
                        + '<div class="bt-node"><div class="bt-branch">' + btEsc(row.from_branch) + '</div><div class="bt-wh">' + btEsc(row.from_warehouse) + '</div></div>'
                        + '<i class="fa fa-arrow-right bt-arrow"></i>'
                        + '<div class="bt-node"><div class="bt-branch">' + btEsc(row.to_branch) + '</div><div class="bt-wh">' + (row.to_warehouse ? btEsc(row.to_warehouse) : '<em>gudang dipilih penerima</em>') + '</div></div>'
                        + '</div>';
                } },
            { data: 'courier', orderable: false, searchable: false,
                render: function(data) { return data ? btEsc(data) : '<span style="color:var(--text-muted)">—</span>'; } },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'status_badge', orderable: false, searchable: false },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) { return '<a href="' + btShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>'; } }
        ]
    });
});
</script>
@endsection
