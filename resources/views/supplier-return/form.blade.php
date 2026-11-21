@extends('layouts.app')

@section('title', 'New Supplier Return')
@section('page-title', 'New Supplier Return')

@section('styles')
<style>
    .rt-card-title { font-weight: 600; }
    .rt-card-hint { font-size: 12px; color: var(--text-muted); }
    .rt-hint { font-size: 12px; color: var(--text-muted); }
    .is-invalid + .select2-container .select2-selection { border-color: var(--danger, #dc3545) !important; }

    /* Strip info PO */
    .rt-po-strip { display: none; margin-top: 14px; padding: 10px 14px; border-radius: 8px; background: var(--accent-subtle, #f0fdf4); border: 1px solid var(--card-border); font-size: 12.5px; gap: 6px 22px; flex-wrap: wrap; }
    .rt-po-strip .k { font-size: 11px; color: var(--text-muted); }
    .rt-po-strip .v { font-weight: 600; }

    /* Satu blok per baris PO */
    .rt-line { border: 1px solid var(--card-border); border-radius: 10px; margin-bottom: 10px; background: var(--card, #fff); }
    .rt-line.is-active { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent) inset; }
    .rt-line.is-invalid-line { border-color: var(--danger, #dc3545); box-shadow: 0 0 0 1px var(--danger, #dc3545) inset; }
    .rt-line-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; padding: 10px 14px; }
    .rt-line-name { font-weight: 600; font-size: 13.5px; color: var(--text-primary); }
    .rt-line-desc { font-size: 12px; color: var(--text-muted); max-width: 640px; }
    .rt-line-desc p { margin: 0; }
    .rt-line-meta { display: flex; gap: 14px; font-size: 12px; color: var(--text-muted); margin-top: 4px; flex-wrap: wrap; }
    .rt-line-meta b { color: var(--text-primary); font-weight: 600; }
    .rt-status { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; background: #f3f4f6; color: #6b7280; }
    .rt-status.on { background: #dcfce7; color: #065f46; }
    .rt-status.bad { background: #fee2e2; color: #b42318; }
    .rt-line-body { border-top: 1px dashed var(--card-border); padding: 10px 14px 12px; display: grid; gap: 12px; }
    .rt-sub-title { font-size: 11.5px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .03em; margin-bottom: 4px; }
    .rt-sub-title span { text-transform: none; font-weight: 400; letter-spacing: 0; }
    .rt-direct-wrap { display: flex; align-items: center; gap: 8px; }
    .rt-direct { width: 90px; text-align: center; }
    .rt-locked { margin-top: 6px; font-size: 12px; color: var(--text-muted); }
    .rt-locked summary { cursor: pointer; }
    .rt-locked ul { margin: 6px 0 0; padding-left: 18px; }
    .rt-empty { text-align: center; padding: 34px 10px; color: var(--text-muted); font-size: 13px; }

    /* Bilah aksi menempel di bawah */
    .rt-actionbar { position: sticky; bottom: 0; z-index: 20; margin-top: 16px; background: var(--card, #fff); border: 1px solid var(--card-border, #e5e7eb); border-radius: 10px; box-shadow: 0 -6px 18px rgba(15, 23, 42, .08); padding: 10px 14px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .rt-ab-info { font-size: 12.5px; min-width: 0; flex: 1 1 240px; }
    .rt-ab-info .l { font-size: 11px; color: var(--text-muted); }
    .rt-ab-info .v { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rt-ab-stats { display: flex; gap: 18px; }
    .rt-ab-stat { text-align: center; }
    .rt-ab-stat .n { font-size: 17px; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .rt-ab-stat .l { font-size: 11px; color: var(--text-muted); }
    .rt-ab-actions { display: flex; gap: 8px; margin-left: auto; align-items: center; }
    .rt-ab-dirty { font-size: 11.5px; color: #b45309; display: none; }
    @media (max-width: 767.98px) { .rt-ab-actions { margin-left: 0; width: 100%; justify-content: flex-end; } }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">New Supplier Return</h1>
        <p class="page-header-sub">Choose the PO, then pick what goes back to the supplier. Only goods that have been received and <strong>not yet billed</strong> can be returned — for billed goods, void the bill first.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('supplier-return.index') }}" class="btn btn-secondary btn-sm">Back</a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="rt-card-title">Return Information</span>
        <span class="rt-card-hint">Stock decreases as soon as the return is posted</span>
    </div>
    <div class="card-body-custom">
        <div class="row g-3">
            <div class="col-lg-6">
                <label class="form-label" for="rt-po">Purchase Order <span class="text-danger">*</span></label>
                <select id="rt-po" class="form-select" style="width:100%">
                    <option value="">— Select PO —</option>
                    @foreach($purchaseOrders as $po)
                        <option value="{{ $po->id }}" @selected($preselectPoId === $po->id)>{{ $po->po_number }} — {{ $po->supplier_name }}</option>
                    @endforeach
                </select>
                @if($purchaseOrders->isEmpty())
                    <div class="rt-hint mt-1">No PO has received goods that are still unbilled.</div>
                @endif
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="rt-warehouse">Source Warehouse</label>
                <select id="rt-warehouse" class="form-select">
                    <option value="">All warehouses</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                    @endforeach
                </select>
                <small class="rt-hint">Optional — narrows the stock rows shown.</small>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="rt-date">Return Date <span class="text-danger">*</span></label>
                <input type="date" id="rt-date" class="form-control" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="rt-reason">Return Reason <span class="text-danger">*</span></label>
                <textarea id="rt-reason" class="form-control" rows="2" maxlength="2000" placeholder="Example: 2 sensor units cracked on arrival, supplier will resend"></textarea>
            </div>
        </div>

        <div class="rt-po-strip" id="rt-po-strip">
            <div><div class="k">Supplier</div><div class="v" id="rt-po-supplier">—</div></div>
            <div><div class="k">Lines on PO</div><div class="v" id="rt-po-lines">0</div></div>
            <div><div class="k">Returnable lines</div><div class="v" id="rt-po-returnable">0</div></div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="rt-card-title">Items to Return</span>
        <span class="rt-card-hint">Tick stock rows and / or enter a direct-use qty per item</span>
    </div>
    <div class="card-body-custom p-2">
        <div id="rt-lines"><div class="rt-empty">Select a PO to show the goods that can be returned.</div></div>
    </div>
</div>

<div class="rt-actionbar">
    <div class="rt-ab-info">
        <div class="l">Returning to</div>
        <div class="v" id="rt-ab-supplier">—</div>
    </div>
    <div class="rt-ab-stats">
        <div class="rt-ab-stat"><div class="n" id="rt-sum-lines">0</div><div class="l">Items</div></div>
        <div class="rt-ab-stat"><div class="n" id="rt-sum-stock">0</div><div class="l">From stock</div></div>
        <div class="rt-ab-stat"><div class="n" id="rt-sum-direct">0</div><div class="l">Direct use</div></div>
    </div>
    <div class="rt-ab-actions">
        <span class="rt-ab-dirty" id="rt-dirty">Not posted yet</span>
        <a href="{{ route('supplier-return.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        <button type="button" class="btn btn-primary btn-sm" id="rt-save" onclick="rtSave()" title="Ctrl+S" disabled>Post Return</button>
    </div>
</div>
@endsection

@section('scripts')
@include('partials.stock-pick')
<script>
const rtOptionsUrl = '{{ route("supplier-return.stock-options") }}';
let rtDirty = false;
let rtSaving = false;

function rtEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function rtFmt(n) { return Num.format(n, 4); }
function rtMarkDirty() { rtDirty = true; $('#rt-dirty').show(); }

function rtEmpty(message) {
    $('#rt-lines').html('<div class="rt-empty">' + message + '</div>');
    rtUpdate();
}

function rtLineHtml(l) {
    var html = '<div class="rt-line" data-id="' + l.purchase_order_item_id + '" data-max="' + l.returnable + '" data-direct-max="' + l.direct_returnable + '" data-unit="' + rtEsc(l.unit || '') + '">';
    html += '<div class="rt-line-head"><div style="min-width:0">'
        + '<div class="rt-line-name">' + rtEsc(l.part_number || '—') + '</div>'
        + (l.description ? '<div class="rt-line-desc">' + l.description + '</div>' : '')
        + '<div class="rt-line-meta"><span>Received ' + l.received + '</span><span>Billed ' + l.billed + '</span><span>Returnable <b>' + l.returnable + ' ' + rtEsc(l.unit || '') + '</b></span></div>'
        + '</div><span class="rt-status">Not selected</span></div>';
    html += '<div class="rt-line-body">';
    if (l.stock_returnable > 0) {
        html += '<div><div class="rt-sub-title">From stock <span>— max ' + l.stock_returnable + ', only stock rows received from this PO</span></div>'
            + '<div class="rt-picker" data-item="' + l.purchase_order_item_id + '"></div></div>';
    }
    if (l.direct_returnable > 0) {
        html += '<div><div class="rt-sub-title">Direct use <span>— goods that were received for direct use (not in stock)</span></div>'
            + '<div class="rt-direct-wrap"><input type="text" inputmode="numeric" class="form-control form-control-sm rt-direct" placeholder="0" aria-label="Direct-use return qty ' + rtEsc(l.part_number) + '">'
            + '<span class="rt-hint">of max ' + l.direct_returnable + ' ' + rtEsc(l.unit || '') + '</span></div></div>';
    }
    html += '</div></div>';
    return html;
}

function rtLoad() {
    var poId = $('#rt-po').val();
    $('#rt-po-strip').hide();
    if (!poId) { rtEmpty('Select a PO to show the goods that can be returned.'); return; }
    $('#rt-lines').html('<div class="rt-empty"><span class="spinner-border spinner-border-sm me-1"></span> Loading items...</div>');
    $.get('{{ route("supplier-return.returnable-lines") }}', { purchase_order_id: poId }).done(function(res) {
        var lines = res.lines || [];
        var open = lines.filter(function(l) { return l.returnable > 0; });
        var locked = lines.filter(function(l) { return l.returnable <= 0; });

        $('#rt-po-supplier').text($('#rt-po option:selected').text().split(' — ').slice(1).join(' — ') || '—');
        $('#rt-po-lines').text(lines.length);
        $('#rt-po-returnable').text(open.length);
        $('#rt-po-strip').css('display', 'flex');

        if (!open.length) {
            rtEmpty('Nothing on this PO can be returned — everything received has already been billed or returned.');
            return;
        }
        var html = open.map(rtLineHtml).join('');
        if (locked.length) {
            html += '<details class="rt-locked px-1"><summary>' + locked.length + ' item(s) on this PO cannot be returned</summary><ul>'
                + locked.map(function(l) { return '<li>' + rtEsc(l.part_number || '—') + ' — received ' + l.received + ', billed ' + l.billed + '</li>'; }).join('')
                + '</ul></details>';
        }
        $('#rt-lines').html(html);
        rtLoadPickers();
        rtUpdate();
    }).fail(function(xhr) {
        rtEmpty('<span class="text-danger">' + rtEsc(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to load.') + '</span>');
    });
}

function rtLoadPickers() {
    $('#rt-lines .rt-picker').each(function() {
        StockPick.load($(this), rtOptionsUrl, { purchase_order_item_id: $(this).data('item'), warehouse_id: $('#rt-warehouse').val() },
            { requireSerial: true, lang: 'en', onChange: rtUpdate });
    });
}

// Hitung per baris (stok + langsung pakai), status, dan ringkasan bilah aksi.
function rtLineState($line) {
    var id = $line.data('id');
    var picks = StockPick.picks($line.find('.rt-picker'));
    var fromStock = picks.reduce(function(s, p) { return s + p.qty; }, 0);
    var direct = parseInt($line.find('.rt-direct').val(), 10) || 0;
    var max = parseFloat($line.data('max')) || 0;
    var directMax = parseInt($line.data('direct-max'), 10) || 0;
    var error = direct > directMax ? 'Direct-use qty exceeds ' + directMax + '.'
        : (fromStock + direct > max ? 'Total exceeds the returnable qty (' + max + ').' : null);
    return { id: id, picks: picks, fromStock: fromStock, direct: direct, total: fromStock + direct, max: max, error: error };
}

function rtUpdate() {
    var lines = 0, stock = 0, direct = 0, invalid = 0;
    $('#rt-lines .rt-line').each(function() {
        var $line = $(this);
        var st = rtLineState($line);
        var $status = $line.find('.rt-status').removeClass('on bad');
        if (st.error) { $status.addClass('bad').text(st.error); invalid++; }
        else if (st.total > 0) { $status.addClass('on').text('Returning ' + rtFmt(st.total) + ' of ' + st.max + ' ' + $line.data('unit')); }
        else { $status.text('Not selected'); }
        $line.toggleClass('is-active', st.total > 0 && !st.error).toggleClass('is-invalid-line', !!st.error);
        $line.find('.rt-direct').toggleClass('is-invalid', st.direct > (parseInt($line.data('direct-max'), 10) || 0));
        if (st.total > 0) lines++;
        stock += st.fromStock;
        direct += st.direct;
    });
    $('#rt-sum-lines').text(lines);
    $('#rt-sum-stock').text(rtFmt(stock));
    $('#rt-sum-direct').text(rtFmt(direct));
    var po = $('#rt-po').val() ? $('#rt-po option:selected').text().trim() : '';
    $('#rt-ab-supplier').text(po || '—');
    $('#rt-save').prop('disabled', rtSaving || lines === 0 || invalid > 0);
}

function rtFail(message, $el) {
    toastr.error(message);
    if (!$el || !$el.length) return;
    $el.addClass('is-invalid');
    if ($el.is('select')) $el.select2('open'); else $el.focus();
}

function rtSave() {
    if (rtSaving) return;
    if (!$('#rt-po').val()) { rtFail('Select a PO.', $('#rt-po')); return; }
    if (!$('#rt-reason').val().trim()) { rtFail('Return reason is required.', $('#rt-reason')); return; }

    var items = [], firstError = null;
    $('#rt-lines .rt-line').each(function() {
        var st = rtLineState($(this));
        if (st.error && !firstError) firstError = { message: $(this).find('.rt-line-name').text() + ': ' + st.error, $line: $(this) };
        if (st.total > 0) items.push({ purchase_order_item_id: st.id, direct_qty: st.direct, picks: st.picks });
    });
    if (firstError) {
        toastr.error(firstError.message);
        firstError.$line[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    if (!items.length) { rtFail('Select stock rows or enter a direct-use qty for at least one item.'); return; }

    var units = items.reduce(function(s, it) { return s + it.direct_qty + it.picks.reduce(function(a, p) { return a + p.qty; }, 0); }, 0);
    Swal.fire({
        title: 'Post this return?',
        html: items.length + ' item(s), ' + rtFmt(units) + ' unit(s) go back to the supplier.<br>Stock decreases and the PO waits for replacement goods again. The return cannot be edited.',
        icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, post', cancelButtonText: 'Cancel'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        rtSaving = true;
        $('#rt-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Posting...');
        $.ajax({
            url: '{{ route("supplier-return.store") }}', method: 'POST', contentType: 'application/json',
            data: JSON.stringify({ purchase_order_id: $('#rt-po').val(), warehouse_id: $('#rt-warehouse').val() || null, return_date: $('#rt-date').val(), reason: $('#rt-reason').val().trim(), items: items }),
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        }).done(function(res) {
            rtDirty = false;
            toastr.success(res.message);
            setTimeout(function() { location.href = '{{ route("supplier-return.show", "__ID__") }}'.replace('__ID__', res.id); }, 700);
        }).fail(function(xhr) {
            var msg = 'Failed to post the return.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
            toastr.error(msg);
            rtSaving = false;
            $('#rt-save').text('Post Return');
            rtUpdate();
        });
    });
}

$(document).on('change', '#rt-po', function() { $(this).removeClass('is-invalid'); rtDirty = false; $('#rt-dirty').hide(); rtLoad(); });
$(document).on('change', '#rt-warehouse', function() { rtLoadPickers(); rtUpdate(); });
$(document).on('input', '.rt-direct', function() { this.value = this.value.replace(/\D/g, ''); rtMarkDirty(); rtUpdate(); });
// Penanda perubahan hanya dari interaksi pengguna (render awal pemilih stok tidak dihitung).
$(document).on('change input', '#rt-lines .sp-check, #rt-lines .sp-qty', rtMarkDirty);
$(document).on('input', '#rt-reason', function() { $(this).removeClass('is-invalid'); rtMarkDirty(); });
$(document).on('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 's') { e.preventDefault(); if (!$('#rt-save').prop('disabled')) rtSave(); }
});
window.addEventListener('beforeunload', function(e) {
    if (rtDirty && !rtSaving) { e.preventDefault(); e.returnValue = ''; }
});

$(document).ready(function() {
    $('#rt-po').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— Select PO —' });
    if ($('#rt-po').val()) rtLoad();
});
</script>
@endsection
