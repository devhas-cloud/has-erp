@extends('layouts.app')

@php
    $isEdit = (bool) $estimate;
    $q = $quotation;
@endphp

@section('title', ($isEdit ? 'Edit' : 'Buat').' Estimasi PL')
@section('page-title', ($isEdit ? 'Edit' : 'Buat').' Estimasi PL')

@section('styles')
<style>
    .pl-card { margin-bottom: 18px; }
    .pl-card .card-body-custom { padding: 14px 16px; }
    .pl-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
    .pl-label { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-bottom: 4px; display: block; }
    .pl-value { font-size: 14px; font-weight: 700; color: var(--text-primary); text-align: right; padding: 6px 10px; background: var(--bg); border-radius: 6px; border: 1px solid var(--card-border); }
    .pl-value.accent { background: var(--accent-soft); color: var(--accent); }
    table.pl-lines th { font-size: 12px; white-space: nowrap; }
    table.pl-lines td { padding: 4px 6px; vertical-align: middle; }
    table.pl-lines input, table.pl-lines select { font-size: 13px; }
    table.pl-lines .idr-cell { text-align: right; font-weight: 600; white-space: nowrap; min-width: 140px; }
    .pl-summary td { padding: 6px 10px; font-size: 13px; }
    .pl-summary td.k { color: var(--text-muted); font-weight: 600; width: 260px; }
    .pl-summary td.v { text-align: right; font-weight: 700; white-space: nowrap; }
    .pl-summary td.pct { width: 130px; }
    .pl-summary tr.total td { background: var(--accent-soft); color: var(--accent); }
    .pl-src { font-size: 12px; color: var(--text-muted); }
    tr.pl-missing-vendor td { background: #fef3c7; }
    tr.pl-manual td.pl-hpp-vendor::after { content: 'manual'; margin-left: 6px; font-size: 10px; padding: 1px 6px; border-radius: 999px; background: #fef3c7; color: #92400e; }
    .pl-derived { font-size: 12px; color: var(--text-muted); white-space: nowrap; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Edit' : 'Buat' }} Estimasi PL</h1>
        <p class="page-header-sub">
            Quotation <strong>{{ $q->quotation_number ?? '#'.$q->id }}</strong>
            &middot; {{ $q->to_name ?? ($q->opportunity?->accountCompany?->account_name ?? '—') }}
            &middot; Grand Total {{ \App\Models\Quotation::formatMoney($q->grand_total) }}
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $isEdit ? route('profit-estimate.show', $estimate->id) : route('profit-estimate.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Batal
        </a>
        <button type="button" class="btn-accent" id="pl-save-btn">
            <i class="fa fa-save me-1"></i> <span>Simpan</span>
        </button>
    </div>
</div>

@if(!empty($syncing))
<div class="alert alert-warning" style="font-size:13px">
    <i class="fa fa-rotate me-1"></i>
    <strong>Mode sinkronisasi.</strong> Nilai project dan daftar item diisi ulang dari quotation. Periksa vendor dan amount tiap item, lalu <strong>Simpan</strong> untuk menghapus tanda outdated.
</div>
@endif

@if($previous)
<div class="alert alert-info" style="font-size:13px">
    <i class="fa fa-circle-info me-1"></i>
    Input manual (vendor/amount item, koreksi HPP, biaya, persentase, kurs, tanda tangan) disalin dari Estimasi PL #{{ $previous->id }}
    milik quotation versi sebelumnya. Nilai project dan daftar item diambil dari quotation versi ini.
</div>
@endif

{{-- Header --}}
<div class="card-custom pl-card fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span></div>
    <div class="card-body-custom">
        <div class="pl-grid">
            <div>
                <label class="pl-label">Tanggal</label>
                <input type="date" id="pl-date" class="form-control form-control-sm" value="{{ $data['date'] }}">
            </div>
            <div style="grid-column: span 2">
                <label class="pl-label">Project / Company</label>
                <input type="text" id="pl-project" class="form-control form-control-sm" value="{{ $data['project_name'] }}" maxlength="255">
            </div>
            @foreach($currencyCodes as $code)
                @continue($code === 'IDR')
                <div>
                    <label class="pl-label">Kurs {{ $code }} (1 {{ $code }} = Rp)</label>
                    <input type="number" step="any" min="0" class="form-control form-control-sm text-end pl-rate" data-code="{{ $code }}" id="pl-rate-{{ $code }}" value="{{ $data['rates'][$code] ?? 0 }}">
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Nilai project --}}
<div class="card-custom pl-card fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-coins me-2" style="color:var(--accent)"></i>Nilai Project</span>
        <span class="pl-src">Nilai Awal = Grand Total quotation. Urutan: PPN dipotong dulu, lalu Discount, lalu Referen.</span>
    </div>
    <div class="card-body-custom">
        <div class="row g-3">
            <div class="col-lg-6">
                <table class="w-100 pl-summary">
                    <tr><td class="k">Nilai Awal</td><td class="v"><input type="number" step="any" min="0" id="pl-nilai-awal" class="form-control form-control-sm text-end pl-num" value="{{ $data['nilai_awal'] }}"></td></tr>
                    <tr><td class="k">PPN</td><td class="v"><input type="number" step="any" min="0" id="pl-ppn" class="form-control form-control-sm text-end pl-num" value="{{ $data['ppn_amount'] }}"></td></tr>
                    <tr><td class="k">Sub Total I</td><td class="v" id="pl-subtotal1">0.00</td></tr>
                    <tr><td class="k">Discount</td><td class="v"><input type="number" step="any" min="0" id="pl-discount" class="form-control form-control-sm text-end pl-num" value="{{ $data['discount_amount'] }}"></td></tr>
                    <tr><td class="k">Sub Total II</td><td class="v" id="pl-subtotal2">0.00</td></tr>
                    <tr><td class="k">Referen <input type="number" step="any" min="0" max="100" id="pl-referral-pct" class="form-control form-control-sm d-inline-block text-end pl-num" style="width:90px" value="{{ $data['referral_percent'] }}"> %</td><td class="v" id="pl-referral">0.00</td></tr>
                    <tr class="total"><td class="k">Nilai Real Project</td><td class="v" id="pl-real">0.00</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Daftar item --}}
