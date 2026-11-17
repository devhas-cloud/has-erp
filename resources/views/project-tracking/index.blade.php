@extends('layouts.app')

@section('title', 'Project Tracking')
@section('page-title', 'Project Tracking')

@section('styles')
@include('partials.list-toolbar')
<style>
    .pt-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: #d1d5db; vertical-align: middle; }
    .pt-dot.done { background: #2f7a4b; }
    .pt-dot.run { background: #d97706; }
    .pt-dot.late { background: #b42318; }
    .pt-dot.na { background: transparent; border: 1.5px dashed #9ca3af; }
    .pt-dots { white-space: nowrap; }
    .pt-dots .pt-dot { margin-right: 5px; }
    .pt-legend { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: var(--text-muted); }
    .pt-legend span { display: inline-flex; align-items: center; gap: 5px; }
    .pt-flag { display: inline-block; font-size: 11px; font-weight: 600; padding: 1px 8px; border-radius: 999px; margin: 1px 3px 1px 0; white-space: nowrap; }
    .pt-flag.danger { background: var(--danger-soft); color: #b42318; }
    .pt-flag.warn { background: var(--warning-soft, #fef3c7); color: #92400e; }
    .pt-bar { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; min-width: 80px; position: relative; }
    .pt-bar span { position: absolute; left: 0; top: 0; height: 100%; }
    .pt-bar .billed { background: color-mix(in srgb, var(--accent) 40%, transparent); }
    .pt-bar .paid { background: var(--accent); }
    .pt-kpi { border: 1px solid var(--card-border); border-radius: var(--radius-sm); padding: 12px 14px; background: var(--card, #fff); height: 100%; }
    .pt-kpi .l { font-size: 12px; color: var(--text-muted); }
    .pt-kpi .v { font-size: 22px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.2; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Project Tracking</h1>
        <p class="page-header-sub">Proses setiap project dari opportunity sampai penagihan, dihitung dari dokumen yang ada</p>
    </div>
</div>

<div class="row g-2 mb-3">
    @foreach([
        ['Project aktif', $summary['active'], null],
        ['Menunggu pembelian', $summary['purchasing'], null],
        ['Tahap kirim (PKL / DO)', $summary['shipping'], null],
        ['Belum ditagih penuh', $summary['unbilled'], null],
        ['Lewat jatuh tempo', $summary['overdue'], '#b42318'],
    ] as [$label, $value, $color])
        <div class="col-6 col-md">
            <div class="pt-kpi"><div class="l">{{ $label }}</div><div class="v" @if($color && $value) style="color:{{ $color }}" @endif>{{ $value }}</div></div>
        </div>
    @endforeach
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-route me-2" style="color:var(--accent)"></i>Daftar Project</span>
        <div class="pt-legend">
            <span><i class="pt-dot done"></i>Selesai</span>
            <span><i class="pt-dot run"></i>Berjalan</span>
            <span><i class="pt-dot late"></i>Terlambat</span>
            <span><i class="pt-dot"></i>Belum mulai</span>
            <span><i class="pt-dot na"></i>Tidak dipakai</span>
        </div>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="pt-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. / nama project, customer…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" aria-label="Status project">
                <option value="active" selected>Project Active</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="">Semua Status</option>
            </select>
            <select class="form-select" data-filter="company" data-searchable aria-label="Customer">
                <option value="">Semua Customer</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}">{{ $c->account_name }}</option>
                @endforeach
            </select>
            <select class="form-select" data-filter="stage" aria-label="Tahap saat ini">
                <option value="">Semua Tahap</option>
                @foreach(\App\Support\ProjectTracker::STAGES as $n => $name)
                    @continue($n === 1)
                    <option value="{{ $n }}">Di tahap: {{ $name }}</option>
                @endforeach
                <option value="done">Semua tahap selesai</option>
            </select>
            <select class="form-select" data-filter="problem" aria-label="Peringatan">
                <option value="">Semua</option>
                <option value="1">Hanya yang bermasalah</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="pt-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th title="1 Project · 2 Pembelian · 3 Terima Barang · 4 Produksi · 5 Packing List · 6 Delivery Order · 7 Invoice">Tahap 1–7</th>
                        <th>Tahap Saat Ini</th>
                        <th>Jatuh Tempo</th>
                        <th style="width:140px">Ditagih / Diterima</th>
                        <th>Peringatan</th>
                    </tr>
                </thead>
            </table>
        </div>
        <div class="px-2 pt-2" style="font-size:12px;color:var(--text-muted)"><i class="fa fa-circle-info me-1"></i>Arahkan kursor ke titik tahap untuk ringkasannya. Peringatan "tertahan" memakai batas {{ $alertDays }} hari.</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let ptTable = null;
let ptToolbar = null;

function ptEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    // Reset toolbar mengosongkan semua filter -> status kosong = semua status.
    ptToolbar = ListToolbar('#pt-toolbar', function() { return ptTable; });
    ptTable = $('#pt-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Tidak ada project yang cocok dengan pencarian / filter.</span>',
            emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Tidak ada project.</span>'
        },
        processing: true,
        serverSide: false,
        pageLength: 25,
        ajax: { url: '{{ route("project-tracking.data") }}', data: function(d) { return ptToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'project_number', render: function(data, type, row) {
                if (type !== 'display') return data + ' ' + row.project_name + ' ' + (row.customer || '');
                return '<a href="' + row.url + '" class="fw-semibold text-decoration-none">' + ptEsc(data) + '</a>'
                    + '<div style="font-size:12px;color:var(--text-secondary)">' + ptEsc(row.project_name) + '</div>'
                    + '<div style="font-size:11.5px;color:var(--text-muted)">' + ptEsc(row.customer || '—') + '</div>';
            } },
            { data: 'stages', orderable: false, className: 'pt-dots', render: function(stages, type) {
                if (type !== 'display') return '';
                return stages.map(function(s) {
                    return '<i class="pt-dot ' + s.status + '" title="' + ptEsc(s.n + ' ' + s.name + ' — ' + s.status_label + ': ' + s.summary) + '"></i>';
                }).join('');
            } },
            { data: 'current_label', render: function(data, type, row) {
                if (type !== 'display') return data;
                return '<span class="fw-semibold">' + ptEsc(data) + '</span>' + (row.current_summary ? '<div style="font-size:11.5px;color:var(--text-muted)">' + ptEsc(row.current_summary) + '</div>' : '');
            } },
            { data: 'due_days', render: function(data, type, row) {
                if (type !== 'display') return data == null ? 999999 : data;
                if (!row.due_date) return '<span style="color:var(--text-muted)">—</span>';
                var note = data < 0 ? '<div style="font-size:11.5px;color:#b42318">lewat ' + Math.abs(data) + ' hari</div>' : '<div style="font-size:11.5px;color:var(--text-muted)">' + data + ' hari lagi</div>';
                return ptEsc(row.due_date) + note;
            } },
            { data: 'billed_pct', render: function(data, type, row) {
                if (type !== 'display') return data;
                return '<div class="pt-bar"><span class="billed" style="width:' + data + '%"></span><span class="paid" style="width:' + row.paid_pct + '%"></span></div>'
                    + '<span style="font-size:11.5px;color:var(--text-muted)">' + data + '% / ' + row.paid_pct + '%</span>';
            } },
            { data: 'alerts', orderable: false, render: function(alerts, type) {
                if (type !== 'display') return alerts.length;
                return alerts.map(function(a) { return '<span class="pt-flag ' + a[0] + '">' + ptEsc(a[1]) + '</span>'; }).join('');
            } }
        ]
    });
});
</script>
@endsection
