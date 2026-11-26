@extends('layouts.app')

@section('title', 'Goods Receipts')
@section('page-title', 'Goods Receipts')

@section('styles')
@include('partials.list-toolbar')
<style>
    #gr-table th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    #gr-table td { vertical-align: top; }
    .gr-mix { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 3px; }
    .gr-chip { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 999px; white-space: nowrap; text-decoration: none; }
    .gr-chip-material { background: #e0f2fe; color: #075985; }
    .gr-chip-serial { background: #fef3c7; color: #92400e; }
    .gr-chip-direct { background: #ede9fe; color: #5b21b6; }
    .gr-chip-missing { background: #fff7ed; color: #b45309; border: 1px solid #fed7aa; }
    .gr-sub { font-size: 11.5px; color: var(--text-muted); }
    .gr-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .gr-kind-tag { display: inline-block; font-size: 10px; font-weight: 600; padding: 0 6px; border-radius: 4px; background: #f1f5f9; color: var(--text-secondary); margin-right: 4px; }
    /* Pengingat SN belum lengkap = filter cepat */
    .gr-alert { display: none; width: 100%; text-align: left; align-items: center; gap: 10px; border: 1px solid #fde68a; background: #fffbeb; color: #78350f; border-radius: var(--radius-sm); padding: 8px 14px; margin: 0 6px 10px; font-size: 13px; transition: background-color .12s ease, border-color .12s ease; }
    .gr-alert:hover { background: #fef3c7; }
    .gr-alert.is-active { border-color: #d97706; background: #fef3c7; }
    .list-toolbar .gr-create { height: 32px; padding: 0 14px; font-size: 12.5px; white-space: nowrap; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Goods Receipts</h1>
        <p class="page-header-sub">Goods arriving from Purchase Orders and Direct Purchases. Stock increases when a receipt is posted: materials are added to the warehouse balance (weighted average cost), serial items are recorded per unit and their SNs can be filled in later.</p>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="gr-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Search receipt no., PO, supplier, warehouse…" aria-label="Search receipts" autocomplete="off">
                <button type="button" class="lt-clear" title="Clear search" aria-label="Clear search"><i class="fa fa-xmark"></i></button>
            </div>
            @if($suppliers->count() > 1)
            <select class="form-select" data-filter="supplier" data-searchable aria-label="Supplier">
                <option value="">All Suppliers</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier }}">{{ $supplier }}</option>
                @endforeach
            </select>
            @endif
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
            <select class="form-select" data-filter="kind" aria-label="Contents">
                <option value="">All Contents</option>
                <option value="material">Contains Materials</option>
                <option value="serial">Contains Serial Items</option>
                <option value="missing_sn">SN incomplete</option>
            </select>
            <div class="lt-date" title="Received date">
                <input type="date" class="form-control" data-filter="date_from" aria-label="Received from">
                <span>–</span>
                <input type="date" class="form-control" data-filter="date_to" aria-label="Received to">
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
            @if($canCreate)
                <a href="{{ route('goods-receipt.create') }}" class="btn btn-sm btn-accent gr-create">Receive Goods</a>
            @endif
        </div>
        <button type="button" class="gr-alert" id="gr-missing-alert" aria-pressed="false" title="Click to show only these receipts; click again to show all">
            <span id="gr-missing-text"></span>
        </button>
        <div class="table-responsive">
            <table id="gr-table" class="table table-custom mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Supplier · Document</th>
                        <th>Warehouse</th>
                        <th>Contents</th>
                        <th class="text-end">Value Into Stock</th>
                        <th>Status</th>
                        <th class="text-center" style="width:70px">Action</th>
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
const grBaseName = @json(\App\Models\Currency::baseName());

function grEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

// Pengingat SN belum lengkap: klik = filter cepat, klik lagi = batal.
function grSyncMissing(total) {
    var active = $('#gr-toolbar [data-filter="kind"]').val() === 'missing_sn';
    $('#gr-missing-alert').css('display', total > 0 || active ? 'flex' : 'none')
        .toggleClass('is-active', active).attr('aria-pressed', active ? 'true' : 'false');
    $('#gr-missing-text').html('<strong>' + total + ' receipt' + (total === 1 ? '' : 's') + '</strong> still have serial units without SN — these units cannot leave stock yet.');
}

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
            { data: 'receipt_number', orderable: false,
                render: function(data, type, row) {
                    return '<a href="' + grShowUrl.replace('__ID__', row.id) + '" class="fw-semibold text-decoration-none" style="font-variant-numeric:tabular-nums">' + grEsc(data) + '</a>'
                        + '<div class="gr-sub">' + grEsc(row.received_date) + (row.creator ? ' · ' + grEsc(row.creator) : '') + '</div>';
                }
            },
            { data: 'supplier_name', orderable: false,
                render: function(data, type, row) {
                    var doc = row.source_url ? '<a href="' + row.source_url + '" class="text-decoration-none">' + grEsc(row.source) + '</a>' : grEsc(row.source);
                    return '<div style="color:var(--text-primary);font-weight:600">' + grEsc(data) + '</div>'
                        + '<div class="gr-sub"><span class="gr-kind-tag">' + grEsc(row.source_kind) + '</span>' + doc + '</div>';
                }
            },
            { data: 'warehouse_name', orderable: false,
                render: function(data, type, row) {
                    if (!data) return '<span class="gr-sub">Direct use only</span>';
                    return grEsc(data) + (row.warehouse_branch ? '<div class="gr-sub">' + grEsc(row.warehouse_branch) + '</div>' : '');
                }
            },
            { data: 'item_count', orderable: false,
                render: function(data, type, row) {
                    var chips = '';
                    if (row.material_lines) chips += '<span class="gr-chip gr-chip-material" title="Added to the material balance in the warehouse">' + row.material_lines + ' material</span>';
                    if (row.serial_units) chips += '<span class="gr-chip gr-chip-serial" title="Serial units (one stock row per unit)">' + row.serial_units + ' SN units</span>';
                    if (row.direct_lines) chips += '<span class="gr-chip gr-chip-direct" title="Not added to stock">' + row.direct_lines + ' direct use</span>';
                    if (row.missing_serial > 0) {
                        chips += '<a class="gr-chip gr-chip-missing" href="' + grShowUrl.replace('__ID__', row.id) + '" title="Serial units whose SN has not been filled in">' + row.missing_serial + ' SN missing</a>';
                    }
                    return '<div style="font-size:12.5px">' + data + ' line' + (data === 1 ? '' : 's') + '</div><div class="gr-mix">' + chips + '</div>';
                }
            },
            { data: 'stock_value', orderable: false, className: 'gr-num',
                render: function(data, type, row) {
                    if (!row.material_lines && !row.serial_units) return '<span class="gr-sub">—</span>';
                    return '<span style="font-weight:600">' + grEsc(grBaseName) + ' ' + Num.money(data) + '</span>';
                }
            },
            { data: 'status_badge', orderable: false },
            {
                data: 'id', orderable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    btn += '<a href="' + grShowUrl.replace('__ID__', data) + '" class="btn-icon" title="View detail" aria-label="View detail"><i class="fa fa-eye"></i></a>';
                    @if($canDelete)
                    if (row.status === 'draft') {
                        btn += '<button type="button" class="btn-icon danger" title="Delete" aria-label="Delete" onclick="deleteReceipt(' + data + ')"><i class="fa fa-trash"></i></button>';
                    }
                    @endif
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
    //grTable.on('xhr.dt', function(e, settings, json) { if (json) grSyncMissing(json.missing_total || 0); });
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
    $('#gr-missing-alert').on('click', function() {
        var $kind = $('#gr-toolbar [data-filter="kind"]');
        $kind.val($kind.val() === 'missing_sn' ? '' : 'missing_sn').trigger('change');
    });
});
</script>
@endsection
