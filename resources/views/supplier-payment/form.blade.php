@extends('layouts.app')

@php $isEdit = (bool) $payment; @endphp

@section('title', $isEdit ? 'Edit Pembayaran Supplier' : 'Catat Pembayaran Supplier')
@section('page-title', $isEdit ? 'Edit Pembayaran Supplier' : 'Catat Pembayaran Supplier')

@section('styles')
<style>
    .sp-hint { font-size: 12px; color: var(--text-muted); }
    .sp-bank { background: var(--accent-subtle); border: 1px dashed var(--card-border); border-radius: var(--radius-sm); padding: 10px 12px; font-size: 13px; }
    #sp-bills td { vertical-align: middle; }
    #sp-bills tr.is-picked { background: var(--accent-subtle); }
    .sp-summary-sticky { position: sticky; top: 84px; }
    .sp-sum-row { display: flex; justify-content: space-between; gap: 8px; padding: 7px 0; font-size: 13.5px; }
    .sp-sum-row + .sp-sum-row { border-top: 1px dashed var(--card-border); }
    .sp-sum-row .lbl { color: var(--text-muted); }
    .sp-sum-row .val { font-weight: 600; font-variant-numeric: tabular-nums; text-align: right; }
    .sp-total { display: flex; justify-content: space-between; margin-top: 8px; padding: 12px 14px; background: var(--accent-soft); border-radius: var(--radius-sm); font-weight: 700; }
    .sp-total .val { color: var(--accent); font-size: 17px; font-variant-numeric: tabular-nums; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Edit Pembayaran '.$payment->payment_number : 'Catat Pembayaran Supplier' }}</h1>
        <p class="page-header-sub">Pilih supplier, centang tagihan yang dibayar, dan sesuaikan nominal bila dibayar sebagian.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $isEdit ? route('supplier-payment.show', $payment->id) : route('supplier-payment.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-money-bill-transfer me-2" style="color:var(--accent)"></i>Informasi Pembayaran</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="sp-supplier">Supplier <span class="text-danger">*</span></label>
                        <select id="sp-supplier" class="form-select" style="width:100%" @disabled($isEdit)>
                            <option value="">— Pilih Supplier —</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" @selected($preselectSupplierId === $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <small class="sp-hint">Hanya supplier yang punya tagihan terverifikasi dan belum lunas.</small>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">Rekening Tujuan</label>
                        <div class="sp-bank" id="sp-bank">Pilih supplier untuk melihat rekening.</div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <label class="form-label" for="sp-date">Tanggal Bayar <span class="text-danger">*</span></label>
                        <input type="date" id="sp-date" class="form-control" max="{{ now()->toDateString() }}" value="{{ $payment?->payment_date?->toDateString() ?? now()->toDateString() }}">
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <label class="form-label" for="sp-method">Metode <span class="text-danger">*</span></label>
                        <select id="sp-method" class="form-select">
                            @foreach(\App\Models\SupplierPayment::METHOD_LABELS as $key => $label)
                                <option value="{{ $key }}" @selected(($payment->method ?? 'transfer') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <label class="form-label" for="sp-reference">No. Referensi <span class="text-danger" id="sp-ref-required">*</span></label>
                        <input type="text" id="sp-reference" class="form-control" maxlength="100" value="{{ $payment->reference_number ?? '' }}" placeholder="No. transfer / giro">
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <label class="form-label" for="sp-source">Rekening Sumber</label>
                        <input type="text" id="sp-source" class="form-control" maxlength="150" value="{{ $payment->source_account ?? '' }}" placeholder="Contoh: BCA Operasional">
                    </div>
                    <div class="col-lg-3 col-sm-6 sp-fx-field" style="display:none">
                        <label class="form-label" for="sp-debit">{{ $baseCurrency }} Didebit Bank</label>
                        <input type="text" inputmode="decimal" id="sp-debit" class="form-control text-end" value="{{ $payment && $payment->exchange_rate != 1 ? number_format($payment->amount_base, 2, ',', '.') : '' }}" placeholder="Sesuai mutasi rekening">
                        <small class="sp-hint">Nilai konversi bank (di luar biaya). Kurs dihitung otomatis.</small>
                    </div>
                    <div class="col-lg-3 col-sm-6 sp-fx-field" id="sp-rate-wrap" style="display:none">
                        <label class="form-label" for="sp-rate">Kurs ke {{ $baseCurrency }} <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" id="sp-rate" class="form-control text-end" value="{{ $payment && $payment->exchange_rate != 1 ? number_format($payment->exchange_rate, 6, ',', '.') : '' }}">
                        <small class="sp-hint">Kurs konversi bank saat bayar.</small>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <label class="form-label" for="sp-charge">Biaya Bank ({{ $baseCurrency }})</label>
                        <input type="text" inputmode="decimal" id="sp-charge" class="form-control text-end" value="{{ $payment && $payment->bank_charge > 0 ? number_format($payment->bank_charge, 2, ',', '.') : '' }}" placeholder="0">
                        <small class="sp-hint">Biaya transfer / bank koresponden — tidak mengurangi hutang.</small>
                    </div>
                    <div class="col">
                        <label class="form-label" for="sp-notes">Catatan</label>
                        <input type="text" id="sp-notes" class="form-control" maxlength="2000" value="{{ $payment->notes ?? '' }}" placeholder="Opsional">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Tagihan Dibayar</span>
                <span class="sp-hint" id="sp-currency-note"></span>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px"></th>
                                <th>Tagihan</th>
                                <th>Jatuh Tempo</th>
                                <th class="text-end">Sisa Hutang</th>
                                <th class="text-end" style="width:190px">Dibayar</th>
                            </tr>
                        </thead>
                        <tbody id="sp-bills">
                            <tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih supplier terlebih dahulu.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in sp-summary-sticky">
            <div class="card-header-custom"><span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <div class="sp-sum-row"><span class="lbl">Tagihan dipilih</span><span class="val" id="sp-sum-count">0</span></div>
                <div class="sp-fx-sum" style="display:none">
                    <div class="sp-sum-row"><span class="lbl">Nilai buku (kurs tagihan)</span><span class="val" id="sp-sum-book">0</span></div>
                    <div class="sp-sum-row"><span class="lbl">{{ $baseCurrency }} dibayar (kurs bank)</span><span class="val" id="sp-sum-base">0</span></div>
                    <div class="sp-sum-row"><span class="lbl" id="sp-sum-fx-lbl">Selisih kurs</span><span class="val" id="sp-sum-fx">0</span></div>
                </div>
                <div class="sp-sum-row" id="sp-sum-charge-row" style="display:none"><span class="lbl">Biaya bank</span><span class="val" id="sp-sum-charge">0</span></div>
                <div class="sp-total"><span>Total Bayar</span><span class="val" id="sp-sum-total">0</span></div>
                <button type="button" class="btn btn-primary w-100 mt-3" id="sp-save" onclick="spSave()">
                    <i class="fa fa-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Draft' }}
                </button>
                <div class="sp-hint mt-2"><i class="fa fa-circle-info me-1"></i>Hutang berkurang setelah draft diposting approver Finance (pemegang hak approve).</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const spBillsUrl = '{{ route("supplier-payment.open-bills") }}';
const spSaveUrl = '{{ $isEdit ? route("supplier-payment.update", $payment->id) : route("supplier-payment.store") }}';
const spSaveMethod = '{{ $isEdit ? "PUT" : "POST" }}';
const spShowUrl = '{{ route("supplier-payment.show", "__ID__") }}';
const spBase = @json($baseCurrency);
const spPaymentId = @json($payment?->id);
const spPreselectBill = @json($preselectBillId);
const spSaveLabel = $('#sp-save').html();
let spInitial = @json($allocations);
let spRates = {};

function spEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function spFmt(n) { return (Number(n) || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }); }
function spParse(v) {
    v = String(v == null ? '' : v).trim();
    if (v === '') return 0;
    var n = parseFloat(v.replace(/\./g, '').replace(',', '.'));
    return isNaN(n) ? 0 : n;
}

function spLoad() {
    var supplierId = $('#sp-supplier').val();
    if (!supplierId) {
        $('#sp-bank').text('Pilih supplier untuk melihat rekening.');
        $('#sp-bills').html('<tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih supplier terlebih dahulu.</td></tr>');
        spRecalc();
        return;
    }
    $('#sp-bills').html('<tr><td colspan="5" class="text-center py-4"><span class="spinner-border spinner-border-sm me-1"></span> Memuat tagihan...</td></tr>');
    $.get(spBillsUrl, { supplier_id: supplierId, payment_id: spPaymentId }).done(function(res) {
        var s = res.supplier;
        $('#sp-bank').html(s.bank_account_no
            ? '<strong>' + spEsc(s.bank_name || '') + ' ' + spEsc(s.bank_account_no) + '</strong><div class="sp-hint">a.n. ' + spEsc(s.bank_account_name || s.name) + '</div>'
            : '<span class="text-danger"><i class="fa fa-triangle-exclamation me-1"></i>Rekening supplier belum diisi di master Supplier.</span>');
        (res.rates || []).forEach(function(r) { spRates[r.name] = r.rate; });

        var initial = {};
        (spInitial || []).forEach(function(a) { initial[a.supplier_bill_id] = a.amount; });
        if (spPreselectBill) initial[spPreselectBill] = initial[spPreselectBill] || 'full';
        spInitial = null;

        var html = '';
        res.bills.forEach(function(b) {
            var pre = initial[b.id];
            var amount = pre === 'full' ? b.outstanding : pre;
            var checked = amount != null;
            html += '<tr data-id="' + b.id + '" data-currency="' + spEsc(b.currency) + '" data-rate="' + (b.exchange_rate || 1) + '" data-max="' + b.outstanding + '" class="' + (checked ? 'is-picked' : '') + '">';
            html += '<td><input type="checkbox" class="form-check-input sp-pick" aria-label="Bayar ' + spEsc(b.bill_number) + '"' + (checked ? ' checked' : '') + '></td>';
            html += '<td><div class="fw-semibold" style="font-size:13px">' + spEsc(b.bill_number) + ' <span class="sp-hint">· Inv. ' + spEsc(b.supplier_invoice_number) + '</span></div>'
                + '<div class="sp-hint">' + spEsc(b.source) + '</div>'
                + (b.in_other_drafts > 0 ? '<div class="sp-hint text-warning"><i class="fa fa-triangle-exclamation me-1"></i>' + spEsc(b.currency) + ' ' + spFmt(b.in_other_drafts) + ' sedang ada di draft pembayaran lain</div>' : '') + '</td>';
            html += '<td>' + (b.is_overdue ? '<span class="text-danger fw-semibold">' + b.due_date + ' <i class="fa fa-exclamation-circle"></i></span>' : b.due_date) + '</td>';
            html += '<td class="text-end" style="white-space:nowrap">' + spEsc(b.currency) + ' ' + spFmt(b.outstanding) + '</td>';
            html += '<td><div class="input-group input-group-sm"><span class="input-group-text" style="font-size:11px">' + spEsc(b.currency) + '</span>'
                + '<input type="text" inputmode="decimal" class="form-control text-end sp-amount" value="' + (checked ? spFmt(amount) : '') + '"' + (checked ? '' : ' disabled') + '></div></td>';
            html += '</tr>';
        });
        $('#sp-bills').html(html || '<tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)">Tidak ada tagihan terbuka untuk supplier ini.</td></tr>');
        spRecalc();
    });
}

function spPicked() {
    var rows = [];
    $('#sp-bills tr[data-id]').each(function() {
        if (!$(this).find('.sp-pick').is(':checked')) return;
        rows.push({ supplier_bill_id: $(this).data('id'), amount: Math.round(spParse($(this).find('.sp-amount').val()) * 100) / 100, currency: $(this).data('currency'), rate: parseFloat($(this).data('rate')) || 1, max: parseFloat($(this).data('max')), $row: $(this) });
    });
    return rows;
}

function spRecalc() {
    var rows = spPicked();
    var total = rows.reduce(function(s, r) { return s + r.amount; }, 0);
    var currency = rows.length ? rows[0].currency : '';
    var mixed = rows.some(function(r) { return r.currency !== currency; });
    var foreign = currency && currency !== spBase;

    $('#sp-currency-note').html(mixed ? '<span class="text-danger">Satu pembayaran hanya untuk satu mata uang.</span>' : (currency ? 'Mata uang: ' + spEsc(currency) : ''));
    $('.sp-fx-field, .sp-fx-sum').toggle(!!foreign);
    if (foreign && !$('#sp-rate').val() && spRates[currency]) $('#sp-rate').val(spFmtRate(spRates[currency]));
    // Rupiah didebit bank diisi -> kurs = Rupiah / nominal valas.
    var debit = spParse($('#sp-debit').val());
    if (foreign && debit > 0 && total > 0) $('#sp-rate').val(spFmtRate(debit / total));
    var paid = foreign ? (debit > 0 ? debit : Math.round(total * spParse($('#sp-rate').val()) * 100) / 100) : total;
    var book = rows.reduce(function(s, r) { return s + Math.round(r.amount * r.rate * 100) / 100; }, 0);
    var fx = Math.round((paid - book) * 100) / 100;
    var charge = spParse($('#sp-charge').val());
    $('#sp-sum-count').text(rows.length);
    $('#sp-sum-total').text((currency ? currency + ' ' : '') + spFmt(total));
    $('#sp-sum-book').text(spBase + ' ' + spFmt(book));
    $('#sp-sum-base').text(spBase + ' ' + spFmt(paid));
    $('#sp-sum-fx-lbl').text(fx > 0 ? 'Rugi selisih kurs' : (fx < 0 ? 'Laba selisih kurs' : 'Selisih kurs'));
    $('#sp-sum-fx').text(spBase + ' ' + spFmt(Math.abs(fx))).toggleClass('text-danger', fx > 0).toggleClass('text-success', fx < 0);
    $('#sp-sum-charge-row').toggle(charge > 0);
    $('#sp-sum-charge').text(spBase + ' ' + spFmt(charge));
    $('#sp-ref-required').toggle($('#sp-method').val() !== 'cash');
}

$(document).on('change', '#sp-supplier', spLoad);
$(document).on('change', '.sp-pick', function() {
    var $row = $(this).closest('tr');
    var on = $(this).is(':checked');
    $row.toggleClass('is-picked', on);
    $row.find('.sp-amount').prop('disabled', !on).val(on ? spFmt($row.data('max')) : '');
    spRecalc();
});
$(document).on('input', '.sp-amount, #sp-rate, #sp-debit, #sp-charge', function() {
    this.value = this.value.replace(/[^\d.,]/g, '');
    $(this).removeClass('is-invalid');
    // Kurs diketik manual -> Rupiah didebit dikosongkan (dihitung dari kurs).
    if (this.id === 'sp-rate') $('#sp-debit').val('');
    spRecalc();
});
$(document).on('blur', '.sp-amount, #sp-debit, #sp-charge', function() { if ($(this).val() !== '') $(this).val(spFmt(spParse($(this).val()))); });
$(document).on('blur', '#sp-rate', function() { if ($(this).val() !== '') $(this).val(spFmtRate(spParse($(this).val()))); });

function spFmtRate(n) { return (Number(n) || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 6 }); }
$(document).on('change', '#sp-method', spRecalc);

function spFail(msg, $el) {
    toastr.error(msg);
    if ($el && $el.length) { $el.addClass('is-invalid'); $el[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); }
}

function spSave() {
    if (!$('#sp-supplier').val()) { spFail('Pilih supplier.'); return; }
    if (!$('#sp-date').val()) { spFail('Tanggal bayar wajib diisi.', $('#sp-date')); return; }
    if ($('#sp-method').val() !== 'cash' && !$('#sp-reference').val().trim()) { spFail('No. referensi wajib diisi untuk transfer / giro.', $('#sp-reference')); return; }
    var rows = spPicked();
    if (!rows.length) { spFail('Pilih minimal satu tagihan yang dibayar.'); return; }
    for (var i = 0; i < rows.length; i++) {
        if (rows[i].amount <= 0) { spFail('Nominal bayar harus lebih dari 0.', rows[i].$row.find('.sp-amount')); return; }
        if (rows[i].amount > rows[i].max + 0.005) { spFail('Nominal melebihi sisa hutang (' + spFmt(rows[i].max) + ').', rows[i].$row.find('.sp-amount')); return; }
        if (rows[i].currency !== rows[0].currency) { spFail('Satu pembayaran hanya untuk satu mata uang.'); return; }
    }
    var foreign = rows[0].currency !== spBase;
    if (foreign && spParse($('#sp-rate').val()) <= 0 && spParse($('#sp-debit').val()) <= 0) { spFail('Isi Rupiah didebit bank atau kurs untuk pembayaran ' + rows[0].currency + '.', $('#sp-debit')); return; }

    $('#sp-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: spSaveUrl,
        method: spSaveMethod,
        contentType: 'application/json',
        data: JSON.stringify({
            supplier_id: $('#sp-supplier').val(),
            payment_date: $('#sp-date').val(),
            method: $('#sp-method').val(),
            reference_number: $('#sp-reference').val().trim(),
            source_account: $('#sp-source').val().trim(),
            exchange_rate: foreign ? spParse($('#sp-rate').val()) : null,
            debit_amount_base: foreign && spParse($('#sp-debit').val()) > 0 ? spParse($('#sp-debit').val()) : null,
            bank_charge: spParse($('#sp-charge').val()),
            notes: $('#sp-notes').val(),
            allocations: rows.map(function(r) { return { supplier_bill_id: r.supplier_bill_id, amount: r.amount }; })
        }),
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).done(function(res) {
        toastr.success(res.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = spShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        $('#sp-save').prop('disabled', false).html(spSaveLabel);
    });
}

$(document).ready(function() {
    $('#sp-supplier').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— Pilih Supplier —' });
    if ($('#sp-supplier').val()) spLoad();
    spRecalc();
});
</script>
@endsection
