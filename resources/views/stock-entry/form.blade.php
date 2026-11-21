@extends('layouts.app')

@section('title', 'New Stock Entry')
@section('page-title', 'New Stock Entry')

@section('styles')
<style>
    .se-kind { font-size: 10px; font-weight: 600; padding: 1px 7px; border-radius: 999px; white-space: nowrap; }
    .se-kind-material { background: #e0f2fe; color: #075985; }
    .se-kind-serial { background: #fef3c7; color: #92400e; }
    .se-sub { font-size: 11.5px; color: var(--text-muted); }
    .se-desc { font-size: 11.5px; color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; max-width: 520px; }
    .se-cost-group { max-width: 200px; margin-left: auto; }
    .se-cost-group .input-group-text { font-size: 12px; color: var(--text-muted); }
    .se-unit { display: inline-block; min-width: 52px; padding: 4px 8px; border-radius: 6px; background: var(--bg, #f4f6fb); color: var(--text-secondary); font-size: 12.5px; text-align: center; }
    .se-balance { font-size: 11.5px; color: #075985; margin-top: 3px; }
    .se-balance.is-new { color: var(--text-muted); }
    .se-detail td { background: #fafbfc; border-top: 0 !important; }
    .se-items th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    .se-sum { display: grid; grid-template-columns: 1fr auto; gap: 8px 10px; font-size: 13px; }
    .se-sum dt { color: var(--text-muted); font-weight: 400; }
    .se-sum dd { margin: 0; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }
    .se-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; font-size: 12px; color: var(--text-muted); padding: 8px 6px 0; }

    /* Popup katalog — skin sama dengan picker IH / Purchase Request */
    .se-pp-toolbar { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1.2fr); gap: 8px; }
    @media (max-width: 767.98px) { .se-pp-toolbar { grid-template-columns: 1fr; } }
    .se-pp-search { position: relative; }
    .se-pp-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; }
    .se-pp-search input { padding-left: 30px; }
    .se-pp-wrap { border: 1px solid var(--card-border, #e5e7eb); border-radius: 6px; overflow: auto; max-height: 52vh; }
    .se-pp-wrap thead th { position: sticky; top: 0; z-index: 1; background: var(--card, #fff); font-size: 11.5px; text-transform: uppercase; color: var(--text-muted); }
    #se-pp-body tr { cursor: pointer; transition: background-color .15s ease; user-select: none; }
    #se-pp-body tr.is-selected, #se-pp-body tr.is-selected td { background-color: #ecfdf5 !important; }
    #se-pp-body tr:not(.is-empty):hover, #se-pp-body tr:not(.is-empty):hover td { background-color: #d1fae5 !important; color: #065f46; }
    #se-pp-body tr.is-empty { cursor: default; }
    .se-pp-check, #se-pp-check-all { width: 16px; height: 16px; cursor: pointer; }
    .se-pp-info { font-size: 12.5px; color: var(--text-muted); }
    .se-pp-badge { font-size: 11.5px; background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 999px; padding: 2px 9px; }
    .se-pp-existing { font-size: 10.5px; background: #fef3c7; color: #92400e; border-radius: 999px; padding: 2px 7px; white-space: nowrap; }
    .se-pp-chips { display: flex; flex-wrap: wrap; gap: 4px; max-height: 56px; overflow-y: auto; margin-bottom: 6px; }
    .se-pp-chip { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; padding: 1px 3px 1px 8px; border-radius: 999px; background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .se-pp-chip button { border: 0; background: transparent; color: inherit; line-height: 1; padding: 0 4px; }
    .se-pp-pager .page-link { font-size: 12.5px; padding: 3px 9px; }
    .se-pp-empty { text-align: center; padding: 28px 10px !important; color: var(--text-muted); font-size: 13px; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">New Stock Entry</h1>
        <p class="page-header-sub">For goods coming in without a PO / Direct Purchase (opening balance, returns, grants / samples). Purchases still go through Goods Receipt.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('stock-entry.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Information</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="se-warehouse">Warehouse <span class="text-danger">*</span></label>
                        <select id="se-warehouse" class="form-select">
                            <option value="">— Select Warehouse —</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="se-project">Ownership</label>
                        <select id="se-project" class="form-select">
                            <option value="">Free stock</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}">Project {{ $p->project_number }} — {{ $p->project_name }}</option>
                            @endforeach
                        </select>
                        <small style="color:var(--text-muted)">Choose a project if the goods belong to it (e.g. a project return).</small>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="se-reason">Reason <span class="text-danger">*</span></label>
                        <select id="se-reason" class="form-select">
                            @foreach(\App\Models\StockEntry::REASON_LABELS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="se-date">Date</label>
                        <input type="date" id="se-date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label" for="se-note">Description <span class="text-danger" id="se-note-required" style="display:none">*</span></label>
                        <input type="text" id="se-note" class="form-control" maxlength="1000" placeholder="e.g. returned from the Gresik site">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Incoming Items</span>
                <button type="button" class="btn btn-soft btn-sm" onclick="seOpenPicker()"><i class="fa fa-plus me-1"></i> Add from Catalog</button>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0 se-items">
                        <thead>
                            <tr>
                                <th style="width:45px" class="text-center">#</th>
                                <th>Product</th>
                                <th class="text-center" style="width:120px">Qty</th>
                                <th class="text-center" style="width:90px">Unit</th>
                                <th class="text-end" style="width:200px">Unit Cost</th>
                                <th class="text-end" style="width:140px">Value</th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="se-items">
                            <tr class="se-empty"><td colspan="7" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> No items yet. Click "Add from Catalog".</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="se-legend">
                    <span><span class="se-kind se-kind-material">Material</span> added to the product balance in this warehouse &amp; ownership; the unit cost moves the weighted average</span>
                    <span><span class="se-kind se-kind-serial">Serial</span> one stock row per unit; SNs can be filled in later</span>
                    <span><i class="fa fa-lock me-1"></i>Unit follows the master product</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in" style="position:sticky;top:80px">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Summary</span>
            </div>
            <div class="card-body-custom">
                <dl class="se-sum mb-0">
                    <dt>Lines</dt><dd id="se-sum-lines">0</dd>
                    <dt style="padding-left:10px">· Material</dt><dd id="se-sum-material" style="font-weight:500">0</dd>
                    <dt style="padding-left:10px">· SN units</dt><dd id="se-sum-serial" style="font-weight:500">0</dd>
                    <dt>Total value</dt><dd id="se-sum-value">0</dd>
                </dl>
                @if($canCreate)
                <button type="button" class="btn btn-primary w-100 mt-3" id="se-save" onclick="seSave()">
                    <i class="fa fa-check me-1"></i> Post Stock Entry
                </button>
                @endif
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Posted immediately and cannot be edited. Unit cost defaults to the master standard cost. Serial units can get their SNs later in <a href="{{ route('stock-detail.index') }}">Complete Stock Data</a>; an SN that left before (customer return) goes back to its own history.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="sePickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-box-open me-2" style="color:var(--accent)"></i>Add from Catalog</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="se-pp-toolbar mb-2">
                    <div class="se-pp-search">
                        <i class="fa fa-magnifying-glass"></i>
                        <input type="text" class="form-control form-control-sm" id="se-pp-q" placeholder="Search code, name, brand or category…" autocomplete="off">
                    </div>
                    <select id="se-pp-kind" class="form-select form-select-sm" aria-label="Item type">
                        <option value="">All item types</option>
                        <option value="material">Material</option>
                        <option value="serial">Serial items</option>
                    </select>
                    <select id="se-pp-category" class="form-select form-select-sm" aria-label="Category">
                        <option value="">All categories</option>
                    </select>
                </div>
                <div class="se-pp-chips" id="se-pp-chips" style="display:none"></div>
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <span class="se-pp-info" id="se-pp-info">Loading…</span>
                    <div class="d-flex align-items-center gap-2">
                        <span class="se-pp-badge" id="se-pp-selected">0 selected</span>
                        <button type="button" class="btn btn-link btn-sm p-0" id="se-pp-clear" style="display:none">Clear selection</button>
                    </div>
                </div>
                <div class="se-pp-wrap">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px" class="text-center"><input type="checkbox" id="se-pp-check-all" title="Select all on this page"></th>
                                <th style="width:150px">Code</th>
                                <th>Name</th>
                                <th style="width:140px">Type / Unit</th>
                                <th style="width:150px" class="text-end">Standard Cost</th>
                            </tr>
                        </thead>
                        <tbody id="se-pp-body"></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2 se-pp-info">
                        Show
                        <select id="se-pp-per-page" class="form-select form-select-sm" style="width:auto">
                            <option value="10">10</option>
                            <option value="15" selected>15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        per page
                    </div>
                    <nav><ul class="pagination pagination-sm mb-0 se-pp-pager" id="se-pp-pager"></ul></nav>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <small style="color:var(--text-muted);font-size:12px"><i class="fa fa-circle-info me-1"></i>Click a row to select · Shift+click for a range · Enter adds the selection · a material already listed gets +1 qty</small>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="se-pp-apply" disabled>
                        <i class="fa fa-plus me-1"></i> Add <span id="se-pp-apply-count">0</span> Item(s)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
const seSearchUrl = '{{ route("stock-entry.search-products") }}';
const seBalancesUrl = '{{ route("stock-entry.balances") }}';
const seStoreUrl = '{{ route("stock-entry.store") }}';
const seShowUrl = '{{ route("stock-entry.show", "__ID__") }}';
const seReasonOther = '{{ \App\Models\StockEntry::REASON_OTHER }}';
const seCurrency = @json($baseSymbol);
const seSupplierOptions = @json($suppliers->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values());
let seRowSeq = 0;
let sePickerInstance = null;
let seBalanceXhr = null;

function seEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function seFmt(n) { return Num.format(n, 2); }
function seMoney(n) { return seEsc(seCurrency) + ' ' + seFmt(n); }
// "1,250,000.5" -> 1250000.5; kosong -> null (server memakai harga modal standar master).
function seParse(v) { return Num.parseOrNull(v); }

/* ── Popup katalog: cari langsung, filter, per halaman, pilih banyak ── */
let sePage = 1, seRows = [], sePicked = {}, sePickOrder = [], sePickLast = null, seSearchTimer = null, seSearchXhr = null, seCategoriesLoaded = false;

function seListedIds() { return $('#se-items tr[data-product-id]').map(function() { return String($(this).data('product-id')); }).get(); }

function seOpenPicker() {
    sePicked = {}; sePickOrder = [];
    $('#se-pp-q').val('');
    if (!sePickerInstance) sePickerInstance = new bootstrap.Modal(document.getElementById('sePickerModal'));
    sePickerInstance.show();
    seSearchProducts(1);
}
$(document).on('shown.bs.modal', '#sePickerModal', function() { $('#se-pp-q').trigger('focus'); });

function sePickSync() {
    var n = sePickOrder.length;
    $('#se-pp-selected').text(n + ' selected');
    $('#se-pp-clear').toggle(n > 0);
    $('#se-pp-apply-count').text(n);
    $('#se-pp-apply').prop('disabled', n === 0);
    $('#se-pp-chips').html(sePickOrder.map(function(id) {
        return '<span class="se-pp-chip">' + seEsc(sePicked[id].code) + '<button type="button" data-unpick="' + id + '" title="Remove">&times;</button></span>';
    }).join('')).toggle(n > 0);
    var $checks = $('#se-pp-body .se-pp-check');
    $('#se-pp-check-all').prop('checked', $checks.length > 0 && $checks.filter(':checked').length === $checks.length);
}

function seSetPicked(idx, on) {
    var p = seRows[idx];
    if (!p) return;
    var key = String(p.id);
    if (on && !sePicked[key]) { sePicked[key] = p; sePickOrder.push(key); }
    if (!on && sePicked[key]) { delete sePicked[key]; sePickOrder = sePickOrder.filter(function(x) { return x !== key; }); }
    $('#se-pp-body tr[data-idx="' + idx + '"]').toggleClass('is-selected', on).find('.se-pp-check').prop('checked', on);
}

function seSearchProducts(page) {
    sePage = page || 1;
    sePickLast = null;
    if (seSearchXhr) seSearchXhr.abort();
    $('#se-pp-body').html('<tr class="is-empty"><td colspan="5" class="se-pp-empty"><span class="spinner-border spinner-border-sm me-1"></span>Loading…</td></tr>');
    seSearchXhr = $.getJSON(seSearchUrl, {
        q: $('#se-pp-q').val().trim(), kind: $('#se-pp-kind').val(), category: $('#se-pp-category').val(),
        per_page: $('#se-pp-per-page').val(), page: sePage, with_categories: seCategoriesLoaded ? 0 : 1
    }).done(function(res) {
        seRows = res.data || [];
        if (!seCategoriesLoaded && res.categories) {
            $('#se-pp-category').html('<option value="">All categories</option>' + res.categories.map(function(c) { return '<option value="' + seEsc(c) + '">' + seEsc(c) + '</option>'; }).join(''));
            seCategoriesLoaded = true;
        }
        var listed = seListedIds();
        if (!seRows.length) {
            $('#se-pp-body').html('<tr class="is-empty"><td colspan="5" class="se-pp-empty"><i class="fa-solid fa-inbox me-1"></i>No products found. Try other keywords or filters.</td></tr>');
        } else {
            $('#se-pp-body').html(seRows.map(function(p, idx) {
                var picked = !!sePicked[String(p.id)], inList = listed.indexOf(String(p.id)) !== -1;
                return '<tr data-idx="' + idx + '"' + (picked ? ' class="is-selected"' : '') + '>'
                    + '<td class="text-center"><input type="checkbox" class="se-pp-check"' + (picked ? ' checked' : '') + ' aria-label="Select"></td>'
                    + '<td><code style="font-size:12.5px">' + seEsc(p.code) + '</code>'
                        + (inList ? '<div><span class="se-pp-existing">' + (p.serialized ? 'Already listed' : 'Listed — qty +1') + '</span></div>' : '') + '</td>'
                    + '<td><div style="font-weight:600;font-size:13px">' + (p.name ? seEsc(p.name) : (p.description ? seEsc(p.description) : '<span class="se-sub">(no name)</span>')) + '</div>'
                        + (p.name && p.description ? '<div class="se-desc">' + seEsc(p.description) + '</div>' : '')
                        + ((p.brand || p.category) ? '<div class="se-sub">' + seEsc([p.brand, p.category].filter(Boolean).join(' · ')) + '</div>' : '') + '</td>'
                    + '<td><span class="se-kind ' + (p.serialized ? 'se-kind-serial">Serial' : 'se-kind-material">Material') + '</span>'
                        + '<div class="se-sub" style="margin-top:3px">' + seEsc(p.unit || 'no unit') + '</div></td>'
                    + '<td class="text-end" style="font-variant-numeric:tabular-nums">' + (p.cost == null ? '—' : seMoney(p.cost)) + '</td>'
                    + '</tr>';
            }).join(''));
        }
        var m = res.meta || {};
        $('#se-pp-info').text(m.total ? 'Showing ' + m.from + '–' + m.to + ' of ' + m.total + ' products' : '0 products');
        sePager(m);
        sePickSync();
    }).fail(function(xhr) {
        if (xhr.statusText === 'abort') return;
        $('#se-pp-body').html('<tr class="is-empty"><td colspan="5" class="se-pp-empty"><i class="fa-solid fa-triangle-exclamation me-1"></i>Failed to load products.</td></tr>');
    });
}

function sePager(m) {
    var last = m.last_page || 1, cur = m.current_page || 1;
    if (last <= 1) { $('#se-pp-pager').html(''); return; }
    var li = function(p, label, disabled, active) {
        return '<li class="page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '"><a class="page-link" href="#" data-page="' + p + '">' + label + '</a></li>';
    };
    var html = li(cur - 1, '&laquo;', cur === 1), from = Math.max(1, cur - 2), to = Math.min(last, cur + 2);
    if (from > 1) html += li(1, '1') + (from > 2 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '');
    for (var p = from; p <= to; p++) html += li(p, p, false, p === cur);
    if (to < last) html += (to < last - 1 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '') + li(last, last);
    $('#se-pp-pager').html(html + li(cur + 1, '&raquo;', cur === last));
}

$(document).on('input', '#se-pp-q', function() { clearTimeout(seSearchTimer); seSearchTimer = setTimeout(function() { seSearchProducts(1); }, 300); });
$(document).on('keydown', '#se-pp-q', function(e) { if (e.key === 'Enter') { e.preventDefault(); if (sePickOrder.length) seApplyPicked(); } });
$(document).on('change', '#se-pp-kind, #se-pp-category, #se-pp-per-page', function() { seSearchProducts(1); });
$(document).on('click', '#se-pp-pager a[data-page]', function(e) {
    e.preventDefault();
    if ($(this).parent().is('.disabled, .active')) return;
    seSearchProducts(parseInt($(this).data('page'), 10));
});
$(document).on('click', '#se-pp-body tr[data-idx]', function(e) {
    var idx = parseInt($(this).data('idx'), 10);
    var on = $(e.target).is('.se-pp-check') ? e.target.checked : !sePicked[String(seRows[idx].id)];
    if (e.shiftKey && sePickLast !== null) {
        var a = Math.min(sePickLast, idx), b = Math.max(sePickLast, idx);
        for (var i = a; i <= b; i++) seSetPicked(i, on);
        if (window.getSelection) window.getSelection().removeAllRanges();
    } else {
        seSetPicked(idx, on);
    }
    sePickLast = idx;
    sePickSync();
});
$(document).on('change', '#se-pp-check-all', function() {
    var on = this.checked;
    seRows.forEach(function(p, idx) { seSetPicked(idx, on); });
    sePickSync();
});
$(document).on('click', '#se-pp-clear', function() {
    sePicked = {}; sePickOrder = [];
    $('#se-pp-body tr').removeClass('is-selected').find('.se-pp-check').prop('checked', false);
    sePickSync();
});
$(document).on('click', '#se-pp-chips [data-unpick]', function() {
    var id = String($(this).data('unpick'));
    delete sePicked[id];
    sePickOrder = sePickOrder.filter(function(x) { return x !== id; });
    seRows.forEach(function(p, idx) { if (String(p.id) === id) $('#se-pp-body tr[data-idx="' + idx + '"]').removeClass('is-selected').find('.se-pp-check').prop('checked', false); });
    sePickSync();
});
$(document).on('click', '#se-pp-apply', function() { seApplyPicked(); });

function seApplyPicked() {
    var added = 0, bumped = 0;
    sePickOrder.forEach(function(id) { if (seAddProduct(sePicked[id])) added++; else bumped++; });
    sePickerInstance.hide();
    seRenumber();
    seLoadBalances();
    toastr.success(added + ' item(s) added' + (bumped ? ', ' + bumped + ' existing material line(s) +1 qty' : '') + '. Check qty & unit cost.');
}

/** Tambah satu produk ke daftar. Material yang sudah ada: qty +1 (melebur ke satu saldo). Return true bila baris baru. */
function seAddProduct(p) {
    var $existing = $('#se-items tr[data-product-id="' + p.id + '"]').first();
    if (!p.serialized && $existing.length) {
        var $q = $existing.find('.se-qty');
        $q.val(Num.format((Num.parseOrNull($q.val()) || 0) + 1, 4)).trigger('input');
        return false;
    }

    $('#se-items .se-empty').remove();
    var key = ++seRowSeq;
    var html = '<tr data-product-id="' + p.id + '" data-row="' + key + '" data-serialized="' + (p.serialized ? 1 : 0) + '">';
    html += '<td class="text-center se-no" style="color:var(--text-muted)"></td>';
    // Nama produk; bila kosong pakai deskripsi. Deskripsi tetap tampil di bawah nama.
    html += '<td><div class="fw-semibold" style="font-size:13px">' + seEsc(p.name || p.description || p.code) + '</div>'
        + (p.name && p.description ? '<div class="se-desc" title="' + seEsc(p.description) + '">' + seEsc(p.description) + '</div>' : '')
        + '<div class="se-sub">' + seEsc(p.code) + ' <span class="se-kind ' + (p.serialized ? 'se-kind-serial">Serial' : 'se-kind-material">Material') + '</span></div>'
        + (p.serialized ? '' : '<div class="se-balance" data-balance-for="' + p.id + '"></div>') + '</td>';
    html += '<td><input type="text" inputmode="decimal" class="form-control form-control-sm text-center se-qty" value="1" aria-label="Qty"></td>';
    html += '<td class="text-center"><span class="se-unit" title="Unit follows the master product">' + seEsc(p.unit || '—') + '</span></td>';
    html += '<td><div class="input-group input-group-sm se-cost-group"><span class="input-group-text">' + seEsc(seCurrency) + '</span>'
        + '<input type="text" inputmode="decimal" class="form-control text-end se-cost" value="' + (p.cost == null ? '' : seFmt(p.cost)) + '" placeholder="Master standard cost" aria-label="Unit cost"></div></td>';
    html += '<td class="text-end se-value" style="font-variant-numeric:tabular-nums">—</td>';
    html += '<td class="text-center"><button type="button" class="btn-icon danger" title="Remove" onclick="seRemoveRow(this)"><i class="fa fa-trash-can"></i></button></td>';
    html += '</tr>';
    html += '<tr class="se-detail" data-for="' + key + '"><td></td><td colspan="6" style="padding-top:0"><div class="row g-2" style="font-size:12px">';
    if (p.serialized) {
        var sup = '<option value="">— Warranty supplier —</option>' + seSupplierOptions.map(function(o) { return '<option value="' + o.id + '">' + seEsc(o.name) + '</option>'; }).join('');
        html += '<div class="col-md-3"><label class="form-label mb-0" style="font-size:11.5px">Batch / MFG No.</label><input type="text" class="form-control form-control-sm se-batch" maxlength="100" placeholder="Optional"></div>';
        html += '<div class="col-md-2"><label class="form-label mb-0" style="font-size:11.5px">Expiry</label><input type="date" class="form-control form-control-sm se-expiry"></div>';
        html += '<div class="col-md-3"><label class="form-label mb-0" style="font-size:11.5px">Warranty supplier</label><select class="form-select form-select-sm se-supplier">' + sup + '</select></div>';
        html += '<div class="col-md-2"><label class="form-label mb-0" style="font-size:11.5px">Warranty until</label><input type="date" class="form-control form-control-sm se-warranty"></div>';
    }
    html += '<div class="' + (p.serialized ? 'col-md-2' : 'col-12') + '"><label class="form-label mb-0" style="font-size:11.5px">Item note</label><input type="text" class="form-control form-control-sm se-item-note" maxlength="255" placeholder="Optional"></div>';
    if (p.serialized) {
        html += '<div class="col-12"><label class="form-label mb-0" style="font-size:11.5px">SN — one per line, may be left empty (<span class="se-sn-count">0</span> / qty)</label><textarea class="form-control form-control-sm se-serials" rows="2" placeholder="Paste or scan SNs"></textarea></div>';
    }
    html += '</div></td></tr>';
    $('#se-items').append(html);
    return true;
}

// Saldo material saat ini di gudang + kepemilikan terpilih (qty & harga rata-rata).
function seLoadBalances() {
    var wh = $('#se-warehouse').val();
    var ids = $('#se-items tr[data-serialized="0"]').map(function() { return $(this).data('product-id'); }).get();
    if (!ids.length) return;
    if (!wh) { $('.se-balance').addClass('is-new').text('Select a warehouse to see the current balance.'); return; }
    if (seBalanceXhr) seBalanceXhr.abort();
    seBalanceXhr = $.getJSON(seBalancesUrl, { warehouse_id: wh, project_id: $('#se-project').val() || '', product_ids: ids }).done(function(res) {
        var data = res.data || {};
        $('.se-balance').each(function() {
            var b = data[$(this).data('balance-for')];
            if (b && b.qty > 0) {
                $(this).removeClass('is-new').html('<i class="fa fa-layer-group me-1"></i>Current balance ' + seEsc(b.qty_label) + ' ' + seEsc(b.unit || '') + ' @ ' + seMoney(b.unit_cost));
            } else {
                $(this).addClass('is-new').html('<i class="fa fa-plus me-1"></i>No balance yet — a new balance will be created');
            }
        });
    });
}

function seSerialList(text) {
    return String(text || '').split(/[\r\n,;]+/).map(function(s) { return s.trim(); }).filter(function(s) { return s !== ''; });
}

$(document).on('input', '.se-serials', function() {
    $(this).closest('tr').find('.se-sn-count').text(seSerialList($(this).val()).length);
});

function seRemoveRow(btn) {
    var $row = $(btn).closest('tr');
    $('#se-items tr.se-detail[data-for="' + $row.data('row') + '"]').remove();
    $row.remove();
    if ($('#se-items tr[data-product-id]').length === 0) {
        $('#se-items').html('<tr class="se-empty"><td colspan="7" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> No items yet. Click "Add from Catalog".</td></tr>');
    }
    seRenumber();
}

function seRenumber() {
    var lines = 0, material = 0, serial = 0, value = 0;
    $('#se-items tr[data-product-id]').each(function(i) {
        $(this).find('.se-no').text(i + 1);
        lines++;
        var qty = Num.parseOrNull($(this).find('.se-qty').val()) || 0;
        var cost = seParse($(this).find('.se-cost').val());
        if ($(this).data('serialized') === 1) serial += qty; else material++;
        $(this).find('.se-value').html(cost == null ? '—' : seMoney(qty * cost));
        value += cost == null ? 0 : qty * cost;
    });
    $('#se-sum-lines').text(lines);
    $('#se-sum-material').text(material);
    $('#se-sum-serial').text(serial);
    $('#se-sum-value').html(seMoney(value));
}

$(document).on('input', '.se-qty', function() {
    var serialized = $(this).closest('tr').data('serialized') === 1;
    this.value = serialized ? this.value.replace(/\D/g, '') : this.value.replace(/[^\d.,]/g, '');
    $(this).removeClass('is-invalid');
    seRenumber();
});

$(document).on('input', '.se-cost', function() {
    this.value = this.value.replace(/[^\d.,]/g, '');
    seRenumber();
});

$(document).on('change', '#se-reason', function() {
    $('#se-note-required').toggle($(this).val() === seReasonOther);
});
$(document).on('change', '#se-warehouse, #se-project', function() { $('#se-warehouse').removeClass('is-invalid'); seLoadBalances(); });

function seSave() {
    if (!$('#se-warehouse').val()) { $('#se-warehouse').addClass('is-invalid').trigger('focus'); toastr.error('Select a warehouse.'); return; }
    if ($('#se-reason').val() === seReasonOther && !$('#se-note').val().trim()) { $('#se-note').addClass('is-invalid').trigger('focus'); toastr.error('A description is required for the reason "Other".'); return; }

    var items = [], invalid = null;
    $('#se-items tr[data-product-id]').each(function() {
        var qty = Num.parseOrNull($(this).find('.se-qty').val()) || 0;
        if (qty <= 0 && !invalid) invalid = $(this).find('.se-qty');
        var cost = seParse($(this).find('.se-cost').val());
        var $d = $('#se-items tr.se-detail[data-for="' + $(this).data('row') + '"]');
        var serials = seSerialList($d.find('.se-serials').val());
        if (serials.length > qty && !invalid) invalid = $d.find('.se-serials');
        items.push({
            master_product_id: $(this).data('product-id'),
            qty: qty,
            unit_cost: cost,
            batch_number: $d.find('.se-batch').val() || null,
            expiry_date: $d.find('.se-expiry').val() || null,
            supplier_id: $d.find('.se-supplier').val() || null,
            warranty_until: $d.find('.se-warranty').val() || null,
            note: $d.find('.se-item-note').val() || null,
            serial_numbers: serials
        });
    });
    if (items.length === 0) { toastr.error('Add at least one item.'); return; }
    if (invalid) { invalid.addClass('is-invalid')[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); toastr.error('Qty must be greater than 0 and the number of SNs cannot exceed qty.'); return; }

    Swal.fire({
        title: 'Post stock entry?',
        text: items.length + ' line(s) will be added to stock in ' + $('#se-warehouse option:selected').text().trim() + '. The entry cannot be edited after posting.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, post',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $('#se-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Posting...');
        $.ajax({
            url: seStoreUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                warehouse_id: $('#se-warehouse').val(),
                project_id: $('#se-project').val() || null,
                reason: $('#se-reason').val(),
                entry_date: $('#se-date').val(),
                note: $('#se-note').val(),
                items: items
            }),
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        }).done(function(res) {
            toastr.success(res.message || 'Stock entry posted.');
            setTimeout(function() { window.location.href = seShowUrl.replace('__ID__', res.id); }, 600);
        }).fail(function(xhr) {
            var msg = 'Failed to post.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
            toastr.error(msg);
            $('#se-save').prop('disabled', false).html('<i class="fa fa-check me-1"></i> Post Stock Entry');
        });
    });
}

$(document).ready(function() {
    $('#se-project').select2({ width: '100%' });
});
</script>
@endsection