<div class="card-custom pl-card fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Daftar Item &amp; Vendor</span>
        <span class="pl-src">Amount = nilai total baris (bukan harga satuan). Vendor wajib diisi.</span>
        <button type="button" class="btn btn-sm btn-soft" onclick="plAddLine('product')"><i class="fa fa-plus me-1"></i> Tambah</button>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0 pl-lines" id="pl-lines-product">
                <thead><tr><th>Item</th><th style="width:80px">Qty</th><th style="width:90px">Unit</th><th style="width:190px">Vendor</th><th style="width:100px">Mata Uang</th><th style="width:160px">Amount</th><th class="text-end" style="width:150px">Rp</th><th style="width:40px"></th></tr></thead>
                <tbody></tbody>
                <tfoot><tr><td colspan="6" class="text-end" style="font-weight:600">Total Item</td><td class="idr-cell" id="pl-total-product">0.00</td><td></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>

{{-- HPP --}}
<div class="card-custom pl-card fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-box me-2" style="color:var(--accent)"></i>Harga Pokok Product (FOB, TT) per Vendor</span>
        <span class="pl-src">Otomatis dari Daftar Item: Σ amount per vendor &amp; mata uang. Nominal boleh dikoreksi manual.</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0 pl-lines" id="pl-lines-hpp">
                <thead><tr><th>Vendor</th><th style="width:110px">Mata Uang</th><th style="width:200px">Nominal</th><th style="width:150px">Otomatis</th><th class="text-end" style="width:160px">Rp</th><th style="width:40px"></th></tr></thead>
                <tbody></tbody>
                <tfoot><tr><td colspan="4" class="text-end" style="font-weight:600">Sub Total HPP</td><td class="idr-cell" id="pl-total-hpp">0.00</td><td></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>

