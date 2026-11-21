@extends('layouts.app')

@section('title', 'New Stock Transfer')
@section('page-title', 'New Stock Transfer')

@section('styles')
<style>
    /* Langkah bernomor */
    .st-step { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: var(--accent); color: #fff; font-size: 11.5px; font-weight: 700; margin-right: 8px; flex-shrink: 0; }
    .st-step.is-done { background: #065f46; }
    .st-card-title { display: flex; align-items: center; font-weight: 600; }
    .st-card-hint { font-size: 12px; color: var(--text-muted); font-weight: 400; }

    /* Rute: Asal -> Tujuan */
    .st-route-grid { display: grid; grid-template-columns: minmax(0, 1fr) 44px minmax(0, 1fr); gap: 12px; align-items: start; }
    .st-route-arrow { display: flex; align-items: center; justify-content: center; height: 38px; margin-top: 52px; color: var(--accent); font-size: 18px; }
    @media (max-width: 991.98px) { .st-route-grid { grid-template-columns: 1fr; } .st-route-arrow { margin-top: 0; height: auto; transform: rotate(90deg); } }
    .st-side { border: 1px solid var(--card-border, #e5e7eb); border-radius: 10px; padding: 12px; }
    .st-side-title { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); font-weight: 600; margin-bottom: 6px; }
    .st-kind-box { border-radius: 8px; padding: 8px 12px; background: #f0f9ff; border: 1px solid #bae6fd; color: #075985; font-size: 12.5px; margin-top: 12px; }
    .st-field-hint { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; }
    .st-more-toggle { font-size: 12.5px; text-decoration: none; }
    .st-more-toggle .fa-chevron-down { transition: transform .2s; }
    .st-more-toggle.collapsed .fa-chevron-down { transform: rotate(-90deg); }
    .st-more-filled { font-size: 11px; background: #d1fae5; color: #065f46; border-radius: 999px; padding: 1px 8px; margin-left: 6px; }

    .st-pick-toolbar { display: grid; grid-template-columns: auto minmax(0, 2fr) minmax(0, 1fr); gap: 8px; padding: 2px 2px 10px; align-items: center; }
    @media (max-width: 991.98px) { .st-pick-toolbar { grid-template-columns: 1fr 1fr; } }
    .st-pick-search { position: relative; }
    .st-pick-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 12px; }
    .st-pick-search input { padding-left: 30px; }
    .st-view { white-space: nowrap; }
    .st-view .btn { font-size: 12.5px; }
    .st-view .btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }
    .st-view .badge { background: rgba(0,0,0,.12); color: inherit; font-size: 10px; }
    .st-pick-wrap { border: 1px solid var(--card-border, #e5e7eb); border-radius: 8px; max-height: 56vh; overflow: auto; }
    .st-pick-table thead th { position: sticky; top: 0; z-index: 1; background: var(--card, #fff); font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; box-shadow: inset 0 -1px 0 var(--card-border, #e5e7eb); }
    .st-pick-table td { font-size: 12.5px; }
    #st-pick-body tr[data-id] { cursor: pointer; transition: background-color .15s ease; user-select: none; }
    #st-pick-body tr[data-id]:hover td { background-color: #d1fae5 !important; }
    #st-pick-body tr.is-picked td { background-color: #ecfdf5 !important; }
    #st-pick-body tr.is-picked td:first-child { box-shadow: inset 3px 0 0 var(--accent); }
    .st-code { font-weight: 600; font-variant-numeric: tabular-nums; }
    .st-sub { font-size: 11.5px; color: var(--text-muted); }
    .st-kind { font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 999px; }
    .st-kind-material { background: #e0f2fe; color: #075985; }
    .st-kind-serial { background: #fef3c7; color: #92400e; }
    .st-qty-group { max-width: 150px; margin-left: auto; }
    .st-qty-group .form-control { text-align: right; font-variant-numeric: tabular-nums; }
    .st-qty-group .btn { font-size: 11px; }
    .st-pick-empty { text-align: center; padding: 36px 10px !important; color: var(--text-muted); font-size: 13px; }
    .st-pick-empty .st-empty-icon { font-size: 26px; color: var(--accent); opacity: .6; display: block; margin-bottom: 8px; }
    .st-pick-foot { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: 8px 4px 0; font-size: 12px; color: var(--text-muted); }
    .st-pick-search kbd { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); font-size: 10px; padding: 1px 5px; background: var(--bg-soft, #f3f4f6); color: var(--text-muted); border: 1px solid var(--card-border, #e5e7eb); }

    /* Bilah aksi menempel di bawah */
    .st-actionbar { position: sticky; bottom: 0; z-index: 20; margin-top: 16px; background: var(--card, #fff); border: 1px solid var(--card-border, #e5e7eb); border-radius: 10px; box-shadow: 0 -6px 18px rgba(15, 23, 42, .08); padding: 10px 14px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .st-ab-route { font-size: 12.5px; min-width: 0; flex: 1 1 260px; }
    .st-ab-route .st-ab-label { font-size: 11px; color: var(--text-muted); }
    .st-ab-route .st-ab-path { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .st-ab-stats { display: flex; gap: 18px; }
    .st-ab-stat { text-align: center; cursor: pointer; }
    .st-ab-stat .n { font-size: 17px; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .st-ab-stat .l { font-size: 11px; color: var(--text-muted); }
    .st-ab-actions { display: flex; gap: 8px; margin-left: auto; align-items: center; }
    .st-ab-dirty { font-size: 11.5px; color: #b45309; display: none; }
    .is-invalid + .select2-container .select2-selection, select.is-invalid { border-color: var(--danger, #dc3545) !important; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">New Stock Transfer</h1>
        <p class="page-header-sub">Choose the route, pick the items, then post. Materials move from the source balance to the destination balance; serial units move as the same unit. Within one branch only.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('stock-transfer.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

{{-- 1. Rute --}}
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span class="st-card-title"><span class="st-step" id="st-step-1">1</span>Source &amp; Destination</span>
        <span class="st-card-hint">Destination warehouses are limited to the source branch</span>
    </div>
    <div class="card-body-custom">
        <div class="st-route-grid">
            <div class="st-side">
                <div class="st-side-title"><i class="fa-solid fa-circle-arrow-up me-1" style="color:var(--danger)"></i>From</div>
                <label class="form-label" for="st-from-warehouse">Source Warehouse <span class="text-danger">*</span></label>
                <select id="st-from-warehouse" class="form-select mb-2">
                    <option value="">— Select Warehouse —</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" data-branch="{{ $w->branch_id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                    @endforeach
                </select>
                <label class="form-label" for="st-from-project">Source Ownership</label>
                <select id="st-from-project" class="form-select st-project">
                    <option value="">Free stock</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}">Project {{ $p->project_number }} — {{ $p->project_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="st-route-arrow"><i class="fa-solid fa-arrow-right-long"></i></div>
            <div class="st-side">
                <div class="st-side-title"><i class="fa-solid fa-circle-arrow-down me-1" style="color:var(--success, #16a34a)"></i>To</div>
                <label class="form-label" for="st-to-warehouse">Destination Warehouse <span class="text-danger">*</span></label>
                <select id="st-to-warehouse" class="form-select mb-2">
                    <option value="">— Select Warehouse —</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" data-branch="{{ $w->branch_id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                    @endforeach
                </select>
                <label class="form-label" for="st-to-project">Destination Ownership</label>
                <select id="st-to-project" class="form-select st-project">
                    <option value="">Free stock</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}">Project {{ $p->project_number }} — {{ $p->project_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="st-kind-box"><strong id="st-kind">Move Warehouse</strong> — <span id="st-kind-hint"></span></div>

        <div class="mt-3 pt-2" style="border-top:1px dashed var(--card-border,#e5e7eb)">
            <a class="st-more-toggle collapsed" data-bs-toggle="collapse" href="#st-more" role="button" aria-expanded="false">
                <i class="fa fa-chevron-down me-1"></i>Date &amp; note <span style="color:var(--text-muted)">(date defaults to today)</span>
                <span class="st-more-filled" id="st-more-filled" style="display:none">filled</span>
            </a>
            <div class="collapse" id="st-more">
                <div class="row g-3 pt-2">
                    <div class="col-md-4">
                        <label class="form-label" for="st-date">Transfer Date <span class="text-danger">*</span></label>
                        <input type="date" id="st-date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="st-note">Note</label>
                        <input type="text" id="st-note" class="form-control st-morefield" maxlength="1000" placeholder="Optional, e.g. preparing to ship to site">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 2. Barang --}}
<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span class="st-card-title"><span class="st-step" id="st-step-2">2</span>Select Items to Transfer</span>
        <span class="st-card-hint" id="st-src-label">Select the source warehouse first</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="st-pick-toolbar">
            <div class="btn-group btn-group-sm st-view" role="group" aria-label="View">
                <button type="button" class="btn btn-outline-secondary active" data-view="all" disabled><i class="fa fa-warehouse me-1"></i>Source Stock <span class="badge rounded-pill ms-1" id="st-view-all-count">0</span></button>
                <button type="button" class="btn btn-outline-secondary" data-view="picked" disabled><i class="fa fa-check me-1"></i>Selected <span class="badge rounded-pill ms-1" id="st-view-count">0</span></button>
            </div>
            <div class="st-pick-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" id="st-q" class="form-control form-control-sm" placeholder="Search code, name, SN, batch, note…" autocomplete="off" disabled>
                <kbd title="Press / to search">/</kbd>
            </div>
            <select id="st-f-kind" class="form-select form-select-sm" disabled aria-label="Item type">
                <option value="">All item types</option>
                <option value="material">Material</option>
                <option value="serial">Serial items</option>
            </select>
        </div>
        <div class="st-pick-wrap">
            <table class="table table-custom align-middle mb-0 st-pick-table">
                <thead>
                    <tr>
                        <th style="width:40px" class="text-center"><input type="checkbox" class="form-check-input" id="st-check-all" title="Select all visible" disabled></th>
                        <th style="width:150px">Code</th>
                        <th>Item Name</th>
                        <th style="width:150px">Received / Expiry</th>
                        <th style="width:130px" class="text-end">Available</th>
                        <th style="width:170px" class="text-end">Take</th>
                    </tr>
                </thead>
                <tbody id="st-pick-body"></tbody>
            </table>
        </div>
        <div class="st-pick-foot">
            <span><i class="fa fa-circle-info me-1"></i>Click a row to select · Shift+click for a range · Enter in qty moves to the next row<br>
                <span class="st-kind st-kind-material">Material</span> = one balance per warehouse &amp; ownership, can be moved partially ·
                <span class="st-kind st-kind-serial">Serial</span> = per unit, moved whole (SN may still be empty)</span>
            <span id="st-pick-info"></span>
        </div>
    </div>
</div>

{{-- Bilah aksi --}}
<div class="st-actionbar" id="st-actionbar">
    <div class="st-ab-route">
        <div class="st-ab-label" id="st-ab-kind">Move Warehouse</div>
        <div class="st-ab-path" id="st-ab-route">—</div>
    </div>
    <div class="st-ab-stats" title="Click to view the selected items">
        <div class="st-ab-stat" data-goto="picked"><div class="n" id="st-sum-lines">0</div><div class="l">Lines selected</div></div>
        <div class="st-ab-stat" data-goto="picked"><div class="n" id="st-sum-material">0</div><div class="l">Material</div></div>
        <div class="st-ab-stat" data-goto="picked"><div class="n" id="st-sum-serial">0</div><div class="l">SN units</div></div>
    </div>
    <div class="st-ab-actions">
        <span class="st-ab-dirty" id="st-dirty"><i class="fa fa-circle me-1" style="font-size:7px"></i>Not posted yet</span>
        <button type="button" class="btn btn-link btn-sm" id="st-clear" style="display:none">Clear selection</button>
        <a href="{{ route('stock-transfer.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        @if($canCreate)
        <button type="button" class="btn btn-primary btn-sm" id="st-save" onclick="stSave()" title="Ctrl+S">
            <i class="fa fa-check me-1"></i> Post Transfer
        </button>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
const stAvailableUrl = '{{ route("stock-transfer.available-stock") }}';
const stStoreUrl = '{{ route("stock-transfer.store") }}';
const stShowUrl = '{{ route("stock-transfer.show", "__ID__") }}';
const stLimit = 2000;
let stRows = [];          // baris stok di asal (dimuat sekali)
let stSel = {};           // {stock_id: qty}
let stView = 'all';
let stVisible = [];
let stLastIdx = null;
let stXhr = null;
let stDirty = false;
let stSaving = false;

function stEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function stIsPicked(id) { return stSel[id] != null; }
function stEmpty(icon, html) { return '<tr><td colspan="6" class="st-pick-empty"><i class="fa-solid ' + icon + ' st-empty-icon"></i>' + html + '</td></tr>'; }

function stKind() {
    var from = $('#st-from-project').val(), to = $('#st-to-project').val();
    if (!from && to) return ['Allocate to Project', 'free stock becomes owned by the destination project (may stay in the same warehouse) until shipped via Delivery Order. Recorded as a project cost.'];
    if (from && !to) return ['Release Allocation', 'project stock goes back to free stock.'];
    if (from && to && from !== to) return ['Move Between Projects', 'ownership moves from one project to another.'];
    return ['Move Warehouse', 'ownership stays the same; only the warehouse changes.'];
}

function stSetToolbar(on) { $('#st-q, #st-f-kind, #st-check-all, .st-view .btn').prop('disabled', !on); }

function stLoad() {
    var wh = $('#st-from-warehouse').val();
    stRows = []; stSel = {}; stLastIdx = null;
    $('#st-src-label').text(wh ? 'Stock in ' + $('#st-from-warehouse option:selected').text() + ' · ' + $('#st-from-project option:selected').text() : 'Select the source warehouse first');
    $('#st-view-all-count').text(0);
    if (!wh) {
        stSetToolbar(false);
        $('#st-pick-body').html(stEmpty('fa-warehouse', 'Select the <strong>Source Warehouse</strong> to show the stock that can be transferred.'));
        $('#st-pick-info').text('');
        stSummary();
        return;
    }
    if (stXhr) stXhr.abort();
    stSetToolbar(false);
    $('#st-pick-body').html('<tr><td colspan="6" class="st-pick-empty"><span class="spinner-border spinner-border-sm me-1"></span>Loading stock…</td></tr>');
    stXhr = $.getJSON(stAvailableUrl, { warehouse_id: wh, project_id: $('#st-from-project').val() || '' }).done(function(res) {
        stRows = res.data || [];
        stSetToolbar(true);
        $('#st-view-all-count').text(stRows.length);
        $('#st-pick-info').text(stRows.length >= stLimit ? 'Showing the first ' + stLimit + ' lines — use search.' : stRows.length + ' stock lines at the source');
        stRender();
    }).fail(function(xhr) {
        if (xhr.statusText === 'abort') return;
        $('#st-pick-body').html('<tr><td colspan="6" class="st-pick-empty text-danger">' + stEsc(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to load stock.') + '</td></tr>');
    });
}

function stMatches(r) {
    var terms = String($('#st-q').val() || '').toLowerCase().split(/\s+/).filter(Boolean);
    var kind = $('#st-f-kind').val();
    if (stView === 'picked' && !stIsPicked(r.id)) return false;
    if (kind === 'material' && r.serialized) return false;
    if (kind === 'serial' && !r.serialized) return false;
    if (!terms.length) return true;
    var hay = [r.product_code, r.product_name, r.serial_number, r.batch_number, r.identity, r.note].join(' ').toLowerCase();
    return terms.every(function(t) { return hay.indexOf(t) !== -1; });
}

function stRowHtml(r, idx) {
    var picked = stIsPicked(r.id);
    var ident = r.serialized
        ? (r.serial_number ? 'SN ' + stEsc(r.serial_number) : '<span style="color:#b45309">SN not filled in yet</span>')
        : '';
    var take = r.serialized
        ? '<span class="st-sub">' + (picked ? '<strong style="color:#065f46">1 unit</strong>' : '1 unit') + '</span>'
        : '<div class="input-group input-group-sm st-qty-group">'
            + '<input type="text" inputmode="decimal" class="form-control st-qty" placeholder="0" value="' + (picked ? stEsc(stSel[r.id]) : '') + '" aria-label="Qty to transfer">'
            + '<button type="button" class="btn btn-outline-secondary st-max" title="Take all (' + stEsc(r.qty_label) + ')">Max</button></div>';
    return '<tr data-id="' + r.id + '" data-idx="' + idx + '"' + (picked ? ' class="is-picked"' : '') + '>'
        + '<td class="text-center"><input type="checkbox" class="form-check-input st-check"' + (picked ? ' checked' : '') + ' aria-label="Select"></td>'
        + '<td><div class="st-code">' + stEsc(r.product_code || '—') + '</div><span class="st-kind ' + (r.serialized ? 'st-kind-serial">Serial' : 'st-kind-material">Material') + '</span></td>'
        + '<td><div style="font-weight:600">' + stEsc(r.product_name || '—') + '</div>'
            + (ident ? '<div class="st-sub">' + ident + '</div>' : '')
            + (r.note ? '<div class="st-sub"><i class="fa fa-note-sticky me-1"></i>' + stEsc(r.note) + '</div>' : '') + '</td>'
        + '<td>' + (r.serialized
            ? stEsc(r.received_date || '—') + (r.expiry_date ? '<div class="st-sub">exp ' + stEsc(r.expiry_date) + '</div>' : '')
            : '<span class="st-sub" title="Material: all receipts are combined in one balance">Combined balance</span>') + '</td>'
        + '<td class="text-end"><strong>' + stEsc(r.qty_label) + '</strong> <span class="st-sub">' + stEsc(r.unit || '') + '</span><div class="st-sub">' + (r.serialized ? '1 unit' : 'balance') + '</div></td>'
        + '<td class="text-end">' + take + '</td>'
        + '</tr>';
}

function stRender() {
    if (!$('#st-from-warehouse').val()) return;
    stVisible = [];
    var html = '';
    stRows.forEach(function(r, idx) {
        if (!stMatches(r)) return;
        stVisible.push(idx);
        html += stRowHtml(r, idx);
    });
    if (!stRows.length) html = stEmpty('fa-inbox', 'No stock available at this source.');
    else if (!stVisible.length) html = stView === 'picked'
        ? stEmpty('fa-hand-pointer', 'Nothing selected yet. Open <strong>Source Stock</strong> and click item rows.')
        : stEmpty('fa-filter-circle-xmark', 'No stock matches the search / filter.');
    $('#st-pick-body').html(html);
    stLastIdx = null;
    stSummary();
}

function stSetPicked(idx, on, qty) {
    var r = stRows[idx];
    if (!r) return;
    if (on) {
        var q = qty != null ? qty : (stSel[r.id] != null ? stSel[r.id] : (r.serialized ? 1 : r.qty));
        stSel[r.id] = Math.min(q, r.qty);
    } else {
        delete stSel[r.id];
    }
    var $tr = $('#st-pick-body tr[data-idx="' + idx + '"]');
    if (!$tr.length) return;
    $tr.toggleClass('is-picked', on).find('.st-check').prop('checked', on);
    if (!r.serialized) {
        var $q = $tr.find('.st-qty');
        if (!$q.is(':focus')) $q.val(on ? stSel[r.id] : '');
    } else {
        $tr.find('td:last').html('<span class="st-sub">' + (on ? '<strong style="color:#065f46">1 unit</strong>' : '1 unit') + '</span>');
    }
}

function stMarkDirty() { stDirty = true; $('#st-dirty').show(); }

function stRouteSummary() {
    var kind = stKind();
    $('#st-kind, #st-ab-kind').text(kind[0]);
    $('#st-kind-hint').text(kind[1]);
    var label = function(wh, pr) {
        var w = $(wh + ' option:selected'), p = $(pr + ' option:selected');
        return w.val() ? w.text().split(' (')[0] + ' · ' + (p.val() ? p.text().replace(/^Project /, '').split(' — ')[0] : 'Free stock') : '—';
    };
    $('#st-ab-route').html(stEsc(label('#st-from-warehouse', '#st-from-project')) + ' <i class="fa fa-arrow-right mx-1" style="color:var(--accent)"></i> ' + stEsc(label('#st-to-warehouse', '#st-to-project')));
    var done = !!($('#st-from-warehouse').val() && $('#st-to-warehouse').val() && $('#st-date').val());
    $('#st-step-1').toggleClass('is-done', done).html(done ? '<i class="fa fa-check" style="font-size:10px"></i>' : '1');
}

function stSummary() {
    stRouteSummary();
    var byId = {};
    stRows.forEach(function(r) { byId[r.id] = r; });
    var ids = Object.keys(stSel), material = 0, serial = 0;
    ids.forEach(function(id) { if (byId[id] && byId[id].serialized) serial++; else material++; });
    $('#st-sum-lines').text(ids.length);
    $('#st-sum-material').text(material);
    $('#st-sum-serial').text(serial);
    $('#st-view-count').text(ids.length);
    $('#st-clear').toggle(ids.length > 0);
    var vis = stVisible.length, pickedVis = stVisible.filter(function(i) { return stIsPicked(stRows[i].id); }).length;
    $('#st-check-all').prop('checked', vis > 0 && pickedVis === vis).prop('indeterminate', pickedVis > 0 && pickedVis < vis);
    $('#st-step-2').toggleClass('is-done', ids.length > 0).html(ids.length > 0 ? '<i class="fa fa-check" style="font-size:10px"></i>' : '2');
}

function stSetView(view) {
    stView = view;
    $('.st-view .btn').removeClass('active').filter('[data-view="' + view + '"]').addClass('active');
    stRender();
}

// Klik baris = pilih/batal; Shift+klik = rentang. Klik di input qty tidak men-toggle.
$(document).on('click', '#st-pick-body tr[data-id]', function(e) {
    if ($(e.target).closest('.st-qty, .st-max').length) return;
    var idx = parseInt($(this).data('idx'), 10);
    var on = $(e.target).is('.st-check') ? e.target.checked : !stIsPicked(stRows[idx].id);
    if (e.shiftKey && stLastIdx !== null) {
        var a = stVisible.indexOf(stLastIdx), b = stVisible.indexOf(idx);
        if (a > b) { var t = a; a = b; b = t; }
        for (var i = a; i <= b; i++) stSetPicked(stVisible[i], on);
        if (window.getSelection) window.getSelection().removeAllRanges();
    } else {
        stSetPicked(idx, on);
    }
    stLastIdx = idx;
    stMarkDirty();
    stSummary();
});
$(document).on('input', '#st-pick-body .st-qty', function() {
    this.value = this.value.replace(/[^\d.]/g, '');
    var idx = parseInt($(this).closest('tr').data('idx'), 10), r = stRows[idx];
    var v = Num.parseOrNull(this.value) || 0;
    if (v > r.qty) { v = r.qty; this.value = r.qty; }
    stSetPicked(idx, v > 0, v);
    stMarkDirty();
    stSummary();
});
$(document).on('keydown', '#st-pick-body .st-qty', function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var $next = $(this).closest('tr').nextAll('tr').find('.st-qty').first();
    if ($next.length) $next.trigger('focus').trigger('select');
});
$(document).on('click', '#st-pick-body .st-max', function() {
    var idx = parseInt($(this).closest('tr').data('idx'), 10);
    stSetPicked(idx, true, stRows[idx].qty);
    $(this).closest('tr').find('.st-qty').val(stRows[idx].qty);
    stMarkDirty();
    stSummary();
});
$(document).on('change', '#st-check-all', function() {
    var on = this.checked;
    stVisible.forEach(function(i) { stSetPicked(i, on); });
    stMarkDirty();
    stSummary();
});
$(document).on('click', '#st-clear', function() {
    if (!confirm('Clear all ' + Object.keys(stSel).length + ' selected line(s)?')) return;
    stSel = {};
    stRender();
});
$(document).on('click', '.st-view .btn', function() { stSetView($(this).data('view')); });
$(document).on('click', '.st-ab-stat[data-goto]', function() {
    if (!stRows.length) return;
    stSetView('picked');
    $('html, body').animate({ scrollTop: $('#st-pick-body').closest('.card-custom').offset().top - 80 }, 200);
});
let stTimer = null;
$(document).on('input', '#st-q', function() { clearTimeout(stTimer); stTimer = setTimeout(stRender, 200); });
$(document).on('change', '#st-f-kind', stRender);

let stPrev = { wh: '', project: '' };
$(document).on('change', '#st-from-warehouse, #st-from-project', function() {
    if (Object.keys(stSel).length && !confirm('Change the source? ' + Object.keys(stSel).length + ' selected line(s) will be cleared.')) {
        // Batal: kembalikan pilihan asal sebelumnya.
        $('#st-from-warehouse').val(stPrev.wh);
        $('#st-from-project').val(stPrev.project).trigger('change.select2');
        return;
    }
    stPrev = { wh: $('#st-from-warehouse').val(), project: $('#st-from-project').val() };
    stMarkDirty();
    stSetView('all');
    stLoad();
});
// Transfer Stok hanya dalam satu branch: gudang tujuan dibatasi ke branch gudang asal.
$(document).on('change', '#st-from-warehouse', function() {
    var branch = String($(this).find('option:selected').data('branch') || '');
    $('#st-to-warehouse option[value!=""]').each(function() {
        $(this).prop('disabled', !!branch && String($(this).data('branch') || '') !== branch);
    });
    if ($('#st-to-warehouse option:selected').prop('disabled')) $('#st-to-warehouse').val('');
});
$(document).on('change', '#st-to-project', function() {
    // Alokasi ke project boleh di gudang yang sama: isi gudang tujuan = asal bila belum dipilih.
    if ($(this).val() && !$('#st-to-warehouse').val() && $('#st-from-warehouse').val()) {
        $('#st-to-warehouse').val($('#st-from-warehouse').val()).trigger('change');
    }
    stSummary();
});
$(document).on('change', '#st-from-warehouse, #st-to-warehouse, #st-date', function() { $(this).removeClass('is-invalid'); stMarkDirty(); stRouteSummary(); });
$(document).on('input', '.st-morefield', function() {
    stMarkDirty();
    $('#st-more-filled').toggle($('.st-morefield').filter(function() { return this.value.trim() !== ''; }).length > 0);
});

// Pintasan: "/" fokus ke pencarian, Ctrl/Cmd+S posting.
$(document).on('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); stSave(); return; }
    if (e.key === '/' && !$(e.target).is('input, textarea, select') && !$('#st-q').prop('disabled')) { e.preventDefault(); $('#st-q').trigger('focus'); }
});

// Peringatan saat meninggalkan halaman dengan pilihan yang belum diposting.
window.addEventListener('beforeunload', function(e) {
    if (stDirty && !stSaving && Object.keys(stSel).length) { e.preventDefault(); e.returnValue = ''; }
});

function stSave() {
    if (stSaving || !$('#st-save').length) return;
    var fromWh = $('#st-from-warehouse').val(), toWh = $('#st-to-warehouse').val();
    var fromProject = $('#st-from-project').val(), toProject = $('#st-to-project').val();
    var missing = [];
    ['#st-from-warehouse', '#st-to-warehouse', '#st-date'].forEach(function(sel) {
        var empty = !$(sel).val();
        $(sel).toggleClass('is-invalid', empty);
        if (empty) missing.push(sel);
    });
    if (missing.length) {
        if (missing.indexOf('#st-date') !== -1) bootstrap.Collapse.getOrCreateInstance(document.getElementById('st-more')).show();
        toastr.error('Complete the route: source warehouse, destination warehouse and transfer date.');
        $(missing[0]).trigger('focus');
        return;
    }
    if (fromWh === toWh && fromProject === toProject) { toastr.error('Source and destination are the same. Change the warehouse or ownership.'); return; }

    var rows = Object.keys(stSel).filter(function(id) { return stSel[id] > 0; }).map(function(id) { return { stock_id: parseInt(id, 10), qty: stSel[id] }; });
    if (rows.length === 0) { toastr.error('Select at least one stock line.'); stSetView('all'); return; }

    Swal.fire({
        title: 'Post transfer?',
        text: stKind()[0] + ' — ' + rows.length + ' line(s). Stock moves immediately and the transfer cannot be edited.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, post',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        stSaving = true;
        $('#st-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Posting...');
        $.ajax({
            url: stStoreUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                from_warehouse_id: fromWh,
                from_project_id: fromProject || null,
                to_warehouse_id: toWh,
                to_project_id: toProject || null,
                transfer_date: $('#st-date').val(),
                note: $('#st-note').val(),
                items: rows
            }),
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        }).done(function(res) {
            stDirty = false;
            toastr.success(res.message || 'Transfer posted.');
            setTimeout(function() { window.location.href = stShowUrl.replace('__ID__', res.id); }, 600);
        }).fail(function(xhr) {
            var msg = 'Failed to post the transfer.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
            toastr.error(msg);
            stSaving = false;
            $('#st-save').prop('disabled', false).html('<i class="fa fa-check me-1"></i> Post Transfer');
        });
    });
}

$(document).ready(function() {
    $('.st-project').select2({ width: '100%' });
    stLoad();
});
</script>
@endsection
