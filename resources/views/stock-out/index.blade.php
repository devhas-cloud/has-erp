@extends('layouts.app')

@section('title', 'Pemenuhan dari Stok')
@section('page-title', 'Pemenuhan dari Stok')

@section('styles')
@include('partials.list-toolbar')
<style>
    .so-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
    .so-stat { border: 1px solid var(--border-color, #e5e7eb); border-radius: 10px; padding: 10px 12px; background: var(--card-bg, #fff); cursor: pointer; text-align: left; transition: border-color .15s, background .15s; }
    .so-stat:hover { border-color: var(--accent); }
    .so-stat.active { border-color: var(--accent); background: var(--accent-subtle, #ecfdf5); }
    .so-stat .so-stat-num { font-size: 20px; font-weight: 700; line-height: 1.1; }
    .so-stat .so-stat-label { font-size: 12px; color: var(--text-muted); }
    .so-stat .so-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
    @media (max-width: 767.98px) { .so-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

    .so-table th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    .so-group td { background: var(--bg-soft, #f8fafc) !important; border-top: 2px solid var(--border-color, #e5e7eb); padding-top: 8px !important; padding-bottom: 8px !important; }
    .so-group .so-req { font-weight: 600; font-size: 13px; }
    .so-group .so-meta { font-size: 12px; color: var(--text-muted); }
    .so-group .so-meta > span + span::before { content: '·'; margin: 0 6px; }
    .so-type { font-size: 10px; font-weight: 600; padding: 3px 7px; border-radius: 999px; }
    .so-type-project { background: #fef3c7; color: #92400e; }
    .so-type-internal { background: var(--info-soft, #dbeafe); color: #1e40af; }
    .so-item td { padding-top: 10px !important; padding-bottom: 10px !important; }
    .so-need { min-width: 170px; }
    .so-need .so-bar { height: 6px; border-radius: 999px; background: #e5e7eb; overflow: hidden; display: flex; margin: 4px 0 3px; }
    .so-need .so-bar span { display: block; height: 100%; }
    .so-need .so-legend { font-size: 11px; color: var(--text-muted); }
    .so-remaining { font-size: 15px; font-weight: 700; }
    .so-cov { font-size: 10.5px; font-weight: 600; padding: 3px 8px; border-radius: 999px; white-space: nowrap; }
    .so-cov-full { background: #d1fae5; color: #065f46; }
    .so-cov-partial { background: #fef3c7; color: #92400e; }
    .so-cov-none { background: #f3f4f6; color: #6b7280; }
    .so-wh { display: inline-block; font-size: 11px; border: 1px solid #a7f3d0; background: #ecfdf5; color: #065f46; border-radius: 6px; padding: 1px 6px; margin: 2px 3px 0 0; white-space: nowrap; }
    .so-wh.dim { opacity: .45; }
    .so-count { font-size: 12px; color: var(--text-muted); padding: 0 6px 6px; }
</style>
@endsection

@php
    $pendingMap = [];
    $groups = [];
    $stats = ['all' => 0, 'full' => 0, 'partial' => 0, 'none' => 0];
    $divisionOptions = [];
    $warehouseOptions = [];
    foreach ($pending as $p) {
        $pendingMap[$p['goods_request_item_id']] = $p;
        $groups[$p['goods_request_id']][] = $p;
        $coverage = $p['free_qty'] <= 0 ? 'none' : ($p['free_qty'] >= $p['remaining_qty'] ? 'full' : 'partial');
        $stats['all']++;
        $stats[$coverage]++;
        if ($p['division_id']) $divisionOptions[$p['division_id']] = $p['division_name'];
        foreach ($p['stock'] as $w) $warehouseOptions[$w['id']] = $w['code'].' — '.$w['name'];
    }
    asort($divisionOptions);
    asort($warehouseOptions);
    $coverageLabels = ['full' => 'Stok cukup', 'partial' => 'Stok sebagian', 'none' => 'Tanpa stok'];
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Pemenuhan dari Stok</h1>
        <p class="page-header-sub">Penuhi item Permintaan Barang yang sudah Approved dari stok bebas. Boleh sebagian. <strong>Internal</strong>: barang keluar dari gudang. <strong>Project</strong>: stok dialokasikan menjadi milik project (boleh tetap di gudang yang sama).</p>
    </div>
</div>

<div class="so-stats fade-in" id="so-stats">
    <button type="button" class="so-stat" data-coverage="">
        <div class="so-stat-num">{{ $stats['all'] }}</div>
        <div class="so-stat-label"><span class="so-dot" style="background:var(--accent)"></span>Semua item menunggu</div>
    </button>
    <button type="button" class="so-stat" data-coverage="full">
        <div class="so-stat-num" style="color:#065f46">{{ $stats['full'] }}</div>
        <div class="so-stat-label"><span class="so-dot" style="background:#10b981"></span>Stok cukup — bisa dipenuhi penuh</div>
    </button>
    <button type="button" class="so-stat" data-coverage="partial">
        <div class="so-stat-num" style="color:#92400e">{{ $stats['partial'] }}</div>
        <div class="so-stat-label"><span class="so-dot" style="background:#f59e0b"></span>Stok sebagian</div>
    </button>
    <button type="button" class="so-stat" data-coverage="none">
        <div class="so-stat-num" style="color:#6b7280">{{ $stats['none'] }}</div>
        <div class="so-stat-label"><span class="so-dot" style="background:#9ca3af"></span>Tanpa stok bebas (perlu PO)</div>
    </button>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-right-from-bracket me-2" style="color:var(--accent)"></i>Item Permintaan dengan Sisa Kebutuhan</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="so-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari barang, part number, no. permintaan, project…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="coverage" aria-label="Ketersediaan stok">
                <option value="">Semua ketersediaan</option>
                @foreach($coverageLabels as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <select class="form-select" data-filter="type" aria-label="Jenis permintaan">
                <option value="">Semua jenis</option>
                <option value="internal">Internal (barang keluar)</option>
                <option value="project">Project (alokasi)</option>
            </select>
            <select class="form-select" data-filter="warehouse" data-searchable aria-label="Gudang">
                <option value="">Semua gudang</option>
                @foreach($warehouseOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
            <select class="form-select" data-filter="division" data-searchable aria-label="Divisi">
                <option value="">Semua divisi</option>
                @foreach($divisionOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="so-count" id="so-count"></div>

        <div class="table-responsive">
            <table class="table table-custom so-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th class="so-need">Kebutuhan</th>
                        <th class="text-center" style="width:90px">Sisa</th>
                        <th>Stok Bebas Tersedia</th>
                        <th class="text-center" style="width:130px">Aksi</th>
                    </tr>
                </thead>
                <tbody id="so-body">
                    @forelse($groups as $requestId => $items)
                        @php $head = $items[0]; @endphp
                        <tr class="so-group" data-group="{{ $requestId }}">
                            <td colspan="5">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="so-type {{ $head['for_project'] ? 'so-type-project' : 'so-type-internal' }}">{{ $head['for_project'] ? 'Project' : 'Internal' }}</span>
                                    <a href="{{ $head['request_url'] }}" target="_blank" class="so-req" title="Buka Permintaan Barang">{{ $head['request_number'] }} <i class="fa fa-arrow-up-right-from-square" style="font-size:10px"></i></a>
                                    <span class="so-meta">
                                        <span>{{ $head['project_name'] ?: '—' }}</span>
                                        @if($head['division_name'])<span>{{ $head['division_name'] }}</span>@endif
                                        @if($head['request_date'])<span>{{ $head['request_date'] }}</span>@endif
                                        <span>{{ count($items) }} item</span>
                                    </span>
                                </div>
                            </td>
                        </tr>
                        @foreach($items as $p)
                            @php
                                $coverage = $p['free_qty'] <= 0 ? 'none' : ($p['free_qty'] >= $p['remaining_qty'] ? 'full' : 'partial');
                                $productLabel = $p['product_name'] ?: $p['description'];
                                $unit = $p['unit'] ?: '';
                                $req = max(1, $p['requested_qty']);
                                $doneStock = min(100, $p['from_stock_qty'] / $req * 100);
                                $donePo = min(100 - $doneStock, ($p['ordered_qty'] + $p['closed_qty']) / $req * 100);
                                $search = mb_strtolower(implode(' ', array_filter([$productLabel, $p['description'], $p['part_number'], $p['request_number'], $p['project_name'], $p['division_name']])));
                            @endphp
                            <tr class="so-item"
                                data-group="{{ $p['goods_request_id'] }}"
                                data-coverage="{{ $coverage }}"
                                data-type="{{ $p['for_project'] ? 'project' : 'internal' }}"
                                data-division="{{ $p['division_id'] }}"
                                data-warehouses="{{ implode(',', array_column($p['stock'], 'id')) }}"
                                data-search="{{ $search }}">
                                <td>
                                    <div class="fw-semibold" style="font-size:13px">{{ $productLabel ?: '—' }}</div>
                                    @if($p['part_number'])<div style="font-size:11px;color:var(--text-muted)">PN {{ $p['part_number'] }}</div>@endif
                                    @if($p['product_name'] && $p['description'] && $p['description'] !== $p['product_name'])<div style="font-size:11px;color:var(--text-muted)">{{ \Illuminate\Support\Str::limit($p['description'], 80) }}</div>@endif
                                </td>
                                <td class="so-need">
                                    <div style="font-size:12.5px">Diminta <strong>{{ $p['requested_qty'] }}</strong> {{ $unit }}</div>
                                    <div class="so-bar" title="Hijau = sudah dari stok, biru = PO / ditutup">
                                        <span style="width:{{ $doneStock }}%;background:#10b981"></span>
                                        <span style="width:{{ $donePo }}%;background:#3b82f6"></span>
                                    </div>
                                    <div class="so-legend">
                                        @if($p['from_stock_qty'])Stok {{ $p['from_stock_qty'] }}@endif
                                        @if($p['ordered_qty']){{ $p['from_stock_qty'] ? ' · ' : '' }}PO {{ $p['ordered_qty'] }}@endif
                                        @if($p['closed_qty']){{ ($p['from_stock_qty'] || $p['ordered_qty']) ? ' · ' : '' }}Ditutup {{ $p['closed_qty'] }}@endif
                                        @if(! $p['from_stock_qty'] && ! $p['ordered_qty'] && ! $p['closed_qty'])Belum terpenuhi @endif
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="so-remaining">{{ $p['remaining_qty'] }}</div>
                                    <div style="font-size:11px;color:var(--text-muted)">{{ $unit ?: 'qty' }}</div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="so-cov so-cov-{{ $coverage }}">{{ $coverageLabels[$coverage] }}</span>
                                        @if($p['free_qty'] > 0)<span style="font-size:12px">Total <strong>{{ \App\Support\StockCost::qty($p['free_qty']) }}</strong></span>@endif
                                    </div>
                                    @foreach($p['stock'] as $w)
                                        <span class="so-wh" data-wh="{{ $w['id'] }}" title="{{ $w['name'] }}">{{ $w['code'] }}: <strong>{{ $w['balance_label'] }}</strong></span>
                                    @endforeach
                                    @if($coverage === 'none')<div style="font-size:11px;color:var(--text-muted)">Penuhi lewat Purchase Order.</div>@endif
                                </td>
                                <td class="text-center">
                                    @if($p['free_qty'] > 0 && $canCreate)
                                        <button type="button" class="btn btn-primary btn-sm" data-item-id="{{ $p['goods_request_item_id'] }}" onclick="openIssueModal(this)" title="{{ $p['for_project'] ? 'Alokasikan stok menjadi milik project' : 'Keluarkan barang dari gudang' }}">
                                            <i class="fa {{ $p['for_project'] ? 'fa-arrow-right-arrow-left' : 'fa-right-from-bracket' }} me-1"></i> {{ $p['for_project'] ? 'Alokasikan' : 'Keluarkan' }}
                                        </button>
                                    @else
                                        <span class="text-muted" style="font-size:12px">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4" style="color:var(--text-muted)">
                                <i class="fa-solid fa-inbox me-1"></i> Tidak ada item Permintaan Barang Approved dengan sisa kebutuhan.
                            </td>
                        </tr>
                    @endforelse
                    <tr id="so-noresult" style="display:none">
                        <td colspan="5" class="text-center py-4" style="color:var(--text-muted)">
                            <i class="fa-solid fa-filter-circle-xmark me-1"></i> Tidak ada item yang cocok dengan pencarian / filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="issueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i><span id="issue-title">Penuhi dari Stok</span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="issue-product" class="mb-3 fw-semibold" style="font-size:14px"></div>
                <div class="mb-3">
                    <label class="form-label">Gudang Asal (stok bebas) <span class="text-danger">*</span></label>
                    <select id="issue-warehouse" class="form-select"></select>
                </div>
                <div class="mb-3" id="issue-destination-wrap">
                    <label class="form-label">Gudang Tujuan Alokasi</label>
                    <select id="issue-destination" class="form-select">
                        <option value="">Sama dengan gudang asal</option>
                        @foreach($allWarehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                        @endforeach
                    </select>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px">Barang menjadi stok milik project di gudang ini sampai dikirim lewat Delivery Order.</div>
                </div>
                <label class="form-label">Pilih Baris Stok <span class="text-danger">*</span></label>
                <div id="issue-picker" style="border:1px solid var(--border-color, #e5e7eb);border-radius:8px;padding:8px">
                    <div class="text-center py-3" style="color:var(--text-muted);font-size:13px">Pilih gudang asal dulu.</div>
                </div>
                <div id="issue-available" style="font-size:12px;color:var(--text-muted);margin-top:4px"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="issue-submit" onclick="submitIssue()">
                    <i class="fa fa-check me-1"></i> Proses
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
@include('partials.stock-pick')
<script>
const pendingMap = @json($pendingMap);
const stockOutStoreUrl = '{{ route("stock-out.store") }}';
const stockOutOptionsUrl = '{{ route("stock-out.stock-options") }}';
let issueModalInstance = null;
let issueItemId = null;

function openIssueModal(btn) {
    issueItemId = $(btn).data('item-id');
    var item = pendingMap[issueItemId];
    if (!item) return;

    $('#issue-product').text((item.product_name || item.description || '') + (item.part_number ? ' (' + item.part_number + ')' : '') + ' — sisa ' + item.remaining_qty + ' ' + (item.unit || ''));

    var opts = '<option value="">— Pilih Gudang —</option>';
    item.stock.forEach(function(w) {
        opts += '<option value="' + w.id + '">' + w.code + ' — ' + w.name + ' (stok ' + w.balance_label + ')</option>';
    });
    $('#issue-warehouse').html(opts);
    $('#issue-picker').html('<div class="text-center py-3" style="color:var(--text-muted);font-size:13px">Pilih gudang asal dulu.</div>');
    $('#issue-available').text('Sisa kebutuhan: ' + item.remaining_qty + ' ' + (item.unit || ''));
    $('#issue-title').text(item.for_project ? 'Alokasikan Stok ke Project' : 'Keluarkan Stok (Internal)');
    $('#issue-destination-wrap').toggle(!!item.for_project);
    $('#issue-destination').val('');

    if (!issueModalInstance) issueModalInstance = new bootstrap.Modal(document.getElementById('issueModal'));
    issueModalInstance.show();
}

$(document).on('change', '#issue-warehouse', function() {
    var item = pendingMap[issueItemId] || {};
    if (!$(this).val()) return;
    // Internal = barang keluar: unit ber-SN wajib sudah ber-SN. Alokasi project boleh tanpa SN.
    StockPick.load($('#issue-picker'), stockOutOptionsUrl, { goods_request_item_id: issueItemId, warehouse_id: $(this).val() }, {
        requireSerial: !item.for_project,
        onChange: function(total) {
            $('#issue-available').html('Dipilih ' + Num.format(total, 4) + ' dari sisa kebutuhan ' + item.remaining_qty + ' ' + (item.unit || '')
                + (total > item.remaining_qty ? ' <span class="text-danger">— melebihi sisa</span>' : ''));
        }
    });
});

// ── Filter daftar (data sudah lengkap di halaman, disaring di browser) ──
const SO_STATE_KEY = 'stock-out-filter-v1';
let soSearch = '';
let soToolbar = null;

function soFilters() {
    return soToolbar ? soToolbar.params({}) : {};
}

function soRender() {
    var f = soFilters();
    var terms = soSearch.toLowerCase().split(/\s+/).filter(Boolean);
    var shown = 0, total = 0, groupsShown = {};

    $('#so-body tr.so-item').each(function() {
        var $r = $(this);
        total++;
        var whs = String($r.data('warehouses') || '').split(',');
        var hay = String($r.data('search') || '');
        var ok = (!f.coverage || $r.data('coverage') === f.coverage)
            && (!f.type || $r.data('type') === f.type)
            && (!f.division || String($r.data('division')) === f.division)
            && (!f.warehouse || whs.indexOf(f.warehouse) !== -1)
            && terms.every(function(t) { return hay.indexOf(t) !== -1; });
        $r.toggle(ok);
        // Gudang terpilih ditonjolkan, gudang lain diredupkan.
        $r.find('.so-wh').each(function() { $(this).toggleClass('dim', !!f.warehouse && String($(this).data('wh')) !== f.warehouse); });
        if (ok) { shown++; groupsShown[$r.data('group')] = true; }
    });
    $('#so-body tr.so-group').each(function() { $(this).toggle(!!groupsShown[$(this).data('group')]); });
    $('#so-noresult').toggle(total > 0 && shown === 0);
    $('#so-count').text(total ? (shown === total ? total + ' item dari ' + Object.keys(groupsShown).length + ' permintaan' : 'Menampilkan ' + shown + ' dari ' + total + ' item') : '');
    $('#so-stats .so-stat').each(function() { $(this).toggleClass('active', ($(this).data('coverage') || '') === (f.coverage || '')); });

    try { localStorage.setItem(SO_STATE_KEY, JSON.stringify({ q: soSearch, f: f })); } catch (e) {}
}

// Adapter agar ListToolbar (dibuat untuk DataTables) bisa memicu filter lokal.
const soTableAdapter = {
    search: function(v) { soSearch = v || ''; return soTableAdapter; },
    draw: function() { soRender(); return soTableAdapter; },
    ajax: { reload: function() { soRender(); } }
};

$(function() {
    try {
        var saved = JSON.parse(localStorage.getItem(SO_STATE_KEY) || 'null');
        if (saved) {
            soSearch = saved.q || '';
            $('#so-toolbar [data-search]').val(soSearch);
            $.each(saved.f || {}, function(k, v) {
                var $el = $('#so-toolbar [data-filter="' + k + '"]');
                if (v && $el.find('option[value="' + v + '"]').length) $el.val(v);
            });
        }
    } catch (e) {}

    soToolbar = ListToolbar('#so-toolbar', function() { return soTableAdapter; });
    soRender();

    $('#so-stats').on('click', '.so-stat', function() {
        $('#so-toolbar [data-filter="coverage"]').val($(this).data('coverage') || '').trigger('change');
    });
});

function submitIssue() {
    var warehouseId = $('#issue-warehouse').val();
    var picks = StockPick.picks($('#issue-picker'));
    var qty = StockPick.total($('#issue-picker'));

    var item = pendingMap[issueItemId] || {};
    var destinationId = $('#issue-destination').val();
    if (!warehouseId) { toastr.error('Pilih gudang asal.'); return; }
    if (!picks.length) { toastr.error('Pilih minimal satu baris stok.'); return; }
    if (qty > item.remaining_qty) { toastr.error('Qty melebihi sisa kebutuhan (' + item.remaining_qty + ').'); return; }

    $('#issue-submit').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Proses...');

    $.ajax({
        url: stockOutStoreUrl,
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        data: JSON.stringify({
            goods_request_item_id: issueItemId,
            warehouse_id: warehouseId,
            destination_warehouse_id: item.for_project && destinationId ? destinationId : null,
            picks: picks
        })
    }).done(function(res) {
        toastr.success(res.message || 'Berhasil.');
        setTimeout(function() { window.location.reload(); }, 800);
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memproses.');
    }).always(function() {
        $('#issue-submit').prop('disabled', false).html('<i class="fa fa-check me-1"></i> Proses');
    });
}
</script>
@endsection
