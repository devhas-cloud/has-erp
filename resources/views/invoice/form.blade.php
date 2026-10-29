@extends('layouts.app')

@section('title', $invoice ? 'Edit Invoice' : 'Buat Invoice')
@section('page-title', $invoice ? 'Edit Invoice' : 'Buat Invoice')

@section('content')
<style>
    .inv-section-sub { font-size: 12px; color: var(--text-muted); font-weight: 400; margin-left: 6px; }
    .inv-project-info {
        background: var(--accent-subtle);
        border: 1px dashed var(--card-border);
        border-radius: var(--radius-sm);
        padding: 10px 12px;
        font-size: 13px;
        color: var(--text-muted);
        min-height: 100%;
    }
    .inv-summary-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; font-size: 14px; }
    .inv-summary-row + .inv-summary-row { border-top: 1px dashed var(--card-border); }
    .inv-summary-row .lbl { color: var(--text-muted); }
    .inv-summary-row .val { font-weight: 600; font-variant-numeric: tabular-nums; }
    .inv-summary-total {
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 8px; padding: 12px 14px;
        background: var(--accent-soft); border-radius: var(--radius-sm);
        font-size: 15px; font-weight: 700;
    }
    .inv-summary-total .val { font-size: 18px; color: var(--accent); font-variant-numeric: tabular-nums; }
    #inv-items-body .inv-total { background: transparent; border-color: transparent; font-weight: 600; }
    .inv-desc[contenteditable="true"] {
        border: 1px solid var(--card-border, #ced4da);
        border-radius: .25rem;
        padding: .25rem .5rem;
        min-height: 34px;
        background: #fff;
        font-size: .85rem;
        line-height: 1.45;
        white-space: pre-wrap;
        margin-top: 6px;
    }
    .inv-desc[contenteditable="true"]:focus {
        outline: none;
        border-color: var(--accent);
    }
    .inv-desc[contenteditable="true"]:empty::before {
        content: attr(data-placeholder);
        color: #999;
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $invoice ? 'Edit Invoice' : 'Buat Invoice' }}</h1>
        <p class="page-header-sub">Lengkapi data pelanggan, pengiriman, item, dan termin pembayaran.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $invoice ? route('invoice.show', $invoice->id) : route('invoice.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-building me-2" style="color:var(--accent)"></i>Pelanggan &amp; Project</span>
            </div>
            <div class="card-body-custom">
                <div class="mb-3">
                    <label class="form-label">Project <span class="text-danger">*</span></label>
                    <select id="inv-project" class="form-select" style="width:100%"></select>
                    <input type="hidden" id="inv-project-id" value="{{ $selectedProject['id'] ?? '' }}">
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Informasi Project</label>
                        <div id="inv-project-info" class="inv-project-info">Pilih project untuk melihat informasi.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ship To</label>
                        <input type="text" id="inv-ship-to" class="form-control" value="{{ $invoice?->ship_to }}" placeholder="Provinsi tujuan">
                        <small style="color:var(--text-muted)">Otomatis dari provinsi billing perusahaan.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Detail Invoice</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label">Tanggal Invoice <span class="text-danger">*</span></label>
                        <input type="date" id="inv-date" class="form-control" value="{{ $invoice?->invoice_date?->format('Y-m-d') ?? now()->toDateString() }}">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Terms (Hari)</label>
                        <div class="input-group">
                            <input type="number" id="inv-terms" class="form-control" min="0" max="3650" step="1" value="{{ is_numeric($invoice?->terms) ? (int) $invoice->terms : \App\Models\Invoice::DEFAULT_TERMS }}">
                            <span class="input-group-text">hari</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <small style="color:var(--text-muted)">Jatuh tempo: <strong id="inv-due-preview">—</strong></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-truck me-2" style="color:var(--accent)"></i>Pengiriman</span>
    </div>
    <div class="card-body-custom">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Tanggal Kirim</label>
                <input type="date" id="inv-date-shipped" class="form-control" value="{{ $invoice?->date_shipped?->format('Y-m-d') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Dikirim Melalui</label>
                <input type="text" id="inv-shipping" class="form-control" maxlength="100" value="{{ $invoice?->shipping_method }}" placeholder="Contoh: Direct">
            </div>
            <div class="col-md-4">
                <label class="form-label">No. DO</label>
                <input type="text" id="inv-do" class="form-control" maxlength="100" value="{{ $invoice?->do_number }}" placeholder="Contoh : 2512322">
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Item Invoice</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-soft btn-sm" id="inv-load-quote" onclick="invOpenQuotePicker()" disabled>
                <i class="fa fa-file-import me-1"></i> Ambil dari Quote
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="invAddItem()">
                <i class="fa fa-plus me-1"></i> Tambah Item
            </button>
        </div>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Produk / Jasa</th>
                        <th style="width:10%">Qty</th>
                        <th style="width:10%">Satuan</th>
                        <th style="width:17%">Harga Satuan</th>
                        <th style="width:17%" class="text-end">Jumlah</th>
                        <th class="text-center" style="width:60px">Aksi</th>
                    </tr>
                </thead>
                <tbody id="inv-items-body"></tbody>
            </table>
        </div>
        <div class="config-card-empty" id="inv-items-empty">
            <i class="fa-solid fa-inbox"></i> Belum ada item. Tambahkan minimal satu item invoice.
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-percent me-2" style="color:var(--accent)"></i>Termin, Pajak &amp; Penanda Tangan</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Keterangan Termin</label>
                        <input type="text" id="inv-noted" class="form-control" maxlength="255" value="{{ $invoice?->noted }}" placeholder="Contoh: Termin 4, DP, Pelunasan">
                        <small style="color:var(--text-muted)">Tampil di PDF sebagai label pembayaran, misal "Termin 4 (7%)".</small>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Diskon <span class="inv-section-sub">opsional</span></label>
                        <div class="input-group">
                            <input type="text" inputmode="decimal" autocomplete="off" id="inv-disc-pct" class="form-control inv-number-input text-end" style="max-width:110px" placeholder="0" value="{{ $invoice?->discount_percent }}">
                            <span class="input-group-text">%</span>
                            <span class="input-group-text">atau Rp</span>
                            <input type="text" inputmode="decimal" autocomplete="off" id="inv-disc-amt" class="form-control inv-number-input text-end" placeholder="Nominal diskon" value="{{ $invoice && ! $invoice->discount_percent ? $invoice->discount_amount : '' }}">
                        </div>
                        <small style="color:var(--text-muted)" id="inv-disc-hint">Default mengikuti diskon penawaran. Isi persen atau nominal.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Persentase Pembayaran (%)</label>
                        <input type="text" inputmode="decimal" autocomplete="off" id="inv-percent-payment" class="form-control inv-number-input text-end" value="{{ $invoice?->percent_payment ?? \App\Models\Invoice::DEFAULT_PERCENT_PAYMENT }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">PPN (%)</label>
                        <input type="text" inputmode="decimal" autocomplete="off" id="inv-ppn" class="form-control inv-number-input text-end" value="{{ $invoice?->ppn ?? 11 }}">
                    </div>
                    <div class="col-12"><hr class="my-1"></div>
                    <div class="col-md-6">
                        <label class="form-label">Penanda Tangan</label>
                        <input type="text" id="inv-signer-name" class="form-control" maxlength="100" value="{{ $invoice?->signer_name ?? \App\Models\Invoice::DEFAULT_SIGNER_NAME }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jabatan</label>
                        <input type="text" id="inv-signer-position" class="form-control" maxlength="100" value="{{ $invoice?->signer_position ?? \App\Models\Invoice::DEFAULT_SIGNER_POSITION }}">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Ringkasan Pembayaran</span>
            </div>
            <div class="card-body-custom">
                <div class="inv-summary-row"><span class="lbl">Sub Total</span><span class="val" id="inv-subtotal">Rp 0</span></div>
                <div class="inv-summary-row" id="inv-discount-row"><span class="lbl" id="inv-discount-label">Diskon</span><span class="val text-danger" id="inv-discount">- Rp 0</span></div>
                <div class="inv-summary-row"><span class="lbl">Nilai Sebelum PPN</span><span class="val" id="inv-net-subtotal">Rp 0</span></div>
                <div class="inv-summary-row"><span class="lbl" id="inv-amount-label">Pembayaran</span><span class="val" id="inv-amount-payment">Rp 0</span></div>
                <div class="inv-summary-row"><span class="lbl" id="inv-ppn-label">PPN (11%)</span><span class="val" id="inv-ppn-amount">Rp 0</span></div>
                <div class="inv-summary-total"><span>Total Tagihan</span><span class="val" id="inv-total-payment">Rp 0</span></div>

                <button type="button" class="btn btn-primary w-100 mt-3" onclick="invSave()">
                    <i class="fa fa-save me-1"></i> Simpan {{ $invoice ? 'Perubahan' : 'Invoice' }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="invQuoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">
                    <i class="fa-solid fa-file-import me-2" style="color:var(--accent)"></i>Ambil Item dari Quote
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:40px" class="text-center">
                                    <input type="checkbox" id="inv-quote-all" title="Pilih semua">
                                </th>
                                <th>Produk / Jasa</th>
                                <th style="width:80px" class="text-center">Qty</th>
                                <th style="width:90px">Satuan</th>
                                <th style="width:150px" class="text-end">Harga Satuan</th>
                            </tr>
                        </thead>
                        <tbody id="inv-quote-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="invApplyQuoteItems()">
                    <i class="fa fa-plus me-1"></i> Tambahkan Item Terpilih
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
const invIsEdit = {{ $invoice ? 'true' : 'false' }};
const invStoreUrl = '{{ route("invoice.store") }}';
const invUpdateUrl = '{{ $invoice ? route("invoice.update", $invoice->id) : "" }}';
const invShowUrl = '{{ route("invoice.show", "__ID__") }}';
const invProjectSearchUrl = '{{ route("invoice.search-projects") }}';
const invProjectFetchUrl = '{{ route("invoice.fetch-project", "__ID__") }}';
const invInitialProject = @json($selectedProject);
const invInitialItems = @json($items);
let invQuoteItems = [];
let invQuoteModalInstance = null;

function invToRaw(value) {
    return String(value == null ? '' : value).replace(/,/g, '');
}

function invFormatInput(value) {
    var s = String(value == null ? '' : value).replace(/[^\d.]/g, '');
    if (s === '') return '';

    var parts = s.split('.');
    var intRaw = parts.shift();
    var dec = parts.join('').slice(0, 2);
    var hasDot = s.indexOf('.') !== -1;
    var int = intRaw === '' ? '' : Number(intRaw).toLocaleString('en-US');

    if (int === '' && hasDot) int = '0';

    return int + (hasDot ? '.' + dec : '');
}

function invFormatStored(value) {
    var raw = invToRaw(value).trim();
    if (raw === '') return '';

    var n = Number(raw);
    if (!isFinite(n)) return invFormatInput(raw);

    return n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function invNumber(value) {
    return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function invMoney(value) {
    return 'Rp ' + invNumber(value);
}

function invCents(value) {
    return Math.round((parseFloat(invToRaw(value)) || 0) * 100);
}

function invFormatNum(el) {
    var $el = $(el);
    var start = 0;
    try { start = el.selectionStart || 0; } catch (e) {}

    var before = invToRaw($el.val()).slice(0, start);
    var digitsBefore = before.replace(/\D/g, '').length;
    var formatted = invFormatInput($el.val());
    var caret = 0;
    var seen = 0;

    for (var i = 0; i < formatted.length && seen < digitsBefore; i++) {
        caret = i + 1;
        if (/\d/.test(formatted[i])) seen++;
    }

    $el.val(formatted);
    try { el.setSelectionRange(caret, caret); } catch (e) {}
}

function invFormatAllStored(scope) {
    $(scope || document).find('.inv-number-input').each(function() {
        $(this).val(invFormatStored($(this).val()));
    });
}

function invInitProject() {
    $('#inv-project').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Cari project...',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: invProjectSearchUrl,
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term || '' }; },
            processResults: function(data) { return data; }
        }
    });

    if (invInitialProject) {
        $('#inv-project').append(new Option(invInitialProject.text, invInitialProject.id, true, true));
        $('#inv-project-id').val(invInitialProject.id);
    }

    $('#inv-project').on('change', function() {
        var id = $(this).val();
        $('#inv-project-id').val(id || '');
        if (id) {
            invFetchProject(id, true);
        } else {
            invQuoteItems = [];
            $('#inv-load-quote').prop('disabled', true);
            $('#inv-project-info').text('Pilih project untuk melihat informasi.');
        }
    });
}

