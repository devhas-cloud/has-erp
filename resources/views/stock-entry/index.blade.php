@extends('layouts.app')

@section('title', 'Masuk Stok Lain-lain')
@section('page-title', 'Masuk Stok Lain-lain')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Masuk Stok Lain-lain</h1>
        <p class="page-header-sub">Barang masuk tanpa dokumen pembelian — saldo awal, retur dari project/customer, hibah/sampel</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('stock-entry.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Masuk Stok</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-square-plus me-2" style="color:var(--accent)"></i>Daftar Masuk Stok Lain-lain</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="se-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Dokumen</th>
                        <th>Tanggal</th>
                        <th>Alasan</th>
                        <th>Gudang</th>
                        <th>Kepemilikan</th>
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
const seShowUrl = '{{ route("stock-entry.show", "__ID__") }}';

$(document).ready(function() {
    $('#se-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("stock-entry.data") }}',
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'entry_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + data + '</strong>'; }
            },
            { data: 'entry_date', orderable: false, searchable: false },
            { data: 'reason_label', orderable: false, searchable: false,
                render: function(data) { return '<span class="status-badge" style="background:var(--accent-soft);color:var(--accent);">' + data + '</span>'; }
            },
            { data: 'warehouse_name', orderable: false, searchable: true },
            { data: 'ownership', orderable: false, searchable: true },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'creator', orderable: false, searchable: false },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) {
                    return '<a href="' + seShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                }
            }
        ]
    });
});
</script>
@endsection
