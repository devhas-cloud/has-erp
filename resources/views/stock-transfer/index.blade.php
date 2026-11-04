@extends('layouts.app')

@section('title', 'Transfer Stok')
@section('page-title', 'Transfer Stok')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Transfer Stok</h1>
        <p class="page-header-sub">Pindah stok antar gudang dan/atau kepemilikan — alokasi stok bebas ke project, lepas alokasi, atau pindah gudang</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('stock-transfer.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Transfer</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-arrow-right-arrow-left me-2" style="color:var(--accent)"></i>Daftar Transfer Stok</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="st-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Transfer</th>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Dari</th>
                        <th>Ke</th>
                        <th class="text-center">Jml Item</th>
                        <th>Dibuat Oleh</th>
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
const stShowUrl = '{{ route("stock-transfer.show", "__ID__") }}';

$(document).ready(function() {
    $('#st-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("stock-transfer.data") }}',
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'transfer_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + data + '</strong>'; }
            },
            { data: 'transfer_date', orderable: false, searchable: false },
            { data: 'kind', orderable: false, searchable: false,
                render: function(data) { return '<span class="status-badge" style="background:var(--accent-soft);color:var(--accent);">' + data + '</span>'; }
            },
            { data: 'from', orderable: false, searchable: true },
            { data: 'to', orderable: false, searchable: true },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'creator', orderable: false, searchable: false },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) {
                    return '<a href="' + stShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                }
            }
        ]
    });
});
</script>
@endsection
