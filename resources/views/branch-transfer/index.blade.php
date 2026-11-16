@extends('layouts.app')

@section('title', 'Branch Transfer')
@section('page-title', 'Branch Transfer')

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
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-truck-fast me-2" style="color:var(--accent)"></i>Daftar Branch Transfer</span>
        <select id="bt-status" class="form-select form-select-sm" style="max-width:200px" aria-label="Filter status">
            <option value="">Semua status</option>
            @foreach(\App\Models\BranchTransfer::STATUS_LABELS as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="bt-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Dokumen</th>
                        <th>Tanggal</th>
                        <th>Dari</th>
                        <th>Ke</th>
                        <th class="text-center">Barang</th>
                        <th>Status</th>
                        <th class="text-center" style="width:80px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const btShowUrl = '{{ route("branch-transfer.show", "__ID__") }}';

$(document).ready(function() {
    var table = $('#bt-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("branch-transfer.data") }}',
            data: function(d) { d.status = $('#bt-status').val(); }
        },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'transfer_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + data + '</strong>'; } },
            { data: 'transfer_date', orderable: false, searchable: false },
            { data: 'from', orderable: false, searchable: false },
            { data: 'to', orderable: false, searchable: false },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'status_badge', orderable: false, searchable: false },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) { return '<a href="' + btShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>'; } }
        ]
    });
    $('#bt-status').on('change', function() { table.ajax.reload(); });
});
</script>
@endsection
