@extends('layouts.app')

@php $isEdit = (bool) $bill; @endphp

@section('title', $isEdit ? 'Edit Tagihan Supplier' : 'Catat Tagihan Supplier')
@section('page-title', $isEdit ? 'Edit Tagihan Supplier' : 'Catat Tagihan Supplier')

@section('styles')
<style>
    .sb-hint { font-size: 12px; color: var(--text-muted); }
    .sb-match { display: flex; gap: 6px; flex-wrap: wrap; font-size: 11.5px; color: var(--text-muted); }
    .sb-match span { white-space: nowrap; }
    #sb-lines td { vertical-align: top; }
    #sb-lines .sb-amount { padding-top: 12px; font-weight: 600; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .sb-summary-sticky { position: sticky; top: 84px; }
    .sb-sum-row { display: flex; justify-content: space-between; gap: 8px; padding: 7px 0; font-size: 13.5px; }
    .sb-sum-row + .sb-sum-row { border-top: 1px dashed var(--card-border); }
    .sb-sum-row .lbl { color: var(--text-muted); }
    .sb-sum-row .val { font-weight: 600; font-variant-numeric: tabular-nums; text-align: right; }
    .sb-total { display: flex; justify-content: space-between; margin-top: 8px; padding: 12px 14px; background: var(--accent-soft); border-radius: var(--radius-sm); font-weight: 700; }
    .sb-total .val { color: var(--accent); font-size: 17px; font-variant-numeric: tabular-nums; }
    .sb-type { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .sb-type input { display: none; }
    .sb-type label { margin: 0; cursor: pointer; padding: 9px 12px; border: 1px solid var(--card-border); border-radius: var(--radius-sm); font-size: 12.5px; color: var(--text-muted); }
    .sb-type label strong { display: block; color: var(--text-primary); font-size: 13.5px; }
    .sb-type input:checked + label { border-color: var(--accent); background: var(--accent-subtle); box-shadow: 0 0 0 1px var(--accent) inset; }
    .sb-type input:disabled + label { cursor: not-allowed; opacity: .75; }
    .sb-adv-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    @media (max-width: 767.98px) { .sb-adv-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .sb-adv-grid .k { font-size: 12px; color: var(--text-muted); }
    .sb-adv-grid .v { font-weight: 600; font-variant-numeric: tabular-nums; }
    .sb-rule { font-size: 12px; line-height: 1.5; color: var(--text-muted); background: var(--accent-subtle); border-radius: var(--radius-sm); padding: 10px 12px; margin-top: 10px; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Edit Tagihan '.$bill->bill_number : 'Catat Tagihan Supplier' }}</h1>
        <p class="page-header-sub">Pilih PO, lalu isi qty sesuai invoice supplier. Hanya barang yang sudah diterima dan belum ditagih yang bisa ditagih.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $isEdit ? route('supplier-bill.show', $bill->id) : route('supplier-bill.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Informasi Tagihan</span></div>
            <div class="card-body-custom">
                <div class="sb-type mb-3">
                    <div>
                        <input type="radio" name="sb-type" id="sb-type-regular" value="regular" @checked($billType === 'regular') @disabled($isEdit)>
                        <label for="sb-type-regular"><strong><i class="fa-solid fa-file-invoice me-1"></i>Tagihan Reguler</strong>Barang sudah diterima — dicocokkan dengan PO &amp; penerimaan.</label>
                    </div>
                    <div>
                        <input type="radio" name="sb-type" id="sb-type-advance" value="advance" @checked($billType === 'advance') @disabled($isEdit)>
                        <label for="sb-type-advance"><strong><i class="fa-solid fa-hand-holding-dollar me-1"></i>Uang Muka</strong>Supplier minta bayar sebelum barang datang (sesuai batas supplier).</label>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label">Purchase Order <span class="text-danger">*</span></label>
                        {{-- Pilihan PO dibangun ulang per tipe tagihan (lihat sbRenderPoOptions). --}}
                        <select id="sb-po" class="form-select" style="width:100%" @disabled($isEdit)></select>
                        <small class="sb-hint" id="sb-po-hint"></small>
                        <div class="sb-rule mt-2" id="sb-po-warning" style="display:none"></div>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">Supplier</label>
                        <div class="form-control bg-light" id="sb-supplier" style="min-height:38px">—</div>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">No. Invoice Supplier <span class="text-danger">*</span></label>
                        <input type="text" id="sb-invoice" class="form-control" maxlength="100" value="{{ $bill->supplier_invoice_number ?? '' }}" placeholder="Sesuai dokumen supplier">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">No. Faktur Pajak <span class="sb-hint" id="sb-pkp-hint"></span></label>
                        <input type="text" id="sb-tax" class="form-control" maxlength="100" value="{{ $bill->tax_invoice_number ?? '' }}" placeholder="Opsional">
                    </div>
                    <div class="col-lg-2 col-6">
                        <label class="form-label">Tgl Tagihan <span class="text-danger">*</span></label>
                        <input type="date" id="sb-date" class="form-control" max="{{ now()->toDateString() }}" value="{{ $bill?->bill_date?->toDateString() ?? now()->toDateString() }}">
                    </div>
                    <div class="col-lg-2 col-6">
                        <label class="form-label">Jatuh Tempo</label>
                        <input type="date" id="sb-due" class="form-control" value="{{ $bill?->due_date?->toDateString() }}">
                        <small class="sb-hint" id="sb-term-hint"></small>
                    </div>
                    <div class="col-lg-4" id="sb-rate-wrap" style="display:none">
                        <label class="form-label" for="sb-rate">Kurs <span class="sb-rate-cur">—</span> → <span class="sb-base-cur">IDR</span> <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" id="sb-rate" class="form-control text-end" value="{{ $bill && $bill->isForeign() ? \App\Support\Number::rate($bill->exchange_rate) : '' }}">
                        <small class="sb-hint">Kurs pada tanggal invoice supplier — menjadi nilai buku hutang. Default kurs master.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan</label>
                        <input type="text" id="sb-notes" class="form-control" maxlength="2000" value="{{ $bill->notes ?? '' }}" placeholder="Opsional">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in" id="sb-advance-card" style="display:none">
            <div class="card-header-custom"><span><i class="fa-solid fa-hand-holding-dollar me-2" style="color:var(--accent)"></i>Uang Muka</span></div>
            <div class="card-body-custom">
                <div class="sb-adv-grid mb-3">
                    <div><div class="k">Nilai PO</div><div class="v" id="sb-adv-po">—</div></div>
                    <div><div class="k">Batas supplier</div><div class="v" id="sb-adv-limit">—</div></div>
                    <div><div class="k">Sudah diminta</div><div class="v" id="sb-adv-billed">—</div></div>
                    <div><div class="k">Sisa boleh diminta</div><div class="v" id="sb-adv-room" style="color:var(--accent)">—</div></div>
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label" for="sb-adv-amount">Nominal Uang Muka <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text sb-adv-cur">—</span><input type="text" inputmode="decimal" id="sb-adv-amount" class="form-control text-end" value="{{ $bill && $bill->isAdvance() ? \App\Support\Number::money($bill->total) : '' }}"></div>
                    </div>
                    <div class="col-md-7 d-flex gap-2 flex-wrap" id="sb-adv-quick"></div>
                </div>
                <div class="sb-hint mt-2" id="sb-adv-note"></div>
            </div>
        </div>

        <div class="card-custom fade-in" id="sb-lines-card">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-list-check me-2" style="color:var(--accent)"></i>Baris Ditagih</span>
                <button type="button" class="btn btn-secondary btn-sm" id="sb-fill-all" style="display:none" onclick="sbFillAll()"><i class="fa fa-angles-right me-1"></i> Tagih Semua yang Bisa</button>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th style="width:170px">PO / Diterima / Ditagih</th>
                                <th class="text-center" style="width:110px">Qty Tagih</th>
                                <th class="text-end" style="width:170px">Harga Satuan</th>
                                <th class="text-end" style="width:140px">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody id="sb-lines">
                            <tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih PO terlebih dahulu.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-2 pt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold" style="font-size:13px"><i class="fa-solid fa-truck-fast me-1" style="color:var(--accent)"></i>Biaya Tambahan <span class="sb-hint">ongkos kirim, asuransi, dll. — ikut DPP</span></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="sbAddCharge()"><i class="fa fa-plus me-1"></i> Tambah Biaya</button>
                    </div>
                    <div id="sb-charges"></div>
                </div>

                <div class="px-2 pt-3 pb-2">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="sb-pph-on">
                        <label class="form-check-label fw-semibold" for="sb-pph-on" style="font-size:13px">Potong PPh <span class="sb-hint">(mis. PPh 23 untuk jasa) — tarif diisi sesuai ketentuan yang berlaku</span></label>
                    </div>
                    <div class="row g-2" id="sb-pph-fields" style="display:none">
                        <div class="col-md-4">
                            <label class="form-label" for="sb-pph-label">Jenis PPh</label>
                            <input type="text" id="sb-pph-label" class="form-control form-control-sm" list="sb-pph-options" maxlength="50" value="PPh 23">
                            <datalist id="sb-pph-options">
                                @foreach(\App\Models\SupplierBill::PPH_LABELS as $label)<option value="{{ $label }}">@endforeach
                            </datalist>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="sb-pph-percent">Tarif</label>
                            <div class="input-group input-group-sm"><input type="text" inputmode="decimal" id="sb-pph-percent" class="form-control text-end" placeholder="0"><span class="input-group-text">%</span></div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="sb-pph-base">Dasar Pengenaan <span class="sb-hint">default = DPP</span></label>
                            <div class="input-group input-group-sm"><span class="input-group-text sb-cur-label">—</span><input type="text" inputmode="decimal" id="sb-pph-base" class="form-control text-end" placeholder="Sama dengan DPP"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in sb-summary-sticky">
            <div class="card-header-custom"><span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <div class="sb-sum-row"><span class="lbl">Sub Total</span><span class="val" id="sb-sum-subtotal">0</span></div>
                <div class="sb-sum-row" id="sb-sum-disc-row"><span class="lbl" id="sb-sum-disc-lbl">Diskon</span><span class="val text-danger" id="sb-sum-disc">0</span></div>
                <div class="sb-sum-row" id="sb-sum-charge-row" style="display:none"><span class="lbl">Biaya Tambahan</span><span class="val" id="sb-sum-charge">0</span></div>
                <div class="sb-sum-row" id="sb-sum-dpp-row" style="display:none"><span class="lbl">DPP</span><span class="val" id="sb-sum-dpp">0</span></div>
                <div class="sb-sum-row" id="sb-sum-ppn-row"><span class="lbl" id="sb-sum-ppn-lbl">PPN</span><span class="val" id="sb-sum-ppn">0</span></div>
                <div class="sb-total"><span>Total Tagihan</span><span class="val" id="sb-sum-total">0</span></div>
                <div class="sb-sum-row" id="sb-sum-base-row" style="display:none"><span class="lbl">≈ <span class="sb-base-cur">IDR</span> (kurs tagihan)</span><span class="val" id="sb-sum-base">0</span></div>
                <div class="sb-sum-row mt-1" id="sb-sum-pph-row" style="display:none"><span class="lbl" id="sb-sum-pph-lbl">PPh dipotong</span><span class="val text-danger" id="sb-sum-pph">0</span></div>
                <div class="sb-sum-row" id="sb-sum-adv-row" style="display:none"><span class="lbl">Dipotong uang muka</span><span class="val text-danger" id="sb-sum-adv">0</span></div>
                <div class="sb-sum-row" id="sb-sum-due-row" style="display:none"><span class="lbl fw-semibold">Yang ditransfer</span><span class="val" id="sb-sum-due">0</span></div>
                <button type="button" class="btn btn-primary w-100 mt-3" id="sb-save" onclick="sbSave()">
                    <i class="fa fa-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Draft' }}
                </button>
                <div class="sb-rule">
                    <i class="fa fa-circle-info me-1"></i> Uang muka PO yang sudah dibayar otomatis dipotongkan pada tagihan reguler. Diskon &amp; PPN mengikuti PO. Harga boleh selisih maks {{ rtrim(rtrim(number_format(\App\Models\SupplierBill::PRICE_TOLERANCE_PERCENT, 2), '0'), '.') }}% dari harga PO. Draft diverifikasi oleh approver Finance (pemegang hak approve).
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const sbLinesUrl = '{{ route("supplier-bill.billable-lines") }}';
const sbSaveUrl = '{{ $isEdit ? route("supplier-bill.update", $bill->id) : route("supplier-bill.store") }}';
const sbSaveMethod = '{{ $isEdit ? "PUT" : "POST" }}';
const sbShowUrl = '{{ route("supplier-bill.show", "__ID__") }}';
const sbBillId = @json($bill?->id);
const sbSaveLabel = $('#sb-save').html();
let sbInitialItems = @json($items);
let sbHeader = null;
const sbInitialCharges = @json($charges ?? []);
const sbInitialPph = @json($bill ? ['label' => $bill->pph_label, 'percent' => $bill->pph_percent, 'base' => $bill->pph_base] : null);
const sbPoOptions = @json($purchaseOrders);
const sbCanSetAdvanceLimit = {{ ($canSetAdvanceLimit ?? false) ? 'true' : 'false' }};
const sbSupplierUrl = '{{ route("supplier.index") }}';
let sbSelectedPo = @json($preselectPoId);

/**
 * PO untuk tipe tagihan aktif. Reguler: hanya PO dengan barang diterima yang belum
 * ditagih. Uang Muka: PO Approved / Diterima Sebagian; PO yang supplier-nya belum
 * diberi batas uang muka (0%) atau batasnya sudah habis tampil nonaktif + alasannya.
 */
function sbRenderPoOptions() {
    var advance = sbType() === 'advance';
    var $po = $('#sb-po');
    var current = $po.val() || sbSelectedPo;
    var reasons = { no_limit: 'batas uang muka supplier 0%', full: 'batas uang muka sudah habis' };
    var html = '<option value="">— Pilih PO —</option>';
    var available = 0, blocked = 0;

    sbPoOptions.forEach(function(o) {
        var state = advance ? o.advance : (o.regular ? 'ok' : null);
        if (!state) return;
        var ok = state === 'ok';
        ok ? available++ : blocked++;
        html += '<option value="' + o.id + '"' + (ok ? '' : ' disabled') + (ok && String(o.id) === String(current) ? ' selected' : '') + '>'
            + sbEsc(o.label + (ok ? '' : ' — ' + reasons[state])) + '</option>';
    });

    $po.html(html).trigger('change.select2');
    $('#sb-po-hint').text(advance
        ? 'PO Approved / Diterima Sebagian. Supplier harus punya batas uang muka lebih dari 0%.'
        : 'Hanya PO yang punya barang diterima dan belum ditagih.');

    var warning = '';
    if (advance && available === 0) {
        warning = blocked > 0
            ? '<i class="fa fa-triangle-exclamation me-1"></i>Ada ' + blocked + ' PO Approved, tetapi supplier-nya belum diizinkan uang muka (batas 0%) atau batasnya sudah habis. '
                + (sbCanSetAdvanceLimit
                    ? 'Atur <strong>Maks. Uang Muka per PO</strong> di <a href="' + sbSupplierUrl + '" target="_blank">Master Data → Supplier</a>, lalu muat ulang halaman ini.'
                    : 'Minta approver Supplier mengatur <strong>Maks. Uang Muka per PO</strong> di Master Data → Supplier.')
            : '<i class="fa fa-circle-info me-1"></i>Belum ada PO Approved yang menunggu barang.';
    } else if (!advance && available === 0) {
        warning = '<i class="fa fa-circle-info me-1"></i>Belum ada PO dengan barang diterima yang belum ditagih. Untuk PO yang barangnya belum datang, pilih <strong>Uang Muka</strong>.';
    }
    $('#sb-po-warning').html(warning).toggle(!!warning);

    // PO terpilih tidak berlaku untuk tipe ini -> kosongkan.
    if (current && String($po.val()) !== String(current)) {
        sbSelectedPo = null;
        sbLoad();
    }
}

function sbEscAttr(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }

function sbAddCharge(c) {
    c = c || {};
    var html = '<div class="row g-2 align-items-center mb-2 sb-charge">'
        + '<div class="col-7"><input type="text" class="form-control form-control-sm sb-charge-desc" maxlength="255" placeholder="Keterangan, mis. Ongkos kirim Jakarta–Gresik" value="' + sbEscAttr(c.description) + '"></div>'
        + '<div class="col-4"><div class="input-group input-group-sm"><span class="input-group-text sb-cur-label">' + sbEsc(sbHeader ? sbHeader.currency : '—') + '</span><input type="text" inputmode="decimal" class="form-control text-end sb-charge-amount" value="' + (c.amount ? sbFmt(c.amount) : '') + '"></div></div>'
        + '<div class="col-1 text-end"><button type="button" class="btn-icon text-danger" title="Hapus biaya" onclick="$(this).closest(\'.sb-charge\').remove(); sbRecalc();"><i class="fa fa-trash"></i></button></div>'
        + '</div>';
    $('#sb-charges').append(html);
    sbRecalc();
}

function sbCollectCharges() {
    var out = [];
    $('#sb-charges .sb-charge').each(function() {
        var amount = sbParse($(this).find('.sb-charge-amount').val());
        var desc = $(this).find('.sb-charge-desc').val().trim();
        if (amount > 0 || desc) out.push({ description: desc, amount: Math.round(amount * 100) / 100, $row: $(this) });
    });
    return out;
}

$(document).on('change', '#sb-pph-on', function() {
    $('#sb-pph-fields').toggle(this.checked);
    if (this.checked && !$('#sb-pph-percent').val() && sbHeader && sbHeader.default_pph_percent) $('#sb-pph-percent').val(sbFmt(sbHeader.default_pph_percent));
    sbRecalc();
});
$(document).on('input', '.sb-charge-amount, #sb-pph-percent, #sb-pph-base', function() { this.value = this.value.replace(/[^\d.,]/g, ''); sbRecalc(); });
$(document).on('blur', '.sb-charge-amount, #sb-pph-base', function() { if ($(this).val() !== '') $(this).val(sbFmt(sbParse($(this).val()))); });
$(document).on('input', '.sb-charge-desc', function() { $(this).removeClass('is-invalid'); });

function sbType() { return $('input[name="sb-type"]:checked').val() || 'regular'; }

function sbSyncType() {
    var adv = sbType() === 'advance';
    $('#sb-advance-card').toggle(adv);
    $('#sb-lines-card').toggle(!adv);
    sbRenderAdvance();
    sbRecalc();
}

function sbRenderAdvance() {
    if (!sbHeader) {
        $('#sb-adv-po, #sb-adv-limit, #sb-adv-billed, #sb-adv-room').text('—');
        $('#sb-adv-quick').empty();
        $('#sb-adv-note').text('Pilih PO terlebih dahulu.');
        return;
    }
    var h = sbHeader, cur = h.currency + ' ';
    var room = Math.max(0, h.advance_limit - h.advance_billed);
    $('.sb-adv-cur').text(h.currency);
    $('#sb-adv-po').text(cur + sbFmt(h.po_total));
    $('#sb-adv-limit').text(sbFmt(h.advance_percent) + '% · ' + cur + sbFmt(h.advance_limit));
    $('#sb-adv-billed').text(cur + sbFmt(h.advance_billed));
    $('#sb-adv-room').text(cur + sbFmt(room));
    var quick = '';
    [25, 50, 100].forEach(function(p) {
        var v = Math.round(h.po_total * p) / 100;
        if (v <= room + 0.005) quick += '<button type="button" class="btn btn-outline-secondary btn-sm sb-adv-pick" data-value="' + v + '">' + p + '% PO</button>';
    });
    if (room > 0) quick += '<button type="button" class="btn btn-outline-secondary btn-sm sb-adv-pick" data-value="' + room + '">Sisa maksimal</button>';
    $('#sb-adv-quick').html(quick);
    var note = !h.advance_allowed ? 'PO ini sudah diterima penuh / tidak menunggu barang — uang muka tidak berlaku.'
        : (h.advance_percent <= 0 ? 'Supplier tidak diizinkan uang muka (batas 0%). Minta approver Supplier mengatur batasnya.' : 'Uang muka jatuh tempo saat diminta dan dipotongkan otomatis dari tagihan reguler PO ini.');
    $('#sb-adv-note').text(note);
}

$(document).on('click', '.sb-adv-pick', function() { $('#sb-adv-amount').val(sbFmt($(this).data('value'))); sbRecalc(); });
$(document).on('input', '#sb-adv-amount', function() { this.value = this.value.replace(/[^\d.,]/g, ''); sbRecalc(); });
$(document).on('blur', '#sb-adv-amount', function() { if ($(this).val() !== '') $(this).val(sbFmt(sbParse($(this).val()))); });
$(document).on('change', 'input[name="sb-type"]', function() {
    if (sbHeader && !$('#sb-due').val()) $('#sb-due').val($('#sb-date').val());
    sbRenderPoOptions();
    sbSyncType();
});

function sbEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
// Format angka satu standar (gaya US) — lihat partials/number-input.
function sbFmt(n) { return Num.format(n, 2); }
function sbParse(v) { return Num.parse(v); }
function sbAddDays(date, days) {
    if (!date) return '';
    var d = new Date(date + 'T00:00:00');
    d.setDate(d.getDate() + (parseInt(days, 10) || 0));
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function sbLoad() {
    var poId = $('#sb-po').val();
    sbHeader = null;
    $('#sb-fill-all').hide();
    if (!poId) {
        $('#sb-supplier').text('—');
        $('#sb-lines').html('<tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih PO terlebih dahulu.</td></tr>');
        sbRecalc();
        return;
    }
    $('#sb-lines').html('<tr><td colspan="5" class="text-center py-4"><span class="spinner-border spinner-border-sm me-1"></span> Memuat baris PO...</td></tr>');
    $.get(sbLinesUrl, { purchase_order_id: poId, bill_id: sbBillId }).done(function(res) {
        sbHeader = res.header;
        $('#sb-supplier').text(sbHeader.supplier_name || '—');
        $('#sb-rate-wrap').toggle(!!sbHeader.is_foreign);
        $('.sb-rate-cur').text(sbHeader.currency);
        $('.sb-base-cur').text(sbHeader.base_currency);
        if (sbHeader.is_foreign && !$('#sb-rate').val()) $('#sb-rate').val(sbFmt(sbHeader.default_rate));
        $('#sb-pkp-hint').text(sbHeader.is_pkp ? '(supplier PKP — sebaiknya diisi)' : '');
        $('#sb-term-hint').text(sbType() === 'advance' ? 'Uang muka: jatuh tempo saat diminta' : 'Termin ' + sbHeader.payment_term_days + ' hari');
        if (!$('#sb-due').val() || !sbBillId) $('#sb-due').val(sbAddDays($('#sb-date').val(), sbType() === 'advance' ? 0 : sbHeader.payment_term_days));
        sbRenderAdvance();

        var initial = {};
        (sbInitialItems || []).forEach(function(i) { initial[i.purchase_order_item_id] = i; });
        sbInitialItems = null;

        var html = '';
        res.lines.forEach(function(l) {
            var pre = initial[l.purchase_order_item_id] || {};
            var locked = l.billable <= 0;
            html += '<tr data-id="' + l.purchase_order_item_id + '" data-max="' + l.billable + '" data-po-price="' + l.price + '"' + (locked ? ' style="opacity:.55"' : '') + '>';
            html += '<td><div class="fw-semibold" style="font-size:13px">' + sbEsc(l.part_number || '—') + '</div>'
                + '<div class="sb-hint">' + (l.description || '') + '</div>'
                + '<div class="sb-hint"><i class="fa fa-diagram-project me-1"></i>' + sbEsc(l.project_label) + '</div></td>';
            html += '<td><div class="sb-match"><span>PO ' + l.ordered + '</span>·<span>Diterima ' + l.received + '</span>·<span>Ditagih ' + l.billed + '</span></div>'
                + '<div class="fw-semibold" style="font-size:12.5px;color:' + (locked ? 'var(--text-muted)' : 'var(--accent)') + '">' + (locked ? 'Tidak ada yang bisa ditagih' : 'Bisa ditagih ' + l.billable + ' ' + sbEsc(l.unit || '')) + '</div></td>';
            html += '<td><input type="text" inputmode="numeric" class="form-control form-control-sm text-center sb-qty" placeholder="0" value="' + (pre.qty || '') + '"' + (locked ? ' disabled' : '') + '></td>';
            html += '<td><div class="input-group input-group-sm"><span class="input-group-text" style="font-size:11px">' + sbEsc(sbHeader.currency) + '</span>'
                + '<input type="text" inputmode="decimal" class="form-control text-end sb-price" value="' + sbFmt(pre.price != null ? pre.price : l.price) + '"' + (locked ? ' disabled' : '') + '></div>'
                + '<div class="sb-hint text-end">PO ' + sbFmt(l.price) + '</div></td>';
            html += '<td class="text-end sb-amount">0</td>';
            html += '</tr>';
        });
        $('#sb-lines').html(html || '<tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)">PO tidak punya baris.</td></tr>');
        $('#sb-fill-all').toggle(res.lines.some(function(l) { return l.billable > 0; }));
        sbRecalc();
    }).fail(function(xhr) {
        $('#sb-lines').html('<tr><td colspan="5" class="text-center py-4 text-danger">' + sbEsc(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat PO.') + '</td></tr>');
    });
}

function sbFillAll() {
    $('#sb-lines tr[data-id]').each(function() {
        var max = parseInt($(this).data('max'), 10) || 0;
        if (max > 0) $(this).find('.sb-qty').val(max).removeClass('is-invalid');
    });
    sbRecalc();
}

function sbCollect() {
    var rows = [];
    $('#sb-lines tr[data-id]').each(function() {
        var qty = parseInt($(this).find('.sb-qty').val(), 10) || 0;
        if (qty > 0) rows.push({ purchase_order_item_id: $(this).data('id'), qty: qty, price: Math.round(sbParse($(this).find('.sb-price').val()) * 100) / 100, $row: $(this) });
    });
    return rows;
}

function sbRecalc() {
    if (sbType() === 'advance') {
        var amt = sbParse($('#sb-adv-amount').val());
        var c = sbHeader ? sbHeader.currency + ' ' : '';
        $('#sb-sum-subtotal').text(c + sbFmt(amt));
        $('#sb-sum-disc-row, #sb-sum-ppn-row, #sb-sum-adv-row, #sb-sum-due-row').hide();
        $('#sb-sum-total').text(c + sbFmt(amt));
        sbRenderBase(amt);
        return;
    }
    var subtotal = 0;
    $('#sb-lines tr[data-id]').each(function() {
        var qty = parseInt($(this).find('.sb-qty').val(), 10) || 0;
        var amount = qty * sbParse($(this).find('.sb-price').val());
        subtotal += amount;
        $(this).find('.sb-amount').text(sbFmt(amount));
    });
    var cur = sbHeader ? sbHeader.currency + ' ' : '';
    var discPct = sbHeader ? sbHeader.discount_percent : 0;
    var ppnPct = sbHeader ? sbHeader.ppn_percent : 0;
    var disc = Math.round(subtotal * discPct) / 100;
    var charges = sbCollectCharges().reduce(function(sum, c) { return sum + c.amount; }, 0);
    var dpp = subtotal - disc + charges;
    var ppn = Math.round(dpp * ppnPct) / 100;
    var total = dpp + ppn;
    var pphPct = $('#sb-pph-on').is(':checked') ? sbParse($('#sb-pph-percent').val()) : 0;
    var pphBaseInput = sbParse($('#sb-pph-base').val());
    var pphBase = pphPct > 0 ? Math.min(pphBaseInput > 0 ? pphBaseInput : dpp, dpp) : 0;
    var pph = Math.round(pphBase * pphPct) / 100;
    var adv = sbHeader ? Math.max(0, Math.min(sbHeader.advance_balance || 0, total - pph)) : 0;

    $('.sb-cur-label').text(sbHeader ? sbHeader.currency : '—');
    $('#sb-pph-base').attr('placeholder', 'DPP ' + sbFmt(dpp));
    $('#sb-sum-subtotal').text(cur + sbFmt(subtotal));
    $('#sb-sum-disc-row').toggle(discPct > 0);
    $('#sb-sum-disc-lbl').text('Diskon ' + sbFmt(discPct) + '%');
    $('#sb-sum-disc').text('- ' + cur + sbFmt(disc));
    $('#sb-sum-charge-row').toggle(charges > 0);
    $('#sb-sum-charge').text(cur + sbFmt(charges));
    $('#sb-sum-dpp-row').toggle(charges > 0 || disc > 0);
    $('#sb-sum-dpp').text(cur + sbFmt(dpp));
    $('#sb-sum-ppn-row').toggle(ppnPct > 0);
    $('#sb-sum-ppn-lbl').text('PPN ' + sbFmt(ppnPct) + '%');
    $('#sb-sum-ppn').text(cur + sbFmt(ppn));
    $('#sb-sum-total').text(cur + sbFmt(total));
    $('#sb-sum-pph-row').toggle(pph > 0);
    $('#sb-sum-pph-lbl').text(($('#sb-pph-label').val() || 'PPh') + ' ' + sbFmt(pphPct) + '% dipotong');
    $('#sb-sum-pph').text('- ' + cur + sbFmt(pph));
    $('#sb-sum-adv-row').toggle(adv > 0);
    $('#sb-sum-adv').text('- ' + cur + sbFmt(adv));
    $('#sb-sum-due-row').toggle(adv > 0 || pph > 0);
    $('#sb-sum-due').text(cur + sbFmt(total - pph - adv));
    sbRenderBase(total);
}

// Nilai Rupiah tagihan valas menurut kurs tanggal invoice supplier.
function sbRenderBase(total) {
    var show = !!(sbHeader && sbHeader.is_foreign);
    $('#sb-sum-base-row').toggle(show);
    if (show) $('#sb-sum-base').text(sbHeader.base_currency + ' ' + sbFmt(Math.round(total * sbParse($('#sb-rate').val()) * 100) / 100));
}

$(document).on('change', '#sb-po', function() { $('#sb-rate').val(''); sbLoad(); });
$(document).on('input', '#sb-rate', function() { this.value = this.value.replace(/[^\d.,]/g, ''); $(this).removeClass('is-invalid'); sbRecalc(); });
$(document).on('blur', '#sb-rate', function() { if ($(this).val() !== '') $(this).val(sbFmt(sbParse($(this).val()))); });
$(document).on('change', '#sb-date', function() {
    if (sbHeader) $('#sb-due').val(sbAddDays($(this).val(), sbType() === 'advance' ? 0 : sbHeader.payment_term_days));
});
$(document).on('input', '.sb-qty', function() {
    this.value = this.value.replace(/\D/g, '');
    $(this).removeClass('is-invalid');
    sbRecalc();
});
$(document).on('input', '.sb-price', function() {
    this.value = this.value.replace(/[^\d.,]/g, '');
    sbRecalc();
});
$(document).on('blur', '.sb-price', function() { if ($(this).val() !== '') $(this).val(sbFmt(sbParse($(this).val()))); });

function sbFail(msg, $el) {
    toastr.error(msg);
    if ($el && $el.length) { $el.addClass('is-invalid'); $el[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); }
}

function sbSave() {
    if (!$('#sb-po').val()) { sbFail('Pilih PO.'); return; }
    if (!$('#sb-invoice').val().trim()) { sbFail('No. invoice supplier wajib diisi.', $('#sb-invoice')); return; }
    if (!$('#sb-date').val()) { sbFail('Tanggal tagihan wajib diisi.', $('#sb-date')); return; }
    var isAdvance = sbType() === 'advance';
    if (sbHeader && sbHeader.is_foreign && sbParse($('#sb-rate').val()) <= 0) { sbFail('Kurs ' + sbHeader.currency + ' pada tanggal invoice supplier wajib diisi.', $('#sb-rate')); return; }
    var rows = isAdvance ? [] : sbCollect();
    var charges = isAdvance ? [] : sbCollectCharges();
    for (var c = 0; c < charges.length; c++) {
        if (!charges[c].description) { sbFail('Keterangan biaya tambahan wajib diisi.', charges[c].$row.find('.sb-charge-desc')); return; }
        if (charges[c].amount <= 0) { sbFail('Nominal biaya tambahan harus lebih dari 0.', charges[c].$row.find('.sb-charge-amount')); return; }
    }
    var pphOn = !isAdvance && $('#sb-pph-on').is(':checked');
    var pphPct = pphOn ? sbParse($('#sb-pph-percent').val()) : 0;
    if (pphOn && (pphPct <= 0 || pphPct > 100)) { sbFail('Isi tarif PPh (0–100%).', $('#sb-pph-percent')); return; }
    if (isAdvance) {
        var amt = sbParse($('#sb-adv-amount').val());
        if (amt <= 0) { sbFail('Nominal uang muka wajib diisi.', $('#sb-adv-amount')); return; }
        if (sbHeader && amt > (sbHeader.advance_limit - sbHeader.advance_billed) + 0.005) { sbFail('Uang muka melebihi sisa batas supplier.', $('#sb-adv-amount')); return; }
    } else {
        if (!rows.length) { sbFail('Isi qty minimal satu baris yang ditagih.'); return; }
        for (var i = 0; i < rows.length; i++) {
            var max = parseInt(rows[i].$row.data('max'), 10) || 0;
            if (rows[i].qty > max) { sbFail('Qty melebihi yang sudah diterima dan belum ditagih (' + max + ').', rows[i].$row.find('.sb-qty')); return; }
        }
    }

    $('#sb-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: sbSaveUrl,
        method: sbSaveMethod,
        contentType: 'application/json',
        data: JSON.stringify({
            purchase_order_id: $('#sb-po').val(),
            bill_type: sbType(),
            advance_amount: isAdvance ? Math.round(sbParse($('#sb-adv-amount').val()) * 100) / 100 : null,
            charges: charges.map(function(c) { return { description: c.description, amount: c.amount }; }),
            pph_label: pphOn ? $('#sb-pph-label').val().trim() : null,
            pph_percent: pphOn ? pphPct : null,
            pph_base: pphOn && sbParse($('#sb-pph-base').val()) > 0 ? sbParse($('#sb-pph-base').val()) : null,
            supplier_invoice_number: $('#sb-invoice').val().trim(),
            tax_invoice_number: $('#sb-tax').val().trim(),
            bill_date: $('#sb-date').val(),
            exchange_rate: sbHeader && sbHeader.is_foreign ? sbParse($('#sb-rate').val()) : null,
            due_date: $('#sb-due').val() || null,
            notes: $('#sb-notes').val(),
            items: rows.map(function(r) { return { purchase_order_item_id: r.purchase_order_item_id, qty: r.qty, price: r.price }; })
        }),
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).done(function(res) {
        toastr.success(res.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = sbShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        $('#sb-save').prop('disabled', false).html(sbSaveLabel);
    });
}

$(document).ready(function() {
    $('#sb-po').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— Pilih PO —' });
    sbRenderPoOptions();
    (sbInitialCharges || []).forEach(function(c) { sbAddCharge(c); });
    if (sbInitialPph && sbInitialPph.percent) {
        $('#sb-pph-on').prop('checked', true);
        $('#sb-pph-fields').show();
        $('#sb-pph-label').val(sbInitialPph.label || 'PPh 23');
        $('#sb-pph-percent').val(sbFmt(sbInitialPph.percent));
        $('#sb-pph-base').val(sbFmt(sbInitialPph.base));
    }
    sbSyncType();
    if ($('#sb-po').val()) sbLoad();
});
</script>
@endsection
