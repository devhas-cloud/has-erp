@extends('layouts.app')

@section('title', 'Laporan Stok')
@section('page-title', 'Laporan Stok')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Laporan Stok</h1>
        <p class="page-header-sub">Saldo dan nilai persediaan (harga rata-rata bergerak) per produk, gudang, dan kepemilikan. Klik kode produk untuk melihat kartu stok.</p>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-body-custom">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label">Gudang</label>
                <select id="sr-warehouse" class="form-select">
                    <option value="">Semua gudang</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->type_label }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-5">
                <label class="form-label">Kepemilikan</label>
                <select id="sr-ownership" class="form-select">
                    <option value="">Semua</option>
                    <option value="free">Stok bebas</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}">Project {{ $p->project_number }} — {{ $p->project_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="sr-include-zero">
                    <label class="form-check-label" for="sr-include-zero">Tampilkan saldo 0</label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-chart-column me-2" style="color:var(--accent)"></i>Saldo Stok</span>
        <span class="fw-semibold">Total Nilai: {{ \App\Models\Currency::baseName() }} <span id="sr-total-value">0</span></span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="sr-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Kode</th>
                        <th>Nama Produk</th>
                        <th>Gudang</th>
                        <th>Kepemilikan</th>
                        <th class="text-end" style="width:110px">Saldo</th>
                        <th style="width:90px">Satuan</th>
                        <th class="text-end" style="width:140px">Harga Rata-rata</th>
                        <th class="text-end" style="width:150px">Nilai</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const srCardUrl = '{{ route("stock-report.card", "__ID__") }}';
let srTable = null;

function srEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function srFmt(n) { return Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }); }

$(document).ready(function() {
    $('#sr-ownership').select2({ width: '100%' });

    srTable = $('#sr-table').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        ajax: {
            url: '{{ route("stock-report.data") }}',
            data: function(d) {
                d.warehouse_id = $('#sr-warehouse').val();
                d.ownership = $('#sr-ownership').val();
                d.include_zero = $('#sr-include-zero').is(':checked') ? 1 : 0;
            }
        },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'code', orderable: false, searchable: true,
                render: function(data, type, row) {
                    var url = srCardUrl.replace('__ID__', row.master_product_id) + '?' + $.param({ warehouse_id: row.warehouse_id, ownership: row.project_id || 'free' });
                    return '<a href="' + url + '" class="fw-semibold" title="Kartu stok">' + srEsc(data) + '</a>';
                }
            },
            { data: 'product_name', orderable: false, searchable: true, render: function(data) { return srEsc(data); } },
            { data: 'warehouse', orderable: false, searchable: false,
                render: function(data, type, row) {
                    var badge = row.warehouse_type === 'transit'
                        ? ' <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px">Transit</span>' : '';
                    return srEsc(data) + badge;
                }
            },
            { data: 'ownership', orderable: false, searchable: false,
                render: function(data, type, row) {
                    return row.project_id
                        ? '<span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px">Project</span> ' + srEsc(data)
                        : '<span style="color:var(--text-muted)">Stok bebas</span>';
                }
            },
            { data: 'balance', orderable: false, searchable: false, className: 'text-end fw-semibold',
                render: function(data) { return data < 0 ? '<span style="color:var(--danger)">' + data + '</span>' : data; }
            },
            { data: 'unit', orderable: false, searchable: false, render: function(data) { return srEsc(data || '—'); } },
            { data: 'average_cost', orderable: false, searchable: false, className: 'text-end', render: srFmt },
            { data: 'value', orderable: false, searchable: false, className: 'text-end fw-semibold', render: srFmt }
        ]
    });
    srTable.on('xhr', function() {
        var json = srTable.ajax.json();
        $('#sr-total-value').text(srFmt(json ? json.total_value : 0));
    });

    $('#sr-warehouse, #sr-ownership, #sr-include-zero').on('change', function() { srTable.ajax.reload(); });
});
</script>
@endsection