function invFetchProject(id, fillShipTo) {
    $.get(invProjectFetchUrl.replace('__ID__', id)).done(function(res) {
        var d = res.data;
        var html = '<strong style="color:var(--text-primary)">' + invEsc(d.project_number) + '</strong> — ' + invEsc(d.project_name);
        html += '<div>' + invEsc(d.company_name || '—') + '</div>';
        html += '<div>Nilai Penawaran (sebelum PPN): ' + invMoney(d.before_ppn) + '</div>';
        html += '<div>Quote: ' + invEsc(d.quotation_number || '—') + ' (' + (d.quotation_items || []).length + ' item)</div>';
        $('#inv-project-info').html(html);

        if (fillShipTo || !$.trim($('#inv-ship-to').val())) {
            $('#inv-ship-to').val(d.ship_to || '');
        }

        // Diskon default mengikuti penawaran: saat project dipilih, atau saat membuat invoice baru.
        if (fillShipTo || !invIsEdit) {
            invApplyQuoteDiscount(d);
        }

        invQuoteItems = d.quotation_items || [];
        $('#inv-load-quote').prop('disabled', invQuoteItems.length === 0);
    }).fail(function() {
        $('#inv-project-info').text('Gagal memuat informasi project.');
    });
}

function invApplyQuoteDiscount(d) {
    var pct = parseFloat(d.discount_percent) || 0;
    var amt = parseFloat(d.discount_amount) || 0;

    $('#inv-disc-pct').val(pct > 0 ? invFormatStored(pct) : '');
    $('#inv-disc-amt').val(pct > 0 || amt <= 0 ? '' : invFormatStored(amt));
    invRecalc();
}

