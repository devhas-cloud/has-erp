@extends('layouts.app')

@section('title', $pos ? 'Edit POS' : 'Buat POS')
@section('page-title', $pos ? 'Edit POS' : 'Buat POS')

@section('styles')
<style>
    .pos-items td, .pos-items th { font-size: 12.5px; vertical-align: middle; }
    .pos-items .pi-desc { min-width: 260px; }
    .pos-items .pi-qty { width: 90px; }
    .pos-items .pi-unit { width: 90px; }
    .pos-items .pi-price { width: 130px; }
    .pos-items .pi-sub { width: 130px; text-align: right; font-variant-numeric: tabular-nums; }
    .pos-items .pi-del { width: 42px; text-align: center; }
    .pos-total-row { font-size: 13px; }
    .pos-total-row .lbl { color: var(--text-muted); }
    .pos-total-row .val { text-align: right; font-variant-numeric: tabular-nums; }
    .pos-grand { font-size: 15px; font-weight: 700; }
    .pos-sticky { position: sticky; top: 1rem; }
    .pos-readonly { background: var(--bg-soft, #f8fafc); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $pos ? $pos->pos_number : 'Buat POS' }}</h1>
        <p class="page-header-sub">POS berbasis quotation — item = penamaan customer. Simpan sebagai Draft, lalu ajukan untuk approval.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $pos ? route('pos.show', $pos->id) : route('pos.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
</div>

<form id="pos-form" autocomplete="off">
    <div class="row g-3 align-items-start">
        <div class="col-lg-8">
            <div class="card-custom fade-in mb-3">
                <div class="card-header-custom">
                    <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Quotation &amp; Customer</span>
                </div>
                <div class="card-body-custom">
                    <div class="mb-3">
                        <label class="form-label" for="pos-quotation">Quotation <span class="text-danger">*</span></label>
                        <select class="form-select" id="pos-quotation">
                            <option value="">— Pilih Quotation —</option>
                            @foreach($quotations as $q)
                                <option value="{{ $q['id'] }}"
                                    data-company="{{ $q['company_name'] }}"
                                    data-requires-dp="{{ $q['requires_dp'] ? 1 : 0 }}"
                                    @if($pos && $pos->quotation_id == $q['id']) selected @endif>{{ $q['label'] }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">POS wajib berbasis quotation. Item &amp; customer mengikuti quotation terpilih.</div>
                    </div>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label" for="pos-date">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="pos-date" value="{{ $pos ? $pos->date->format('Y-m-d') : now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pos-company">Customer</label>
                            <input type="text" class="form-control pos-readonly" id="pos-company" readonly placeholder="Mengikuti quotation" value="{{ $pos?->company?->account_name ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block">Syarat Pembayaran</label>
                            <span id="pos-dp-badge">—</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-custom fade-in mb-3">
                <div class="card-header-custom d-flex align-items-center justify-content-between">
                    <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Item POS (penamaan customer)</span>
                    <button type="button" class="btn btn-sm btn-accent" id="pos-add-item"><i class="fa fa-plus me-1"></i>Tambah Item</button>
                </div>
                <div class="card-body-custom p-2">
                    <div class="table-responsive">
                        <table class="table table-sm pos-items mb-0">
                            <thead>
                                <tr>
                                    <th style="width:34px" class="text-center">#</th>
                                    <th class="pi-desc">Deskripsi (penamaan customer) <span class="text-danger">*</span></th>
                                    <th class="pi-qty text-end">Qty <span class="text-danger">*</span></th>
                                    <th class="pi-unit">Unit</th>
                                    <th class="pi-price text-end">Harga <span class="text-danger">*</span></th>
                                    <th class="pi-sub">Subtotal</th>
                                    <th class="pi-del"></th>
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
                <div class="card-header-custom">
                    <span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Diskon, PPN &amp; Ringkasan</span>
                </div>
                <div class="card-body-custom">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label" for="pos-disc-percent">Diskon (%)</label>
                            <input type="text" inputmode="decimal" data-number="percent" class="form-control" id="pos-disc-percent" value="{{ $pos?->discount_percent ?? '' }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="pos-disc-amount">Diskon (nominal)</label>
                            <input type="text" inputmode="decimal" data-number="money" class="form-control" id="pos-disc-amount" value="{{ $pos?->discount_amount ?? '' }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="pos-ppn-percent">PPN (%)</label>
                            <input type="text" inputmode="decimal" data-number="percent" class="form-control" id="pos-ppn-percent" value="{{ $pos ? $pos->ppn_percent : 11 }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="pos-ppn-amount">PPN (nominal)</label>
                            <input type="text" inputmode="decimal" data-number="money" class="form-control" id="pos-ppn-amount" value="{{ $pos?->ppn_amount ?? '' }}">
                        </div>
                    </div>
                    <hr>
                    <table class="table table-sm pos-total-row mb-3">
                        <tr><td class="lbl">Subtotal</td><td class="val" id="pos-t-subtotal">0</td></tr>
                        <tr><td class="lbl">Diskon</td><td class="val" id="pos-t-discount">0</td></tr>
                        <tr><td class="lbl">PPN</td><td class="val" id="pos-t-ppn">0</td></tr>
                        <tr class="pos-grand"><td class="lbl">Grand Total</td><td class="val" id="pos-t-grand">0</td></tr>
                    </table>
                    <button type="button" class="btn btn-accent w-100" id="pos-save"><i class="fa fa-save me-1"></i> Simpan</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<script>
const posStoreUrl = '{{ route("pos.store") }}';
const posUpdateUrl = '{{ route("pos.update", $pos?->id ?? "__ID__") }}';
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
const posSaveLabel = '{!! '<i class="fa fa-save me-1"></i> Simpan' !!}';

let posItems = posInitialItems.slice();

function posEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function posNum(v) { return window.Num.parse(v); }
function posNumOrNull(v) { return (v == null || v === '') ? null : posNum(v); }
function posMoney(v) { return 'Rp ' + window.Num.money(v); }

function posDpBadge(requiresDp) {
    return requiresDp
        ? '<span class="status-badge" style="background:#fef3c7;color:#92400e;">Perlu DP</span>'
        : '<span class="status-badge" style="background:#dcfce7;color:#166534;">Tanpa DP</span>';
}

function posRenderItems() {
    const body = $('#pos-items-rows');
    if (!posItems.length) {
        body.html('<tr><td colspan="7" class="text-center py-3" style="color:var(--text-muted)">Belum ada item. Pilih Quotation untuk memuat item.</td></tr>');
        return;
    }
    let html = '';
    posItems.forEach(function(item, idx) {
        html += '<tr data-idx="' + idx + '">'
            + '<td class="text-center" style="color:var(--text-muted)">' + (idx + 1) + '</td>'
            + '<td class="pi-desc"><textarea class="form-control form-control-sm pi-desc-input" rows="1" placeholder="Deskripsi item (penamaan customer)">' + posEsc(item.description) + '</textarea></td>'
            + '<td class="pi-qty"><input type="text" inputmode="decimal" data-number="decimal" class="form-control form-control-sm text-end pi-qty-input" value="' + posEsc(item.qty) + '"></td>'
            + '<td class="pi-unit"><input type="text" class="form-control form-control-sm pi-unit-input" value="' + posEsc(item.unit) + '" placeholder="unit"></td>'
            + '<td class="pi-price"><input type="text" inputmode="decimal" data-number="money" class="form-control form-control-sm text-end pi-price-input" value="' + posEsc(item.unit_price) + '"></td>'
            + '<td class="pi-sub">' + posMoney(posNum(item.qty) * posNum(item.unit_price)) + '</td>'
            + '<td class="pi-del"><button type="button" class="btn btn-sm btn-outline-danger btn-icon pi-del-btn" title="Hapus"><i class="fa fa-trash"></i></button></td>'
            + '</tr>';
    });
    body.html(html);
    posRecompute();
}

function posAddRow(item) {
    posItems.push(item || { quotation_item_id: null, description: '', qty: 1, unit: '', unit_price: 0 });
    posRenderItems();
}

function posCollect() {
    const items = [];
    $('#pos-items-rows tr[data-idx]').each(function() {
        const idx = $(this).data('idx');
        const item = posItems[idx];
        if (!item) return;
        items.push({
            quotation_item_id: item.quotation_item_id,
            description: $(this).find('.pi-desc-input').val() || '',
            qty: posNum($(this).find('.pi-qty-input').val()),
            unit: $(this).find('.pi-unit-input').val() || '',
            unit_price: posNum($(this).find('.pi-price-input').val()),
        });
    });
    return items.filter(function(i) { return i.description.trim() !== '' || posNum(i.qty) > 0 || posNum(i.unit_price) > 0; });
}

function posRecompute() {
    const items = posCollect();
    let subtotal = 0;
    items.forEach(function(i) { subtotal += posNum(i.qty) * posNum(i.unit_price); });

    const discPercent = posNum($('#pos-disc-percent').val());
    const discount = discPercent > 0 ? subtotal * discPercent / 100 : posNum($('#pos-disc-amount').val());
    const netto = subtotal - discount;

    const ppnPercent = posNum($('#pos-ppn-percent').val());
    const ppn = ppnPercent > 0 ? netto * ppnPercent / 100 : posNum($('#pos-ppn-amount').val());
    const grand = netto + ppn;

    $('#pos-t-subtotal').text(posMoney(subtotal));
    $('#pos-t-discount').text(posMoney(discount));
    $('#pos-t-ppn').text(posMoney(ppn));
    $('#pos-t-grand').text(posMoney(grand));
}

function posSyncHeader() {
    const opt = $('#pos-quotation option:selected');
    $('#pos-company').val(opt.data('company') || '');
    $('#pos-dp-badge').html(posDpBadge(!!Number(opt.data('requires-dp'))));
}

function posLoadQuotation() {
    const id = $('#pos-quotation').val();
    if (!id) { posItems = []; posRenderItems(); return; }
    $.get(posQuotationItemsUrl.replace('__ID__', id)).done(function(res) {
        $('#pos-disc-percent').val(res.discount_percent ?? '');
        $('#pos-disc-amount').val(res.discount_amount ?? '');
        $('#pos-ppn-percent').val(res.ppn_percent ?? '');
        $('#pos-ppn-amount').val(res.ppn_amount ?? '');
        posItems = (res.data || []).map(function(r) {
            return { quotation_item_id: r.quotation_item_id, description: r.description, qty: r.qty, unit: r.unit, unit_price: r.unit_price };
        });
        posRenderItems();
        window.Num.formatAll('#pos-form');
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat item quotation.');
    });
}

$(document).on('input', '.pi-desc-input, .pi-qty-input, .pi-price-input', function() {
    const tr = $(this).closest('tr[data-idx]');
    const qty = posNum(tr.find('.pi-qty-input').val());
    const price = posNum(tr.find('.pi-price-input').val());
    tr.find('.pi-sub').text(posMoney(qty * price));
    posRecompute();
});
$(document).on('click', '.pi-del-btn', function() {
    const idx = $(this).closest('tr[data-idx]').data('idx');
    posItems.splice(idx, 1);
    posRenderItems();
});
$(document).on('click', '#pos-add-item', function() { posAddRow(); });
$(document).on('change', '#pos-quotation', function() {
    posSyncHeader();
    posLoadQuotation();
});
$(document).on('input', '#pos-disc-percent, #pos-disc-amount, #pos-ppn-percent, #pos-ppn-amount', posRecompute);

function posSave() {
    const quotationId = $('#pos-quotation').val();
    if (!quotationId) { toastr.error('Quotation wajib dipilih. POS harus berbasis quotation.'); return; }

    const payload = {
        date: $('#pos-date').val(),
        quotation_id: quotationId,
        discount_percent: posNumOrNull($('#pos-disc-percent').val()),
        discount_amount: posNumOrNull($('#pos-disc-amount').val()),
        ppn_percent: posNumOrNull($('#pos-ppn-percent').val()),
        ppn_amount: posNumOrNull($('#pos-ppn-amount').val()),
        items: posCollect(),
    };
    if (!payload.date) { toastr.error('Tanggal wajib diisi.'); return; }
    if (!payload.items.length) { toastr.error('Minimal satu item wajib diisi.'); return; }

    const url = posIsEdit ? posUpdateUrl.replace('__ID__', {{ $pos?->id ?? 'null' }}) : posStoreUrl;
    const method = posIsEdit ? 'PUT' : 'POST';

    $('#pos-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: url, method: method, contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        data: JSON.stringify(payload)
    }).done(function(res) {
        toastr.success(res.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = posShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        $('#pos-save').prop('disabled', false).html(posSaveLabel);
    });
}

$(document).ready(function() {
    posRenderItems();
    posSyncHeader();
    window.Num.formatAll('#pos-form');
    $('#pos-save').on('click', posSave);
});
</script>
@endsection
