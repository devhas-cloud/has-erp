@extends('layouts.app')

@section('title', 'Laporan Stok')
@section('page-title', 'Laporan Stok')

@section('styles')
@include('partials.list-toolbar')
<style>
    .sr-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
    @media (max-width: 991.98px) { .sr-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .sr-stat { border: 1px solid var(--card-border, #e5e7eb); border-radius: 10px; padding: 11px 13px; background: var(--card, #fff); text-align: left; }
    button.sr-stat { cursor: pointer; transition: border-color .15s, background .15s; }
    button.sr-stat:hover { border-color: var(--accent); }
    button.sr-stat.active { border-color: var(--accent); background: var(--accent-subtle, #ecfdf5); }
    .sr-stat .sr-stat-label { font-size: 12px; color: var(--text-muted); }
    .sr-stat .sr-stat-num { font-size: 19px; font-weight: 700; line-height: 1.2; font-variant-numeric: tabular-nums; margin-top: 2px; }
    .sr-stat .sr-stat-sub { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }
    .sr-kind { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 999px; white-space: nowrap; }
    .sr-kind-material { background: #e0f2fe; color: #075985; }
    .sr-kind-serial { background: #fef3c7; color: #92400e; }
    .sr-own-project { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 999px; background: #fef3c7; color: #92400e; }
    .sr-own-free { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 999px; background: #f3f4f6; color: #4b5563; }
    #sr-table th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    #sr-table td { vertical-align: middle; }
    .sr-code { font-weight: 700; font-variant-numeric: tabular-nums; }
    .sr-sub { font-size: 11.5px; color: var(--text-muted); }
    .sr-qty { font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums; }
    .sr-money { font-variant-numeric: tabular-nums; }
    .sr-warn { font-size: 11px; color: #b45309; white-space: nowrap; }
    .sr-zero td { opacity: .55; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Laporan Stok</h1>
        <p class="page-header-sub">Saldo dan nilai persediaan per produk, gudang, dan kepemilikan. <strong>Material</strong> = satu saldo per posisi dengan harga rata-rata tertimbang; <strong>barang ber-SN</strong> dihitung per unit. Klik kode produk untuk kartu stok (riwayat masuk/keluar).</p>
    </div>
</div>

<div class="sr-stats fade-in" id="sr-stats">
    <div class="sr-stat">
        <div class="sr-stat-label"><i class="fa fa-sack-dollar me-1" style="color:var(--accent)"></i>Total nilai persediaan</div>
        <div class="sr-stat-num">{{ \App\Models\Currency::baseName() }} <span id="sr-total-value">0</span></div>
        <div class="sr-stat-sub"><span id="sr-sum-products">0</span> produk sesuai filter</div>
    </div>
    <button type="button" class="sr-stat" data-kind="material">
        <div class="sr-stat-label"><span class="sr-kind sr-kind-material">Material</span> dihitung per jumlah</div>
        <div class="sr-stat-num" id="sr-sum-material-value">0</div>
        <div class="sr-stat-sub"><span id="sr-sum-material-positions">0</span> saldo (produk × gudang × kepemilikan)</div>
    </button>
    <button type="button" class="sr-stat" data-kind="serial">
        <div class="sr-stat-label"><span class="sr-kind sr-kind-serial">Ber-SN</span> dihitung per unit</div>
        <div class="sr-stat-num" id="sr-sum-serial-value">0</div>
        <div class="sr-stat-sub"><span id="sr-sum-serial-units">0</span> unit di stok</div>
    </button>
    <button type="button" class="sr-stat" data-missing="1">
        <div class="sr-stat-label"><i class="fa fa-triangle-exclamation me-1" style="color:#b45309"></i>SN belum diisi</div>
        <div class="sr-stat-num" id="sr-sum-missing" style="color:#b45309">0</div>
        <div class="sr-stat-sub">unit belum bisa dikeluarkan (DO / retur)</div>
    </button>
</div>

@if($inTransit->isNotEmpty())
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-truck-fast me-2" style="color:var(--accent)"></i>Dalam Pengiriman Antar Branch <span class="badge rounded-pill ms-1" style="background:var(--accent);font-size:10px">{{ $inTransit->count() }}</span></span>
        <span class="fw-semibold">Nilai: {{ \App\Models\Currency::baseName() }} {{ \App\Support\StockCost::format($inTransit->sum('value')) }}</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead><tr><th>No. Dokumen</th><th>Rute</th><th>Dikirim</th><th class="text-center">Barang</th><th class="text-end">Nilai (saat dikirim)</th></tr></thead>
                <tbody>
                    @foreach($inTransit as $t)
                        <tr>
                            <td><a href="{{ route('branch-transfer.show', $t['id']) }}" class="fw-semibold">{{ $t['number'] }}</a></td>
                            <td>{{ $t['from'] }} <i class="fa fa-arrow-right mx-1" style="color:var(--accent)"></i> {{ $t['to'] }}</td>
                            <td>{{ $t['shipped_at'] }}</td>
                            <td class="text-center">{{ $t['items'] }}</td>
                            <td class="text-end sr-money">{{ \App\Support\StockCost::format($t['value']) }}</td>
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
    <div class="card-header-custom">
        <span><i class="fa-solid fa-chart-column me-2" style="color:var(--accent)"></i>Saldo Stok</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="sr-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari kode atau nama produk…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="kind" aria-label="Jenis">
                <option value="">Semua jenis</option>
                <option value="material">Material</option>
                <option value="serial">Barang ber-SN</option>
            </select>
            @if($branches->count() > 1)
            <select class="form-select" data-filter="branch_id" aria-label="Branch">
                <option value="">Semua branch</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}">{{ $b->code }} — {{ $b->name }}</option>
                @endforeach
            </select>
            @endif
            <select class="form-select" data-filter="warehouse_id" data-searchable aria-label="Gudang">
                <option value="">Semua gudang</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                @endforeach
            </select>
            <select class="form-select" data-filter="ownership" data-searchable aria-label="Kepemilikan">
                <option value="">Semua kepemilikan</option>
                <option value="free">Stok bebas</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}">Project {{ $p->project_number }} — {{ $p->project_name }}</option>
                @endforeach
            </select>
            <select class="form-select" data-filter="missing_sn" aria-label="SN">
                <option value="">Semua SN</option>
                <option value="1">SN belum diisi</option>
            </select>
            <select class="form-select" data-filter="include_zero" aria-label="Saldo nol">
                <option value="">Saldo &gt; 0</option>
                <option value="1">Termasuk saldo 0</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="sr-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:44px">#</th>
                        <th>Produk</th>
                        <th style="width:190px">Gudang</th>
                        <th style="width:220px">Kepemilikan</th>
                        <th class="text-end" style="width:140px">Saldo</th>
                        <th class="text-end" style="width:140px">Harga Rata-rata</th>
                        <th class="text-end" style="width:150px">Nilai</th>
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
const srCardUrl = '{{ route("stock-report.card", "__ID__") }}';
const srDetailUrl = '{{ route("stock-detail.index") }}';
let srTable = null;
let srToolbar = null;

function srEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function srMoney(n) { return Num.format(n, 2); }
function srQty(n) { return Num.format(n, 4); }

function srCardLink(row) {
    return srCardUrl.replace('__ID__', row.master_product_id) + '?' + $.param({ warehouse_id: row.warehouse_id, ownership: row.project_id || 'free' });
}

function srSyncStats() {
    var p = srToolbar ? srToolbar.params({}) : {};
    $('#sr-stats [data-kind]').each(function() { $(this).toggleClass('active', $(this).data('kind') === (p.kind || '')); });
    $('#sr-stats [data-missing]').toggleClass('active', p.missing_sn === '1');
}

$(document).ready(function() {
    srToolbar = ListToolbar('#sr-toolbar', function() { return srTable; });
    srTable = $('#sr-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Tidak ada saldo yang cocok dengan pencarian / filter.</span>',
            emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Belum ada stok.</span>'
        },
        processing: true,
        serverSide: true,
        pageLength: 25,
        ajax: { url: '{{ route("stock-report.data") }}', data: function(d) { return srToolbar.params(d); } },
        order: [],
        createdRow: function(tr, row) { if (row.balance <= 0) $(tr).addClass('sr-zero'); },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'code', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<a href="' + srCardLink(row) + '" class="sr-code" title="Kartu stok">' + srEsc(data) + '</a> '
                        + '<span class="sr-kind ' + (row.serialized ? 'sr-kind-serial">Ber-SN' : 'sr-kind-material">Material') + '</span>'
                        + '<div>' + srEsc(row.product_name) + '</div>'
                        + (row.missing_serial > 0 ? '<div class="sr-warn"><i class="fa fa-triangle-exclamation me-1"></i>' + row.missing_serial + ' unit SN belum diisi</div>' : '');
                } },
            { data: 'warehouse', orderable: false, searchable: false,
                render: function(data, type, row) {
                    return srEsc(data) + (row.branch ? '<div class="sr-sub">' + srEsc(row.branch) + '</div>' : '');
                } },
            { data: 'ownership', orderable: false, searchable: false,
                render: function(data, type, row) {
                    return row.project_id
                        ? '<span class="sr-own-project">Project</span><div class="sr-sub">' + srEsc(data) + '</div>'
                        : '<span class="sr-own-free">Stok bebas</span>';
                } },
            { data: 'balance', orderable: false, searchable: false, className: 'text-end',
                render: function(data, type, row) {
                    return '<span class="sr-qty">' + srQty(data) + '</span> <span class="sr-sub">' + srEsc(row.unit || '') + '</span>'
                        + (row.serialized ? '<div class="sr-sub">' + row.units + ' unit SN</div>' : (row.last_change ? '<div class="sr-sub">ubah ' + srEsc(row.last_change) + '</div>' : ''))
                        + (row.on_loan > 0 ? '<div class="sr-sub" style="color:#92400e" title="Unit dipinjam (Item Loan): tetap di saldo, tidak tersedia untuk dokumen lain">' + row.on_loan + ' sedang dipinjam</div>' : '');
                } },
            { data: 'average_cost', orderable: false, searchable: false, className: 'text-end sr-money',
                render: function(data, type, row) { return row.balance > 0 ? srMoney(data) : '<span class="sr-sub">—</span>'; } },
            { data: 'value', orderable: false, searchable: false, className: 'text-end fw-semibold sr-money', render: srMoney },
            { data: 'master_product_id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var html = '<div class="d-flex justify-content-center gap-1">'
                        + '<a href="' + srCardLink(row) + '" class="btn-icon" title="Kartu stok (riwayat masuk/keluar)"><i class="fa fa-clock-rotate-left"></i></a>';
                    if (row.serialized) {
                        html += '<a href="' + srDetailUrl + '?' + $.param({ product_id: data, warehouse_id: row.warehouse_id, show: 'all' }) + '" class="btn-icon" title="Daftar unit per SN"><i class="fa fa-barcode"></i></a>';
                    }
                    return html + '</div>';
                } }
        ]
    });

    srTable.on('xhr', function() {
        var json = srTable.ajax.json() || {};
        var s = json.summary || {};
        $('#sr-total-value').text(srMoney(json.total_value || 0));
        $('#sr-sum-products').text(s.products || 0);
        $('#sr-sum-material-value').text(srMoney(s.material_value || 0));
        $('#sr-sum-material-positions').text(s.material_positions || 0);
        $('#sr-sum-serial-value').text(srMoney(s.serial_value || 0));
        $('#sr-sum-serial-units').text(s.serial_units || 0);
        $('#sr-sum-missing').text(s.missing_serial || 0);
        srSyncStats();
    });

    // Kartu ringkasan sebagai filter cepat.
    $('#sr-stats').on('click', '[data-kind]', function() {
        var $f = $('#sr-toolbar [data-filter="kind"]');
        $f.val($f.val() === $(this).data('kind') ? '' : $(this).data('kind')).trigger('change');
    });
    $('#sr-stats').on('click', '[data-missing]', function() {
        var $f = $('#sr-toolbar [data-filter="missing_sn"]');
        $f.val($f.val() === '1' ? '' : '1').trigger('change');
    });
});
</script>
@endsection