function invItemHtml(item) {
    item = item || {};

    var html = '<tr>';
    html += '<td class="text-center inv-item-num"></td>';
    html += '<td>';
    html += '<input type="hidden" class="inv-product-id" value="' + (item.product_id || '') + '">';
    html += '<input type="hidden" class="inv-product-name" value="' + invEsc(item.product_name || '') + '">';
    html += '<div class="form-control form-control-sm inv-desc" contenteditable="true" data-placeholder="Deskripsi item (wajib)">' + invDescHtml(item) + '</div>';
    html += '</td>';
    html += '<td><input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm inv-number-input inv-qty text-end" value="' + invFormatStored(item.qty) + '"></td>';
    html += '<td><input type="text" class="form-control form-control-sm inv-unit" value="' + invEsc(item.unit || '') + '" maxlength="50"></td>';
    html += '<td><input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm inv-number-input inv-unit-price text-end" value="' + invFormatStored(item.unit_price) + '"></td>';
    html += '<td class="text-end"><input type="text" class="form-control form-control-sm inv-total text-end" value="Rp 0" readonly></td>';
    html += '<td class="text-center"><button type="button" class="btn-icon text-danger" title="Hapus" onclick="invRemoveItem(this)"><i class="fa fa-trash"></i></button></td>';
    html += '</tr>';

    return html;
}

