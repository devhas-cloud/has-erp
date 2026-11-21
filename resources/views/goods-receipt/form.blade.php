@extends('layouts.app')

@section('title', 'Receive Goods')
@section('page-title', 'Receive Goods')

@section('styles')
<style>
    .gr-step { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: var(--accent); color: #fff; font-size: 11.5px; font-weight: 700; margin-right: 8px; flex-shrink: 0; }
    .gr-step.is-done { background: #065f46; }
    .gr-card-title { display: inline-flex; align-items: center; font-weight: 600; }
    .gr-card-hint { font-size: 12px; color: var(--text-muted); }

    /* Strip info dokumen sumber */
    .gr-source-strip { display: none; margin-top: 14px; padding: 10px 14px; border-radius: 8px; background: var(--accent-subtle, #f0fdf4); border: 1px solid var(--card-border); font-size: 12.5px; gap: 6px 22px; flex-wrap: wrap; }
    .gr-source-strip .k { font-size: 11px; color: var(--text-muted); }
    .gr-source-strip .v { font-weight: 600; }

    /* Kurs (bagian dari langkah 1) */
    .gr-fx-meta { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; }
    .gr-fx-error { font-size: 12px; color: var(--danger); }

    /* Tabel barang */
    .gr-tools { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; padding: 4px 4px 10px; }
    .gr-items th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    .gr-items tbody tr[data-line-id] { cursor: pointer; }
    .gr-items tbody tr[data-line-id]:hover td { background-color: #f8fafc; }
    .gr-row-off td { opacity: .5; }
    .gr-detail td { background: #fafbfc; border-top: 0 !important; }
    .gr-name { font-weight: 600; color: var(--text-primary); font-size: 13px; }
    .gr-sub { font-size: 11.5px; color: var(--text-muted); }
    .gr-kind { font-size: 10px; font-weight: 600; padding: 1px 7px; border-radius: 999px; white-space: nowrap; }
    .gr-kind-material { background: #e0f2fe; color: #075985; }
    .gr-kind-serial { background: #fef3c7; color: #92400e; }
    .gr-kind-direct { background: #ede9fe; color: #5b21b6; }
    .gr-own-project { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: #fef3c7; color: #92400e; white-space: normal; display: inline-block; }
    .gr-qty-wrap { display: flex; align-items: center; gap: 6px; justify-content: center; }
    .gr-qty { width: 72px; text-align: center; }
    .gr-of { font-size: 11.5px; color: var(--text-muted); white-space: nowrap; line-height: 1.2; }
    .gr-status { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px; }
    .gr-st-full { background: #dcfce7; color: #065f46; }
    .gr-st-partial { background: #fef3c7; color: #92400e; }
    .gr-st-bad { background: #fee2e2; color: #b42318; }
    .gr-st-off { background: #f3f4f6; color: #6b7280; }
    .gr-toggle { border: 0; background: none; padding: 0; font-size: 11.5px; color: var(--accent-hover); margin-top: 2px; }
    .gr-toggle:hover { text-decoration: underline; }
    .gr-sn-tag { font-size: 10.5px; color: #92400e; margin-left: 4px; }
    .gr-info { font-size: 12px; color: #075985; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 6px 10px; }
    .gr-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; font-size: 12px; color: var(--text-muted); padding: 10px 4px 0; }
    .gr-empty { text-align: center; padding: 36px 10px !important; color: var(--text-muted); font-size: 13px; }

    /* Bilah aksi menempel di bawah */
    .gr-actionbar { position: sticky; bottom: 0; z-index: 20; margin-top: 16px; background: var(--card, #fff); border: 1px solid var(--card-border, #e5e7eb); border-radius: 10px; box-shadow: 0 -6px 18px rgba(15, 23, 42, .08); padding: 10px 14px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .gr-ab-route { font-size: 12.5px; min-width: 0; flex: 1 1 260px; }
    .gr-ab-route .gr-ab-label { font-size: 11px; color: var(--text-muted); }
    .gr-ab-route .gr-ab-path { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .gr-ab-stats { display: flex; gap: 18px; }
    .gr-ab-stat { text-align: center; }
    .gr-ab-stat .n { font-size: 17px; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .gr-ab-stat .l { font-size: 11px; color: var(--text-muted); }
    .gr-ab-actions { display: flex; gap: 8px; margin-left: auto; align-items: center; }
    .gr-ab-dirty { font-size: 11.5px; color: #b45309; display: none; }
    .is-invalid + .select2-container .select2-selection { border-color: var(--danger, #dc3545) !important; }
    @media (max-width: 767.98px) { .gr-ab-stats { gap: 12px; } .gr-ab-actions { margin-left: 0; width: 100%; justify-content: flex-end; } }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Receive Goods</h1>
        <p class="page-header-sub">Choose the purchase document, tick the goods that arrived and enter the qty, then post. Stock increases in the destination warehouse as soon as the receipt is posted.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('goods-receipt.index') }}" class="btn btn-secondary btn-sm">
            Back
        </a>
    </div>
</div>

{{-- Langkah 1: dokumen, gudang, tanggal, kurs --}}
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="gr-card-title">Purchase Document &amp; Warehouse</span>
        <span class="gr-card-hint">All arriving goods go into one destination warehouse</span>
    </div>
    <div class="card-body-custom">
        <div class="row g-3">
            <div class="col-lg-6">
                <label class="form-label" for="gr-source">Purchase Document <span class="text-danger">*</span></label>
                <select id="gr-source" class="form-select" style="width:100%">
                    <option value="">— Select PO / Direct Purchase —</option>
                    @if($purchaseOrders->isNotEmpty())
                        <optgroup label="Purchase Order">
                            @foreach($purchaseOrders as $po)
                                <option value="po:{{ $po->id }}" {{ $preselect['type'] === 'po' && $preselect['id'] === $po->id ? 'selected' : '' }}>{{ $po->po_number ?: 'PO #'.$po->id }} — {{ $po->supplier_name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    @if($directPurchases->isNotEmpty())
                        <optgroup label="Direct Purchase">
                            @foreach($directPurchases as $dp)
                                <option value="dp:{{ $dp->id }}" {{ $preselect['type'] === 'dp' && $preselect['id'] === $dp->id ? 'selected' : '' }}>{{ $dp->dp_number ?: 'DPO #'.$dp->id }} — {{ $dp->supplier_name }} ({{ $dp->purpose_label }})</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
                @if($purchaseOrders->isEmpty() && $directPurchases->isEmpty())
                    <div class="gr-sub mt-1">No approved PO / Direct Purchase is waiting for goods.</div>
                @endif
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="gr-warehouse">Destination Warehouse <span class="text-danger gr-wh-required">*</span></label>
                <select id="gr-warehouse" class="form-select" style="width:100%">
                    <option value="">— Select Warehouse —</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-4" id="gr-date-col">
                <label class="form-label" for="gr-date">Received Date</label>
                <input type="date" id="gr-date" class="form-control" value="{{ now()->toDateString() }}">
            </div>
            {{-- Kurs per mata uang asing disisipkan di sini (sejajar tanggal terima) --}}
            <div class="col-lg col-md-8">
                <label class="form-label" for="gr-note">Note</label>
                <input type="text" id="gr-note" class="form-control" maxlength="255" placeholder="Optional, e.g. supplier delivery note no.">
            </div>
        </div>

        <div class="gr-source-strip" id="gr-source-strip" style="display:none">
            <div><div class="k">Supplier</div><div class="v" id="gr-src-supplier">—</div></div>
            <div><div class="k">Document</div><div class="v" id="gr-src-number">—</div></div>
            <div id="gr-src-purpose-wrap" style="display:none"><div class="k">Purpose</div><div class="v" id="gr-src-purpose">—</div></div>
            <div><div class="k">Lines outstanding</div><div class="v" id="gr-src-lines">0</div></div>
        </div>
    </div>
</div>

{{-- Langkah 2: barang yang datang --}}
<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="gr-card-title">Check Arriving Goods</span>
        <span class="gr-card-hint">Click a row to tick / untick · Enter in qty moves to the next line</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="gr-tools" id="gr-tools" style="display:none">
            <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input" id="gr-check-all" checked>
                <label class="form-check-label" for="gr-check-all" style="font-size:12.5px">Select all lines</label>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="gr-fill-all">Receive all outstanding</button>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0 gr-items">
                <thead>
                    <tr>
                        <th style="width:36px"></th>
                        <th>Item</th>
                        <th style="width:200px">For</th>
                        <th class="text-center" style="width:190px">Qty Received</th>
                        <th class="text-center" style="width:110px">Status</th>
                    </tr>
                </thead>
                <tbody id="gr-items-body">
                    <tr><td colspan="5" class="gr-empty">Select a purchase document in step 1 to show its items.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="gr-legend" style="display: none">
            <span><span class="gr-kind gr-kind-material">Material</span> added to the product balance in the warehouse</span>
            <span><span class="gr-kind gr-kind-serial">Serial</span> one stock row per unit — SN can be filled in later</span>
            <span><span class="gr-kind gr-kind-direct">Direct Use</span> not linked to a master product, so not added to stock</span>
        </div>
    </div>
</div>

{{-- Bilah aksi --}}
<div class="gr-actionbar" id="gr-actionbar">
    <div class="gr-ab-route">
        <div class="gr-ab-label" id="gr-ab-supplier">No document selected</div>
        <div class="gr-ab-path" id="gr-ab-route">—</div>
    </div>
    <div class="gr-ab-stats" style="display: none">
        <div class="gr-ab-stat"><div class="n" id="gr-sum-lines">0</div><div class="l">Lines</div></div>
        <div class="gr-ab-stat"><div class="n" id="gr-sum-material">0</div><div class="l">Material</div></div>
        <div class="gr-ab-stat"><div class="n" id="gr-sum-serial">0</div><div class="l">SN units</div></div>
        <div class="gr-ab-stat gr-sum-direct-row" style="display:none"><div class="n" id="gr-sum-direct">0</div><div class="l">Direct use</div></div>
    </div>
    <div class="gr-ab-actions">
        <span class="gr-ab-dirty" id="gr-dirty">Not posted yet</span>
        <a href="{{ route('goods-receipt.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        <button type="button" class="btn btn-primary btn-sm" id="gr-save-btn" onclick="grSave()" title="Ctrl+S" disabled>
            Receive &amp; Post
        </button>
    </div>
</div>
@endsection

@section('scripts')
<script>
const grFetchItemsUrl = '{{ route("goods-receipt.fetch-source-items") }}';
const grStoreUrl = '{{ route("goods-receipt.store") }}';
const grShowUrl = '{{ route("goods-receipt.show", "__ID__") }}';
const grSaveLabel = 'Receive &amp; Post';
const grProductUrl = '{{ route("product-management.index") }}';
const grBaseSymbol = @json($baseSymbol);
// Kurs penerimaan per mata uang asing: { USD: { symbol, known, rate (master), stale, updated_at } }.
let grFx = {};
let grDirty = false;
let grSaving = false;
// Satuan yang wajar untuk barang ber-SN (dicatat per unit).
const grUnitUnits = ['unit', 'units', 'pcs', 'pc', 'ea', 'set', 'buah', 'bh', 'nos', 'no', 'lot'];

function grEsc(s) {
    return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function grSource() {
    var parts = String($('#gr-source').val() || '').split(':');
    return parts.length === 2 ? { type: parts[0], id: parts[1] } : null;
}

function grMarkDirty() { grDirty = true; $('#gr-dirty').show(); }

function grEmpty(message) {
    $('#gr-items-body').html('<tr><td colspan="5" class="gr-empty">' + message + '</td></tr>');
    $('#gr-tools').hide();
    grUpdateSummary();
}

// Semua barang masuk gudang tujuan. Barang yang belum terhubung master produk
// tidak bisa masuk stok, jadi otomatis dicatat Langsung Pakai.
function grDestination(it) {
    return it.master_product_id ? 'warehouse' : 'direct';
}

// Rincian per baris. Barang ber-SN: SN, batch / No. MFG, kedaluwarsa, garansi, catatan.
// Material: hanya catatan (masuk ke satu saldo; batch/kedaluwarsa disembunyikan sementara).
function grDetailRow(it) {
    var unit = String(it.unit || '').trim().toLowerCase();
    var warn = it.serialized && ((unit && grUnitUnits.indexOf(unit) === -1) || it.remaining > 50)
        ? '<div class="mt-2" style="font-size:12px;color:#b45309">'
          + grEsc(it.product_code || 'This product') + ' is recorded as a serial item (one stock row per unit). If it is counted by quantity, change it to Material in the <a href="' + grProductUrl + '" target="_blank">master product</a>.</div>'
        : '';
    var html = '<tr class="gr-detail" data-for="' + it.source_line_id + '" style="display:none"><td></td><td colspan="4">';
    if (it.serialized) {
        html += '<div class="row g-2" style="font-size:12px">';
        html += '<div class="col-lg-6"><label class="form-label mb-0" style="font-size:11.5px">Serial numbers (<span class="gr-sn-count">0</span> of <span class="gr-sn-need">' + it.remaining + '</span>) — one per line, can be filled in later</label>'
            + '<textarea class="form-control form-control-sm gr-serials" rows="3" placeholder="Paste or scan SNs" aria-label="Serial numbers"></textarea></div>';
        html += '<div class="col-lg-6"><div class="row g-2">';
        html += '<div class="col-6"><label class="form-label mb-0" style="font-size:11.5px">Batch / No. MFG</label><input type="text" class="form-control form-control-sm gr-batch" maxlength="100" aria-label="Batch / No. MFG"></div>';
        html += '<div class="col-6"><label class="form-label mb-0" style="font-size:11.5px">Expiry</label><input type="date" class="form-control form-control-sm gr-expiry" aria-label="Expiry"></div>';
        html += '<div class="col-6"><label class="form-label mb-0" style="font-size:11.5px">Warranty until</label><input type="date" class="form-control form-control-sm gr-warranty" aria-label="Warranty until"></div>';
        html += '<div class="col-6"><label class="form-label mb-0" style="font-size:11.5px">Note</label><input type="text" class="form-control form-control-sm gr-item-note" maxlength="255" aria-label="Note"></div>';
        html += '</div></div></div>';
    } else {
        html += '<div class="gr-info mb-2">Material: qty is added to the balance of ' + grEsc(it.product_code || 'this product')
            + ' in the destination warehouse' + (it.project_label ? ' (project-owned)' : ' (free stock)') + '.</div>';
        html += '<label class="form-label mb-0" style="font-size:11.5px">Note</label><input type="text" class="form-control form-control-sm gr-item-note" maxlength="255" aria-label="Note">';
    }
    html += warn + '</td></tr>';
    return html;
}

function grSerialList(text) {
    return String(text || '').split(/[\r\n,;]+/).map(function(s) { return s.trim(); }).filter(function(s) { return s !== ''; });
}

function grToggleDetail(lineId, show) {
    var $d = $('#gr-items-body tr.gr-detail[data-for="' + lineId + '"]');
    var open = show === undefined ? !$d.is(':visible') : show;
    $d.toggle(open);
    $('#gr-items-body tr[data-line-id="' + lineId + '"] .gr-toggle i').toggleClass('fa-chevron-down', !open).toggleClass('fa-chevron-up', open);
}

function grRenderFx(currencies, sourceType) {
    grFx = {};
    var html = '';
    (currencies || []).forEach(function(c) {
        grFx[c.code] = c;
        html += '<div class="col-lg-3 col-md-4 gr-fx-col">';
        if (!c.known) {
            html += '<div class="form-label">Exchange Rate (1 ' + grEsc(c.code) + ' = ' + grEsc(grBaseSymbol) + ')</div>'
                + '<div class="gr-fx-error">' + grEsc(c.code) + ' is not registered in the Currency master, so these goods cannot be received. Add the currency (with its rate) first.</div>';
        } else {
            html += '<label class="form-label" for="gr-fx-' + grEsc(c.code) + '">Exchange Rate (1 ' + grEsc(c.code) + ' = ' + grEsc(grBaseSymbol) + ')</label>'
                + '<input type="text" class="form-control text-end gr-fx-rate" id="gr-fx-' + grEsc(c.code) + '" data-number="rate" data-code="' + grEsc(c.code) + '" value="' + Num.format(c.rate, 2) + '"'
                + (c.locked ? ' readonly tabindex="-1" style="background:var(--bg-soft, #f3f4f6)"' : '') + '>';
            html += '<div class="gr-fx-meta">' + (c.locked
                ? (sourceType === 'dp' ? 'Set on the direct purchase' : 'Set on the purchase order')
                : 'Master rate — adjust to the rate on the received date') + '</div>';
        }
        html += '</div>';
    });
    $('.gr-fx-col').remove();
    $('#gr-date-col').after(html);
}

// Kurs yang dipakai untuk satu kode mata uang (base = 1; tidak dikenal = null).
function grRateOf(code) {
    code = String(code || '').trim().toUpperCase();
    if (!grFx[code]) return 1;
    if (!grFx[code].known) return null;
    return Num.val($('.gr-fx-rate[data-code="' + code + '"]'));
}

function grRenderSource(src, count) {
    if (!src) { $('#gr-source-strip').hide(); return; }
    $('#gr-src-supplier').text(src.supplier_name || '—');
    $('#gr-src-number').text((src.type === 'dp' ? 'Direct Purchase ' : 'PO ') + (src.number || ''));
    $('#gr-src-purpose-wrap').toggle(!!src.purpose);
    $('#gr-src-purpose').text(src.purpose || '');
    $('#gr-src-lines').text(count);
    $('#gr-source-strip').css('display', 'flex');
}

function grLoadItems() {
    var source = grSource();
    grRenderFx([]);
    //grRenderSource(null);
    if (!source) {
        grEmpty('Select a purchase document in step 1 to show its items.');
        return;
    }

    $('#gr-items-body').html('<tr><td colspan="5" class="gr-empty"><span class="spinner-border spinner-border-sm me-1"></span> Loading items...</td></tr>');

    $.get(grFetchItemsUrl, { source_type: source.type, source_id: source.id })
        .done(function(res) {
            var items = res.items || [];
            //grRenderSource(res.source, items.length);
            grRenderFx(res.currencies, res.source && res.source.type);
            if (!items.length) {
                grEmpty('All items of this document have been received.');
                return;
            }
            var html = '';
            items.forEach(function(it) {
                var name = it.product_name || $('<div>').html(it.description || '').text() || '—';
                var dest = grDestination(it);
                var kind = dest === 'direct' ? '<span class="gr-kind gr-kind-direct">Direct Use</span>'
                    : (it.serialized ? '<span class="gr-kind gr-kind-serial">Serial</span>' : '<span class="gr-kind gr-kind-material">Material</span>');
                html += '<tr data-line-id="' + it.source_line_id + '" data-remaining="' + it.remaining + '" data-serialized="' + (it.serialized ? 1 : 0) + '" data-dest="' + dest + '"'
                    + ' data-priced="' + (it.price == null ? 0 : 1) + '" data-currency="' + grEsc(it.currency || '') + '">';
                html += '<td><input type="checkbox" class="form-check-input gr-check" checked aria-label="Receive ' + grEsc(name) + '"></td>';
                html += '<td><div class="gr-name">' + grEsc(name) + '</div>'
                    + '<div class="gr-sub">' + grEsc(it.product_code || it.part_number || '') + ' ' + kind
                    + '<span class="gr-sn-tag" style="display:none"></span></div>'
                    + (dest === 'warehouse'
                        ? '<button type="button" class="gr-toggle" data-line="' + it.source_line_id + '">' + (it.serialized ? 'SN &amp; details' : 'Add note') + ' <i class="fa fa-chevron-down" style="font-size:9px"></i></button>'
                        : '')
                    + '</td>';
                html += '<td>' + (it.project_label
                    ? '<span class="gr-own-project">' + grEsc(it.project_label) + '</span>'
                    : '<span class="gr-sub">Free stock</span>') + '</td>';
                html += '<td><div class="gr-qty-wrap">'
                    + '<input type="text" inputmode="numeric" class="form-control form-control-sm gr-qty" value="' + it.remaining + '" aria-label="Qty received for ' + grEsc(name) + '">'
                    + '<span class="gr-of">of ' + it.remaining + ' ' + grEsc(it.unit || '') + (it.ordered !== it.remaining ? '<br><span style="font-size:10.5px">ordered ' + it.ordered + '</span>' : '') + '</span>'
                    + '</div></td>';
                html += '<td class="text-center gr-status-cell"></td>';
                html += '</tr>';
                html += grDetailRow(it);
            });
            $('#gr-items-body').html(html);
            $('#gr-tools').show();
            // Barang ber-SN: rincian terbuka supaya SN mudah diisi.
            $('#gr-items-body tr[data-serialized="1"][data-dest="warehouse"]').each(function() {
                grToggleDetail($(this).attr('data-line-id'), true);
            });
            grSuggestWarehouse();
            grUpdateSummary();
        })
        .fail(function(xhr) {
            grEmpty(grEsc(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to load items.'));
        });
}

// Hanya satu gudang yang bisa dipilih: isi otomatis bila belum dipilih.
function grSuggestWarehouse() {
    if ($('#gr-warehouse').val()) return;
    var $opts = $('#gr-warehouse option').filter(function() { return this.value !== ''; });
    if ($opts.length === 1) $('#gr-warehouse').val($opts.val()).trigger('change');
}

// Status per baris: Full / Partial / qty tidak valid / dilewati.
function grRowStatus($row) {
    var on = $row.find('.gr-check').is(':checked');
    var qty = parseInt($row.find('.gr-qty').val(), 10) || 0;
    var remaining = parseInt($row.attr('data-remaining'), 10) || 0;
    var html;
    if (!on) html = '<span class="gr-status gr-st-off">Skipped</span>';
    else if (qty < 1) html = '<span class="gr-status gr-st-bad">Enter qty</span>';
    else if (qty > remaining) html = '<span class="gr-status gr-st-bad">Max ' + remaining + '</span>';
    else if (qty === remaining) html = '<span class="gr-status gr-st-full">Full</span>';
    else html = '<span class="gr-status gr-st-partial">Partial</span>';
    $row.find('.gr-status-cell').html(html);
    $row.find('.gr-qty').toggleClass('is-invalid', on && (qty < 1 || qty > remaining)).prop('disabled', !on);
    $row.toggleClass('gr-row-off', !on);

    // Penanda SN di bawah nama: berapa SN yang sudah diisi.
    if ($row.attr('data-serialized') === '1' && $row.attr('data-dest') === 'warehouse') {
        var sn = grSerialList($('#gr-items-body tr.gr-detail[data-for="' + $row.attr('data-line-id') + '"] .gr-serials').val()).length;
        $row.find('.gr-sn-tag').toggle(on).text('· SN ' + sn + '/' + qty);
    }
}

function grUpdateSummary() {
    var lines = 0, direct = 0, material = 0, serial = 0, total = 0, invalid = 0;
    $('#gr-items-body tr[data-line-id]').each(function() {
        var $row = $(this);
        total++;
        grRowStatus($row);
        if (!$row.find('.gr-check').is(':checked')) return;
        lines++;
        var qty = parseInt($row.find('.gr-qty').val(), 10) || 0;
        var remaining = parseInt($row.attr('data-remaining'), 10) || 0;
        if (qty < 1 || qty > remaining) invalid++;
        if ($row.attr('data-dest') === 'direct') { direct++; return; }
        if ($row.attr('data-serialized') === '1') serial += qty; else material++;
    });
    var toStock = lines - direct;
    $('#gr-sum-lines').text(lines);
    $('#gr-sum-material').text(material);
    $('#gr-sum-serial').text(serial);
    $('#gr-sum-direct').text(direct);
    $('.gr-sum-direct-row').toggle(direct > 0);
    $('.gr-wh-required').toggle(toStock > 0 || lines === 0);
    $('#gr-check-all').prop('checked', total > 0 && lines === total).prop('indeterminate', lines > 0 && lines < total);

    // Bilah aksi: dokumen -> gudang tujuan.
    var source = $('#gr-source').val() ? $('#gr-source option:selected').text().trim() : '';
    var wh = $('#gr-warehouse').val() ? $('#gr-warehouse option:selected').text().trim() : '';
    $('#gr-ab-supplier').text(source ? 'Receiving from' : 'No document selected');
    $('#gr-ab-route').html(source
        ? grEsc(source) + ' &rarr; '
            + (wh ? grEsc(wh) : (toStock > 0 ? '<span style="color:var(--danger)">select a warehouse</span>' : '—'))
        : '—');

    // Penanda langkah.
    var step1 = !!source && (!!wh || (lines > 0 && toStock === 0));
    $('#gr-step-1').toggleClass('is-done', step1);
    var step2 = lines > 0 && invalid === 0;
    $('#gr-step-2').toggleClass('is-done', step2);
    $('#gr-save-btn').prop('disabled', grSaving || lines === 0);
}

function grRatesPayload() {
    var rates = {};
    // Kurs dari PO ditentukan server; hanya kurs Direct Purchase yang dikirim.
    Object.keys(grFx).forEach(function(code) { if (grFx[code].known && !grFx[code].locked) rates[code] = grRateOf(code); });
    return rates;
}

// Pesan + tandai field yang salah (toast hanya pelengkap).
function grFail(message, $focus) {
    toastr.error(message);
    if (!$focus || !$focus.length) return;
    $focus.addClass('is-invalid');
    if ($focus.is('select')) $focus.select2('open'); else $focus.focus();
}

function grSave() {
    if (grSaving) return;
    var source = grSource();
    if (!source) { grFail('Select a purchase document.', $('#gr-source')); return; }

    var items = [];
    var ok = true;
    $('#gr-items-body tr[data-line-id]').each(function() {
        var $row = $(this);
        if (!$row.find('.gr-check').is(':checked')) return;
        var qty = parseInt($row.find('.gr-qty').val(), 10) || 0;
        var remaining = parseInt($row.attr('data-remaining'), 10) || 0;
        var label = $row.find('.gr-name').text();
        if (qty < 1) { ok = false; grFail(label + ': qty must be at least 1 (untick the line if it has not arrived).', $row.find('.gr-qty')); return false; }
        if (qty > remaining) { ok = false; grFail(label + ': qty exceeds the outstanding qty (' + remaining + ').', $row.find('.gr-qty')); return false; }
        var $detail = $('#gr-items-body tr.gr-detail[data-for="' + $row.attr('data-line-id') + '"]');
        var code = String($row.attr('data-currency') || '').toUpperCase();
        if ($row.attr('data-dest') === 'warehouse' && $row.attr('data-priced') === '1') {
            var rate = grRateOf(code);
            if (rate === null) { ok = false; grFail(label + ': currency ' + code + ' is not registered in the Currency master.'); return false; }
            if (!(rate > 0)) { ok = false; grFail('Enter a valid exchange rate for ' + code + '.', $('.gr-fx-rate[data-code="' + code + '"]')); return false; }
        }
        var serials = grSerialList($detail.find('.gr-serials').val());
        if (serials.length > qty) { ok = false; grToggleDetail($row.attr('data-line-id'), true); grFail(label + ': ' + serials.length + ' SNs entered for qty ' + qty + '.', $detail.find('.gr-serials')); return false; }
        items.push({
            source_line_id: $row.attr('data-line-id'),
            qty: qty,
            destination: $row.attr('data-dest'),
            serial_numbers: serials,
            batch_number: $detail.find('.gr-batch').val() || '',
            expiry_date: $detail.find('.gr-expiry').val() || '',
            warranty_until: $detail.find('.gr-warranty').val() || '',
            note: $detail.find('.gr-item-note').val() || ''
        });
    });
    if (!ok) return;
    if (!items.length) { grFail('Tick at least one item that arrived.'); return; }

    var needWarehouse = items.some(function(it) { return it.destination === 'warehouse'; });
    if (needWarehouse && !$('#gr-warehouse').val()) { grFail('Select a destination warehouse.', $('#gr-warehouse')); return; }

    var units = items.reduce(function(sum, it) { return sum + it.qty; }, 0);
    Swal.fire({
        title: 'Post this receipt?',
        html: items.length + ' line(s), ' + units + ' unit(s) will be received'
            + (needWarehouse ? ' into <strong>' + grEsc($('#gr-warehouse option:selected').text().trim()) + '</strong>' : '')
            + '.<br>Stock increases immediately; the receipt cannot be edited after posting.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, receive & post',
        cancelButtonText: 'Cancel'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        grSaving = true;
        $('#gr-save-btn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Posting...');
        $.ajax({
            url: grStoreUrl,
            method: 'POST',
            data: {
                source_type: source.type,
                source_id: source.id,
                warehouse_id: needWarehouse ? $('#gr-warehouse').val() : '',
                received_date: $('#gr-date').val(),
                note: $('#gr-note').val(),
                items: items,
                rates: grRatesPayload(),
                _token: '{{ csrf_token() }}'
            }
        }).done(function(res) {
            grDirty = false;
            toastr.success(res.message || 'Saved.');
            setTimeout(function() { window.location.href = grShowUrl.replace('__ID__', res.id); }, 800);
        }).fail(function(xhr) {
            var msg = 'Failed to save.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
            toastr.error(msg);
            grSaving = false;
            $('#gr-save-btn').html(grSaveLabel);
            grUpdateSummary();
        });
    });
}

$(document).ready(function() {
    $('#gr-source').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— Select PO / Direct Purchase —', allowClear: true })
        .on('change', function() { $(this).removeClass('is-invalid'); grDirty = false; $('#gr-dirty').hide(); grLoadItems(); });
    $('#gr-warehouse').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— Select Warehouse —', allowClear: true })
        .on('change', function() { $(this).removeClass('is-invalid'); grUpdateSummary(); });

    $('#gr-check-all').on('change', function() {
        $('#gr-items-body .gr-check').prop('checked', $(this).is(':checked')).trigger('change');
    });
    $('#gr-fill-all').on('click', function() {
        $('#gr-items-body tr[data-line-id]').each(function() {
            $(this).find('.gr-qty').val($(this).attr('data-remaining'));
            $(this).find('.gr-check').prop('checked', true).trigger('change');
        });
    });
    // Klik di mana saja pada baris untuk centang / batal (kecuali input & tombol).
    $(document).on('click', '#gr-items-body tr[data-line-id]', function(e) {
        if ($(e.target).closest('input, button, a, textarea, label').length) return;
        var $check = $(this).find('.gr-check');
        $check.prop('checked', !$check.is(':checked')).trigger('change');
    });
    $(document).on('click', '.gr-toggle', function() { grToggleDetail($(this).data('line')); });
    $(document).on('change', '.gr-check', function() {
        var $row = $(this).closest('tr');
        var on = $(this).is(':checked');
        if ($row.attr('data-dest') === 'warehouse') {
            $row.find('.gr-toggle').toggle(on);
            if (!on) grToggleDetail($row.attr('data-line-id'), false);
            else if ($row.attr('data-serialized') === '1') grToggleDetail($row.attr('data-line-id'), true);
        }
        grMarkDirty();
        grUpdateSummary();
    });
    $(document).on('input', '.gr-fx-rate', function() { $(this).removeClass('is-invalid'); grMarkDirty(); });
    $(document).on('input', '.gr-serials', function() {
        $(this).removeClass('is-invalid');
        $(this).closest('tr').find('.gr-sn-count').text(grSerialList($(this).val()).length);
        grMarkDirty();
        grUpdateSummary();
    });
    $(document).on('input', '.gr-batch, .gr-expiry, .gr-warranty, .gr-item-note, #gr-note', grMarkDirty);
    $(document).on('input', '.gr-qty', function() {
        var clean = $(this).val().replace(/\D/g, '');
        if (clean !== $(this).val()) $(this).val(clean);
        $('#gr-items-body tr.gr-detail[data-for="' + $(this).closest('tr').attr('data-line-id') + '"] .gr-sn-need').text(clean || 0);
        grMarkDirty();
        grUpdateSummary();
    });
    // Enter di qty: pindah ke baris berikutnya.
    $(document).on('keydown', '.gr-qty', function(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var $inputs = $('#gr-items-body .gr-qty:enabled');
        var $next = $inputs.eq($inputs.index(this) + 1);
        if ($next.length) $next.focus().select();
    });
    // Ctrl+S = posting.
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 's') {
            e.preventDefault();
            if (!$('#gr-save-btn').prop('disabled')) grSave();
        }
    });
    window.addEventListener('beforeunload', function(e) {
        if (grDirty && !grSaving && $('#gr-items-body tr[data-line-id]').length) { e.preventDefault(); e.returnValue = ''; }
    });

    if (grSource()) grLoadItems(); else grUpdateSummary();
});
</script>
@endsection
