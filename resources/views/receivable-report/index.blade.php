@extends('layouts.app')

@section('title', 'Laporan Piutang')
@section('page-title', 'Laporan Piutang')

@section('styles')
@include('partials.list-toolbar')
<style>
    .rr-tabs { display: flex; gap: 4px; flex-wrap: wrap; border-bottom: 1px solid var(--card-border); margin-bottom: 6px; }
    .rr-tabs a { padding: 8px 14px; font-size: 13px; font-weight: 500; color: var(--text-muted); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; display: inline-flex; align-items: center; gap: 6px; }
    .rr-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
    .rr-tabs a:hover { color: var(--text-primary); }
    .rr-tabs .rr-count { font-size: 11px; font-weight: 600; padding: 0 6px; border-radius: 99px; background: #f1f5f9; color: #475569; }
    .rr-tabs a.active .rr-count { background: var(--accent-soft); color: var(--accent); }
    .rr-toolbar { position: sticky; top: calc(var(--topbar-h) + 8px); z-index: 5; background: #f8fafc; border: 1px solid var(--card-border); border-radius: var(--radius-sm); }
    .rr-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .rr-muted { font-size: 12px; color: var(--text-muted); }
    .rr-bar { display: flex; height: 6px; border-radius: 99px; overflow: hidden; background: #eef2f7; min-width: 120px; }
    .rr-bar span { display: block; height: 100%; }
    .rr-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; color: var(--text-muted); margin-bottom: 10px; }
    .rr-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 4px; vertical-align: -1px; }
    .rr-tag { display: inline-block; font-size: 10.5px; font-weight: 600; padding: 1px 7px; border-radius: 99px; }
    .rr-tag.certain { background: #dcfce7; color: #166534; }
    .rr-tag.estimate { background: #f1f5f9; color: #475569; }
    table.rr-table { width: 100%; }
    table.rr-table thead th { font-weight: 700; white-space: nowrap; }
    table.rr-table tbody tr td { transition: background .12s ease; }
    table.rr-table tbody tr:hover td { background: #f8fafc; }
    table.rr-table tbody tr.rr-row-overdue td { background: #fef2f2; }
    table.rr-table tbody tr.rr-row-overdue td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
    table.rr-table tbody tr.rr-row-late td { background: #fffbeb; }
    table.rr-table tbody tr.rr-row-late td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
    table.rr-table tfoot td { font-weight: 700; border-top: 2px solid var(--card-border); }
    .rr-group-row td { background: var(--accent-subtle) !important; font-weight: 600; }
    .rr-group-row.overdue td { background: #fef2f2 !important; color: #7f1d1d; }
    .rr-group-row.late td { background: #fffbeb !important; color: #92400e; }
    .rr-group-head { cursor: pointer; }
    .rr-group-head:hover td { filter: brightness(.98); }
    .rr-empty { text-align: center; padding: 36px 0; color: var(--text-muted); }
    .rr-empty i { font-size: 22px; display: block; margin-bottom: 8px; opacity: .45; }
    .rr-export { font-size: 12.5px; height: 32px; white-space: nowrap; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Laporan Piutang</h1>
        <p class="page-header-sub">Posisi piutang customer per {{ now()->format('d/m/Y') }}. Invoice termasuk PPN; termin yang belum diinvoice dihitung sebelum PPN.</p>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-body-custom">
        <nav class="rr-tabs" aria-label="Jenis laporan">
            @foreach($tabs as $key => $label)
                <a href="{{ route('receivable-report.index', ['tab' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}" @if($tab === $key) aria-current="page" @endif>
                    {{ $label }} <span class="rr-count">{{ $tabCounts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="list-toolbar rr-toolbar" id="rr-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search autocomplete="off"
                       placeholder="@if($tab === 'customer')Cari customer…@elseif($tab === 'project')Cari no./nama project, customer…@elseCari customer, project, no. invoice…@endif">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            @if($tab === 'customer')
                <select class="form-select" data-filter="overdue" aria-label="Status">
                    <option value="">Semua Status</option>
                    <option value="1">Hanya terlambat</option>
                </select>
            @elseif($tab === 'project')
                <select class="form-select" data-filter="late_billing" aria-label="Penagihan">
                    <option value="">Semua Penagihan</option>
                    <option value="1">Hanya terlambat ditagih</option>
                </select>
                <select class="form-select" data-filter="receivable" aria-label="Piutang">
                    <option value="">Semua Piutang</option>
                    <option value="1">Hanya punya piutang</option>
                </select>
            @else
                <select class="form-select" data-filter="kind" aria-label="Jenis">
                    <option value="">Semua Jenis</option>
                    <option value="invoice">Pasti (invoice)</option>
                    <option value="estimate">Estimasi</option>
                </select>
                <select class="form-select" data-filter="group_by" aria-label="Kelompokkan">
                    <option value="period">Periode (minggu)</option>
                    <option value="customer">Customer</option>
                </select>
                <div class="lt-date" title="Tanggal">
                    <input type="date" class="form-control" data-filter="from" aria-label="Dari tanggal">
                    <span>–</span>
                    <input type="date" class="form-control" data-filter="to" aria-label="Sampai tanggal">
                </div>
            @endif
            <a href="#" class="btn btn-sm btn-outline-secondary rr-export" title="Export tab ini ke Excel dengan filter aktif"><i class="fa fa-file-excel me-1"></i>Export</a>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>

        @if($tab === 'project')
            <div class="rr-legend">@foreach(\App\Support\ReceivableReport::POSITIONS as $key => $label)<span><i style="background:{{ ['unscheduled' => '#cbd5e1', 'locked' => '#94a3b8', 'ready' => '#0ea5e9', 'in_process' => '#8b5cf6', 'receivable' => '#f59e0b', 'paid' => '#10b981'][$key] }}"></i>{{ $label }}</span>@endforeach</div>
        @endif

        @if($tab === 'forecast')
            <p class="rr-muted mb-2">
                <span class="rr-tag certain">Pasti</span> invoice Approved menurut jatuh tempo (termasuk PPN).
                <span class="rr-tag estimate ms-1">Estimasi</span> termin yang belum menjadi piutang menurut target tanggal termin; bila kosong memakai PO due date project (sebelum PPN, kecuali invoice yang sedang diproses).
            </p>
        @endif

        <div class="table-responsive">
        @switch($tab)
            @case('forecast')
                <table id="rr-forecast" class="table table-custom align-middle mb-0 rr-table">
                    <thead><tr><th class="text-center" style="width:50px">No</th><th>Customer</th><th>Termin / Invoice</th><th>Tanggal</th><th>Posisi</th><th class="rr-num">Nominal</th></tr></thead>
                    <tbody><tr><td colspan="6"><div class="rr-empty"><i class="fa fa-spinner fa-spin"></i>Memuat proyeksi…</div></td></tr></tbody>
                </table>
                @break

            @case('project')
                <table id="rr-project" class="table table-custom align-middle mb-0 rr-table" style="width:100%">
                    <thead><tr><th class="text-center" style="width:50px">No</th><th>Project</th><th class="rr-num">Nilai Kontrak</th><th style="width:150px">Progres</th><th class="rr-num">Total Piutang</th><th class="rr-num">Total Pembayaran</th><th class="rr-num">Sisa Piutang</th><th>Due Date</th></tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><td></td><td></td><td class="rr-num"></td><td></td><td class="rr-num"></td><td class="rr-num"></td><td class="rr-num"></td><td></td></tr></tfoot>
                </table>
                @break

            @default
                <table id="rr-customer" class="table table-custom align-middle mb-0 rr-table" style="width:100%">
                    <thead><tr><th class="text-center" style="width:50px">No</th><th>Customer</th><th class="rr-num">Total Piutang</th><th class="rr-num">Total Pembayaran</th><th class="rr-num">Sisa Piutang</th></tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><td></td><td></td><td class="rr-num"></td><td class="rr-num"></td><td class="rr-num"></td></tr></tfoot>
                </table>
        @endswitch
        </div>
    </div>
</div>

{{-- Status pengingat WhatsApp; pengaturannya di Master Data → Notifikasi Finance --}}
<div class="d-flex align-items-center flex-wrap gap-2 mt-3 rr-muted">
    <i class="fa-brands fa-whatsapp" style="color:#16a34a"></i>
    <span>Pengingat Piutang (WhatsApp):</span>
    <span class="status-badge" style="{{ $reminder['enabled'] ? 'background:#dcfce7;color:#166534' : 'background:#f3f4f6;color:#4b5563' }}">{{ $reminder['enabled'] ? 'Aktif · setiap hari '.$reminder['send_time'] : 'Nonaktif' }}</span>
    @if($reminder['last_sent_date'])<span>· terakhir terkirim {{ \Illuminate\Support\Carbon::parse($reminder['last_sent_date'])->format('d/m/Y') }}</span>@endif
    @if($canManageNotification)<a href="{{ route('finance-notification.index') }}" class="ms-1"><i class="fa fa-gear me-1"></i>Atur</a>@endif
</div>
@endsection

@section('scripts')
<script>
const rrDataUrl = '{{ route("receivable-report.data") }}';
const rrForecastUrl = '{{ route("receivable-report.forecast") }}';
const rrExportBase = '{{ route("receivable-report.export") }}';
const rrTab = '{{ $tab }}';
const rrLang = {
    lengthMenu: 'Tampilkan _MENU_ baris',
    info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
    infoEmpty: 'Tidak ada baris',
    infoFiltered: '(difilter dari _MAX_)',
    zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Tidak ada data yang cocok.</span>',
    emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Belum ada data.</span>',
    paginate: { first: '«', previous: '‹', next: '›', last: '»' }
};
let rrToolbar = null;

function rrToolbarParams(extra) {
    var params = $.extend({ tab: rrTab }, extra || {});
    $('#rr-toolbar [data-filter]').each(function() { var v = $(this).val(); if (v) params[$(this).data('filter')] = v; });
    var q = $('#rr-toolbar [data-search]').val();
    if (q) params.q = q;
    return params;
}
function rrSyncExport() { $('.rr-export').attr('href', rrExportBase + '?' + $.param(rrToolbarParams())); }

$(document).ready(function() {
    // Export mengikuti filter aktif; diperbarui setiap ada interaksi toolbar.
    $('#rr-toolbar').on('change input click', '[data-filter], [data-search], .lt-clear, [data-reset]', function() { setTimeout(rrSyncExport, 60); });

    // Reset mengosongkan semua [data-filter]; group_by adalah mode kelompok, kembalikan ke default.
    $(document).on('click', '[data-reset]', function() {
        setTimeout(function() {
            var $g = $('#rr-toolbar [data-filter="group_by"]');
            if ($g.length && !$g.val()) { $g.val('period'); }
        }, 0);
    });

    if (rrTab === 'forecast') {
        function loadForecast() {
            $('#rr-forecast tbody').remove();
            $('#rr-forecast').append('<tbody><tr><td colspan="6"><div class="rr-empty"><i class="fa fa-spinner fa-spin"></i>Memuat proyeksi…</div></td></tr></tbody>');
            $.get(rrForecastUrl + '?' + $.param(rrToolbarParams())).done(function(html) {
                $('#rr-forecast tbody').remove();
                $('#rr-forecast').append(html);
            });
        }
        var forecastShim = { ajax: { reload: loadForecast }, search: function() { return forecastShim; }, draw: loadForecast };
        rrToolbar = ListToolbar('#rr-toolbar', function() { return forecastShim; });
        loadForecast();
        rrSyncExport();
        return;
    }

    var cols = rrTab === 'project' ? 8 : 5;
    rrToolbar = ListToolbar('#rr-toolbar', function() { return rrTable; });
    var rrTable = $('#' + (rrTab === 'project' ? 'rr-project' : 'rr-customer')).DataTable({
        layout: ListToolbar.layout,
        language: rrLang,
        processing: true,
        serverSide: true,
        ajax: { url: rrDataUrl, data: function(d) { d.tab = rrTab; return rrToolbar.params(d); } },
        order: [],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        columns: Array.from({ length: cols }, function() { return { data: null, defaultContent: '', render: function() { return ''; } }; }),
        columnDefs: [{ orderable: false, targets: '_all' }],
        createdRow: function(row, data) { $(row).html(data.html); }
    });
    rrTable.on('xhr.dt', function(e, s, json) {
        if (json && json.footer) { json.footer.forEach(function(cell, idx) { $('#rr-' + rrTab + ' tfoot td').eq(idx).html(cell); }); }
        rrSyncExport();
    });
    rrSyncExport();
});

// Kelompok Proyeksi Piutang bisa dibuka/ditutup.
$(document).on('click', '.rr-group-head', function() {
    $(this).closest('tbody').find('.rr-group-item').toggle();
    $(this).find('.rr-chev').toggleClass('fa-chevron-down fa-chevron-right');
});
</script>
@endsection
