@extends('layouts.app')

@section('title', 'Goods Receipts')
@section('page-title', 'Goods Receipts')

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
        <h1 class="page-header-title">Goods Receipts</h1>
        <p class="page-header-sub">Goods arriving from Purchase Orders and Direct Purchases. <strong>Materials</strong> are added to the warehouse balance (weighted average cost); <strong>serial-numbered items</strong> are recorded per unit, and SNs can be filled in later.</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('goods-receipt.create') }}" class="btn-accent">
            <span>Receive Goods</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span>Goods Receipt List</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="gr-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Search receipt no., PO, supplier, warehouse…" autocomplete="off">
                <button type="button" class="lt-clear" title="Clear search"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="source_type" aria-label="Document type">
                <option value="">All Documents</option>
                <option value="po">Purchase Order</option>
                <option value="dp">Direct Purchase</option>
            </select>
            <select class="form-select" data-filter="warehouse" aria-label="Warehouse">
                <option value="">All Warehouses</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>
                @endforeach
                <option value="direct">Direct use</option>
            </select>
            @if($suppliers->count() > 1)
            <select class="form-select" data-filter="supplier" data-searchable aria-label="Supplier">
                <option value="">All Suppliers</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier }}">{{ $supplier }}</option>
                @endforeach
            </select>
            @endif
            <div class="lt-date" title="Received date">
                <input type="date" class="form-control" data-filter="date_from" aria-label="From date">
                <span>–</span>
                <input type="date" class="form-control" data-filter="date_to" aria-label="To date">
            </div>
            <select class="form-select" data-filter="kind" aria-label="Item type">
                <option value="">All Item Types</option>
                <option value="material">Contains Materials</option>
                <option value="serial">Contains Serial Items</option>
            </select>
            <select class="form-select" data-filter="missing_sn" aria-label="SN completeness">
                <option value="">All SN</option>
                <option value="1">SN incomplete</option>
            </select>
            <select class="form-select" data-filter="mine" aria-label="Recorded by">
                <option value="">All Recorders</option>
                <option value="1">Recorded by Me</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="gr-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Receipt No.</th>
                        <th>Document</th>
                        <th>Supplier</th>
                        <th>Warehouse</th>
                        <th>Date</th>
                        <th class="text-center">Contents</th>
                        <th class="text-end">Value Into Stock</th>
                        <th>Status</th>
                        <th class="text-center" style="width:90px">Actions</th>
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
            zeroRecords: '<span style="color:var(--text-muted)">No receipts match the search / filters.</span>',
            emptyTable: '<span style="color:var(--text-muted)">No goods receipts yet.</span>'
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
                    if (!data) return '<span class="badge" style="background:var(--info-soft);color:#4338ca">Direct use</span>';
                    return grEsc(data) + (row.warehouse_branch ? '<div style="font-size:11.5px;color:var(--text-muted)">' + grEsc(row.warehouse_branch) + '</div>' : '');
                }
            },
            { data: 'received_date', orderable: false, searchable: false },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var chips = '';
                    if (row.material_lines) chips += '<span class="gr-chip gr-chip-material" title="Added to the material balance in the warehouse">' + row.material_lines + ' material</span>';
                    if (row.serial_units) chips += '<span class="gr-chip gr-chip-serial" title="Serial-numbered units (one stock row per unit)">' + row.serial_units + ' SN units</span>';
                    if (row.direct_lines) chips += '<span class="gr-chip gr-chip-direct" title="Not added to stock">' + row.direct_lines + ' direct use</span>';
                    if (row.missing_serial > 0) {
                        chips += '<a class="gr-chip gr-chip-missing" href="' + grShowUrl.replace('__ID__', row.id) + '" title="Serial-numbered units whose SN has not been filled in">' + row.missing_serial + ' SN missing</a>';
                    }
                    return '<div class="fw-semibold">' + data + ' lines</div><div class="gr-mix">' + chips + '</div>';
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
                    btn += '<a href="' + grShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Details"><i class="fa fa-eye"></i></a>';
                    @if($canDelete)
                    if (row.status === 'draft') {
                        btn += '<button class="btn-icon danger" title="Delete" onclick="deleteReceipt(' + data + ')"><i class="fa fa-trash"></i></button>';
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
        title: 'Delete this receipt?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: grDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Deleted.');
            grTable.ajax.reload();
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to delete.');
        });
    });
}

$(document).ready(function() {
    grToolbar = ListToolbar('#gr-toolbar', function() { return grTable; });
    initGrTable();
});
</script>
@endsection
