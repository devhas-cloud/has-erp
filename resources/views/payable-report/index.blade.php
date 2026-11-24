@extends('layouts.app')

@section('title', 'Laporan Hutang')
@section('page-title', 'Laporan Hutang')

@php
    $money = fn ($v) => \App\Support\Number::money((float) $v);
@endphp

@section('styles')
@include('partials.list-toolbar')
<style>
    .pr-tabs { display: flex; gap: 4px; flex-wrap: wrap; border-bottom: 1px solid var(--card-border); margin-bottom: 6px; }
    .pr-tabs a { padding: 8px 14px; font-size: 13px; font-weight: 500; color: var(--text-muted); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; display: inline-flex; align-items: center; gap: 6px; }
    .pr-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
    .pr-tabs a:hover { color: var(--text-primary); }
    .pr-toolbar { position: sticky; top: calc(var(--topbar-h) + 8px); z-index: 5; background: #f8fafc; border: 1px solid var(--card-border); border-radius: var(--radius-sm); }
    .pr-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .pr-muted { font-size: 12px; color: var(--text-muted); }
    .pr-bar { display: flex; height: 6px; border-radius: 99px; overflow: hidden; background: #eef2f7; min-width: 120px; }
    .pr-bar span { display: block; height: 100%; }
    .pr-group-row td { background: var(--accent-subtle) !important; font-weight: 600; }
    .pr-group-row.overdue td { background: #fef2f2 !important; color: #7f1d1d; }
    table.pr-table { width: 100%; }
    table.pr-table thead th { font-weight: 700; white-space: nowrap; }
    table.pr-table tbody tr td { transition: background .12s ease; }
    table.pr-table tbody tr:hover td { background: #f8fafc; }
    table.pr-table tbody tr.pr-row-overdue td { background: #fef2f2; }
    table.pr-table tbody tr.pr-row-overdue td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
    table.pr-table tfoot td { font-weight: 700; border-top: 2px solid var(--card-border); }
    .pr-export { font-size: 12.5px; height: 32px; white-space: nowrap; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Laporan Hutang</h1>
        <p class="page-header-sub">Posisi hutang supplier per {{ now()->format('d/m/Y') }}. Nilai ditampilkan dalam mata uang masing-masing tagihan.</p>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-body-custom">
        <nav class="pr-tabs" aria-label="Jenis laporan">
            @foreach($tabs as $key => $label)
                <a href="{{ route('payable-report.index', ['tab' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if($tab === 'supplier' || $tab === 'project')
            <div class="list-toolbar pr-toolbar" id="pr-toolbar">
                <div class="lt-search">
                    <i class="fa fa-magnifying-glass"></i>
                    <input type="search" class="form-control" data-search autocomplete="off"
                           placeholder="@if($tab === 'supplier')Cari supplier…@elseCari project, supplier, no. tagihan…@endif">
                    <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
                </div>
                @if($currencies->count() > 1)
                <select class="form-select" data-filter="currency" aria-label="Mata uang">
                    <option value="">Semua Mata Uang</option>
                    @foreach($currencies as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                </select>
                @endif
                @if($tab === 'project')
                <div class="lt-date" title="Tanggal tagihan">
                    <input type="date" class="form-control" data-filter="from" aria-label="Dari tanggal">
                    <span>–</span>
                    <input type="date" class="form-control" data-filter="to" aria-label="Sampai tanggal">
                </div>
                @endif
                <a href="#" class="btn btn-sm btn-outline-secondary pr-export" title="Export tab ini ke Excel dengan filter aktif"><i class="fa fa-file-excel me-1"></i>Export</a>
                <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
            </div>
        @else
            <form class="list-toolbar pr-toolbar" id="pr-getform" method="GET" action="{{ route('payable-report.index') }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if($tab === 'fx')
                    <div class="lt-date" title="Periode">
                        <input type="date" class="form-control" name="from" value="{{ $period['from'] }}" aria-label="Dari tanggal">
                        <span>–</span>
                        <input type="date" class="form-control" name="to" value="{{ $period['to'] }}" aria-label="Sampai tanggal">
                    </div>
                @endif
                <button type="submit" class="btn btn-sm btn-outline-secondary ms-auto pr-export" formaction="{{ route('payable-report.export') }}" title="Export tab ini ke Excel"><i class="fa fa-file-excel me-1"></i>Export</button>
            </form>
        @endif

        <div class="table-responsive">
        @switch($tab)
            @case('aging')
                @php $agingColors = ['current' => '#10b981', 'd1_30' => '#f59e0b', 'd31_60' => '#f97316', 'd61_90' => '#ef4444', 'd90' => '#991b1b']; @endphp
                <table class="table table-custom align-middle mb-0 pr-table">
                    <thead><tr><th>Supplier</th>@foreach(\App\Support\PayableReport::AGING_BUCKETS as $label)<th class="pr-num">{{ $label }}</th>@endforeach<th class="pr-num">Total</th><th style="width:140px">Komposisi</th></tr></thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr>
                                <td><a href="{{ route('supplier-bill.index', ['supplier_id' => $r['supplier']?->id]) }}" class="fw-semibold">{{ $r['supplier']?->name }}</a></td>
                                @foreach($r['buckets'] as $key => $value)<td class="pr-num {{ $key !== 'current' && $value > 0 ? 'text-danger' : '' }}">{{ $value > 0 ? $money($value) : '—' }}</td>@endforeach
                                <td class="pr-num fw-semibold">{{ $money($r['total']) }}</td>
                                <td><div class="pr-bar" title="Komposisi umur hutang">@foreach($r['buckets'] as $key => $value)@if($value > 0)<span style="width:{{ $r['total'] > 0 ? $value / $r['total'] * 100 : 0 }}%;background:{{ $agingColors[$key] }}"></span>@endif @endforeach</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-4 pr-muted">Tidak ada hutang terbuka.</td></tr>
                        @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                    <tfoot><tr><td>Total</td>@foreach(array_keys(\App\Support\PayableReport::AGING_BUCKETS) as $key)<td class="pr-num">{{ $money($rows->sum(fn ($r) => $r['buckets'][$key])) }}</td>@endforeach<td class="pr-num">{{ $money($rows->sum('total')) }}</td><td></td></tr></tfoot>
                    @endif
                </table>
                @break

            @case('schedule')
                <table class="table table-custom align-middle mb-0 pr-table">
                    <thead><tr><th>Tagihan</th><th>Supplier</th><th>Jatuh Tempo</th><th class="pr-num">Sisa</th><th class="pr-num">Sisa ({{ $base }})</th></tr></thead>
                    <tbody>
                        @forelse($rows as $group)
                            <tr class="pr-group-row {{ $group['key'] === '0_overdue' ? 'overdue' : '' }}"><td colspan="4">{{ $group['label'] }} <span class="pr-muted">({{ $group['bills']->count() }} tagihan)</span></td><td class="pr-num">{{ $money($group['total']) }}</td></tr>
                            @foreach($group['bills'] as $bill)
                                <tr>
                                    <td><a href="{{ route('supplier-bill.show', $bill->id) }}" class="fw-semibold">{{ $bill->bill_number }}</a>@if($bill->isAdvance()) <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px">Uang Muka</span>@endif<div class="pr-muted">Inv. {{ $bill->supplier_invoice_number }} · {{ $bill->sourceNumber() }}</div></td>
                                    <td>{{ $bill->supplier?->name }}</td>
                                    <td>{{ $bill->due_date?->format('d/m/Y') }}@if($report->daysOverdue($bill) > 0)<div class="pr-muted text-danger">terlambat {{ $report->daysOverdue($bill) }} hari</div>@endif</td>
                                    <td class="pr-num">{{ $bill->currency }} {{ \App\Support\Number::money($bill->outstanding()) }}</td>
                                    <td class="pr-num">{{ $money($bill->outstandingBase()) }}@if($bill->isForeign())<div class="pr-muted">kurs {{ \App\Support\Number::rate($bill->exchange_rate) }}</div>@endif</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="5" class="text-center py-4 pr-muted">Tidak ada tagihan yang perlu dibayar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @break

            @case('fx')
                @php $fxMoney = fn ($v) => ($v > 0 ? '' : ($v < 0 ? '−' : '')).$money(abs($v)); @endphp
                <p class="pr-muted mb-2">
                    <strong>Terealisasi</strong> = pembayaran / refund valas yang sudah diposting: nilai aktual (kurs bank) dibanding nilai buku (kurs tagihan / kurs uang muka).
                    Angka positif = <span class="text-danger">rugi kurs</span>, negatif = <span style="color:#15803d">laba kurs</span>.
                </p>
                <table class="table table-custom align-middle mb-4 pr-table">
                    <thead><tr><th>Tanggal</th><th>Dokumen</th><th>Supplier</th><th class="pr-num">Nominal</th><th class="pr-num">Kurs Buku → Aktual</th><th class="pr-num">Nilai Buku</th><th class="pr-num">Nilai Aktual</th><th class="pr-num">Selisih</th></tr></thead>
                    <tbody>
                        @forelse($rows['realized'] as $r)
                            <tr>
                                <td>{{ $r['date']?->format('d/m/Y') }}</td>
                                <td><a href="{{ $r['url'] }}" class="fw-semibold">{{ $r['number'] }}</a><div class="pr-muted">{{ $r['reference'] }}</div></td>
                                <td>{{ $r['supplier'] }}</td>
                                <td class="pr-num">{{ $r['currency'] }} {{ \App\Support\Number::money($r['amount']) }}</td>
                                <td class="pr-num">{{ \App\Support\Number::money($r['book_rate']) }} → {{ \App\Support\Number::money($r['actual_rate']) }}</td>
                                <td class="pr-num">{{ $money($r['book']) }}</td>
                                <td class="pr-num">{{ $money($r['actual']) }}</td>
                                <td class="pr-num fw-semibold {{ $r['difference'] > 0 ? 'text-danger' : ($r['difference'] < 0 ? 'text-success' : '') }}">{{ $fxMoney($r['difference']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-4 pr-muted">Belum ada pembayaran / refund valas pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                    @if($rows['realized']->isNotEmpty())
                    <tfoot><tr><td colspan="7">Total selisih kurs terealisasi ({{ $rows['realized_total'] > 0 ? 'rugi' : ($rows['realized_total'] < 0 ? 'laba' : 'nihil') }})</td><td class="pr-num {{ $rows['realized_total'] > 0 ? 'text-danger' : 'text-success' }}">{{ $fxMoney($rows['realized_total']) }}</td></tr></tfoot>
                    @endif
                </table>

                <p class="pr-muted mb-2"><strong>Belum terealisasi</strong> = tagihan valas yang masih terbuka: nilai buku dibanding nilai bila dibayar dengan kurs master saat ini. Hanya informasi, belum dicatat.</p>
                <table class="table table-custom align-middle mb-0 pr-table">
                    <thead><tr><th>Tagihan</th><th>Supplier</th><th class="pr-num">Sisa</th><th class="pr-num">Kurs Tagihan → Terbaru</th><th class="pr-num">Nilai Buku</th><th class="pr-num">Nilai Kini</th><th class="pr-num">Selisih</th></tr></thead>
                    <tbody>
                        @forelse($rows['unrealized'] as $u)
                            <tr>
                                <td><a href="{{ route('supplier-bill.show', $u['bill']->id) }}" class="fw-semibold">{{ $u['bill']->bill_number }}</a>@if($u['bill']->rate_estimated) <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px" title="Kurs diisi otomatis dari kurs master">kurs perkiraan</span>@endif<div class="pr-muted">Inv. {{ $u['bill']->supplier_invoice_number }}</div></td>
                                <td>{{ $u['bill']->supplier?->name }}</td>
                                <td class="pr-num">{{ $u['bill']->currency }} {{ \App\Support\Number::money($u['outstanding']) }}</td>
                                <td class="pr-num">{{ \App\Support\Number::money($u['book_rate']) }} → {{ \App\Support\Number::money($u['current_rate']) }}</td>
                                <td class="pr-num">{{ $money($u['book']) }}</td>
                                <td class="pr-num">{{ $money($u['current']) }}</td>
                                <td class="pr-num fw-semibold {{ $u['difference'] > 0 ? 'text-danger' : ($u['difference'] < 0 ? 'text-success' : '') }}">{{ $fxMoney($u['difference']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-4 pr-muted">Tidak ada tagihan valas terbuka.</td></tr>
                        @endforelse
                    </tbody>
                    @if($rows['unrealized']->isNotEmpty())
                    <tfoot><tr><td colspan="6">Total selisih belum terealisasi</td><td class="pr-num">{{ $fxMoney($rows['unrealized_total']) }}</td></tr></tfoot>
                    @endif
                </table>
                @break

            @case('project')
                <table id="pr-project" class="table table-custom align-middle mb-0 pr-table" style="width:100%">
                    <thead><tr><th class="text-center" style="width:50px">No</th><th>Project</th><th>Tanggal</th><th class="pr-num">Total Biaya</th><th class="pr-num">Bayar</th><th class="pr-num">Sisa Biaya</th></tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><td></td><td></td><td></td><td class="pr-num"></td><td class="pr-num"></td><td class="pr-num"></td></tr></tfoot>
                </table>
                @break

            @default
                <table id="pr-supplier" class="table table-custom align-middle mb-0 pr-table" style="width:100%">
                    <thead><tr><th class="text-center" style="width:50px">No</th><th>Supplier</th><th class="pr-num">Total Hutang</th><th class="pr-num">Bayar</th><th class="pr-num">Sisa Hutang</th></tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><td></td><td></td><td class="pr-num"></td><td class="pr-num"></td><td class="pr-num"></td></tr></tfoot>
                </table>
        @endswitch
        </div>
    </div>
</div>

{{-- Status pengingat WhatsApp; pengaturannya di Master Data → Notifikasi Finance --}}
<div class="d-flex align-items-center flex-wrap gap-2 mt-3 pr-muted">
    <i class="fa-brands fa-whatsapp" style="color:#16a34a"></i>
    <span>Pengingat Jatuh Tempo (WhatsApp):</span>
    <span class="status-badge" style="{{ $reminder['enabled'] ? 'background:#dcfce7;color:#166534' : 'background:#f3f4f6;color:#4b5563' }}">{{ $reminder['enabled'] ? 'Aktif · setiap hari '.$reminder['send_time'] : 'Nonaktif' }}</span>
    @if($reminder['last_sent_date'])<span>· terakhir terkirim {{ \Illuminate\Support\Carbon::parse($reminder['last_sent_date'])->format('d/m/Y') }}</span>@endif
    @if($canManageNotification)<a href="{{ route('finance-notification.index') }}" class="ms-1"><i class="fa fa-gear me-1"></i>Atur</a>@endif
</div>
@endsection

@section('scripts')
<script>
const prDataUrl = '{{ route("payable-report.data") }}';
const prExportBase = '{{ route("payable-report.export") }}';
const prTab = '{{ $tab }}';
const prLang = {
    lengthMenu: 'Tampilkan _MENU_ baris',
    info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
    infoEmpty: 'Tidak ada baris',
    infoFiltered: '(difilter dari _MAX_)',
    zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Tidak ada data yang cocok.</span>',
    emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Belum ada data.</span>',
    paginate: { first: '«', previous: '‹', next: '›', last: '»' }
};
let prToolbar = null;

function prToolbarParams() {
    var params = { tab: prTab };
    $('#pr-toolbar [data-filter]').each(function() { var v = $(this).val(); if (v) params[$(this).data('filter')] = v; });
    var q = $('#pr-toolbar [data-search]').val();
    if (q) params.q = q;
    return params;
}
function prSyncExport() { $('.pr-export').attr('href', prExportBase + '?' + $.param(prToolbarParams())); }

$(document).ready(function() {
    if (prTab === 'supplier' || prTab === 'project') {
        $('#pr-toolbar').on('change input click', '[data-filter], [data-search], .lt-clear, [data-reset]', function() { setTimeout(prSyncExport, 60); });

        var cols = prTab === 'project' ? 6 : 5;
        prToolbar = ListToolbar('#pr-toolbar', function() { return prTable; });
        var prTable = $('#pr-' + prTab).DataTable({
            layout: ListToolbar.layout,
            language: prLang,
            processing: true,
            serverSide: true,
            ajax: { url: prDataUrl, data: function(d) { d.tab = prTab; return prToolbar.params(d); } },
            order: [],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            columns: Array.from({ length: cols }, function() { return { data: null, defaultContent: '', render: function() { return ''; } }; }),
            columnDefs: [{ orderable: false, targets: '_all' }],
            createdRow: function(row, data) { $(row).html(data.html); }
        });
        prTable.on('xhr.dt', function(e, s, json) {
            if (json && json.footer) { json.footer.forEach(function(cell, idx) { $('#pr-' + prTab + ' tfoot td').eq(idx).html(cell); }); }
            prSyncExport();
        });
        prSyncExport();
    } else {
        // Tab aging/schedule/fx: form GET, auto-submit saat periode berubah.
        $('#pr-getform').on('change', 'input[name="from"], input[name="to"]', function() { this.form.submit(); });
    }
});
</script>
@endsection
