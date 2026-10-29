@extends('layouts.app')

@section('title', $goodsRequest ? 'Edit Permintaan Barang' : 'Buat Permintaan Barang')
@section('page-title', $goodsRequest ? 'Edit Permintaan Barang' : 'Buat Permintaan Barang')

@section('styles')
<style>
    .gr-section-sub { font-size: 12px; color: var(--text-muted); font-weight: 400; margin-left: 6px; }
    .gr-hint { font-size: 12px; color: var(--text-muted); }

    .gr-project-info {
        background: var(--accent-subtle);
        border: 1px dashed var(--card-border);
        border-radius: var(--radius-sm);
        padding: 10px 12px;
        font-size: 13px;
        color: var(--text-muted);
    }
    .gr-project-info dl { display: grid; grid-template-columns: 110px 1fr; gap: 4px 10px; margin: 0; }
    .gr-project-info dt { font-weight: 500; }
    .gr-project-info dd { margin: 0; color: var(--text-primary); }

    .gr-chip {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 1px 8px; border-radius: 999px;
        font-size: 11px; font-weight: 600; white-space: nowrap;
        background: var(--accent-soft); color: var(--accent);
    }
    .gr-chip.config { background: #dbeafe; color: #1e40af; }
    .gr-chip.catalog { background: #dcfce7; color: #166534; }
    .gr-chip.manual { background: #f3f4f6; color: #4b5563; }
    .gr-chip.warn { background: #fef3c7; color: #92400e; }

    .gr-desc[contenteditable="true"] {
        border: 1px solid var(--card-border, #ced4da);
        border-radius: .25rem;
        padding: .25rem .5rem;
        min-height: 34px;
        background: #fff;
        font-size: .85rem;
        line-height: 1.45;
        white-space: pre-wrap;
    }
    .gr-desc[contenteditable="true"]:focus { outline: none; border-color: var(--accent); }
    .gr-desc[contenteditable="true"]:empty::before { content: attr(data-placeholder); color: #999; }
    .gr-desc.is-invalid { border-color: var(--danger); }

    #gr-items-body td { vertical-align: top; }
    #gr-items-body .gr-row-num { padding-top: 14px; color: var(--text-muted); font-weight: 600; }
    #gr-items-body .gr-row-source { margin-top: 4px; }
    .gr-qty-group { flex-wrap: nowrap; }
    .gr-qty-group .btn { padding: 0 8px; }
    .gr-qty-group .gr-qty { text-align: center; min-width: 48px; }

    .gr-empty-actions { display: flex; gap: 8px; justify-content: center; margin-top: 10px; flex-wrap: wrap; }

    .gr-summary-sticky { position: sticky; top: 84px; }
    .gr-summary-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; font-size: 14px; }
    .gr-summary-row + .gr-summary-row { border-top: 1px dashed var(--card-border); }
    .gr-summary-row .lbl { color: var(--text-muted); }
    .gr-summary-row .val { font-weight: 600; font-variant-numeric: tabular-nums; }
    .gr-summary-note {
        margin-top: 10px; padding: 10px 12px; font-size: 12px; line-height: 1.5;
        background: var(--accent-subtle); border-radius: var(--radius-sm); color: var(--text-muted);
    }

    /* Picker katalog */
    .gr-pp-toolbar { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1.4fr); gap: 8px; }
    @media (max-width: 767.98px) { .gr-pp-toolbar { grid-template-columns: 1fr; } }
    .gr-pp-search { position: relative; }
    .gr-pp-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; }
    .gr-pp-search input { padding-left: 30px; }
    .gr-pp-table-wrap { border: 1px solid var(--card-border); border-radius: var(--radius-sm); overflow: auto; max-height: 52vh; }
    .gr-pp-table-wrap thead th { position: sticky; top: 0; z-index: 1; background: var(--card); }
    #gr-product-body tr { cursor: pointer; }
    #gr-product-body tr.is-selected { background: var(--accent-subtle); }
    #gr-product-body tr.is-added { opacity: .55; cursor: default; }
    .gr-pp-name { font-weight: 600; font-size: 13px; }
    .gr-pp-desc { font-size: 12px; color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .gr-pp-meta { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }
    .gr-pp-footer-info { font-size: 12.5px; color: var(--text-muted); }
    .gr-pp-pager .page-link { font-size: 12.5px; padding: 3px 9px; }
    .gr-pp-selected-badge { font-size: 12px; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $goodsRequest ? 'Edit Permintaan Barang' : 'Buat Permintaan Barang' }}</h1>
        <p class="page-header-sub">Pilih project, lalu tambahkan barang yang dibutuhkan. Harga tidak diisi di sini — ditentukan saat Purchase Order ke supplier.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $goodsRequest ? route('goods-request.show', $goodsRequest->id) : route('goods-request.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Informasi Permintaan</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-7">
                        <label class="form-label">Project <span class="text-danger">*</span></label>
                        <select id="gr-project-id" class="form-select" style="width:100%" @if($goodsRequest) disabled @endif>
                            <option value="">— Pilih Project —</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}"
                                    data-opportunity-id="{{ $p->quotation?->opportunity_id }}"
                                    data-quotation="{{ $p->quotation?->quotation_number ?? '' }}"
                                    data-opportunity="{{ $p->quotation?->opportunity?->opportunity_name ?? '' }}"
                                    data-company="{{ $p->company?->account_name ?? '' }}"
                                    data-number="{{ $p->project_number }}"
                                    data-name="{{ $p->project_name }}"
                                    @selected(optional($preselected)->id === $p->id)>
                                    {{ $p->project_number }} — {{ $p->project_name }} ({{ $p->quotation?->opportunity?->opportunity_name ?? 'tanpa quotation' }})
                                </option>
                            @endforeach
                        </select>
                        @if($goodsRequest)
                            <input type="hidden" id="gr-project-id-hidden" value="{{ $goodsRequest->project_id }}">
                            <input type="hidden" id="gr-opportunity-id-hidden" value="{{ $goodsRequest->opportunity_id }}">
                            <small class="gr-hint"><i class="fa fa-lock me-1"></i>Project tidak bisa diganti setelah permintaan barang dibuat.</small>
                        @else
                            <small class="gr-hint">Hanya project aktif yang PO Supplier Approval-nya sudah disetujui 2 approver.</small>
                        @endif
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label">Informasi Project</label>
                        <div id="gr-project-info" class="gr-project-info">Pilih project untuk melihat informasi.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan <span class="gr-section-sub">opsional</span></label>
                        <textarea id="gr-notes" class="form-control" rows="2" placeholder="Contoh: barang dibutuhkan sebelum instalasi tanggal 10, kirim ke site Gresik">{{ $goodsRequest?->notes }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>
                    <i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Daftar Item
                    <span class="gr-chip ms-1" id="gr-items-count">0 item</span>
                </span>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-soft btn-sm" id="gr-btn-pick" disabled title="Khusus project dengan quotation">
                        <i class="fa fa-cart-plus me-1"></i> Ambil dari Configuration
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="grOpenProductPicker()">
                        <i class="fa fa-magnifying-glass me-1"></i> Tambah dari Katalog
                    </button>
                </div>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom mb-0" id="gr-items-table">
                        <thead>
                            <tr>
                                <th style="width:42px" class="text-center">#</th>
                                <th style="width:18%">Part Number</th>
                                <th>Nama Barang / Deskripsi</th>
                                <th style="width:130px" class="text-center">Qty</th>
                                <th style="width:110px">Unit</th>
                                <th style="width:100px">Mata Uang</th>
                                <th class="text-center" style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="gr-items-body"></tbody>
                    </table>
                </div>
                <div class="config-card-empty" id="gr-items-empty">
                    <i class="fa-solid fa-inbox"></i> Belum ada item.
                    <div class="gr-empty-actions">
                        <button type="button" class="btn btn-soft btn-sm" id="gr-empty-pick" onclick="grLoadPicker()">
                            <i class="fa fa-cart-plus me-1"></i> Ambil dari Configuration
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="grOpenProductPicker()">
                            <i class="fa fa-magnifying-glass me-1"></i> Cari di Katalog
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="grAddBlankRow()">
                            <i class="fa fa-plus me-1"></i> Baris Kosong
                        </button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center px-2 pt-2" id="gr-items-footer" style="display:none !important">
                    <button type="button" class="btn btn-link btn-sm px-0" onclick="grAddBlankRow()">
                        <i class="fa fa-plus me-1"></i> Tambah baris kosong
                    </button>
                    <small class="gr-hint">Deskripsi bisa diformat: <b>Ctrl+B</b> tebal, <i>Ctrl+I</i> miring.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in gr-summary-sticky">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Ringkasan</span>
            </div>
            <div class="card-body-custom">
                <div class="gr-summary-row"><span class="lbl">Jumlah Item</span><span class="val" id="gr-sum-items">0</span></div>
                <div class="gr-summary-row"><span class="lbl">Total Qty</span><span class="val" id="gr-sum-qty">0</span></div>
                <div class="gr-summary-row"><span class="lbl"><span class="gr-chip config">Configuration</span></span><span class="val" id="gr-sum-config">0</span></div>
                <div class="gr-summary-row"><span class="lbl"><span class="gr-chip catalog">Katalog</span></span><span class="val" id="gr-sum-catalog">0</span></div>
                <div class="gr-summary-row"><span class="lbl"><span class="gr-chip manual">Manual</span></span><span class="val" id="gr-sum-manual">0</span></div>

                <button type="button" class="btn btn-primary w-100 mt-3" id="gr-save-btn" onclick="grSave()">
                    <i class="fa fa-save me-1"></i> Simpan {{ $goodsRequest ? 'Perubahan' : 'Draft' }}
                </button>
                <div class="gr-summary-note">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Disimpan sebagai <strong>Draft</strong>. Ajukan approval dari halaman detail setelah item lengkap.
                </div>
            </div>
        </div>
    </div>
</div>

<datalist id="gr-unit-options">
    <option value="Unit"><option value="Pcs"><option value="Set"><option value="Lot">
    <option value="Paket"><option value="Box"><option value="Meter"><option value="Roll">
</datalist>
@endsection

@push('modals')
<div class="modal fade" id="grPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-cart-plus me-2" style="color:var(--accent)"></i>Ambil Item dari Configuration</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <label class="d-flex align-items-center gap-2 mb-0" style="font-size:12px;color:var(--text-muted);cursor:pointer">
                        <input type="checkbox" id="gr-picker-check-all"> Pilih Semua
                    </label>
                    <small style="color:var(--text-muted)">Setiap configuration ditampilkan sebagai tabel terpisah, urutan mengikuti hierarki parent → anak.</small>
                </div>
                <div id="gr-picker-body">
                    <div class="config-card-empty"><span class="config-spinner"></span>Memuat...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="grApplyPicked()">
                    <i class="fa fa-plus me-1"></i> Tambahkan Terpilih
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="grProductPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-box-open me-2" style="color:var(--accent)"></i>Tambah dari Katalog Product</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="gr-pp-toolbar mb-2">
                    <div class="gr-pp-search">
                        <i class="fa fa-magnifying-glass"></i>
                        <input type="text" class="form-control form-control-sm" id="gr-product-search" placeholder="Cari nama, part number, brand, atau deskripsi..." autocomplete="off">
                    </div>
                    <select id="gr-product-division" class="form-select form-select-sm">
                        <option value="">Semua Divisi</option>
                    </select>
                    <select id="gr-product-category" style="width:100%">
                        <option value="">Semua Kategori</option>
                    </select>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <span class="gr-pp-footer-info" id="gr-product-info">Memuat...</span>
                    <div class="d-flex align-items-center gap-2">
                        <span class="gr-chip gr-pp-selected-badge" id="gr-product-selected">0 dipilih</span>
                        <button type="button" class="btn btn-link btn-sm p-0" id="gr-product-clear" style="display:none">Kosongkan pilihan</button>
                    </div>
                </div>
                <div class="gr-pp-table-wrap">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px" class="text-center"><input type="checkbox" id="gr-product-check-all" title="Pilih semua di halaman ini"></th>
                                <th style="width:150px">Part Number</th>
                                <th>Nama Barang / Deskripsi</th>
                                <th style="width:120px">Divisi / Mata Uang</th>
                            </tr>
                        </thead>
                        <tbody id="gr-product-body">
                            <tr><td colspan="4" class="config-card-empty"><span class="config-spinner"></span>Memuat...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2 gr-pp-footer-info">
                        Tampilkan
                        <select id="gr-product-per-page" class="form-select form-select-sm" style="width:auto">
                            <option value="10">10</option>
                            <option value="15" selected>15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        per halaman
                    </div>
                    <nav><ul class="pagination pagination-sm mb-0 gr-pp-pager" id="gr-product-pager"></ul></nav>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-soft btn-sm" onclick="grAddBlankRow()">
                    <i class="fa fa-file me-1"></i> Baris Kosong (di luar katalog)
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary btn-sm" id="gr-product-apply" onclick="grApplyProductPicked()" disabled>
                        <i class="fa fa-plus me-1"></i> Tambahkan <span id="gr-product-apply-count">0</span> Item
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let grKeySeq = 0;
let grPickerInstance = null;
let grPickerPool = [];
let grProductPickerInstance = null;
let grProductPool = [];
let grProductSearchTimer = null;
let grProductRequest = null;
let grProductFiltersLoaded = false;
// Pilihan katalog disimpan per id agar tetap terpilih saat pindah halaman / filter.
let grProductSelected = {};
const grProductState = { q: '', category: '', division_id: '', page: 1, per_page: 15 };

const grFetchItemsUrl = '{{ route("goods-request.fetch-config-items") }}';
const grSearchProductsUrl = '{{ route("goods-request.search-products") }}';
const grStoreUrl = '{{ route("goods-request.store") }}';
const grUpdateUrl = '{{ $goodsRequest ? route("goods-request.update", $goodsRequest->id) : "" }}';
const grShowUrl = '{{ $goodsRequest ? route("goods-request.show", $goodsRequest->id) : "" }}';
const grInitialItems = @json($items ?? []);
const grCurrencies = @json(collect($currencies ?? [])->pluck('name')->values());
const grBaseCurrency = grCurrencies.length ? grCurrencies[0] : 'IDR';

function grNewKey() {
    return 'row-' + (++grKeySeq);
}

function grEsc(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function grPlainText(html) {
    return $('<div>').html(html || '').text().replace(/ /g, ' ').trim();
}

function grNumber(value) {
    return Number(value || 0).toLocaleString('en-US');
}

// ── Project ──

function grCurrentOpportunityId() {
    var selected = $('#gr-project-id option:selected');
    var fromSelect = selected.length ? selected.data('opportunity-id') : null;
    return fromSelect || $('#gr-opportunity-id-hidden').val() || '';
}

function grRenderProjectInfo() {
    var $opt = $('#gr-project-id option:selected');
    if (!$opt.val()) {
        $('#gr-project-info').text('Pilih project untuk melihat informasi.');
        return;
    }

    var hasConfig = !!grCurrentOpportunityId();
    var html = '<dl>';
    html += '<dt>Project</dt><dd><strong>' + grEsc($opt.data('number')) + '</strong> — ' + grEsc($opt.data('name')) + '</dd>';
    html += '<dt>Customer</dt><dd>' + grEsc($opt.data('company') || '—') + '</dd>';
    html += '<dt>Quotation</dt><dd>' + grEsc($opt.data('quotation') || '—') + '</dd>';
    html += '<dt>Opportunity</dt><dd>' + grEsc($opt.data('opportunity') || '—') + '</dd>';
    html += '<dt>Sumber Item</dt><dd>' + (hasConfig
        ? '<span class="gr-chip config">Configuration</span> <span class="gr-chip catalog">Katalog</span>'
        : '<span class="gr-chip warn">Tanpa quotation — hanya Katalog / manual</span>') + '</dd>';
    html += '</dl>';
    $('#gr-project-info').html(html);
}

function grSyncProject() {
    var hasConfig = !!grCurrentOpportunityId();
    $('#gr-btn-pick').prop('disabled', !hasConfig);
    $('#gr-empty-pick').toggle(hasConfig);
    grRenderProjectInfo();
}

// ── Daftar item ──

function grSourceOf(item) {
    if (item.quote_configuration_item_id) return 'config';
    if (item.master_product_id) return 'catalog';
    return 'manual';
}

function grSourceChip(source) {
    var labels = { config: 'Configuration', catalog: 'Katalog', manual: 'Manual' };
    return '<span class="gr-chip ' + source + '">' + labels[source] + '</span>';
}

// Mata uang dikunci sesuai sumber (configuration / katalog); hanya baris manual
// yang bisa memilih. Server menegakkan aturan yang sama.
function grCurrencyCell(item, source) {
    var currency = String(item.currency || grBaseCurrency).toUpperCase();
    if (source !== 'manual') {
        return '<input type="hidden" class="gr-currency" value="' + grEsc(currency) + '">'
            + '<span class="gr-chip" style="margin-top:6px" title="Mengikuti ' + (source === 'config' ? 'configuration' : 'katalog') + ', tidak bisa diubah">'
            + '<i class="fa fa-lock" style="font-size:9px"></i> ' + grEsc(currency) + '</span>';
    }
    var html = '<select class="form-select form-select-sm gr-currency">';
    (grCurrencies.length ? grCurrencies : [grBaseCurrency]).forEach(function(c) {
        html += '<option value="' + grEsc(c) + '"' + (c === currency ? ' selected' : '') + '>' + grEsc(c) + '</option>';
    });
    return html + '</select>';
}

function grRowHtml(item) {
    var key = grNewKey();
    var source = grSourceOf(item);
    var html = '<tr data-key="' + key + '" data-config-item-id="' + (item.quote_configuration_item_id || '') + '" data-product-id="' + (item.master_product_id || '') + '">';
    html += '<td class="text-center gr-row-num"></td>';
    html += '<td><input type="text" class="form-control form-control-sm gr-pn" maxlength="100" placeholder="Part number" value="' + grEsc(item.part_number || '') + '"></td>';
    {{-- Deskripsi sudah HTML tersanitasi dari server (Quotation::renderDescription) --}}
    html += '<td><div class="form-control form-control-sm gr-desc" contenteditable="true" data-placeholder="Nama barang / deskripsi (wajib)">' + (item.description || grEsc(item.name || '')) + '</div>';
    html += '<div class="gr-row-source">' + grSourceChip(source) + '</div></td>';
    html += '<td><div class="input-group input-group-sm gr-qty-group">';
    html += '<button type="button" class="btn btn-outline-secondary gr-qty-step" data-step="-1" tabindex="-1">−</button>';
    html += '<input type="text" inputmode="numeric" class="form-control gr-qty" value="' + (item.qty != null && item.qty !== '' ? parseInt(item.qty, 10) || '' : '') + '">';
    html += '<button type="button" class="btn btn-outline-secondary gr-qty-step" data-step="1" tabindex="-1">+</button>';
    html += '</div></td>';
    html += '<td><input type="text" class="form-control form-control-sm gr-unit" maxlength="50" list="gr-unit-options" placeholder="Unit" value="' + grEsc(item.unit || '') + '"></td>';
    html += '<td>' + grCurrencyCell(item, source) + '</td>';
    html += '<td class="text-center"><button type="button" class="btn-icon text-danger mt-1" title="Hapus baris" onclick="grRemoveRow(this)"><i class="fa fa-trash"></i></button></td>';
    html += '</tr>';
    return html;
}

function grAddRow(item) {
    var $row = $(grRowHtml(item || {}));
    $('#gr-items-body').append($row);
    grRefresh();
    return $row;
}

function grAddBlankRow() {
    var $row = grAddRow({ qty: 1, unit: 'Unit' });
    if (grProductPickerInstance) grProductPickerInstance.hide();
    setTimeout(function() { $row.find('.gr-desc').trigger('focus'); }, 200);
}

function grRemoveRow(btn) {
    var $row = $(btn).closest('tr');
    var label = grPlainText($row.find('.gr-desc').html()) || $row.find('.gr-pn').val();

    var remove = function() {
        $row.remove();
        grRefresh();
    };

    if (!label) { remove(); return; }

    Swal.fire({
        title: 'Hapus item?',
        text: label.length > 80 ? label.slice(0, 80) + '…' : label,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (result.isConfirmed) remove();
    });
}

function grRefresh() {
    var counts = { config: 0, catalog: 0, manual: 0 };
    var totalQty = 0;
    var $rows = $('#gr-items-body tr');

    $rows.each(function(i) {
        var $row = $(this);
        $row.find('.gr-row-num').text(i + 1);
        counts[grSourceOf({
            quote_configuration_item_id: $row.attr('data-config-item-id'),
            master_product_id: $row.attr('data-product-id')
        })]++;
        totalQty += parseInt($row.find('.gr-qty').val(), 10) || 0;
    });

    var n = $rows.length;
    $('#gr-items-empty').toggle(n === 0);
    $('#gr-items-footer').attr('style', n === 0 ? 'display:none !important' : '');
    $('#gr-items-count').text(n + ' item');
    $('#gr-sum-items').text(grNumber(n));
    $('#gr-sum-qty').text(grNumber(totalQty));
    $('#gr-sum-config').text(counts.config);
    $('#gr-sum-catalog').text(counts.catalog);
    $('#gr-sum-manual').text(counts.manual);
}

function grExistingIds(attr) {
    var ids = {};
    $('#gr-items-body tr').each(function() {
        var id = $(this).attr(attr);
        if (id) ids[id] = true;
    });
    return ids;
}

function grCollectItems() {
    var items = [];
    $('#gr-items-body tr').each(function() {
        items.push({
            quote_configuration_item_id: $(this).attr('data-config-item-id') || null,
            master_product_id: $(this).attr('data-product-id') || null,
            part_number: $(this).find('.gr-pn').val(),
            description: $(this).find('.gr-desc').html(),
            qty: $(this).find('.gr-qty').val(),
            unit: $(this).find('.gr-unit').val(),
            currency: $(this).find('.gr-currency').val()
        });
    });
    return items;
}

// ── Picker: Ambil dari Configuration ──

function grLoadPicker() {
    var opportunityId = grCurrentOpportunityId();
    if (!opportunityId) {
        toastr.error('Project ini tidak terikat quotation, jadi tidak ada configuration. Tambahkan item lewat katalog.');
        return;
    }

    $('#gr-picker-body').html('<div class="config-card-empty"><span class="config-spinner"></span>Memuat...</div>');
    $('#gr-picker-check-all').prop('checked', false);
    if (!grPickerInstance) {
        grPickerInstance = new bootstrap.Modal(document.getElementById('grPickerModal'));
    }
    grPickerInstance.show();

    $.get(grFetchItemsUrl, { opportunity_id: opportunityId })
        .done(function(res) {
            grPickerPool = res.data || [];
            if (grPickerPool.length === 0) {
                $('#gr-picker-body').html('<div class="config-card-empty"><i class="fa-solid fa-inbox"></i>Belum ada item configuration approved untuk opportunity ini.</div>');
                return;
            }
            $('#gr-picker-body').html(grBuildPickerGroupsHtml(grPickerPool));
        })
        .fail(function() {
            $('#gr-picker-body').html('<div class="config-card-empty"><i class="fa-solid fa-triangle-exclamation"></i>Gagal memuat item.</div>');
        });
}

// Setiap Divisi (Water/IMS/dst) ditampilkan sebagai kartu + tabel TERPISAH
// ("Water Configuration", "IMS Configuration", dst), supaya lebih rapi dan
// mudah dibaca saat ada lebih dari satu configuration. Di dalam tiap tabel,
// urutan item mengikuti hierarki parent -> anak apa adanya (sesuai `depth`
// dari QuoteConfiguration::flattenTree() di server — lihat
// GoodsRequestController::fetchConfigItems()), BUKAN dikelompokkan per
// kategori. data-group memakai id buatan sendiri (d0, d1, dst) — bukan nama
// divisi mentah — supaya aman dipakai langsung sebagai selector.
function grBuildPickerGroupsHtml(pool) {
    var divOrder = [], divMap = {};
    var added = grExistingIds('data-config-item-id');

    pool.forEach(function(it, idx) {
        var div = it.division_name || 'Tanpa Divisi';
        if (!divMap[div]) { divMap[div] = { items: [], count: 0 }; divOrder.push(div); }
        divMap[div].items.push(idx);
        divMap[div].count++;
    });

    var html = '';
    var divIdx = 0;
    divOrder.forEach(function(div) {
        var d = divMap[div];
        var divGroup = 'd' + (divIdx++);
        var divLabel = /configuration/i.test(div) ? div : (div + ' Configuration');

        html += '<div class="gr-picker-division mb-3" style="border:1px solid var(--card-border);border-radius:var(--radius);overflow:hidden">';
        html += '<div class="d-flex justify-content-between align-items-center px-3 py-2" style="background:var(--bg);border-bottom:1px solid var(--card-border)">';
        html += '<label class="d-flex align-items-center gap-2 mb-0" style="cursor:pointer">';
        html += '<input type="checkbox" class="gr-picker-check-div" data-group="' + divGroup + '">';
        html += '<strong style="font-size:13px">' + grEsc(divLabel) + '</strong>';
        html += '</label>';
        html += '<span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:11px">' + d.count + ' item</span>';
        html += '</div>';
        html += '<div class="table-responsive">';
        html += '<table class="table table-custom align-middle mb-0">';
        html += '<thead><tr>';
        html += '<th style="width:36px"></th>';
        html += '<th style="width:14%">No</th>';
        html += '<th style="width:18%">Part Number</th>';
        html += '<th>Nama Barang / Deskripsi</th>';
        html += '<th class="text-center" style="width:70px">Qty</th>';
        html += '<th style="width:80px">Mata Uang</th>';
        html += '</tr></thead><tbody>';

        d.items.forEach(function(idx) {
            var it = pool[idx];
            var pad = (it.depth || 0) * 18;
            var isAdded = it.quote_configuration_item_id && added[it.quote_configuration_item_id];
            html += '<tr class="gr-picker-item-row" data-group="' + divGroup + '"' + (isAdded ? ' style="opacity:.55"' : '') + '>';
            html += '<td>' + (isAdded
                ? '<i class="fa fa-check" style="color:var(--accent)" title="Sudah ada di daftar"></i>'
                : '<input type="checkbox" class="gr-picker-check" data-idx="' + idx + '">') + '</td>';
            html += '<td>' + grEsc(it.item_no || '') + '</td>';
            html += '<td style="padding-left:' + pad + 'px">' + grEsc(it.part_number || '—') + '</td>';
            {{-- Deskripsi sudah HTML tersanitasi dari server (Quotation::renderDescription) --}}
            html += '<td style="padding-left:' + pad + 'px">' + (it.description || it.name || '—')
                + (isAdded ? ' <span class="gr-chip manual">Sudah ditambahkan</span>' : '') + '</td>';
            html += '<td class="text-center">' + grEsc(it.qty || '') + '</td>';
            html += '<td>' + grEsc(it.currency || '') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div></div>';
    });

    return html;
}

$(document).on('change', '.gr-picker-check-div', function() {
    var group = $(this).data('group');
    var checked = $(this).is(':checked');
    $('.gr-picker-item-row[data-group="' + group + '"]').find('.gr-picker-check').prop('checked', checked);
});

function grApplyPicked() {
    var picked = 0;
    $('.gr-picker-check:checked').each(function() {
        var item = grPickerPool[$(this).data('idx')];
        if (item) {
            grAddRow(item);
            picked++;
        }
    });
    if (picked === 0) {
        toastr.error('Pilih minimal satu item.');
        return;
    }
    toastr.success(picked + ' item ditambahkan.');
    if (grPickerInstance) grPickerInstance.hide();
}

// ── Picker: Katalog Master Product (tanpa harga) dengan search, filter, paging ──

function grProductSelectedCount() {
    return Object.keys(grProductSelected).length;
}

function grSyncProductSelection() {
    var n = grProductSelectedCount();
    $('#gr-product-selected').text(n + ' dipilih');
    $('#gr-product-clear').toggle(n > 0);
    $('#gr-product-apply-count').text(n);
    $('#gr-product-apply').prop('disabled', n === 0);

    var $checks = $('#gr-product-body .gr-product-check');
    $('#gr-product-check-all').prop('checked', $checks.length > 0 && $checks.filter(':checked').length === $checks.length);
}

function grRenderProductPool(meta) {
    var added = grExistingIds('data-product-id');

    if (grProductPool.length === 0) {
        $('#gr-product-body').html('<tr><td colspan="4" class="config-card-empty"><i class="fa-solid fa-inbox"></i>Product tidak ditemukan. Coba kata kunci atau filter lain, atau tambahkan baris kosong.</td></tr>');
    } else {
        var html = '';
        grProductPool.forEach(function(p, idx) {
            var isAdded = !!added[p.id];
            var isSelected = !!grProductSelected[p.id];
            var desc = grPlainText(p.description);
            var meta = [p.brand, p.category].filter(Boolean).join(' · ');

            html += '<tr data-idx="' + idx + '" class="' + (isAdded ? 'is-added' : (isSelected ? 'is-selected' : '')) + '">';
            html += '<td class="text-center">' + (isAdded
                ? '<i class="fa fa-check" style="color:var(--accent)" title="Sudah ada di daftar"></i>'
                : '<input type="checkbox" class="gr-product-check" data-idx="' + idx + '"' + (isSelected ? ' checked' : '') + '>') + '</td>';
            html += '<td style="font-size:12.5px">' + grEsc(p.code || '—') + '</td>';
            html += '<td><div class="gr-pp-name">' + grEsc(p.name || desc || '—') + (isAdded ? ' <span class="gr-chip manual">Sudah ditambahkan</span>' : '') + '</div>';
            if (desc && desc !== p.name) html += '<div class="gr-pp-desc">' + grEsc(desc) + '</div>';
            if (meta) html += '<div class="gr-pp-meta"><i class="fa fa-tag me-1"></i>' + grEsc(meta) + '</div>';
            html += '</td>';
            html += '<td style="font-size:12.5px">' + grEsc(p.division_name || '—') + '<div class="gr-pp-meta">' + grEsc(p.currency || '') + '</div></td>';
            html += '</tr>';
        });
        $('#gr-product-body').html(html);
    }

    $('#gr-product-info').text(meta && meta.total
        ? 'Menampilkan ' + grNumber(meta.from) + '–' + grNumber(meta.to) + ' dari ' + grNumber(meta.total) + ' product'
        : '0 product');
    grRenderProductPager(meta || { page: 1, last_page: 1 });
    grSyncProductSelection();
}

function grRenderProductPager(meta) {
    var page = meta.page, last = meta.last_page;
    var item = function(label, target, disabled, active) {
        return '<li class="page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '">'
            + '<a class="page-link" href="#" data-page="' + target + '">' + label + '</a></li>';
    };

    if (last <= 1) { $('#gr-product-pager').html(''); return; }

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
    $('#gr-product-pager').html(html);
}

function grLoadProducts() {
    if (grProductRequest) grProductRequest.abort();

    $('#gr-product-body').html('<tr><td colspan="4" class="config-card-empty"><span class="config-spinner"></span>Memuat...</td></tr>');

    var params = {
        q: grProductState.q,
        category: grProductState.category,
        division_id: grProductState.division_id,
        page: grProductState.page,
        per_page: grProductState.per_page
    };
    if (!grProductFiltersLoaded) params.with_filters = 1;

    grProductRequest = $.get(grSearchProductsUrl, params)
        .done(function(res) {
            grProductPool = res.data || [];
            if (res.filters) grFillProductFilters(res.filters);
            grRenderProductPool(res.meta);
        })
        .fail(function(xhr, status) {
            if (status === 'abort') return;
            $('#gr-product-body').html('<tr><td colspan="4" class="config-card-empty"><i class="fa-solid fa-triangle-exclamation"></i>Gagal memuat product.</td></tr>');
        });
}

function grFillProductFilters(filters) {
    grProductFiltersLoaded = true;

    var divHtml = '<option value="">Semua Divisi</option>';
    (filters.divisions || []).forEach(function(d) {
        divHtml += '<option value="' + d.id + '">' + grEsc(d.name) + '</option>';
    });
    $('#gr-product-division').html(divHtml);

    var catHtml = '<option value="">Semua Kategori</option>';
    (filters.categories || []).forEach(function(c) {
        catHtml += '<option value="' + grEsc(c) + '">' + grEsc(c) + '</option>';
    });
    $('#gr-product-category').html(catHtml).trigger('change.select2');
}

function grOpenProductPicker() {
    if (!grProductPickerInstance) {
        grProductPickerInstance = new bootstrap.Modal(document.getElementById('grProductPickerModal'));

        $('#gr-product-category').select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $('#grProductPickerModal'),
            placeholder: 'Semua Kategori',
            allowClear: true,
            selectionCssClass: 'select2--small',
            dropdownCssClass: 'select2--small'
        });
    }

    grProductSelected = {};
    grProductState.q = '';
    grProductState.page = 1;
    $('#gr-product-search').val('');
    grProductPickerInstance.show();
    grLoadProducts();
}

function grToggleProduct(idx, checked) {
    var p = grProductPool[idx];
    if (!p) return;
    if (checked) grProductSelected[p.id] = p; else delete grProductSelected[p.id];
    $('#gr-product-body tr[data-idx="' + idx + '"]').toggleClass('is-selected', checked);
}

function grApplyProductPicked() {
    var added = grExistingIds('data-product-id');
    var picked = 0;

    Object.keys(grProductSelected).forEach(function(id) {
        var p = grProductSelected[id];
        if (added[p.id]) return;
        grAddRow({
            master_product_id: p.id,
            part_number: p.code,
            description: p.description || grEsc(p.name || ''),
            qty: 1,
            unit: 'Unit',
            currency: p.currency
        });
        picked++;
    });

    if (picked === 0) {
        toastr.error('Pilih minimal satu product.');
        return;
    }
    toastr.success(picked + ' item ditambahkan. Sesuaikan qty dan unit bila perlu.');
    if (grProductPickerInstance) grProductPickerInstance.hide();
}

// ── Simpan ──

function grClearInvalid() {
    $('#gr-items-body .is-invalid').removeClass('is-invalid');
}

function grFail(message, $el) {
    toastr.error(message);
    if ($el && $el.length) {
        $el.addClass('is-invalid');
        $el[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function() { $el.trigger('focus'); }, 300);
    }
    return false;
}

function grValidate() {
    grClearInvalid();

    var projectId = $('#gr-project-id').val() || $('#gr-project-id-hidden').val();
    if (!projectId) {
        toastr.error('Pilih project terlebih dahulu.');
        $('#gr-project-id').select2('open');
        return false;
    }

    var $rows = $('#gr-items-body tr');
    if ($rows.length === 0) return grFail('Tambahkan minimal satu item.');

    var ok = true;
    $rows.each(function(i) {
        var $row = $(this);
        if (!grPlainText($row.find('.gr-desc').html())) {
            ok = grFail('Nama barang / deskripsi baris ' + (i + 1) + ' wajib diisi.', $row.find('.gr-desc'));
            return false;
        }
        if (!((parseInt($row.find('.gr-qty').val(), 10) || 0) >= 1)) {
            ok = grFail('Qty baris ' + (i + 1) + ' minimal 1.', $row.find('.gr-qty'));
            return false;
        }
    });

    return ok;
}

function grSetSaving(saving) {
    $('#gr-save-btn').prop('disabled', saving).html(saving
        ? '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...'
        : '<i class="fa fa-save me-1"></i> Simpan {{ $goodsRequest ? 'Perubahan' : 'Draft' }}');
}

function grSave() {
    if (!grValidate()) return;

    var payload = {
        project_id: $('#gr-project-id').val() || $('#gr-project-id-hidden').val(),
        notes: $('#gr-notes').val(),
        items: grCollectItems(),
        _token: '{{ csrf_token() }}'
    };

    var url = grUpdateUrl || grStoreUrl;
    var method = grUpdateUrl ? 'PUT' : 'POST';

    grSetSaving(true);
    $.ajax({ url: url, method: method, data: payload })
        .done(function(res) {
            toastr.success(res.message || 'Tersimpan.');
            setTimeout(function() {
                window.location.href = grUpdateUrl ? grShowUrl : '{{ route("goods-request.index") }}';
            }, 800);
        })
        .fail(function(xhr) {
            grSetSaving(false);
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan.');
        });
}

// ── Event ──

$(document).on('click', '.gr-qty-step', function() {
    var $input = $(this).closest('.gr-qty-group').find('.gr-qty');
    var next = Math.max(1, (parseInt($input.val(), 10) || 0) + parseInt($(this).data('step'), 10));
    $input.val(next).removeClass('is-invalid');
    grRefresh();
});

$(document).on('input', '.gr-qty', function() {
    var clean = $(this).val().replace(/\D/g, '');
    if (clean !== $(this).val()) $(this).val(clean);
    $(this).removeClass('is-invalid');
    grRefresh();
});

$(document).on('input', '.gr-desc', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('change', '#gr-product-body .gr-product-check', function(e) {
    grToggleProduct($(this).data('idx'), $(this).is(':checked'));
    grSyncProductSelection();
});

// Klik di mana saja pada baris untuk memilih.
$(document).on('click', '#gr-product-body tr[data-idx]', function(e) {
    if ($(e.target).is('input')) return;
    var $check = $(this).find('.gr-product-check');
    if ($check.length) $check.prop('checked', !$check.is(':checked')).trigger('change');
});

$(document).on('click', '#gr-product-pager a[data-page]', function(e) {
    e.preventDefault();
    if ($(this).parent().hasClass('disabled') || $(this).parent().hasClass('active')) return;
    grProductState.page = parseInt($(this).data('page'), 10) || 1;
    grLoadProducts();
});

$(document).ready(function() {
    $('#gr-project-id').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: '— Pilih Project —'
    }).on('change', grSyncProject);

    (grInitialItems || []).forEach(function(it) { grAddRow(it); });
    grRefresh();
    grSyncProject();

    $('#gr-btn-pick').on('click', grLoadPicker);

    $('#gr-picker-check-all').on('change', function() {
        var checked = $(this).is(':checked');
        $('.gr-picker-check, .gr-picker-check-div').prop('checked', checked);
    });

    $('#gr-product-check-all').on('change', function() {
        var checked = $(this).is(':checked');
        $('#gr-product-body .gr-product-check').each(function() {
            $(this).prop('checked', checked);
            grToggleProduct($(this).data('idx'), checked);
        });
        grSyncProductSelection();
    });

    $('#gr-product-clear').on('click', function() {
        grProductSelected = {};
        $('#gr-product-body .gr-product-check').prop('checked', false);
        $('#gr-product-body tr').removeClass('is-selected');
        grSyncProductSelection();
    });

    $('#gr-product-search').on('input', function() {
        var val = $.trim($(this).val());
        clearTimeout(grProductSearchTimer);
        grProductSearchTimer = setTimeout(function() {
            grProductState.q = val;
            grProductState.page = 1;
            grLoadProducts();
        }, 350);
    });

    $('#gr-product-division').on('change', function() {
        grProductState.division_id = $(this).val();
        grProductState.page = 1;
        grLoadProducts();
    });

    $('#gr-product-category').on('change', function() {
        if ((grProductState.category || '') === ($(this).val() || '')) return;
        grProductState.category = $(this).val() || '';
        grProductState.page = 1;
        grLoadProducts();
    });

    $('#gr-product-per-page').on('change', function() {
        grProductState.per_page = parseInt($(this).val(), 10) || 15;
        grProductState.page = 1;
        grLoadProducts();
    });
});
</script>
@endsection
