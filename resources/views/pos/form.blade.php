@extends('layouts.app')

@section('title', $pos ? 'Edit POS' : 'Create POS')
@section('page-title', $pos ? 'Edit POS' : 'Create POS')

@section('styles')
<style>
    .pos-step { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: var(--accent); color: #fff; font-size: 11.5px; font-weight: 700; margin-right: 8px; flex-shrink: 0; }
    .pos-step.is-done { background: #065f46; }
    .pos-items td, .pos-items th { font-size: 12.5px; vertical-align: middle; }
    .pos-items .pi-desc { min-width: 260px; }
    .pos-items .pi-desc textarea { resize: vertical; min-height: 31px; }
    .pos-items .pi-qty { width: 90px; }
    .pos-items .pi-unit { width: 90px; }
    .pos-items .pi-price { width: 140px; }
    .pos-items .pi-sub { width: 140px; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .pos-items .pi-del { width: 42px; text-align: center; }
    .pos-ro { background: #f8fafc; }
    .pos-hint { font-size: 11.5px; color: var(--text-muted); }
    .pos-sum { display: flex; justify-content: space-between; padding: 5px 0; font-size: 13px; }
    .pos-sum .l { color: var(--text-muted); }
    .pos-sum .v { font-variant-numeric: tabular-nums; }
    .pos-sum.grand { border-top: 1px solid var(--card-border); margin-top: 4px; padding-top: 10px; font-size: 15px; font-weight: 700; }
    .pos-sum.grand .l { color: var(--text-primary); }
    .pos-match { border-radius: var(--radius-sm); padding: 8px 10px; font-size: 12.5px; margin: 10px 0 12px; }
    .pos-match strong { display: block; }
    .pos-match.ok { background: #dcfce7; color: #065f46; }
    .pos-match.bad { background: var(--danger-soft); color: #7f1d1d; }
    .pos-match.idle { background: #f3f4f6; color: #4b5563; }
    .pos-sticky { position: sticky; top: calc(var(--topbar-h, 60px) + 12px); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $pos ? 'Edit '.$pos->pos_number : 'Create POS' }}</h1>
        <p class="page-header-sub">Choose a quotation, adjust the item names for the customer, then save as Draft. The POS grand total must equal the quotation grand total.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $pos ? route('pos.show', $pos->id) : route('pos.index') }}" class="btn btn-secondary btn-sm">Back</a>
    </div>
</div>

<form id="pos-form" autocomplete="off" novalidate>
    <div class="row g-3 align-items-start">
        <div class="col-lg-8">
            <div class="card-custom fade-in mb-3">
                <div class="card-header-custom d-flex align-items-center"><span class="pos-step" id="pos-step-1">1</span>Quotation &amp; Customer</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="pos-quotation">Quotation <span class="text-danger">*</span></label>
                            <select class="form-select" id="pos-quotation">
                                <option value="">— Select Quotation —</option>
                                @foreach($quotations as $q)
                                    <option value="{{ $q['id'] }}"
                                        data-company="{{ $q['company_name'] }}"
                                        data-requires-dp="{{ $q['requires_dp'] ? 1 : 0 }}"
                                        data-total="{{ (float) $q['grand_total'] }}"
                                        @if($pos && $pos->quotation_id == $q['id']) selected @endif>{{ $q['label'] }}</option>
                                @endforeach
                            </select>
                            <div class="pos-hint mt-1">Only Approved / Finish quotations with a Profit Estimate. Items, discount and VAT are loaded from the quotation.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pos-date">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="pos-date" value="{{ $pos ? $pos->date->format('Y-m-d') : now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="pos-company">Customer</label>
                            <input type="text" class="form-control pos-ro" id="pos-company" readonly placeholder="Follows the quotation" value="{{ $pos?->company?->account_name ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <span class="form-label d-block">Payment Terms</span>
                            <span id="pos-dp-badge" style="color:var(--text-muted)">—</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-custom fade-in mb-3">
                <div class="card-header-custom d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <span class="d-flex align-items-center"><span class="pos-step" id="pos-step-2">2</span>Items <span class="ms-1" style="color:var(--text-muted);font-weight:400">(customer naming)</span></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pos-add-item">Add Item</button>
                </div>
                <div class="card-body-custom p-2">
                    <div class="table-responsive">
                        <table class="table table-sm pos-items mb-0">
                            <thead>
                                <tr>
                                    <th style="width:34px" class="text-center">#</th>
                                    <th class="pi-desc">Description <span class="text-danger">*</span></th>
                                    <th class="pi-qty text-end">Qty <span class="text-danger">*</span></th>
                                    <th class="pi-unit">Unit</th>
                                    <th class="pi-price text-end">Price <span class="text-danger">*</span></th>
                                    <th class="pi-sub">Subtotal</th>
                                    <th class="pi-del"><span class="visually-hidden">Remove</span></th>
                                </tr>
                            </thead>
                            <tbody id="pos-items-rows"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-custom fade-in mb-3 pos-sticky">
                <div class="card-header-custom d-flex align-items-center"><span class="pos-step" id="pos-step-3">3</span>Discount, VAT &amp; Summary</div>
                <div class="card-body-custom">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="pos-disc-percent">Discount (%)</label>
                            <input type="text" inputmode="decimal" data-number="percent" class="form-control" id="pos-disc-percent" value="{{ $pos?->discount_percent ?? '' }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="pos-disc-amount">Discount (Rp)</label>
                            <input type="text" inputmode="decimal" data-number="money" class="form-control" id="pos-disc-amount" value="{{ $pos?->discount_amount ?? '' }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="pos-ppn-percent">VAT (%)</label>
                            <input type="text" inputmode="decimal" data-number="percent" class="form-control" id="pos-ppn-percent" value="{{ $pos ? $pos->ppn_percent : 11 }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="pos-ppn-amount">VAT (Rp)</label>
                            <input type="text" inputmode="decimal" data-number="money" class="form-control" id="pos-ppn-amount" value="{{ $pos?->ppn_amount ?? '' }}">
                        </div>
                    </div>
                    <div class="pos-hint mt-1 mb-2">When a percentage is filled in, the amount is calculated from it.</div>

                    <div class="pos-sum"><span class="l">Subtotal <span id="pos-t-lines"></span></span><span class="v" id="pos-t-subtotal">—</span></div>
                    <div class="pos-sum"><span class="l">Discount</span><span class="v" id="pos-t-discount">—</span></div>
                    <div class="pos-sum"><span class="l">VAT</span><span class="v" id="pos-t-ppn">—</span></div>
                    <div class="pos-sum grand"><span class="l">Grand Total</span><span class="v" id="pos-t-grand">—</span></div>

                    <div class="pos-match idle" id="pos-match" role="status"><strong>No quotation yet</strong>Select a quotation to compare the totals.</div>

                    <button type="button" class="btn btn-accent w-100" id="pos-save">Save as Draft</button>
                    <div class="pos-hint text-center mt-1">Ctrl+S to save</div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<script>
const posStoreUrl = '{{ route("pos.store") }}';
const posUpdateUrl = '{{ $pos ? route("pos.update", $pos->id) : "" }}';
const posQuotationItemsUrl = '{{ route("pos.quotation-items", "__ID__") }}';
const posShowUrl = '{{ route("pos.show", "__ID__") }}';
const posIsEdit = {{ $pos ? 'true' : 'false' }};
@php
    $initialItems = $pos ? $pos->items->map(fn ($i) => [
        'quotation_item_id' => $i->quotation_item_id,
        'description' => $i->description,
        'qty' => $i->qty,
        'unit' => $i->unit,
        'unit_price' => $i->unit_price,
    ])->values()->all() : [];
@endphp
const posInitialItems = @json($initialItems);
const posSaveLabel = 'Save as Draft';
// Toleransi selisih pembulatan, sama dengan validasi server.
const posTolerance = 1;

let posItems = posInitialItems.slice();
let posDirty = false;
let posSaving = false;

function posEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function posNum(v) { return window.Num.parse(v); }
function posNumOrNull(v) { return (v == null || v === '') ? null : posNum(v); }
function posMoney(v) { return 'Rp ' + window.Num.money(v); }
function posRound(v) { return Math.round(v * 100) / 100; }

function posDpBadge(requiresDp) {
    return requiresDp
        ? '<span class="status-badge" style="background:#fef3c7;color:#92400e;">DP required</span>'
        : '<span class="status-badge" style="background:#dcfce7;color:#065f46;">No DP</span>';
}

function posRenderItems() {
    const body = $('#pos-items-rows');
    if (!posItems.length) {
        body.html('<tr><td colspan="7" class="text-center py-3" style="color:var(--text-muted)">'
            + ($('#pos-quotation').val() ? 'No items yet. Click Add Item.' : 'Select a quotation to load its items.') + '</td></tr>');
        posRecompute();
        return;
    }
    let html = '';
    posItems.forEach(function(item, idx) {
        html += '<tr data-idx="' + idx + '">'
            + '<td class="text-center" style="color:var(--text-muted)">' + (idx + 1) + '</td>'
            + '<td class="pi-desc"><textarea class="form-control form-control-sm pi-desc-input" rows="1" aria-label="Item ' + (idx + 1) + ' description" placeholder="Item name for the customer">' + posEsc(item.description) + '</textarea></td>'
            + '<td class="pi-qty"><input type="text" inputmode="decimal" data-number="decimal" class="form-control form-control-sm text-end pi-qty-input" aria-label="Item ' + (idx + 1) + ' qty" value="' + posEsc(item.qty) + '"></td>'
            + '<td class="pi-unit"><input type="text" class="form-control form-control-sm pi-unit-input" aria-label="Item ' + (idx + 1) + ' unit" value="' + posEsc(item.unit) + '" placeholder="unit"></td>'
            + '<td class="pi-price"><input type="text" inputmode="decimal" data-number="money" class="form-control form-control-sm text-end pi-price-input" aria-label="Item ' + (idx + 1) + ' price" value="' + posEsc(item.unit_price) + '"></td>'
            + '<td class="pi-sub">' + posMoney(posNum(item.qty) * posNum(item.unit_price)) + '</td>'
            + '<td class="pi-del"><button type="button" class="btn btn-sm btn-outline-danger btn-icon pi-del-btn" title="Remove item" aria-label="Remove item ' + (idx + 1) + '"><i class="fa fa-trash"></i></button></td>'
            + '</tr>';
    });
    body.html(html);
    window.Num.formatAll('#pos-items-rows');
    posRecompute();
}

// Simpan isian baris ke posItems sebelum render ulang (tambah/hapus baris).
function posSyncFromDom() {
    $('#pos-items-rows tr[data-idx]').each(function() {
        const item = posItems[$(this).data('idx')];
        if (!item) return;
        item.description = $(this).find('.pi-desc-input').val() || '';
        item.qty = posNum($(this).find('.pi-qty-input').val());
        item.unit = $(this).find('.pi-unit-input').val() || '';
        item.unit_price = posNum($(this).find('.pi-price-input').val());
    });
}

function posCollect() {
    posSyncFromDom();
    return posItems
        .filter(function(i) { return String(i.description || '').trim() !== '' || posNum(i.qty) > 0 || posNum(i.unit_price) > 0; })
        .map(function(i) {
            return { quotation_item_id: i.quotation_item_id, description: i.description, qty: posNum(i.qty), unit: i.unit || '', unit_price: posNum(i.unit_price) };
        });
}

function posTotals() {
    let subtotal = 0;
    const items = posCollect();
    items.forEach(function(i) { subtotal += posNum(i.qty) * posNum(i.unit_price); });
    subtotal = posRound(subtotal);
    const discPercent = posNum($('#pos-disc-percent').val());
    const discount = posRound(discPercent > 0 ? subtotal * discPercent / 100 : posNum($('#pos-disc-amount').val()));
    const netto = posRound(subtotal - discount);
    const ppnPercent = posNum($('#pos-ppn-percent').val());
    const ppn = posRound(ppnPercent > 0 ? netto * ppnPercent / 100 : posNum($('#pos-ppn-amount').val()));
    return { lines: items.length, subtotal: subtotal, discount: discount, ppn: ppn, grand: posRound(netto + ppn) };
}

function posRecompute() {
    const t = posTotals();
    const opt = $('#pos-quotation option:selected');
    const hasQuotation = !!opt.val();
    const target = Number(opt.data('total') || 0);
    const diff = posRound(t.grand - target);
    const matched = hasQuotation && Math.abs(diff) <= posTolerance;

    $('#pos-t-lines').text(t.lines ? '(' + t.lines + ' item)' : '');
    $('#pos-t-subtotal').text(t.lines ? posMoney(t.subtotal) : '—');
    $('#pos-t-discount').text(t.lines ? (t.discount > 0 ? '-' : '') + posMoney(t.discount) : '—');
    $('#pos-t-ppn').text(t.lines ? posMoney(t.ppn) : '—');
    $('#pos-t-grand').text(t.lines ? posMoney(t.grand) : '—');

    const box = $('#pos-match').removeClass('ok bad idle');
    if (!hasQuotation) {
        box.addClass('idle').html('<strong>No quotation yet</strong>Select a quotation to compare the totals.');
    } else if (matched) {
        box.addClass('ok').html('<strong>Matches the quotation</strong>Quotation grand total ' + posMoney(target) + '.');
    } else {
        box.addClass('bad').html('<strong>Does not match the quotation</strong>Quotation grand total ' + posMoney(target) + ', difference '
            + (diff > 0 ? '+' : '-') + posMoney(Math.abs(diff)) + '. Adjust qty, price, discount or VAT.');
    }

    // Penanda langkah.
    $('#pos-step-1').toggleClass('is-done', hasQuotation && !!$('#pos-date').val());
    $('#pos-step-2').toggleClass('is-done', t.lines > 0 && posItemsValid(false));
    $('#pos-step-3').toggleClass('is-done', matched);
    $('#pos-save').prop('disabled', posSaving);
}

// Validasi baris item; mark = tandai field yang salah.
function posItemsValid(mark) {
    let ok = true;
    $('#pos-items-rows tr[data-idx]').each(function() {
        const desc = $(this).find('.pi-desc-input');
        const qty = $(this).find('.pi-qty-input');
        const price = $(this).find('.pi-price-input');
        const blank = !desc.val().trim() && !(posNum(qty.val()) > 0) && !(posNum(price.val()) > 0);
        if (blank) return;
        const badDesc = !desc.val().trim();
        const badQty = !(posNum(qty.val()) > 0);
        const badPrice = price.val() === '' || posNum(price.val()) < 0;
        if (mark) { desc.toggleClass('is-invalid', badDesc); qty.toggleClass('is-invalid', badQty); price.toggleClass('is-invalid', badPrice); }
        if (badDesc || badQty || badPrice) ok = false;
    });
    return ok;
}

function posSyncHeader() {
    const opt = $('#pos-quotation option:selected');
    $('#pos-company').val(opt.val() ? (opt.data('company') || '') : '');
    $('#pos-dp-badge').html(opt.val() ? posDpBadge(!!Number(opt.data('requires-dp'))) : '—');
}

function posLoadQuotation() {
    const id = $('#pos-quotation').val();
    if (!id) { posItems = []; posRenderItems(); return; }
    $('#pos-items-rows').html('<tr><td colspan="7" class="text-center py-3" style="color:var(--text-muted)"><span class="spinner-border spinner-border-sm me-1"></span>Loading quotation items…</td></tr>');
    $.get(posQuotationItemsUrl.replace('__ID__', id)).done(function(res) {
        $('#pos-disc-percent').val(res.discount_percent ?? '');
        $('#pos-disc-amount').val(res.discount_amount ?? '');
        $('#pos-ppn-percent').val(res.ppn_percent ?? '');
        $('#pos-ppn-amount').val(res.ppn_amount ?? '');
        posItems = (res.data || []).map(function(r) {
            return { quotation_item_id: r.quotation_item_id, description: r.description, qty: r.qty, unit: r.unit, unit_price: r.unit_price };
        });
        window.Num.formatAll('#pos-form');
        posRenderItems();
    }).fail(function(xhr) {
        posItems = [];
        posRenderItems();
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to load quotation items.');
    });
}

let posPrevQuotation = $('#pos-quotation').val();

function posChangeQuotation() {
    const next = $('#pos-quotation').val();
    const apply = function() {
        posPrevQuotation = next;
        posDirty = true;
        $('#pos-quotation').removeClass('is-invalid').next('.select2-container').find('.select2-selection').removeClass('is-invalid');
        posSyncHeader();
        posLoadQuotation();
    };
    // Ganti quotation menimpa item yang sudah ada -> konfirmasi dulu.
    if (posPrevQuotation && posCollect().length && next !== posPrevQuotation) {
        Swal.fire({
            title: 'Change quotation?',
            text: 'Items, discount and VAT will be replaced with the new quotation. Item names you have changed will be lost.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, change',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) { apply(); return; }
            $('#pos-quotation').val(posPrevQuotation).trigger('change.select2');
        });
        return;
    }
    apply();
}

$(document).on('input', '.pi-desc-input, .pi-qty-input, .pi-price-input, .pi-unit-input', function() {
    posDirty = true;
    $(this).removeClass('is-invalid');
    const tr = $(this).closest('tr[data-idx]');
    tr.find('.pi-sub').text(posMoney(posNum(tr.find('.pi-qty-input').val()) * posNum(tr.find('.pi-price-input').val())));
    posRecompute();
});
// Enter di qty / harga pindah ke baris berikutnya.
$(document).on('keydown', '.pi-qty-input, .pi-price-input', function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const cls = $(this).hasClass('pi-qty-input') ? '.pi-qty-input' : '.pi-price-input';
    $(this).closest('tr').next('tr').find(cls).trigger('focus').trigger('select');
});
$(document).on('click', '.pi-del-btn', function() {
    posSyncFromDom();
    posItems.splice($(this).closest('tr[data-idx]').data('idx'), 1);
    posDirty = true;
    posRenderItems();
});
$(document).on('click', '#pos-add-item', function() {
    posSyncFromDom();
    posItems.push({ quotation_item_id: null, description: '', qty: 1, unit: '', unit_price: 0 });
    posDirty = true;
    posRenderItems();
    $('#pos-items-rows tr[data-idx]').last().find('.pi-desc-input').trigger('focus');
});
$(document).on('input', '#pos-disc-percent, #pos-disc-amount, #pos-ppn-percent, #pos-ppn-amount', function() { posDirty = true; posRecompute(); });
$(document).on('change', '#pos-date', function() { posDirty = true; $(this).removeClass('is-invalid'); posRecompute(); });

function posSave() {
    if (posSaving) return;
    const quotationId = $('#pos-quotation').val();
    const date = $('#pos-date').val();
    const items = posCollect();

    if (!quotationId) {
        $('#pos-quotation').next('.select2-container').find('.select2-selection').addClass('is-invalid');
        $('#pos-quotation').select2('open');
        toastr.error('Please select a quotation.');
        return;
    }
    if (!date) { $('#pos-date').addClass('is-invalid').trigger('focus'); toastr.error('Date is required.'); return; }
    if (!items.length) { toastr.error('At least one item is required.'); return; }
    if (!posItemsValid(true)) {
        $('#pos-items-rows .is-invalid').first().trigger('focus');
        toastr.error('Complete the description, qty (> 0) and price of the items marked in red.');
        return;
    }
    const target = Number($('#pos-quotation option:selected').data('total') || 0);
    if (Math.abs(posTotals().grand - target) > posTolerance) {
        toastr.error('The POS grand total does not match the quotation yet.');
        return;
    }

    const payload = {
        date: date,
        quotation_id: quotationId,
        discount_percent: posNumOrNull($('#pos-disc-percent').val()),
        discount_amount: posNumOrNull($('#pos-disc-amount').val()),
        ppn_percent: posNumOrNull($('#pos-ppn-percent').val()),
        ppn_amount: posNumOrNull($('#pos-ppn-amount').val()),
        items: items,
    };

    posSaving = true;
    $('#pos-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');
    $.ajax({
        url: posIsEdit ? posUpdateUrl : posStoreUrl,
        method: posIsEdit ? 'PUT' : 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        data: JSON.stringify(payload)
    }).done(function(res) {
        posDirty = false;
        toastr.success(res.message || 'Saved.');
        setTimeout(function() { window.location.href = posShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to save.';
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        posSaving = false;
        $('#pos-save').prop('disabled', false).text(posSaveLabel);
    });
}

$(document).ready(function() {
    $('#pos-quotation').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— Select Quotation —' })
        .on('change', posChangeQuotation);
    posSyncHeader();
    window.Num.formatAll('#pos-form');
    posRenderItems();
    $('#pos-save').on('click', posSave);

    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) { e.preventDefault(); posSave(); }
    });
    window.addEventListener('beforeunload', function(e) {
        if (!posDirty || posSaving) return;
        e.preventDefault();
        e.returnValue = '';
    });
});
</script>
@endsection