function invEsc(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function invDescHtml(item) {
    if (item && item.description != null && String(item.description).trim() !== '') {
        return String(item.description);
    }

    return invEsc(item && item.product_name ? item.product_name : '');
}

function invPlainText(html) {
    return $('<div>').html(html || '').text().replace(/\u00a0/g, ' ').trim();
}

function invAddItem(item) {
    var $row = $(invItemHtml(item));
    $('#inv-items-body').append($row);
    invRenumber();
    invRecalc();
}

function invRemoveItem(btn) {
    $(btn).closest('tr').remove();
    invRenumber();
    invRecalc();
}

function invRenumber() {
    $('#inv-items-body tr').each(function(i) {
        $(this).find('.inv-item-num').text(i + 1);
    });
    $('#inv-items-empty').toggle($('#inv-items-body tr').length === 0);
}

function invHasFilledItems() {
    return invCollectItems().some(function(item) {
        return invPlainText(item.description) !== '' || item.unit_price !== '';
    });
}

function invOpenQuotePicker() {
    if (!invQuoteItems.length) {
        toastr.warning('Quote project ini tidak memiliki item berharga.');
        return;
    }

    var html = '';
    invQuoteItems.forEach(function(item, index) {
        var description = invPlainText(item.description || '') || item.product_name || '—';

        html += '<tr>';
        html += '<td class="text-center"><input type="checkbox" class="inv-quote-check" value="' + index + '"></td>';
        html += '<td><div style="font-weight:600">' + invEsc(item.product_name || '') + '</div>';
        html += '<div style="font-size:12px;color:var(--text-muted)">' + invEsc(description) + '</div></td>';
        html += '<td class="text-center">' + invNumber(item.qty) + '</td>';
        html += '<td>' + invEsc(item.unit || '—') + '</td>';
        html += '<td class="text-end">' + invMoney(item.unit_price) + '</td>';
        html += '</tr>';
    });

    $('#inv-quote-body').html(html);
    $('#inv-quote-all').prop('checked', false);

    if (!invQuoteModalInstance) {
        invQuoteModalInstance = new bootstrap.Modal(document.getElementById('invQuoteModal'));
    }
    invQuoteModalInstance.show();
}

function invApplyQuoteItems() {
    var indexes = $('#inv-quote-body .inv-quote-check:checked').map(function() {
        return parseInt($(this).val(), 10);
    }).get();

    if (!indexes.length) {
        toastr.error('Pilih minimal satu item quote.');
        return;
    }

    var apply = function() {
        $('#inv-items-body').empty();
        indexes.forEach(function(index) { invAddItem(invQuoteItems[index]); });
        invQuoteModalInstance.hide();
        toastr.success(indexes.length + ' item diambil dari quote. Silakan sesuaikan bila perlu.');
    };

    if (invHasFilledItems()) {
        Swal.fire({
            title: 'Ganti item invoice?',
            text: 'Item yang sudah diisi akan diganti dengan item quote yang dipilih.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Ganti',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) apply();
        });
        return;
    }

    apply();
}

