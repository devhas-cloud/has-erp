@extends('layouts.app')

@section('title', 'Laporan Stok')
@section('page-title', 'Laporan Stok')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Laporan Stok</h1>
        <p class="page-header-sub">Saldo dan nilai persediaan (harga modal per baris stok) per produk, gudang, dan kepemilikan. Klik kode produk untuk melihat kartu stok.</p>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-body-custom">
        <div class="row g-3 align-items-end">
            <div class="col-lg-3">
                <label class="form-label" for="sr-branch">Branch</label>
                <select id="sr-branch" class="form-select">
                    <option value="">Semua branch</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->code }} — {{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label">Gudang</label>
                <select id="sr-warehouse" class="form-select">
                    <option value="">Semua gudang</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4">
                <label class="form-label">Kepemilikan</label>
                <select id="sr-ownership" class="form-select">
                    <option value="">Semua</option>
                    <option value="free">Stok bebas</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}">Project {{ $p->project_number }} — {{ $p->project_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="sr-include-zero">
                    <label class="form-check-label" for="sr-include-zero">Tampilkan saldo 0</label>
                </div>
            </div>
        </div>
    </div>
</div>

@if($inTransit->isNotEmpty())
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-truck-fast me-2" style="color:var(--accent)"></i>Dalam Pengiriman Antar Branch</span>
        <span class="fw-semibold">Nilai: {{ \App\Models\Currency::baseName() }} {{ \App\Support\StockCost::format($inTransit->sum('value')) }}</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead><tr><th>No. Dokumen</th><th>Dari</th><th>Ke</th><th>Dikirim</th><th class="text-center">Barang</th><th class="text-end">Nilai</th></tr></thead>
                <tbody>
                    @foreach($inTransit as $t)
                        <tr>
                            <td><a href="{{ route('branch-transfer.show', $t['id']) }}" class="fw-semibold">{{ $t['number'] }}</a></td>
                            <td>{{ $t['from'] }}</td>
                            <td>{{ $t['to'] }}</td>
                            <td>{{ $t['shipped_at'] }}</td>
                            <td class="text-center">{{ $t['items'] }}</td>
                            <td class="text-end">{{ \App\Support\StockCost::format($t['value']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-2 pt-2" style="font-size:12px;color:var(--text-muted)"><i class="fa fa-circle-info me-1"></i>Barang ini sudah keluar dari gudang asal dan belum diterima branch tujuan, sehingga tidak termasuk saldo gudang di bawah.</div>
    </div>
</div>
@endif

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
                        <th class="text-end" style="width:140px">Harga Modal Rata-rata</th>
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
const srDetailUrl = '{{ route("stock-detail.index") }}';
let srTable = null;

function srEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function srFmt(n) { return Num.format(n, 2); }

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
                d.branch_id = $('#sr-branch').val();
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
            { data: 'product_name', orderable: false, searchable: true, render: function(data, type, row) {
                var detail = srDetailUrl + '?' + $.param({ product_id: row.master_product_id, warehouse_id: row.warehouse_id, show: 'all' });
                var html = srEsc(data) + '<div style="font-size:11px"><a href="' + detail + '">Lihat per ' + (row.serialized ? 'SN' : 'batch') + '</a>';
                if (row.missing_serial > 0) html += ' &middot; <span style="color:#b45309"><i class="fa fa-triangle-exclamation"></i> ' + row.missing_serial + ' unit SN belum diisi</span>';
                return html + '</div>';
            } },
            { data: 'warehouse', orderable: false, searchable: false,
                render: function(data, type, row) {
                    return srEsc(data) + (row.branch ? '<div style="font-size:11px;color:var(--text-muted)">' + srEsc(row.branch) + '</div>' : '');
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
                render: function(data) { return Num.format(data, 4); }
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

    $('#sr-branch, #sr-warehouse, #sr-ownership, #sr-include-zero').on('change', function() { srTable.ajax.reload(); });
});
</script>
@endsection