{{-- Operasional --}}
<div class="card-custom pl-card fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-truck me-2" style="color:var(--accent)"></i>Operasional Cost &mdash; 1. Yang Telah Dikeluarkan</span>
        <button type="button" class="btn btn-sm btn-soft" onclick="plAddLine('cost_spent')"><i class="fa fa-plus me-1"></i> Tambah</button>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0 pl-lines" id="pl-lines-cost_spent">
                <thead><tr><th>Deskripsi</th><th style="width:110px">Mata Uang</th><th style="width:180px">Nominal</th><th class="text-end" style="width:160px">Rp</th><th style="width:40px"></th></tr></thead>
                <tbody></tbody>
                <tfoot><tr><td colspan="3" class="text-end" style="font-weight:600">Sub Total</td><td class="idr-cell" id="pl-total-cost_spent">0.00</td><td></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>

<div class="card-custom pl-card fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-screwdriver-wrench me-2" style="color:var(--accent)"></i>Operasional Cost &mdash; 2. Yang Akan Dikeluarkan</span>
        <button type="button" class="btn btn-sm btn-soft" onclick="plAddLine('cost_planned')"><i class="fa fa-plus me-1"></i> Tambah</button>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0 pl-lines" id="pl-lines-cost_planned">
                <thead><tr><th>Deskripsi</th><th style="width:110px">Mata Uang</th><th style="width:180px">Nominal</th><th class="text-end" style="width:160px">Rp</th><th style="width:40px"></th></tr></thead>
                <tbody></tbody>
                <tfoot><tr><td colspan="3" class="text-end" style="font-weight:600">Sub Total</td><td class="idr-cell" id="pl-total-cost_planned">0.00</td><td></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>