function invUpdateDuePreview() {
    var date = $('#inv-date').val();
    var terms = parseInt($('#inv-terms').val(), 10);
    if (!date || isNaN(terms)) { $('#inv-due-preview').text('—'); return; }

    var d = new Date(date + 'T00:00:00');
    d.setDate(d.getDate() + terms);
    $('#inv-due-preview').text(d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }));
}

function invCollectItems() {
    var items = [];

    $('#inv-items-body tr').each(function() {
        var $row = $(this);
        items.push({
            product_id: $row.find('.inv-product-id').val(),
            product_name: $row.find('.inv-product-name').val(),
            description: $row.find('.inv-desc').html() || '',
            qty: invToRaw($row.find('.inv-qty').val()),
            unit: $row.find('.inv-unit').val(),
            unit_price: invToRaw($row.find('.inv-unit-price').val())
        });
    });

    return items;
}

function invRecalc() {
    var subtotalCents = 0;

    $('#inv-items-body tr').each(function() {
        var $row = $(this);
        var totalCents = Math.round((parseFloat(invToRaw($row.find('.inv-qty').val())) || 0)
            * (parseFloat(invToRaw($row.find('.inv-unit-price').val())) || 0) * 100);

        subtotalCents += totalCents;
        $row.find('.inv-total').val(invMoney(totalCents / 100));
    });

    var discPct = parseFloat(invToRaw($('#inv-disc-pct').val())) || 0;
    var discountCents = discPct > 0
        ? Math.round(subtotalCents * discPct / 100)
        : invCents($('#inv-disc-amt').val());
    var netCents = subtotalCents - discountCents;

    $('#inv-disc-amt').prop('readonly', discPct > 0);
    if (discPct > 0) $('#inv-disc-amt').val(invFormatStored(discountCents / 100));

    var percent = parseFloat(invToRaw($('#inv-percent-payment').val())) || 0;
    var ppnPercent = parseFloat(invToRaw($('#inv-ppn').val())) || 0;
    var amountCents = Math.round(netCents * percent / 100);
    var ppnCents = Math.round(amountCents * ppnPercent / 100);
    var totalCents = amountCents + ppnCents;

    var termLabel = $.trim($('#inv-noted').val()) || 'Pembayaran';

    $('#inv-subtotal').text(invMoney(subtotalCents / 100));
    $('#inv-discount-row').toggle(discountCents !== 0);
    $('#inv-discount-label').text('Diskon' + (discPct > 0 ? ' (' + invNumber(discPct) + '%)' : ''));
    $('#inv-discount').text('- ' + invMoney(discountCents / 100));
    $('#inv-net-subtotal').text(invMoney(netCents / 100)).toggleClass('text-danger', netCents < 0);
    $('#inv-amount-label').text(termLabel + ' (' + invNumber(percent) + '%)');
    $('#inv-amount-payment').text(invMoney(amountCents / 100));
    $('#inv-ppn-label').text('PPN (' + invNumber(ppnPercent) + '%)');
    $('#inv-ppn-amount').text(invMoney(ppnCents / 100));
    $('#inv-total-payment').text(invMoney(totalCents / 100));
}

