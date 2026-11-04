@extends('layouts.app')

@section('title', $directPurchase ? 'Edit Direct Purchase' : 'Buat Direct Purchase')
@section('page-title', $directPurchase ? 'Edit Direct Purchase' : 'Buat Direct Purchase')

@section('styles')
<style>
    .dp-section-sub { font-size: 12px; color: var(--text-muted); font-weight: 400; margin-left: 6px; }
    .dp-hint { font-size: 12px; color: var(--text-muted); }
    .dp-chip {
        display: inline-flex; align-items: center; gap: 4px; padding: 1px 8px; border-radius: 999px;
        font-size: 11px; font-weight: 600; white-space: nowrap; background: var(--accent-soft); color: var(--accent);
    }
    .dp-chip.muted { background: #f3f4f6; color: #4b5563; }
    .dp-chip.warn { background: #fef3c7; color: #92400e; }

    /* Tujuan barang */
    .dp-purpose { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
    @media (max-width: 767.98px) { .dp-purpose { grid-template-columns: 1fr; } }
    .dp-purpose input { display: none; }
    .dp-purpose label {
        margin: 0; cursor: pointer; padding: 9px 12px; height: 100%;
        border: 1px solid var(--card-border); border-radius: var(--radius-sm);
        font-size: 12.5px; color: var(--text-muted);
    }
    .dp-purpose label strong { display: block; color: var(--text-primary); font-size: 13.5px; margin-bottom: 1px; }
    .dp-purpose input:checked + label { border-color: var(--accent); background: var(--accent-subtle); box-shadow: 0 0 0 1px var(--accent) inset; }

    /* Item */
    .dp-desc[contenteditable="true"] {
        border: 1px solid var(--card-border, #ced4da); border-radius: .25rem; padding: .25rem .5rem;
        min-height: 34px; background: #fff; font-size: .85rem; line-height: 1.45; white-space: pre-wrap;
    }
    .dp-desc[contenteditable="true"]:focus { outline: none; border-color: var(--accent); }
    .dp-desc[contenteditable="true"]:empty::before { content: attr(data-placeholder); color: #999; }
    .dp-desc.is-invalid { border-color: var(--danger); }
    #dp-items-body td { vertical-align: top; }
    #dp-items-body .dp-row-num { padding-top: 14px; color: var(--text-muted); font-weight: 600; }
    #dp-items-body .dp-amount { padding-top: 14px; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .dp-product-link { font-size: 12px; }
    .dp-qty-group { flex-wrap: nowrap; }
    .dp-qty-group .btn { padding: 0 8px; }
    .dp-qty-group .dp-qty { text-align: center; min-width: 44px; }
    .dp-price-group .input-group-text { font-size: 11px; padding: 0 6px; }
    .dp-empty-actions { display: flex; gap: 8px; justify-content: center; margin-top: 10px; flex-wrap: wrap; }

    /* Ringkasan */
    .dp-summary-sticky { position: sticky; top: 84px; }
    .dp-summary-number { text-align: center; padding: 12px; margin-bottom: 10px; background: var(--accent-subtle); border-radius: var(--radius-sm); }
    .dp-summary-number .lbl { font-size: 11.5px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
    .dp-summary-number .val { font-size: 20px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }
    .dp-summary-number .val.pending { font-size: 13px; font-weight: 500; color: var(--text-muted); }
    .dp-summary-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 7px 0; font-size: 13.5px; }
    .dp-summary-row + .dp-summary-row { border-top: 1px dashed var(--card-border); }
    .dp-summary-row .lbl { color: var(--text-muted); white-space: nowrap; }
    .dp-summary-row .val { font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; overflow: hidden; text-overflow: ellipsis; }
    .dp-summary-row .dp-ppn-input { width: 74px; text-align: right; }
    .dp-summary-total {
        display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding: 12px 14px;
        background: var(--accent-soft); border-radius: var(--radius-sm); font-weight: 700;
    }
    .dp-summary-total .val { font-size: 17px; color: var(--accent); font-variant-numeric: tabular-nums; }
    .dp-summary-note { margin-top: 10px; font-size: 12px; line-height: 1.5; color: var(--text-muted); }

    .dp-sign summary { cursor: pointer; list-style: none; }
    .dp-sign summary::-webkit-details-marker { display: none; }
    .dp-sign summary .chev { transition: transform .15s; }
    .dp-sign[open] summary .chev { transform: rotate(180deg); }

    /* Picker katalog (selaras Permintaan Barang) */
    .dp-pp-toolbar { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1.4fr); gap: 8px; }
    @media (max-width: 767.98px) { .dp-pp-toolbar { grid-template-columns: 1fr; } }
    .dp-pp-search { position: relative; }
    .dp-pp-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; }
    .dp-pp-search input { padding-left: 30px; }
    .dp-pp-table-wrap { border: 1px solid var(--card-border); border-radius: var(--radius-sm); overflow: auto; max-height: 52vh; }
    .dp-pp-table-wrap thead th { position: sticky; top: 0; z-index: 1; background: var(--card); }
    #dp-product-body tr { cursor: pointer; }
    #dp-product-body tr.is-selected { background: var(--accent-subtle); }
    #dp-product-body tr.is-added, #dp-product-body tr.is-locked { opacity: .55; cursor: default; }
    .dp-pp-name { font-weight: 600; font-size: 13px; }
    .dp-pp-desc { font-size: 12px; color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .dp-pp-meta { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }
    .dp-pp-footer-info { font-size: 12.5px; color: var(--text-muted); }
    .dp-pp-pager .page-link { font-size: 12.5px; padding: 3px 9px; }
</style>
@endsection

@section('content')
@php($dpPurpose = $directPurchase?->purpose ?? \App\Models\DirectPurchase::PURPOSE_STOCK)
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $directPurchase ? 'Edit Direct Purchase '.($directPurchase->dp_number ?: '') : 'Buat Direct Purchase' }}</h1>
        <p class="page-header-sub">Pembelian langsung tanpa Permintaan Barang / PO — pilih supplier dan tujuan barang, lalu tambahkan item dari katalog produk.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $directPurchase ? route('direct-purchase.show', $directPurchase->id) : route('direct-purchase.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-9">
        {{-- 1. Supplier & tujuan --}}
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Informasi Pembelian</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-5">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select id="dp-supplier-select" class="form-select" style="width:100%">
                            @if($directPurchase && $directPurchase->supplier_id)
                                <option value="{{ $directPurchase->supplier_id }}" selected>{{ $directPurchase->supplier_name }}</option>
                            @endif
                        </select>
                        <input type="hidden" id="dp-supplier-id" value="{{ $directPurchase?->supplier_id }}">
                        <input type="hidden" id="dp-supplier-name" value="{{ $directPurchase?->supplier_name }}">
                        <small class="dp-hint">Dari master Supplier. Belum ada? Tambahkan lewat menu Supplier.</small>
                    </div>
                    <div class="col-lg-3 col-sm-4">
                        <label class="form-label">Telepon Supplier</label>
                        <input type="text" class="form-control" id="dp-supplier-phone" maxlength="50" value="{{ $directPurchase?->supplier_phone }}" placeholder="Otomatis dari supplier">
                    </div>
                    <div class="col-lg-2 col-sm-4">
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-control" id="dp-date" value="{{ $directPurchase ? $directPurchase->date?->format('Y-m-d') : now()->toDateString() }}">
                    </div>
                    <div class="col-lg-2 col-sm-4">
                        <label class="form-label">Termin Bayar</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="dp-payment-term-days" min="0" max="365" step="1" placeholder="Ikut supplier" value="{{ $directPurchase?->payment_term_days }}">
                            <span class="input-group-text">hari</span>
                        </div>
                        <small class="dp-hint">0 = tunai.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Tujuan Barang <span class="text-danger">*</span></label>
                        <div class="dp-purpose">
                            @foreach([
                                'stock' => ['fa-warehouse', 'Untuk Stok', 'Diterima ke gudang sebagai stok bebas.'],
                                'project' => ['fa-diagram-project', 'Untuk Project', 'Diterima ke gudang (umumnya transit) & dialokasikan ke project.'],
                                'direct' => ['fa-hand-holding', 'Langsung Pakai', 'Cukup konfirmasi terima, tidak masuk stok.'],
                            ] as $purposeKey => [$purposeIcon, $purposeLabel, $purposeHint])
                                <div>
                                    <input type="radio" name="dp-purpose" id="dp-purpose-{{ $purposeKey }}" value="{{ $purposeKey }}" @checked($dpPurpose === $purposeKey)>
                                    <label for="dp-purpose-{{ $purposeKey }}"><strong><i class="fa-solid {{ $purposeIcon }} me-1"></i>{{ $purposeLabel }}</strong>{{ $purposeHint }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-lg-8" id="dp-project-wrap" style="{{ $dpPurpose === 'project' ? '' : 'display:none' }}">
                        <label class="form-label">Project <span class="text-danger">*</span></label>
                        <select id="dp-project" class="form-select" style="width:100%">
                            <option value="">Pilih project...</option>
                            @foreach($projects as $projectOption)
                                <option value="{{ $projectOption['id'] }}" @selected((int) $directPurchase?->project_id === $projectOption['id'])>{{ $projectOption['text'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan <span class="dp-section-sub">opsional, tercetak di bawah item</span></label>
                        <textarea id="dp-notes" class="form-control" rows="2" placeholder="Contoh: Pembelian consumable untuk site Gresik">{{ $directPurchase?->notes }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Item --}}
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>
                    <i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Daftar Item
                    <span class="dp-chip ms-1" id="dp-items-count">0 item</span>
                    <span class="dp-chip muted ms-1" id="dp-doc-currency" style="display:none" title="Satu dokumen satu mata uang, mengikuti produk"></span>
                </span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="dpOpenProductPicker()">
                    <i class="fa fa-magnifying-glass me-1"></i> Tambah dari Katalog
                </button>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom mb-0" id="dp-items-table">
                        <thead>
                            <tr>
                                <th style="width:40px" class="text-center">#</th>
                                <th style="width:20%">Produk</th>
                                <th>Nama Barang / Deskripsi</th>
                                <th style="width:120px" class="text-center">Qty</th>
                                <th style="width:90px">Unit</th>
                                <th style="width:160px" class="text-end">Harga Satuan</th>
                                <th style="width:130px" class="text-end">Subtotal</th>
                                <th class="text-center" style="width:44px"></th>
                            </tr>
                        </thead>
                        <tbody id="dp-items-body"></tbody>
                    </table>
                </div>
                <div class="config-card-empty" id="dp-items-empty">
                    <i class="fa-solid fa-inbox"></i> Belum ada item.
                    <div class="dp-empty-actions">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="dpOpenProductPicker()">
                            <i class="fa fa-magnifying-glass me-1"></i> Cari di Katalog
                        </button>
                    </div>
                </div>
                <div class="d-flex justify-content-between flex-wrap gap-2 px-2 pt-2" id="dp-items-footer" style="display:none !important">
                    <small class="dp-hint"><i class="fa fa-circle-info me-1"></i>Item wajib dari katalog produk (untuk pencatatan stok). Harga terisi dari harga beli master — sesuaikan dengan nota.</small>
                    <small class="dp-hint">Deskripsi bisa diformat: <b>Ctrl+B</b> tebal, <i>Ctrl+I</i> miring.</small>
                </div>
            </div>
        </div>

        {{-- 3. Penanda tangan (opsional, dilipat) --}}
        <details class="card-custom fade-in dp-sign" @if($directPurchase?->request_by_name || $directPurchase?->finance_name || $directPurchase?->accounting_name) open @endif>
            <summary class="card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-signature me-2" style="color:var(--accent)"></i>Penanda Tangan <span class="dp-section-sub">opsional, tercetak di PDF</span></span>
                <i class="fa fa-chevron-down chev" style="color:var(--text-muted);font-size:12px"></i>
            </summary>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Request By</label>
                        <input type="text" class="form-control" id="dp-request-by" placeholder="Nama pemohon" value="{{ $directPurchase?->request_by_name }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Finance Dept</label>
                        <input type="text" class="form-control" id="dp-finance-name" placeholder="Nama penanggung jawab Finance" value="{{ $directPurchase?->finance_name }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Accounting Dept</label>
                        <input type="text" class="form-control" id="dp-accounting-name" placeholder="Nama penanggung jawab Accounting" value="{{ $directPurchase?->accounting_name }}">
                    </div>
                </div>
            </div>
        </details>
    </div>

    {{-- Ringkasan --}}
    <div class="col-xl-3">
        <div class="card-custom fade-in dp-summary-sticky">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Ringkasan</span>
            </div>
            <div class="card-body-custom">
                <div class="dp-summary-number">
                    <div class="lbl">No. Dokumen</div>
                    <div class="val" id="dp-sum-number">—</div>
                </div>
                <div class="dp-summary-row"><span class="lbl">Supplier</span><span class="val" id="dp-sum-supplier" title="">—</span></div>
                <div class="dp-summary-row"><span class="lbl">Tujuan</span><span class="val" id="dp-sum-purpose">—</span></div>
                <div class="dp-summary-row"><span class="lbl">Item</span><span class="val" id="dp-sum-items">0</span></div>
                <div class="dp-summary-row"><span class="lbl">Sub Total</span><span class="val" id="dp-sum-subtotal">0</span></div>
                <div class="dp-summary-row">
                    <span class="lbl d-flex align-items-center gap-1">PPN
                        <span class="input-group input-group-sm" style="width:auto">
                            <input type="number" class="form-control dp-ppn-input" id="dp-ppn-percent" min="0" max="100" step="any" placeholder="0" value="{{ $directPurchase ? $directPurchase->ppn_percent : \App\Models\DirectPurchase::DEFAULT_PPN_PERCENT }}">
                            <span class="input-group-text">%</span>
                        </span>
                    </span>
                    <span class="val" id="dp-sum-ppn">0</span>
                </div>
                <div class="dp-summary-total"><span>Total</span><span class="val" id="dp-sum-total">0</span></div>

                <button type="button" class="btn btn-primary w-100 mt-3" id="dp-save-btn" onclick="dpSave()">
                    <i class="fa fa-save me-1"></i> Simpan {{ $directPurchase ? 'Perubahan' : 'Draft' }}
                </button>
                <div class="dp-summary-note">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Disimpan sebagai <strong>Draft</strong>. Ajukan approval dari halaman detail setelah harga lengkap.
                </div>
            </div>
        </div>
    </div>
</div>

<datalist id="dp-unit-options">
    <option value="Unit"><option value="Pcs"><option value="Set"><option value="Lot">
    <option value="Paket"><option value="Box"><option value="Meter"><option value="Roll">
</datalist>
@endsection

@push('modals')
<div class="modal fade" id="dpProductPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="dp-picker-title"><i class="fa-solid fa-box-open me-2" style="color:var(--accent)"></i>Tambah dari Katalog Product</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="dp-pp-toolbar mb-2">
                    <div class="dp-pp-search">
                        <i class="fa fa-magnifying-glass"></i>
                        <input type="text" class="form-control form-control-sm" id="dp-product-search" placeholder="Cari nama, part number, brand, atau deskripsi..." autocomplete="off">
                    </div>
                    <select id="dp-product-division" class="form-select form-select-sm">
                        <option value="">Semua Divisi</option>
                    </select>
                    <select id="dp-product-category" style="width:100%">
                        <option value="">Semua Kategori</option>
                    </select>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <span class="dp-pp-footer-info" id="dp-product-info">Memuat...</span>
                    <div class="d-flex align-items-center gap-2">
                        <span class="dp-chip" id="dp-product-selected">0 dipilih</span>
                        <button type="button" class="btn btn-link btn-sm p-0" id="dp-product-clear" style="display:none">Kosongkan pilihan</button>
                    </div>
                </div>
                <div class="dp-pp-table-wrap">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px" class="text-center"><input type="checkbox" id="dp-product-check-all" title="Pilih semua di halaman ini"></th>
                                <th style="width:150px">Part Number</th>
                                <th>Nama Barang / Deskripsi</th>
                                <th style="width:120px">Divisi</th>
                                <th style="width:150px" class="text-end">Harga Beli</th>
                            </tr>
                        </thead>
                        <tbody id="dp-product-body">
                            <tr><td colspan="5" class="config-card-empty"><span class="config-spinner"></span>Memuat...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2 dp-pp-footer-info">
                        Tampilkan
                        <select id="dp-product-per-page" class="form-select form-select-sm" style="width:auto">
                            <option value="10">10</option>
                            <option value="15" selected>15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        per halaman
                    </div>
                    <nav><ul class="pagination pagination-sm mb-0 dp-pp-pager" id="dp-product-pager"></ul></nav>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <small class="dp-hint" id="dp-picker-hint"><i class="fa fa-circle-info me-1"></i>Barang belum ada di katalog? Tambahkan dulu di Master Produk.</small>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary btn-sm" id="dp-product-apply" onclick="dpApplyProductPicked()" disabled></button>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let dpKeySeq = 0;

const dpStoreUrl = '{{ route("direct-purchase.store") }}';
const dpUpdateUrl = '{{ $directPurchase ? route("direct-purchase.update", $directPurchase->id) : "" }}';
const dpShowUrl = '{{ $directPurchase ? route("direct-purchase.show", $directPurchase->id) : "" }}';
const dpSearchProductsUrl = '{{ route("direct-purchase.search-products") }}';
const dpCurrencies = @json($currencies ?? []);
const dpInitialItems = @json($items ?? []);
const dpCurrentNumber = @json($directPurchase?->dp_number);
const dpSaveLabel = '<i class="fa fa-save me-1"></i> Simpan {{ $directPurchase ? 'Perubahan' : 'Draft' }}';

function dpEsc(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function dpPlainText(html) {
    return $('<div>').html(html || '').text().replace(/ /g, ' ').trim();
}

// Angka format Indonesia: "1.250.000,5" <-> 1250000.5
// Format angka satu standar (gaya US) — lihat partials/number-input.
function dpParse(v) { return Num.parse(v); }

function dpFmt(v) { return Num.format(v, 2); }

function dpBaseCurrency() {
    return dpCurrencies.length ? dpCurrencies[0].name : 'IDR';
}

// Mata uang dokumen = mata uang item pertama (satu dokumen satu mata uang).
function dpDocCurrency($exceptRow) {
    var active = null;
    $('#dp-items-body tr').each(function() {
        if ($exceptRow && this === $exceptRow[0]) return;
        var c = $(this).find('.dp-currency').val();
        if (c) { active = c; return false; }
    });
    return active;
}

function dpExistingProductIds() {
    var ids = {};
    $('#dp-items-body tr').each(function() { ids[$(this).attr('data-master-product-id')] = true; });
    return ids;
}

// ── Baris item ──

function dpProductCell(item) {
    return '<div class="dp-product-cell"><div style="font-weight:600;font-size:13px">' + dpEsc(item.part_number || '—') + '</div>'
        + '<div class="dp-hint">' + dpEsc(item.product_name || '') + '</div>'
        + '<a href="javascript:void(0)" class="dp-product-link" onclick="dpPickProductForRow(this)"><i class="fa fa-arrows-rotate me-1"></i>Ganti produk</a></div>';
}

function dpRowHtml(item) {
    var currency = String(item.currency || dpDocCurrency() || dpBaseCurrency()).toUpperCase();
    var html = '<tr data-key="row-' + (++dpKeySeq) + '" data-master-product-id="' + (item.master_product_id || '') + '">';
    html += '<td class="text-center dp-row-num"></td>';
    html += '<td><input type="hidden" class="dp-pn" value="' + dpEsc(item.part_number || '') + '">'
        + '<input type="hidden" class="dp-currency" value="' + dpEsc(currency) + '">' + dpProductCell(item) + '</td>';
    {{-- Deskripsi sudah HTML tersanitasi dari server (Quotation::renderDescription) --}}
    html += '<td><div class="form-control form-control-sm dp-desc" contenteditable="true" data-placeholder="Nama barang / deskripsi (wajib)">' + (item.description || '') + '</div></td>';
    html += '<td><div class="input-group input-group-sm dp-qty-group">';
    html += '<button type="button" class="btn btn-outline-secondary dp-qty-step" data-step="-1" tabindex="-1">−</button>';
    html += '<input type="text" inputmode="numeric" class="form-control dp-qty" value="' + (item.qty != null && item.qty !== '' ? parseInt(item.qty, 10) || '' : '') + '">';
    html += '<button type="button" class="btn btn-outline-secondary dp-qty-step" data-step="1" tabindex="-1">+</button>';
    html += '</div></td>';
    html += '<td><input type="text" class="form-control form-control-sm dp-unit" maxlength="50" list="dp-unit-options" placeholder="Unit" value="' + dpEsc(item.unit || '') + '"></td>';
    html += '<td><div class="input-group input-group-sm dp-price-group"><span class="input-group-text dp-price-currency">' + dpEsc(currency) + '</span>'
        + '<input type="text" inputmode="decimal" class="form-control text-end dp-price" value="' + (item.price != null && item.price !== '' ? dpFmt(item.price) : '') + '" placeholder="0"></div></td>';
    html += '<td class="text-end dp-amount">0</td>';
    html += '<td class="text-center"><button type="button" class="btn-icon text-danger mt-1" title="Hapus baris" onclick="dpRemoveRow(this)"><i class="fa fa-trash"></i></button></td>';
    html += '</tr>';
    return html;
}

function dpAddRow(item) {
    $('#dp-items-body').append(dpRowHtml(item || {}));
    dpRecalc();
}

function dpRemoveRow(btn) {
    var $row = $(btn).closest('tr');
    var label = dpPlainText($row.find('.dp-desc').html()) || $row.find('.dp-pn').val();
    var remove = function() { $row.remove(); dpRecalc(); };
    if (!label) { remove(); return; }

    Swal.fire({
        title: 'Hapus item?',
        text: label.length > 80 ? label.slice(0, 80) + '…' : label,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) { if (result.isConfirmed) remove(); });
}

function dpRecalc() {
    var subtotal = 0;
    var $rows = $('#dp-items-body tr');
    $rows.each(function(i) {
        var $row = $(this);
        var amount = dpParse($row.find('.dp-qty').val()) * dpParse($row.find('.dp-price').val());
        subtotal += amount;
        $row.find('.dp-row-num').text(i + 1);
        $row.find('.dp-amount').text(dpFmt(amount));
    });

    var n = $rows.length;
    $('#dp-items-empty').toggle(n === 0);
    $('#dp-items-footer').attr('style', n === 0 ? 'display:none !important' : '');
    $('#dp-items-count').text(n + ' item');
    $('#dp-sum-items').text(n);

    var code = dpDocCurrency() || dpBaseCurrency();
    $('#dp-doc-currency').toggle(n > 0).html('<i class="fa fa-lock" style="font-size:9px"></i> ' + dpEsc(code));

    var ppnPct = parseFloat($('#dp-ppn-percent').val()) || 0;
    var ppn = Math.round(subtotal * ppnPct) / 100;
    $('#dp-sum-subtotal').text(code + ' ' + dpFmt(subtotal));
    $('#dp-sum-ppn').text(code + ' ' + dpFmt(ppn));
    $('#dp-sum-total').text(code + ' ' + dpFmt(subtotal + ppn));
}

function dpCollectItems() {
    var items = [];
    $('#dp-items-body tr').each(function() {
        var $row = $(this);
        items.push({
            master_product_id: $row.attr('data-master-product-id') || null,
            part_number: $row.find('.dp-pn').val(),
            description: $row.find('.dp-desc').html(),
            qty: parseInt($row.find('.dp-qty').val(), 10) || 0,
            unit: $row.find('.dp-unit').val(),
            currency: $row.find('.dp-currency').val(),
            price: Math.round(dpParse($row.find('.dp-price').val()) * 100) / 100
        });
    });
    return items;
}

// ── Picker katalog: search, filter divisi/kategori, paging, multi-pilih ──

let dpPickerInstance = null;
let dpPickerTargetRow = null;  // null = tambah baris; selain itu ganti produk baris tsb.
let dpProductPool = [];
let dpProductSelected = {};
let dpProductRequest = null;
let dpProductSearchTimer = null;
let dpProductFiltersLoaded = false;
const dpProductState = { q: '', category: '', division_id: '', page: 1, per_page: 15 };

function dpPickProductForRow(el) {
    dpOpenProductPicker($(el).closest('tr'));
}

function dpOpenProductPicker($row) {
    dpPickerTargetRow = $row || null;
    $('#dp-picker-title').html(dpPickerTargetRow
        ? '<i class="fa-solid fa-arrows-rotate me-2" style="color:var(--accent)"></i>Ganti Produk Baris'
        : '<i class="fa-solid fa-box-open me-2" style="color:var(--accent)"></i>Tambah dari Katalog Product');

    if (!dpPickerInstance) {
        dpPickerInstance = new bootstrap.Modal(document.getElementById('dpProductPickerModal'));
        $('#dp-product-category').select2({
            theme: 'bootstrap-5', width: '100%', dropdownParent: $('#dpProductPickerModal'),
            placeholder: 'Semua Kategori', allowClear: true,
            selectionCssClass: 'select2--small', dropdownCssClass: 'select2--small'
        });
    }

    dpProductSelected = {};
    dpProductState.q = '';
    dpProductState.page = 1;
    $('#dp-product-search').val('');
    dpPickerInstance.show();
    dpLoadProducts();
}

function dpLoadProducts() {
    if (dpProductRequest) dpProductRequest.abort();
    $('#dp-product-body').html('<tr><td colspan="5" class="config-card-empty"><span class="config-spinner"></span>Memuat...</td></tr>');

    var params = $.extend({}, dpProductState);
    if (!dpProductFiltersLoaded) params.with_filters = 1;

    dpProductRequest = $.get(dpSearchProductsUrl, params)
        .done(function(res) {
            dpProductPool = res.data || [];
            if (res.filters) dpFillProductFilters(res.filters);
            dpRenderProductPool(res.meta);
        })
        .fail(function(xhr, status) {
            if (status === 'abort') return;
            $('#dp-product-body').html('<tr><td colspan="5" class="config-card-empty"><i class="fa-solid fa-triangle-exclamation"></i>Gagal memuat product.</td></tr>');
        });
}

function dpFillProductFilters(filters) {
    dpProductFiltersLoaded = true;
    var divHtml = '<option value="">Semua Divisi</option>';
    (filters.divisions || []).forEach(function(d) { divHtml += '<option value="' + d.id + '">' + dpEsc(d.name) + '</option>'; });
    $('#dp-product-division').html(divHtml);
    var catHtml = '<option value="">Semua Kategori</option>';
    (filters.categories || []).forEach(function(c) { catHtml += '<option value="' + dpEsc(c) + '">' + dpEsc(c) + '</option>'; });
    $('#dp-product-category').html(catHtml).trigger('change.select2');
}

function dpRenderProductPool(meta) {
    var added = dpPickerTargetRow ? {} : dpExistingProductIds();
    var docCurrency = dpDocCurrency(dpPickerTargetRow);

    if (dpProductPool.length === 0) {
        $('#dp-product-body').html('<tr><td colspan="5" class="config-card-empty"><i class="fa-solid fa-inbox"></i>Product tidak ditemukan. Coba kata kunci atau filter lain.</td></tr>');
    } else {
        var html = '';
        dpProductPool.forEach(function(p, idx) {
            var isAdded = !!added[p.id];
            var isLocked = !isAdded && docCurrency && p.currency !== docCurrency;
            var isSelected = !!dpProductSelected[p.id];
            var desc = dpPlainText(p.description);
            var meta = [p.brand, p.category].filter(Boolean).join(' · ');

            html += '<tr data-idx="' + idx + '" class="' + (isAdded ? 'is-added' : (isLocked ? 'is-locked' : (isSelected ? 'is-selected' : ''))) + '">';
            html += '<td class="text-center">';
            if (isAdded) html += '<i class="fa fa-check" style="color:var(--accent)" title="Sudah ada di daftar"></i>';
            else if (isLocked) html += '<i class="fa fa-lock" style="color:var(--text-muted)" title="Mata uang berbeda dengan dokumen"></i>';
            else html += '<input type="' + (dpPickerTargetRow ? 'radio' : 'checkbox') + '" name="dp-product-pick" class="dp-product-check" data-idx="' + idx + '"' + (isSelected ? ' checked' : '') + '>';
            html += '</td>';
            html += '<td style="font-size:12.5px">' + dpEsc(p.code || '—') + '</td>';
            html += '<td><div class="dp-pp-name">' + dpEsc(p.name || desc || '—')
                + (isAdded ? ' <span class="dp-chip muted">Sudah ditambahkan</span>' : '')
                + (isLocked ? ' <span class="dp-chip warn">Mata uang ' + dpEsc(p.currency) + ' — beda dengan dokumen (' + dpEsc(docCurrency) + ')</span>' : '') + '</div>';
            if (desc && desc !== p.name) html += '<div class="dp-pp-desc">' + dpEsc(desc) + '</div>';
            if (meta) html += '<div class="dp-pp-meta"><i class="fa fa-tag me-1"></i>' + dpEsc(meta) + '</div>';
            html += '</td>';
            html += '<td style="font-size:12.5px">' + dpEsc(p.division_name || '—') + '</td>';
            html += '<td class="text-end" style="font-size:12.5px;white-space:nowrap">' + (p.price ? dpEsc(p.currency) + ' ' + dpFmt(p.price) : '<span class="dp-hint">' + dpEsc(p.currency) + ' —</span>') + '</td>';
            html += '</tr>';
        });
        $('#dp-product-body').html(html);
    }

    $('#dp-product-info').text(meta && meta.total
        ? 'Menampilkan ' + dpFmt(meta.from) + '–' + dpFmt(meta.to) + ' dari ' + dpFmt(meta.total) + ' product'
        : '0 product');
    $('#dp-product-check-all').closest('th').toggle(!dpPickerTargetRow);
    dpRenderProductPager(meta || { page: 1, last_page: 1 });
    dpSyncProductSelection();
}

function dpRenderProductPager(meta) {
    var page = meta.page, last = meta.last_page;
    if (last <= 1) { $('#dp-product-pager').html(''); return; }
    var item = function(label, target, disabled, active) {
        return '<li class="page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '"><a class="page-link" href="#" data-page="' + target + '">' + label + '</a></li>';
    };
    var html = item('&laquo;', page - 1, page <= 1, false);
    var pages = [1];
    for (var p = Math.max(2, page - 1); p <= Math.min(last - 1, page + 1); p++) pages.push(p);
    pages.push(last);
    var prev = 0;
    pages.forEach(function(p) {
        if (p - prev > 1) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
        html += item(p, p, false, p === page);
        prev = p;
    });
    html += item('&raquo;', page + 1, page >= last, false);
    $('#dp-product-pager').html(html);
}

function dpSyncProductSelection() {
    var n = Object.keys(dpProductSelected).length;
    $('#dp-product-selected').text(n + ' dipilih');
    $('#dp-product-clear').toggle(n > 0);
    $('#dp-product-apply').prop('disabled', n === 0).html(dpPickerTargetRow
        ? '<i class="fa fa-check me-1"></i> Pakai Produk Terpilih'
        : '<i class="fa fa-plus me-1"></i> Tambahkan ' + n + ' Item');
    var $checks = $('#dp-product-body .dp-product-check');
    $('#dp-product-check-all').prop('checked', $checks.length > 0 && $checks.filter(':checked').length === $checks.length);
}

function dpToggleProduct(idx, checked) {
    var p = dpProductPool[idx];
    if (!p) return;
    if (dpPickerTargetRow) {
        dpProductSelected = {};
        $('#dp-product-body tr').removeClass('is-selected');
    }
    if (checked) dpProductSelected[p.id] = p; else delete dpProductSelected[p.id];
    $('#dp-product-body tr[data-idx="' + idx + '"]').toggleClass('is-selected', checked);
}

function dpApplyProductPicked() {
    var picked = Object.keys(dpProductSelected).map(function(id) { return dpProductSelected[id]; });
    if (!picked.length) { toastr.error('Pilih minimal satu product.'); return; }

    // Satu dokumen satu mata uang — termasuk di antara produk yang dipilih sekaligus.
    var currencies = picked.map(function(p) { return p.currency; }).filter(function(c, i, all) { return all.indexOf(c) === i; });
    var docCurrency = dpDocCurrency(dpPickerTargetRow);
    if (currencies.length > 1 || (docCurrency && currencies[0] !== docCurrency)) {
        toastr.error('Satu dokumen hanya boleh satu mata uang (' + (docCurrency || currencies.join(', ')) + '). Buat dokumen terpisah untuk mata uang lain.');
        return;
    }

    if (dpPickerTargetRow) {
        var p = picked[0];
        var $row = dpPickerTargetRow;
        $row.attr('data-master-product-id', p.id);
        $row.find('.dp-pn').val(p.code || '');
        $row.find('.dp-currency').val(p.currency);
        $row.find('.dp-price-currency').text(p.currency);
        $row.find('.dp-product-cell').replaceWith(dpProductCell({ part_number: p.code, product_name: p.name }));
        if (!dpPlainText($row.find('.dp-desc').html())) $row.find('.dp-desc').html(p.description || dpEsc(p.name || ''));
        if (p.price) $row.find('.dp-price').val(dpFmt(p.price));
        dpRecalc();
        toastr.success('Produk baris diganti.');
    } else {
        picked.forEach(function(p) {
            dpAddRow({ master_product_id: p.id, part_number: p.code, product_name: p.name, description: p.description || dpEsc(p.name || ''), qty: 1, unit: 'Unit', currency: p.currency, price: p.price || null });
        });
        toastr.success(picked.length + ' item ditambahkan. Sesuaikan qty dan harga bila perlu.');
    }
    dpPickerInstance.hide();
}

// ── Simpan ──

function dpFail(message, $el) {
    toastr.error(message);
    if ($el && $el.length) {
        $el.addClass('is-invalid');
        $el[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function() { $el.trigger('focus'); }, 300);
    }
    return false;
}

function dpPurpose() {
    return $('input[name="dp-purpose"]:checked').val() || 'stock';
}

function dpSyncPurpose() {
    $('#dp-project-wrap').toggle(dpPurpose() === 'project');
    $('#dp-sum-purpose').text($('input[name="dp-purpose"]:checked').next('label').find('strong').text().trim() || '—');
}

function dpValidate() {
    $('#dp-items-body .is-invalid, #dp-ppn-percent').removeClass('is-invalid');

    if (!$('#dp-supplier-name').val()) {
        toastr.error('Supplier wajib dipilih.');
        $('#dp-supplier-select').select2('open');
        return false;
    }
    if (dpPurpose() === 'project' && !$('#dp-project').val()) {
        toastr.error('Pilih project untuk pembelian bertujuan project.');
        $('#dp-project').select2('open');
        return false;
    }
    var ppnPct = parseFloat($('#dp-ppn-percent').val()) || 0;
    if (ppnPct < 0 || ppnPct > 100) return dpFail('PPN harus 0 sampai 100%.', $('#dp-ppn-percent'));

    var $rows = $('#dp-items-body tr');
    if ($rows.length === 0) return dpFail('Tambahkan minimal satu item dari katalog.');

    var ok = true;
    $rows.each(function(i) {
        var $row = $(this);
        if (!$row.attr('data-master-product-id')) { ok = dpFail('Baris ' + (i + 1) + ' belum terhubung master produk.', $row.find('.dp-product-cell')); return false; }
        if (!dpPlainText($row.find('.dp-desc').html())) { ok = dpFail('Nama barang / deskripsi baris ' + (i + 1) + ' wajib diisi.', $row.find('.dp-desc')); return false; }
        if (!((parseInt($row.find('.dp-qty').val(), 10) || 0) >= 1)) { ok = dpFail('Qty baris ' + (i + 1) + ' minimal 1.', $row.find('.dp-qty')); return false; }
    });
    return ok;
}

function dpSetSaving(saving) {
    $('#dp-save-btn').prop('disabled', saving).html(saving ? '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...' : dpSaveLabel);
}

function dpSave() {
    if (!dpValidate()) return;

    var payload = {
        supplier_name: $('#dp-supplier-name').val(),
        supplier_id: $('#dp-supplier-id').val() || null,
        supplier_phone: $('#dp-supplier-phone').val(),
        date: $('#dp-date').val(),
        payment_term_days: $('#dp-payment-term-days').val(),
        ppn_percent: $('#dp-ppn-percent').val(),
        purpose: dpPurpose(),
        project_id: dpPurpose() === 'project' ? $('#dp-project').val() : '',
        notes: $('#dp-notes').val(),
        request_by_name: $('#dp-request-by').val(),
        finance_name: $('#dp-finance-name').val(),
        accounting_name: $('#dp-accounting-name').val(),
        items: dpCollectItems(),
        _token: '{{ csrf_token() }}'
    };

    dpSetSaving(true);
    $.ajax({ url: dpUpdateUrl || dpStoreUrl, method: dpUpdateUrl ? 'PUT' : 'POST', data: payload })
        .done(function(res) {
            toastr.success(res.message || 'Tersimpan.');
            setTimeout(function() {
                window.location.href = dpUpdateUrl ? dpShowUrl : '{{ route("direct-purchase.index") }}';
            }, 800);
        })
        .fail(function(xhr) {
            dpSetSaving(false);
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan.');
        });
}

// ── Event ──

$(document).on('click', '.dp-qty-step', function() {
    var $input = $(this).closest('.dp-qty-group').find('.dp-qty');
    $input.val(Math.max(1, (parseInt($input.val(), 10) || 0) + parseInt($(this).data('step'), 10))).removeClass('is-invalid');
    dpRecalc();
});
$(document).on('input', '.dp-qty', function() {
    var clean = $(this).val().replace(/\D/g, '');
    if (clean !== $(this).val()) $(this).val(clean);
    $(this).removeClass('is-invalid');
    dpRecalc();
});
$(document).on('input', '.dp-price', function() {
    var clean = $(this).val().replace(/[^\d.,]/g, '');
    if (clean !== $(this).val()) $(this).val(clean);
    dpRecalc();
});
$(document).on('blur', '.dp-price', function() {
    if ($(this).val() !== '') $(this).val(dpFmt(dpParse($(this).val())));
});
$(document).on('input', '.dp-desc', function() { $(this).removeClass('is-invalid'); });
$(document).on('input', '#dp-ppn-percent', dpRecalc);

$(document).on('change', '#dp-product-body .dp-product-check', function() {
    dpToggleProduct($(this).data('idx'), $(this).is(':checked'));
    dpSyncProductSelection();
});
$(document).on('click', '#dp-product-body tr[data-idx]', function(e) {
    if ($(e.target).is('input')) return;
    var $check = $(this).find('.dp-product-check');
    if ($check.length) $check.prop('checked', dpPickerTargetRow ? true : !$check.is(':checked')).trigger('change');
});
$(document).on('click', '#dp-product-pager a[data-page]', function(e) {
    e.preventDefault();
    if ($(this).parent().is('.disabled, .active')) return;
    dpProductState.page = parseInt($(this).data('page'), 10) || 1;
    dpLoadProducts();
});

$(document).ready(function() {
    (dpInitialItems || []).forEach(function(it) { dpAddRow(it); });
    dpRecalc();

    if (dpCurrentNumber) $('#dp-sum-number').text(dpCurrentNumber);
    else $('#dp-sum-number').addClass('pending').text('Otomatis saat disimpan');

    $('#dp-project').select2({ theme: 'bootstrap-5', width: '100%', placeholder: 'Pilih project...' });
    $(document).on('change', 'input[name="dp-purpose"]', dpSyncPurpose);
    dpSyncPurpose();

    var syncSupplier = function() {
        var name = $('#dp-supplier-name').val();
        $('#dp-sum-supplier').text(name || '—').attr('title', name || '');
    };
    $('#dp-supplier-select').select2({
        theme: 'bootstrap-5', placeholder: 'Cari supplier...', allowClear: true, width: '100%', minimumInputLength: 0,
        ajax: {
            url: '{{ route("supplier.search") }}', dataType: 'json', delay: 250,
            data: function(params) { return { q: params.term || '' }; },
            processResults: function(res) { return { results: res.results }; }
        }
    }).on('select2:select', function(e) {
        var s = e.params.data;
        $('#dp-supplier-id').val(s.id);
        $('#dp-supplier-name').val(s.text);
        $('#dp-supplier-phone').val(s.phone || '');
        if (s.payment_term_days != null) $('#dp-payment-term-days').val(s.payment_term_days);
        syncSupplier();
    }).on('select2:clear', function() {
        $('#dp-supplier-id').val('');
        $('#dp-supplier-name').val('');
        syncSupplier();
    });
    syncSupplier();

    // Picker
    $('#dp-product-check-all').on('change', function() {
        var checked = $(this).is(':checked');
        $('#dp-product-body .dp-product-check').each(function() {
            $(this).prop('checked', checked);
            dpToggleProduct($(this).data('idx'), checked);
        });
        dpSyncProductSelection();
    });
    $('#dp-product-clear').on('click', function() {
        dpProductSelected = {};
        $('#dp-product-body .dp-product-check').prop('checked', false);
        $('#dp-product-body tr').removeClass('is-selected');
        dpSyncProductSelection();
    });
    $('#dp-product-search').on('input', function() {
        var val = $.trim($(this).val());
        clearTimeout(dpProductSearchTimer);
        dpProductSearchTimer = setTimeout(function() { dpProductState.q = val; dpProductState.page = 1; dpLoadProducts(); }, 350);
    });
    $('#dp-product-division').on('change', function() { dpProductState.division_id = $(this).val(); dpProductState.page = 1; dpLoadProducts(); });
    $('#dp-product-category').on('change', function() {
        if ((dpProductState.category || '') === ($(this).val() || '')) return;
        dpProductState.category = $(this).val() || '';
        dpProductState.page = 1;
        dpLoadProducts();
    });
    $('#dp-product-per-page').on('change', function() { dpProductState.per_page = parseInt($(this).val(), 10) || 15; dpProductState.page = 1; dpLoadProducts(); });
});
</script>
@endsection