{{-- Ringkasan --}}
<div class="card-custom pl-card fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Ringkasan Profit</span></div>
    <div class="card-body-custom">
        <div class="row g-3">
            <div class="col-lg-7">
                <table class="w-100 pl-summary">
                    <tr><td class="k">Company Investment &amp; Development <input type="number" step="any" min="0" max="100" id="pl-investment-pct" class="form-control form-control-sm d-inline-block text-end pl-num" style="width:90px" value="{{ $data['investment_percent'] }}"> %</td><td class="v" id="pl-investment">0.00</td><td class="pct"></td></tr>
                    <tr class="total"><td class="k">Total Biaya Yang Dikeluarkan</td><td class="v" id="pl-total-cost">0.00</td><td class="pct"></td></tr>
                    <tr><td class="k">Nilai Real Project</td><td class="v" id="pl-real-2">0.00</td><td class="pct"></td></tr>
                    <tr><td class="k">Estimasi Nilai Profit</td><td class="v" id="pl-profit">0.00</td><td class="pct v" id="pl-profit-pct">0.00 %</td></tr>
                    <tr><td class="k">Fee Marketing <input type="number" step="any" min="0" max="100" id="pl-marketing-pct" class="form-control form-control-sm d-inline-block text-end pl-num" style="width:90px" value="{{ $data['marketing_fee_percent'] }}"> %</td><td class="v" id="pl-marketing">0.00</td><td class="pct"></td></tr>
                    <tr><td class="k">Fee PM <input type="number" step="any" min="0" max="100" id="pl-pm-pct" class="form-control form-control-sm d-inline-block text-end pl-num" style="width:90px" value="{{ $data['pm_fee_percent'] }}"> %</td><td class="v" id="pl-pm">0.00</td><td class="pct"></td></tr>
                    <tr class="total"><td class="k">Real Profit</td><td class="v" id="pl-real-profit">0.00</td><td class="pct v" id="pl-real-profit-pct">0.00 %</td></tr>
                </table>
            </div>
            <div class="col-lg-5">
                <label class="pl-label">Sales Person</label>
                <input type="text" id="pl-sales" class="form-control form-control-sm mb-2" value="{{ $data['sales_person_name'] }}" maxlength="150">
                <label class="pl-label">Finance Dept</label>
                <input type="text" id="pl-finance" class="form-control form-control-sm mb-2" value="{{ $data['finance_name'] }}" maxlength="150">
                <label class="pl-label">Accounting</label>
                <input type="text" id="pl-accounting" class="form-control form-control-sm mb-2" value="{{ $data['accounting_name'] }}" maxlength="150">
                <label class="pl-label">Catatan</label>
                <textarea id="pl-notes" class="form-control form-control-sm" rows="3">{{ $data['notes'] }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end mb-4">
    <button type="button" class="btn-accent" onclick="$('#pl-save-btn').click()">
        <i class="fa fa-save me-1"></i> <span>Simpan</span>
    </button>
</div>
@endsection

@section('scripts')
<script>
const PL = {
    lines: @json($data['lines']),
    currencies: @json($currencyCodes),
    saveUrl: '{{ $isEdit ? route('profit-estimate.update', $estimate->id) : route('profit-estimate.store') }}',
    method: '{{ $isEdit ? 'PUT' : 'POST' }}',
    quotationId: {{ $q->id }},
    showUrl: '{{ route('profit-estimate.show', '__ID__') }}'
};

function plFmt(n) {
    n = Number(n) || 0;
    return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function plNum(sel) {
    var v = parseFloat($(sel).val());
    return isNaN(v) ? 0 : v;
}
function plEsc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}
function plCurrencySelect(selected) {
    var html = '<select class="form-select form-select-sm pl-currency">';
    PL.currencies.forEach(function(c) {
        html += '<option value="' + c + '"' + ((selected || 'IDR') === c ? ' selected' : '') + '>' + c + '</option>';
    });
    return html + '</select>';
}

function plRowHtml(section, line) {
    line = line || {};
    var del = '<td class="text-center"><button type="button" class="btn-icon text-danger" title="Hapus" onclick="plRemoveLine(this)"><i class="fa fa-trash"></i></button></td>';
    if (section === 'product') {
        return '<tr data-section="product">'
            + '<td><input type="text" class="form-control form-control-sm pl-label-in" value="' + plEsc(line.label) + '" maxlength="255" placeholder="Nama item"></td>'
            + '<td><input type="number" step="any" min="0" class="form-control form-control-sm text-end pl-qty" value="' + plEsc(line.qty == null ? 1 : line.qty) + '"></td>'
            + '<td><input type="text" class="form-control form-control-sm pl-unit" value="' + plEsc(line.unit || 'Each') + '" maxlength="50"></td>'
            + '<td><input type="text" class="form-control form-control-sm pl-vendor" value="' + plEsc(line.vendor) + '" maxlength="150" list="pl-vendor-list" placeholder="Wajib"></td>'
            + '<td>' + plCurrencySelect(line.currency) + '</td>'
            + '<td><input type="number" step="any" min="0" class="form-control form-control-sm text-end pl-amount pl-num" value="' + plEsc(line.amount == null ? 0 : line.amount) + '"></td>'
            + '<td class="idr-cell pl-idr">0.00</td>'
            + del + '</tr>';
    }
    return '<tr data-section="' + section + '">'
        + '<td><input type="text" class="form-control form-control-sm pl-label-in" value="' + plEsc(line.label) + '" maxlength="255" placeholder="Deskripsi biaya"></td>'
        + '<td>' + plCurrencySelect(line.currency) + '</td>'
        + '<td><input type="number" step="any" min="0" class="form-control form-control-sm text-end pl-amount pl-num" value="' + plEsc(line.amount == null ? 0 : line.amount) + '"></td>'
        + '<td class="idr-cell pl-idr">0.00</td>'
        + del + '</tr>';
}

function plHppRowHtml(g) {
    return '<tr data-section="hpp" data-key="' + plEsc(g.key) + '" data-manual="0">'
        + '<td class="pl-hpp-vendor"></td>'
        + '<td class="pl-hpp-cur"></td>'
        + '<td><input type="number" step="any" min="0" class="form-control form-control-sm text-end pl-amount pl-hpp-amount" value="0"></td>'
        + '<td class="pl-derived">0.00</td>'
        + '<td class="idr-cell pl-idr">0.00</td>'
        + '<td class="text-center"><button type="button" class="btn-icon pl-reset" title="Kembalikan ke nilai otomatis" onclick="plResetHpp(this)" style="display:none"><i class="fa fa-rotate-left"></i></button></td>'
        + '</tr>';
}

function plAddLine(section, line) {
    $('#pl-lines-' + section + ' tbody').append(plRowHtml(section, line));
    plRecalc();
}
function plRemoveLine(btn) {
    $(btn).closest('tr').remove();
    plRecalc();
}
function plHppKey(vendor, currency) {
    return String(vendor || '').trim().toLowerCase() + '|' + (currency || 'IDR');
}
function plResetHpp(btn) {
    $(btn).closest('tr').attr('data-manual', '0');
    plRecalc();
}

function plRates() {
    var rates = {};
    $('.pl-rate').each(function() {
        var v = parseFloat($(this).val());
        rates[$(this).data('code')] = isNaN(v) ? 0 : v;
    });
    return rates;
}
function plToIdr(amount, currency, rates) {
    if (!currency || currency === 'IDR') return Math.round(amount * 100) / 100;
    return Math.round(amount * (rates[currency] || 0) * 100) / 100;
}

/**
 * Kelompokkan baris item per vendor + mata uang (Σ amount), sekaligus isi
 * kolom Rp tiap item. Item tanpa vendor ditandai (ditolak saat simpan).
 */
function plDerivedGroups(rates) {
    var groups = {}, order = [], productTotal = 0;
    $('#pl-lines-product tbody tr').each(function() {
        var $r = $(this);
        var vendor = ($r.find('.pl-vendor').val() || '').trim();
        var cur = $r.find('.pl-currency').val() || 'IDR';
        var amount = parseFloat($r.find('.pl-amount').val()) || 0;
        var idr = plToIdr(amount, cur, rates);
        $r.find('.pl-idr').text(plFmt(idr));
        $r.toggleClass('pl-missing-vendor', !vendor);
        productTotal += idr;
        var key = plHppKey(vendor, cur);
        if (!groups[key]) {
            groups[key] = { key: key, vendor: vendor, currency: cur, amount: 0 };
            order.push(key);
        }
        groups[key].amount += amount;
    });
    $('#pl-total-product').text(plFmt(productTotal));
    return order.map(function(k) { groups[k].amount = Math.round(groups[k].amount * 10000) / 10000; return groups[k]; });
}

/**
 * Bangun ulang tabel HPP dari kelompok item. Baris yang dikoreksi manual
 * (data-manual=1) mempertahankan nominalnya; baris lain mengikuti nilai otomatis.
 */
function plRebuildHpp(rates) {
    var $tb = $('#pl-lines-hpp tbody');
    var groups = plDerivedGroups(rates);
    var keys = groups.map(function(g) { return g.key; });
    $tb.find('tr').each(function() {
        if (keys.indexOf($(this).attr('data-key')) === -1) $(this).remove();
    });
    groups.forEach(function(g) {
        var $row = $tb.find('tr').filter(function() { return $(this).attr('data-key') === g.key; });
        if (!$row.length) $row = $(plHppRowHtml(g));
        $tb.append($row); // urutan mengikuti urutan item
        var manual = $row.attr('data-manual') === '1';
        $row.find('.pl-hpp-vendor').text(g.vendor || '(tanpa vendor)');
        $row.find('.pl-hpp-cur').text(g.currency);
        $row.find('.pl-derived').text(plFmt(g.amount)).attr('data-derived', g.amount);
        $row.toggleClass('pl-missing-vendor', !g.vendor).toggleClass('pl-manual', manual);
        if (!manual) $row.find('.pl-hpp-amount').val(g.amount);
        $row.find('.pl-reset').toggle(manual);
    });
}

function plRecalc() {
    var rates = plRates();
    var nilaiAwal = plNum('#pl-nilai-awal');
    var ppn = plNum('#pl-ppn');
    var discount = plNum('#pl-discount');
    var referralPct = plNum('#pl-referral-pct');
    var investPct = plNum('#pl-investment-pct');
    var mktPct = plNum('#pl-marketing-pct');
    var pmPct = plNum('#pl-pm-pct');

    var sub1 = Math.round((nilaiAwal - ppn) * 100) / 100;
    var sub2 = Math.round((sub1 - discount) * 100) / 100;
    var referral = Math.round(sub2 * referralPct) / 100;
    var real = Math.round((sub2 - referral) * 100) / 100;

    plRebuildHpp(rates);

    var linesTotal = 0;
    ['hpp', 'cost_spent', 'cost_planned'].forEach(function(section) {
        var sub = 0;
        $('#pl-lines-' + section + ' tbody tr').each(function() {
            var amount = parseFloat($(this).find('.pl-amount').val()) || 0;
            var cur = section === 'hpp' ? $(this).find('.pl-hpp-cur').text() : $(this).find('.pl-currency').val();
            var idr = plToIdr(amount, cur, rates);
            $(this).find('.pl-idr').text(plFmt(idr));
            sub += idr;
        });
        $('#pl-total-' + section).text(plFmt(sub));
        linesTotal += sub;
    });

    var investment = Math.round(real * investPct) / 100;
    var totalCost = Math.round((linesTotal + investment) * 100) / 100;
    var profit = Math.round((real - totalCost) * 100) / 100;
    var mkt = Math.round(real * mktPct) / 100;
    var pm = Math.round(real * pmPct) / 100;
    var realProfit = Math.round((profit - mkt - pm) * 100) / 100;
    var pct = function(v) { return real > 0 ? (Math.round(v / real * 10000) / 100).toFixed(2) + ' %' : '0.00 %'; };

    $('#pl-subtotal1').text(plFmt(sub1));
    $('#pl-subtotal2').text(plFmt(sub2));
    $('#pl-referral').text(plFmt(referral));
    $('#pl-real').text(plFmt(real));
    $('#pl-real-2').text(plFmt(real));
    $('#pl-investment').text(plFmt(investment));
    $('#pl-total-cost').text(plFmt(totalCost));
    $('#pl-profit').text(plFmt(profit));
    $('#pl-profit-pct').text(pct(profit));
    $('#pl-marketing').text(plFmt(mkt));
    $('#pl-pm').text(plFmt(pm));
    $('#pl-real-profit').text(plFmt(realProfit));
    $('#pl-real-profit-pct').text(pct(realProfit));

    // Datalist vendor untuk autocomplete.
    var vendors = {};
    $('.pl-vendor').each(function() { var v = $(this).val().trim(); if (v) vendors[v] = true; });
    var dl = $('#pl-vendor-list');
    if (!dl.length) dl = $('<datalist id="pl-vendor-list"></datalist>').appendTo('body');
    dl.html(Object.keys(vendors).map(function(v) { return '<option value="' + plEsc(v) + '">'; }).join(''));
}

function plCollectLines() {
    var lines = [];
    $('#pl-lines-product tbody tr').each(function() {
        var $r = $(this);
        lines.push({
            section: 'product',
            label: $r.find('.pl-label-in').val() || '',
            qty: $r.find('.pl-qty').val() || '',
            unit: $r.find('.pl-unit').val() || '',
            vendor: $r.find('.pl-vendor').val() || '',
            currency: $r.find('.pl-currency').val() || '',
            amount: $r.find('.pl-amount').val() || ''
        });
    });
    $('#pl-lines-hpp tbody tr').each(function() {
        var $r = $(this);
        lines.push({
            section: 'hpp',
            vendor: $r.find('.pl-hpp-vendor').text() === '(tanpa vendor)' ? '' : $r.find('.pl-hpp-vendor').text(),
            currency: $r.find('.pl-hpp-cur').text() || '',
            amount: $r.find('.pl-hpp-amount').val() || '',
            is_manual: $r.attr('data-manual') === '1' ? 1 : 0
        });
    });
    ['cost_spent', 'cost_planned'].forEach(function(section) {
        $('#pl-lines-' + section + ' tbody tr').each(function() {
            var $r = $(this);
            lines.push({
                section: section,
                label: $r.find('.pl-label-in').val() || '',
                currency: $r.find('.pl-currency').val() || '',
                amount: $r.find('.pl-amount').val() || ''
            });
        });
    });
    return lines;
}

$(document).on('input change', '.pl-hpp-amount', function() {
    var $row = $(this).closest('tr');
    var derived = parseFloat($row.find('.pl-derived').attr('data-derived')) || 0;
    var val = parseFloat($(this).val()) || 0;
    $row.attr('data-manual', Math.abs(val - derived) < 0.00005 ? '0' : '1');
});
$(document).on('input change', '.pl-num, .pl-rate, .pl-currency, .pl-amount, .pl-vendor, .pl-qty', plRecalc);

$('#pl-save-btn').on('click', function() {
    var missing = [];
    $('#pl-lines-product tbody tr').each(function() {
        if (!($(this).find('.pl-vendor').val() || '').trim()) missing.push($(this).find('.pl-label-in').val() || '(tanpa nama)');
    });
    if (missing.length) {
        toastr.error('Setiap item harus memiliki vendor. Item tanpa vendor: ' + missing.join(', '));
        return;
    }
    var $btn = $(this).prop('disabled', true);
    $.ajax({
        url: PL.saveUrl,
        method: PL.method,
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        data: {
            quotation_id: PL.quotationId,
            date: $('#pl-date').val(),
            project_name: $('#pl-project').val(),
            rates: plRates(),
            nilai_awal: $('#pl-nilai-awal').val(),
            ppn_amount: $('#pl-ppn').val(),
            discount_amount: $('#pl-discount').val(),
            referral_percent: $('#pl-referral-pct').val(),
            investment_percent: $('#pl-investment-pct').val(),
            marketing_fee_percent: $('#pl-marketing-pct').val(),
            pm_fee_percent: $('#pl-pm-pct').val(),
            notes: $('#pl-notes').val(),
            sales_person_name: $('#pl-sales').val(),
            finance_name: $('#pl-finance').val(),
            accounting_name: $('#pl-accounting').val(),
            lines: plCollectLines()
        }
    }).done(function(res) {
        toastr.success(res.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = PL.showUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = 'Gagal menyimpan.';
        if (xhr.responseJSON) {
            if (xhr.responseJSON.errors) {
                msg = Object.values(xhr.responseJSON.errors).map(function(e) { return e.join(' '); }).join('<br>');
            } else if (xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
        }
        toastr.error(msg);
        $btn.prop('disabled', false);
    });
});

$(document).ready(function() {
    (PL.lines || []).forEach(function(line) {
        if (line.section === 'hpp') return;
        $('#pl-lines-' + line.section + ' tbody').append(plRowHtml(line.section, line));
    });
    plRecalc();
    // Terapkan koreksi manual HPP yang tersimpan.
    (PL.lines || []).forEach(function(line) {
        if (line.section !== 'hpp' || !line.is_manual) return;
        var key = plHppKey(line.vendor, line.currency);
        var $row = $('#pl-lines-hpp tbody tr').filter(function() { return $(this).attr('data-key') === key; });
        if ($row.length) {
            $row.attr('data-manual', '1').find('.pl-hpp-amount').val(line.amount);
        }
    });
    plRecalc();
});
</script>
@endsection