function invValidate() {
    if (!$('#inv-project-id').val()) { toastr.error('Project wajib dipilih.'); return false; }
    if (!$('#inv-date').val()) { toastr.error('Tanggal invoice wajib diisi.'); return false; }

    var items = invCollectItems();
    if (items.length === 0) { toastr.error('Tambahkan minimal satu item invoice.'); return false; }

    for (var i = 0; i < items.length; i++) {
        if (!invPlainText(items[i].description)) { toastr.error('Deskripsi item baris ' + (i + 1) + ' wajib diisi.'); return false; }
        if (!(parseFloat(items[i].qty) > 0)) { toastr.error('Qty baris ' + (i + 1) + ' harus lebih dari 0.'); return false; }
        if (items[i].unit_price === '' || parseFloat(items[i].unit_price) < 0) { toastr.error('Harga satuan baris ' + (i + 1) + ' wajib diisi.'); return false; }
    }

    var percent = parseFloat(invToRaw($('#inv-percent-payment').val())) || 0;
    if (percent < 0 || percent > 100) { toastr.error('Percent payment harus 0 sampai 100.'); return false; }

    var ppn = parseFloat(invToRaw($('#inv-ppn').val())) || 0;
    if (ppn < 0 || ppn > 100) { toastr.error('PPN harus 0 sampai 100.'); return false; }

    var discPct = parseFloat(invToRaw($('#inv-disc-pct').val())) || 0;
    if (discPct < 0 || discPct > 100) { toastr.error('Diskon persen harus 0 sampai 100.'); return false; }

    var subtotalCents = items.reduce(function(sum, item) {
        return sum + Math.round((parseFloat(item.qty) || 0) * (parseFloat(item.unit_price) || 0) * 100);
    }, 0);
    if (discPct === 0 && invCents($('#inv-disc-amt').val()) > subtotalCents) {
        toastr.error('Diskon tidak boleh melebihi sub total.');
        return false;
    }

    return true;
}

function invSave() {
    if (!invValidate()) return;

    var fd = new FormData();
    fd.append('_token', '{{ csrf_token() }}');
    if (invIsEdit) fd.append('_method', 'PUT');

    fd.append('project_id', $('#inv-project-id').val());
    fd.append('invoice_date', $('#inv-date').val());
    fd.append('date_shipped', $('#inv-date-shipped').val());
    fd.append('terms', $('#inv-terms').val());
    fd.append('do_number', $('#inv-do').val());
    fd.append('shipping_method', $('#inv-shipping').val());
    fd.append('noted', $('#inv-noted').val());
    fd.append('signer_name', $('#inv-signer-name').val());
    fd.append('signer_position', $('#inv-signer-position').val());
    fd.append('ship_to', $('#inv-ship-to').val());
    fd.append('percent_payment', invToRaw($('#inv-percent-payment').val()));
    fd.append('ppn', invToRaw($('#inv-ppn').val()));

    var discPct = invToRaw($('#inv-disc-pct').val()).trim();
    if (parseFloat(discPct) > 0) {
        fd.append('discount_percent', discPct);
    } else {
        fd.append('discount_amount', invToRaw($('#inv-disc-amt').val()).trim() || 0);
    }

    invCollectItems().forEach(function(item, i) {
        fd.append('items[' + i + '][product_id]', item.product_id);
        fd.append('items[' + i + '][product_name]', item.product_name);
        fd.append('items[' + i + '][description]', item.description);
        fd.append('items[' + i + '][qty]', item.qty);
        fd.append('items[' + i + '][unit]', item.unit);
        fd.append('items[' + i + '][unit_price]', item.unit_price);
    });

    $.ajax({
        url: invIsEdit ? invUpdateUrl : invStoreUrl,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false
    }).done(function(res) {
        toastr.success(res.message || 'Invoice tersimpan.');
        setTimeout(function() {
            window.location.href = res.redirect || invShowUrl.replace('__ID__', res.id);
        }, 600);
    }).fail(function(xhr) {
        var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan invoice.';
        toastr.error(message);
    });
}

$(document).on('input', '.inv-number-input', function() {
    invFormatNum(this);
    invRecalc();
});

$(document).on('input', '#inv-noted', invRecalc);
$(document).on('input change', '#inv-date, #inv-terms', invUpdateDuePreview);
$(document).on('change', '#inv-quote-all', function() {
    $('#inv-quote-body .inv-quote-check').prop('checked', $(this).is(':checked'));
});

$(document).ready(function() {
    invInitProject();
    invFormatAllStored();

    (invInitialItems.length ? invInitialItems : [{}]).forEach(function(item) {
        invAddItem(item);
    });

    if (invInitialProject) {
        invFetchProject(invInitialProject.id, false);
    }

    invRecalc();
    invUpdateDuePreview();
});
</script>
@endsection
