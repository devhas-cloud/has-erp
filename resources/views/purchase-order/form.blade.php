@extends('layouts.app')

@section('title', $purchaseOrder ? 'Edit Purchase Order' : 'Buat Purchase Order')
@section('page-title', $purchaseOrder ? 'Edit Purchase Order' : 'Buat Purchase Order')

@section('styles')
<style>
    .po-section-sub { font-size: 12px; color: var(--text-muted); font-weight: 400; margin-left: 6px; }
    .po-hint { font-size: 12px; color: var(--text-muted); }

    .po-desc[contenteditable="true"] {
        border: 1px solid var(--card-border, #ced4da);
        border-radius: .25rem;
        padding: .25rem .5rem;
        min-height: 34px;
        background: #fff;
        font-size: .85rem;
        line-height: 1.45;
        white-space: pre-wrap;
    }
    .po-desc[contenteditable="true"]:focus { outline: none; border-color: var(--accent); }
    .po-desc[contenteditable="true"]:empty::before { content: attr(data-placeholder); color: #999; }
    .po-desc.is-invalid { border-color: var(--danger); }

    /* Pilihan jenis PO sebagai kartu */
    .po-type-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
    @media (max-width: 575.98px) { .po-type-cards { grid-template-columns: 1fr; } }
    .po-type-card {
        display: flex; gap: 10px; align-items: flex-start;
        padding: 10px 12px; margin: 0;
        border: 1px solid var(--card-border); border-radius: var(--radius-sm);
        cursor: pointer; transition: all .15s; background: var(--card);
    }
    .po-type-card:hover { border-color: var(--accent); }
    .po-type-card .icon {
        width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--accent-subtle); color: var(--text-muted);
    }
    .po-type-card .name { font-weight: 600; font-size: 13.5px; color: var(--text-primary); }
    .po-type-card .name .sfx { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-left: 4px; }
    .po-type-card .desc { font-size: 11.5px; color: var(--text-muted); line-height: 1.35; }
    .btn-check:checked + .po-type-card { border-color: var(--accent); background: var(--accent-subtle); box-shadow: 0 0 0 1px var(--accent) inset; }
    .btn-check:checked + .po-type-card .icon { background: var(--accent); color: #fff; }

    .po-supplier-box {
        background: var(--accent-subtle); border: 1px dashed var(--card-border);
        border-radius: var(--radius-sm); padding: 12px;
    }

    #po-items-body td { vertical-align: top; }
    #po-items-body .po-row-num { padding-top: 14px; color: var(--text-muted); font-weight: 600; }
    #po-items-body tr[data-type="group"] td { background: var(--accent-subtle); }
    #po-items-body .po-amount { padding-top: 14px; font-weight: 600; font-variant-numeric: tabular-nums; }
    #po-items-table tfoot td { border-top: 2px solid var(--card-border); font-size: 14px; }
    .po-empty-actions { display: flex; gap: 8px; justify-content: center; margin-top: 10px; flex-wrap: wrap; }

    .po-summary-sticky { position: sticky; top: 84px; }
    .po-summary-number {
        text-align: center; padding: 12px; margin-bottom: 10px;
        background: var(--accent-subtle); border-radius: var(--radius-sm);
    }
    .po-summary-number .lbl { font-size: 11.5px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
    .po-summary-number .val { font-size: 20px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }
    .po-summary-number .val.pending { font-size: 13px; font-weight: 500; color: var(--text-muted); }
    .po-summary-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 7px 0; font-size: 13.5px; }
    .po-summary-row + .po-summary-row { border-top: 1px dashed var(--card-border); }
    .po-summary-row .lbl { color: var(--text-muted); white-space: nowrap; }
    .po-summary-row .val { font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; overflow: hidden; text-overflow: ellipsis; }
    .po-summary-total {
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 8px; padding: 12px 14px;
        background: var(--accent-soft); border-radius: var(--radius-sm);
        font-weight: 700;
    }
    .po-summary-total .val { font-size: 17px; color: var(--accent); font-variant-numeric: tabular-nums; }
    .po-summary-note { margin-top: 10px; font-size: 12px; line-height: 1.5; color: var(--text-muted); }

    .po-picker-toolbar { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 8px; }
    @media (max-width: 767.98px) { .po-picker-toolbar { grid-template-columns: 1fr; } }
</style>
@endsection

@section('content')
@php
    $poType = $purchaseOrder?->po_type ?? \App\Models\PurchaseOrder::TYPE_MANUFACTURE;
    $typeMeta = [
        'manufacture' => ['fa-industry', 'Pembelian dari pabrikan / principal'],
        'local' => ['fa-store', 'Pembelian dari supplier lokal'],
        'calibration' => ['fa-scale-balanced', 'Jasa kalibrasi alat'],
    ];
@endphp
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $purchaseOrder ? 'Edit Purchase Order' : 'Buat Purchase Order' }}</h1>
        <p class="page-header-sub">Pilih supplier dan jenis PO, lalu ambil item dari Permintaan Barang yang sudah Approved — satu PO boleh menggabungkan beberapa project.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $purchaseOrder ? route('purchase-order.show', $purchaseOrder->id) : route('purchase-order.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Jenis &amp; Detail PO</span>
            </div>
            <div class="card-body-custom">
                <label class="form-label">Jenis PO <span class="text-danger">*</span></label>
                <div class="po-type-cards mb-1" role="radiogroup">
                    @foreach(\App\Models\PurchaseOrder::TYPE_LABELS as $typeKey => $typeLabel)
                        <input type="radio" class="btn-check" name="po-type" id="po-type-{{ $typeKey }}" value="{{ $typeKey }}" autocomplete="off" @checked($poType === $typeKey)>
                        <label class="po-type-card" for="po-type-{{ $typeKey }}">
                            <span class="icon"><i class="fa-solid {{ $typeMeta[$typeKey][0] ?? 'fa-file' }}"></i></span>
                            <span>
                                <span class="name">{{ $typeLabel }}@if(\App\Models\PurchaseOrder::TYPE_SUFFIXES[$typeKey])<span class="sfx">{{ \App\Models\PurchaseOrder::TYPE_SUFFIXES[$typeKey] }}</span>@endif</span>
                                <span class="desc d-block">{{ $typeMeta[$typeKey][1] ?? '' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="po-hint mb-3" id="po-number-preview"></div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tanggal PO</label>
                        <input type="date" class="form-control" id="po-date" value="{{ $purchaseOrder ? $purchaseOrder->date?->format('Y-m-d') : now()->toDateString() }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Terms</label>
                        <input type="text" class="form-control" id="po-terms" list="po-terms-options" placeholder="Contoh: TT" value="{{ $purchaseOrder?->terms }}">
                        <datalist id="po-terms-options">
                            <option value="TT"><option value="TT in Advance"><option value="COD">
                            <option value="Net 14 Days"><option value="Net 30 Days"><option value="DP 50%">
                        </datalist>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Delivery <span class="po-section-sub">opsional</span></label>
                        <input type="text" class="form-control" id="po-delivery" maxlength="150" placeholder="Contoh: Franco Jakarta" value="{{ $purchaseOrder?->delivery }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Your Ref <span class="po-section-sub">no. penawaran supplier</span></label>
                        <input type="text" class="form-control" id="po-your-ref" maxlength="100" placeholder="Contoh: 1055/TSC/10/2026" value="{{ $purchaseOrder?->your_ref }}">
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label">Diskon</label>
                        <div class="input-group">
                            <input type="number" class="form-control po-percent-input" id="po-discount-percent" min="0" max="100" step="any" placeholder="0" value="{{ $purchaseOrder?->discount_percent }}">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label">PPN</label>
                        <div class="input-group">
                            <input type="number" class="form-control po-percent-input" id="po-ppn-percent" min="0" max="100" step="any" placeholder="0" value="{{ $purchaseOrder ? $purchaseOrder->ppn_percent : \App\Models\PurchaseOrder::DEFAULT_PPN_PERCENT }}">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan <span class="po-section-sub">opsional, tercetak di bawah item</span></label>
                        <textarea id="po-notes" class="form-control" rows="2" placeholder="Contoh: Freight to Jakarta using FedEx account ...">{{ $purchaseOrder?->notes }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-truck-field me-2" style="color:var(--accent)"></i>Supplier</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-5">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select id="po-supplier-select" class="form-select" style="width:100%">
                            @if($purchaseOrder && $purchaseOrder->supplier_id)
                                <option value="{{ $purchaseOrder->supplier_id }}" selected>{{ $purchaseOrder->supplier_name }}</option>
                            @endif
                        </select>
                        <input type="hidden" id="po-supplier-id" value="{{ $purchaseOrder?->supplier_id }}">
                        <input type="hidden" id="po-supplier-name" value="{{ $purchaseOrder?->supplier_name }}">
                        @if($purchaseOrder && ! $purchaseOrder->supplier_id)
                            <small class="po-hint">PO ini dibuat sebelum modul Supplier ada ("{{ $purchaseOrder->supplier_name }}") — pilih ulang dari master untuk menautkan.</small>
                        @else
                            <small class="po-hint">Kontak terisi otomatis dari master Supplier. Belum ada? Tambahkan lewat menu Supplier.</small>
                        @endif
                    </div>
                    <div class="col-lg-7">
                        <div class="po-supplier-box">
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label mb-1" style="font-size:12px">Alamat</label>
                                    <textarea id="po-supplier-address" class="form-control form-control-sm" rows="2" placeholder="Terisi otomatis saat memilih supplier, bisa disesuaikan">{{ $purchaseOrder?->supplier_address }}</textarea>
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label mb-1" style="font-size:12px">Telepon</label>
                                    <input type="text" class="form-control form-control-sm" id="po-supplier-phone" value="{{ $purchaseOrder?->supplier_phone }}">
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label mb-1" style="font-size:12px">Fax</label>
                                    <input type="text" class="form-control form-control-sm" id="po-supplier-fax" value="{{ $purchaseOrder?->supplier_fax }}">
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label mb-1" style="font-size:12px">Attn</label>
                                    <input type="text" class="form-control form-control-sm" id="po-supplier-attn" value="{{ $purchaseOrder?->supplier_attn }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>
                    <i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Daftar Item
                    <span class="badge ms-1" style="background:var(--accent-soft);color:var(--accent);font-size:11px" id="po-items-count">0 item</span>
                </span>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-soft btn-sm" onclick="poLoadPicker()">
                        <i class="fa fa-dolly me-1"></i> Ambil dari Permintaan Barang
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="poAddGroup()">
                        <i class="fa fa-folder-plus me-1"></i> Group
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="poAddBlankRow()">
                        <i class="fa fa-plus me-1"></i> Baris Manual
                    </button>
                </div>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom mb-0" id="po-items-table">
                        <thead>
                            <tr>
                                <th style="width:40px" class="text-center">#</th>
                                <th style="width:11%">Asal</th>
                                <th style="width:15%">Part Number</th>
                                <th>Nama Barang / Deskripsi</th>
                                <th style="width:7%">Qty</th>
                                <th style="width:7%">Unit</th>
                                <th style="width:9%">Mata Uang</th>
                                <th style="width:11%" class="text-end">Harga Satuan</th>
                                <th style="width:12%" class="text-end">Subtotal</th>
                                <th class="text-center" style="width:70px"></th>
                            </tr>
                        </thead>
                        <tbody id="po-items-body"></tbody>
                        <tfoot>
                            <tr>
                                <td colspan="8" class="text-end fw-bold">Grand Total <span class="po-currency-code"></span></td>
                                <td class="text-end fw-bold" id="po-grand-total">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="config-card-empty" id="po-items-empty">
                    <i class="fa-solid fa-inbox"></i> Belum ada item.
                    <div class="po-empty-actions">
                        <button type="button" class="btn btn-soft btn-sm" onclick="poLoadPicker()">
                            <i class="fa fa-dolly me-1"></i> Ambil dari Permintaan Barang
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="poAddBlankRow()">
                            <i class="fa fa-plus me-1"></i> Baris Manual
                        </button>
                    </div>
                </div>
                <div class="px-2 pt-2 po-hint">
                    <i class="fa fa-circle-info me-1"></i>
                    Item dari Permintaan Barang otomatis dikelompokkan per project. Klik <i class="fa fa-search"></i> di Part Number untuk mengambil harga dari master produk.
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-signature me-2" style="color:var(--accent)"></i>Penanda Tangan <span class="po-section-sub">tercetak di PDF</span></span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Request By</label>
                        <input type="text" class="form-control" id="po-request-by" placeholder="Nama pemohon" value="{{ $purchaseOrder?->request_by_name }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Finance Dept</label>
                        <input type="text" class="form-control" id="po-finance-name" placeholder="Nama penanggung jawab Finance" value="{{ $purchaseOrder?->finance_name }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Accounting Dept</label>
                        <input type="text" class="form-control" id="po-accounting-name" placeholder="Nama penanggung jawab Accounting" value="{{ $purchaseOrder?->accounting_name }}">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in po-summary-sticky">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Ringkasan</span>
            </div>
            <div class="card-body-custom">
                <div class="po-summary-number">
                    <div class="lbl">No. PO</div>
                    <div class="val" id="po-sum-number">—</div>
                </div>
                <div class="po-summary-row"><span class="lbl">Jenis</span><span class="val" id="po-sum-type">—</span></div>
                <div class="po-summary-row"><span class="lbl">Supplier</span><span class="val" id="po-sum-supplier" title="">—</span></div>
                <div class="po-summary-row"><span class="lbl">Mata Uang</span><span class="val" id="po-sum-currency">—</span></div>
                <div class="po-summary-row"><span class="lbl">Jumlah Item</span><span class="val" id="po-sum-items">0</span></div>
                <div class="po-summary-row"><span class="lbl">Project</span><span class="val" id="po-sum-projects">0</span></div>
                <div class="po-summary-row"><span class="lbl">Sub Total</span><span class="val" id="po-sum-subtotal">Rp 0</span></div>
                <div class="po-summary-row" id="po-sum-discount-row"><span class="lbl" id="po-sum-discount-label">Diskon</span><span class="val text-danger" id="po-sum-discount">- Rp 0</span></div>
                <div class="po-summary-row" id="po-sum-ppn-row"><span class="lbl" id="po-sum-ppn-label">PPN</span><span class="val" id="po-sum-ppn">Rp 0</span></div>
                <div class="po-summary-total"><span>Total</span><span class="val" id="po-sum-total">0</span></div>

                <button type="button" class="btn btn-primary w-100 mt-3" id="po-save-btn" onclick="poSave()">
                    <i class="fa fa-save me-1"></i> Simpan {{ $purchaseOrder ? 'Perubahan' : 'Draft' }}
                </button>
                <div class="po-summary-note">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Disimpan sebagai <strong>Draft</strong>. Ajukan approval dari halaman detail setelah harga lengkap.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="poPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-dolly me-2" style="color:var(--accent)"></i>Ambil Item dari Permintaan Barang</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="po-picker-toolbar mb-2">
                    <select id="po-picker-project" style="width:100%">
                        <option value="">Semua Project</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" id="po-picker-search" placeholder="Cari part number / nama barang..." autocomplete="off">
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <label class="d-flex align-items-center gap-2 mb-0" style="font-size:12px;color:var(--text-muted);cursor:pointer">
                        <input type="checkbox" id="po-picker-check-all"> Pilih Semua
                    </label>
                    <small style="color:var(--text-muted)">Dikelompokkan per Permintaan Barang &amp; project — satu PO boleh menggabungkan beberapa project. Hanya item Approved yang belum dipakai di PO manapun.</small>
                </div>
                <div id="po-picker-body">
                    <div class="config-card-empty"><span class="config-spinner"></span>Memuat...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="poApplyPicked()">
                    <i class="fa fa-plus me-1"></i> Tambahkan Terpilih
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="poProductPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-box me-2" style="color:var(--accent)"></i>Pilih Produk Master</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="po-product-q" placeholder="Cari kode / nama / brand / kategori produk">
                    <button type="button" class="btn btn-primary" onclick="poSearchProducts()"><i class="fa fa-search me-1"></i>Cari</button>
                </div>
                <div id="po-product-results">
                    <div class="config-card-empty"><i class="fa-solid fa-inbox"></i>Ketik kata kunci lalu tekan Cari.</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let poKeySeq = 0;
let poPickerInstance = null;
let poPickerPool = [];

const poFetchAvailableItemsUrl = '{{ route("purchase-order.fetch-available-items") }}';
const poStoreUrl = '{{ route("purchase-order.store") }}';
const poUpdateUrl = '{{ $purchaseOrder ? route("purchase-order.update", $purchaseOrder->id) : "" }}';
const poShowUrl = '{{ $purchaseOrder ? route("purchase-order.show", $purchaseOrder->id) : "" }}';
const poCurrencies = @json($currencies ?? []);
const poInitialItems = @json($items ?? []);
const poCurrentNumber = @json($purchaseOrder?->po_number);
const poTypeSuffixes = @json(\App\Models\PurchaseOrder::TYPE_SUFFIXES);
const poTypeLabels = @json(\App\Models\PurchaseOrder::TYPE_LABELS);
const poSaveLabel = '<i class="fa fa-save me-1"></i> Simpan {{ $purchaseOrder ? 'Perubahan' : 'Draft' }}';
let poPickerProjectsLoaded = false;

const poSearchProductsUrl = '{{ route("purchase-order.search-products") }}';

function poNewKey() {
    return 'row-' + (++poKeySeq);
}

function poEsc(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function poToRaw(str) {
    return String(str == null ? '' : str).replace(/,/g, '');
}

function poFmt(v) {
    v = Number(v) || 0;
    return v.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

function poBaseCurrency() {
    return poCurrencies.length ? poCurrencies[0].name : 'IDR';
}

// Satu PO hanya boleh satu mata uang: ambil mata uang item pertama (selain $exceptRow).
function poActiveCurrency($exceptRow) {
    var active = null;
    $('#po-items-body tr').not('[data-type="group"]').each(function() {
        if ($exceptRow && this === $exceptRow[0]) return;
        var c = $(this).find('.po-currency').val();
        if (c) { active = c; return false; }
    });
    return active;
}

function poItemCurrencies() {
    var set = {};
    $('#po-items-body tr').not('[data-type="group"]').each(function() {
        var c = $(this).find('.po-currency').val();
        if (c) set[c] = true;
    });
    return Object.keys(set);
}

// locked: mata uang item Permintaan Barang tidak bisa diubah (server juga mengunci).
function poCurrencySelect(selected, locked) {
    var base = poCurrencies.length ? poCurrencies[0].name : 'IDR';
    var html = '<select class="form-select form-select-sm po-currency"' + (locked ? ' disabled title="Mengikuti mata uang Permintaan Barang"' : '') + '>';
    (poCurrencies.length ? poCurrencies : [{ name: base }]).forEach(function(c) {
        html += '<option value="' + c.name + '"' + (c.name === (selected || base) ? ' selected' : '') + '>' + c.name + '</option>';
    });
    return html + '</select>';
}

function poSyncEmpty() {
    $('#po-items-empty').toggle($('#po-items-body tr').length === 0);
}

// ── Hirarki: baris group (kategori/project) sebagai parent, item sebagai anak. ──

function poGroupRowHtml(key, label) {
    var html = '<tr data-key="' + key + '" data-parent="" data-depth="0" data-type="group">';
    html += '<td class="text-center po-row-num"></td>';
    html += '<td colspan="7"><input type="text" class="form-control form-control-sm po-category" value="' + poEsc(label || '') + '" placeholder="Nama kategori / project (mis. SPARING Gresik)"></td>';
    html += '<td class="text-end text-muted" style="font-size:11px">Grup</td>';
    html += '<td class="text-center">';
    html += '<button type="button" class="btn-icon" title="Tambah Baris di group ini" onclick="poAddChild(this)"><i class="fa fa-plus"></i></button>';
    html += '<button type="button" class="btn-icon text-danger" title="Hapus group" onclick="poRemoveRow(this)"><i class="fa fa-trash"></i></button>';
    html += '</td></tr>';
    return html;
}

function poAddGroup() {
    $('#po-items-body').append(poGroupRowHtml(poNewKey(), ''));
    poRenumber();
    poSyncEmpty();
    poRecalc();
}

function poEnsureGroup(label) {
    if (!label) return '';
    label = String(label).trim();
    var found = null;
    $('#po-items-body tr[data-type="group"]').each(function() {
        if (String($(this).find('.po-category').val()).trim() === label) found = $(this).attr('data-key');
    });
    if (found) return found;
    var key = poNewKey();
    $('#po-items-body').append(poGroupRowHtml(key, label));
    poRenumber();
    poSyncEmpty();
    poRecalc();
    return key;
}

function poAddChild(btn) {
    var parentKey = $(btn).closest('tr').attr('data-key');
    poAddRow({}, parentKey);
}

function poRowHtml(item, parentKey) {
    var key = poNewKey();
    var parentKeyAttr = parentKey || '';
    var depth = 0;
    if (parentKeyAttr) {
        var parentRow = $('tr[data-key="' + parentKeyAttr + '"]');
        depth = (parseInt(parentRow.attr('data-depth')) || 0) + 1;
    }
    var sourceBadge = item.goods_request_item_id
        ? '<span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:10px;white-space:normal" title="Dari Permintaan Barang">' + poEsc(item.source_label || ('GR#' + item.goods_request_id)) + '</span>'
        : '<span class="text-muted" style="font-size:11px">Manual</span>';

    var html = '<tr data-key="' + key + '" data-parent="' + parentKeyAttr + '" data-depth="' + depth + '"';
    html += ' data-goods-request-item-id="' + (item.goods_request_item_id || '') + '" data-goods-request-id="' + (item.goods_request_id || '') + '"';
    html += ' data-master-product-id="' + (item.master_product_id || '') + '" data-purchase-price="' + (item.purchase_price_currency != null ? item.purchase_price_currency : '') + '">';
    html += '<td class="text-center po-row-num"></td>';
    html += '<td>' + sourceBadge + '</td>';
    html += '<td><div class="d-flex gap-1">';
    html += '<input type="text" class="form-control form-control-sm po-pn" value="' + poEsc(item.part_number || '') + '">';
    html += '<button type="button" class="btn-icon" title="Pilih produk master" onclick="poOpenProductPicker(this)"><i class="fa fa-search"></i></button>';
    html += '</div></td>';
    html += '<td><div class="form-control form-control-sm po-desc" contenteditable="true" style="margin-left:' + (depth * 18) + 'px" data-placeholder="Nama barang / deskripsi">' + (item.description || '') + '</div></td>';
    html += '<td><input type="text" inputmode="decimal" class="form-control form-control-sm po-qty" value="' + (item.qty != null ? item.qty : '') + '"></td>';
    html += '<td><input type="text" class="form-control form-control-sm po-unit" value="' + poEsc(item.unit || '') + '"></td>';
    html += '<td>' + poCurrencySelect(item.currency || poActiveCurrency() || poBaseCurrency(), !!item.goods_request_item_id) + '</td>';
    html += '<td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end po-price-currency" value="' + (item.selling_price_currency != null ? poFmt(item.selling_price_currency) : '') + '"></td>';
    html += '<td class="text-end po-amount">0</td>';
    html += '<td class="text-center">';
    html += '<button type="button" class="btn-icon" title="Tambah Baris Anak" onclick="poAddChild(this)"><i class="fa fa-plus"></i></button>';
    html += '<button type="button" class="btn-icon text-danger" title="Hapus" onclick="poRemoveRow(this)"><i class="fa fa-trash"></i></button>';
    html += '</td></tr>';
    return html;
}

function poAddRow(item, parentKey) {
    item = item || {};

    // Baris group (kategori) dari data edit/awal.
    if (item.category) {
        var gkey = item._key || poNewKey();
        $('#po-items-body').append(poGroupRowHtml(gkey, item.category));
        poRenumber();
        poSyncEmpty();
        poRecalc();
        return gkey;
    }

    var html = poRowHtml(item, parentKey);

    // Sisipkan langsung setelah keturunan terakhir dari parent (menjaga urutan DFS).
    if (parentKey) {
        var last = $('tr[data-key="' + parentKey + '"]');
        var stack = [parentKey];
        while (stack.length) {
            var cur = stack.pop();
            $('tr[data-key="' + cur + '"]').nextAll('tr').each(function() {
                var p = $(this).attr('data-parent');
                if (p === cur) {
                    last = this;
                    stack.push($(this).attr('data-key'));
                }
            });
        }
        $(html).insertAfter(last);
    } else {
        $('#po-items-body').append(html);
    }
    poRenumber();
    poSyncEmpty();
    poRecalc();
}

function poAddBlankRow() {
    poAddRow({});
}

function poRemoveRow(btn) {
    var row = $(btn).closest('tr');
    var key = row.attr('data-key');
    // Hapus juga semua turunannya.
    var toRemove = [];
    var walk = function(k) {
        $('tr[data-parent="' + k + '"]').each(function() {
            toRemove.push(this);
            walk($(this).attr('data-key'));
        });
    };
    walk(key);
    toRemove.forEach(function(el) { $(el).remove(); });
    row.remove();
    poRenumber();
    poSyncEmpty();
    poRecalc();
}

function poRenumber() {
    var n = 0;
    $('#po-items-body tr').each(function() {
        if ($(this).attr('data-type') === 'group') return;
        $(this).find('.po-row-num').text(++n);
    });
}

function poRecalc() {
    var grand = 0;
    $('#po-items-body tr').each(function() {
        var $row = $(this);
        if ($row.attr('data-type') === 'group') {
            $row.find('.po-amount').text('');
            return;
        }
        var qty = parseFloat(poToRaw($row.find('.po-qty').val())) || 0;
        var currency = $row.find('.po-currency').val();
        var priceCurrency = parseFloat(poToRaw($row.find('.po-price-currency').val())) || 0;
        // Tanpa konversi kurs: harga & total dalam mata uang PO.
        var price = priceCurrency;
        var amount = qty * price;
        grand += amount;
        $row.find('.po-amount').text(poFmt(amount));
    });
    $('#po-grand-total').text(poFmt(grand));
    poUpdateSummary(grand);
}

function poUpdateSummary(grand) {
    var $items = $('#po-items-body tr').not('[data-type="group"]');
    var projects = {};
    $('#po-items-body tr[data-type="group"]').each(function() {
        var label = $.trim($(this).find('.po-category').val());
        var hasChild = $('#po-items-body tr[data-parent="' + $(this).attr('data-key') + '"]').length > 0;
        if (label && hasChild) projects[label] = true;
    });

    $('#po-items-count').text($items.length + ' item');
    $('#po-sum-items').text($items.length);
    $('#po-sum-projects').text(Object.keys(projects).length);
    var currencies = poItemCurrencies();
    $('#po-sum-currency').text(currencies.length ? currencies.join(', ') : '—').toggleClass('text-danger', currencies.length > 1);

    // Sama dengan PurchaseOrder::totalsFrom(): Diskon -> Sub Total 1 -> PPN -> Total.
    var discPct = parseFloat($('#po-discount-percent').val()) || 0;
    var ppnPct = parseFloat($('#po-ppn-percent').val()) || 0;
    var discount = Math.round(grand * discPct) / 100;
    var ppn = Math.round((grand - discount) * ppnPct) / 100;
    var code = currencies.length === 1 ? currencies[0] : (currencies.length ? '' : poBaseCurrency());
    $('.po-currency-code').text(code ? '(' + code + ')' : '');
    $('#po-sum-subtotal').text(code + ' ' + poFmt(grand));
    $('#po-sum-discount-row').toggle(discPct > 0);
    $('#po-sum-discount-label').text('Diskon ' + poFmt(discPct) + '%');
    $('#po-sum-discount').text('- ' + code + ' ' + poFmt(discount));
    $('#po-sum-ppn-row').toggle(ppnPct > 0);
    $('#po-sum-ppn-label').text('PPN ' + poFmt(ppnPct) + '%');
    $('#po-sum-ppn').text(code + ' ' + poFmt(ppn));
    $('#po-sum-total').text(code + ' ' + poFmt(grand - discount + ppn));
}

function poUpdateSupplierSummary() {
    var name = $('#po-supplier-name').val();
    $('#po-sum-supplier').text(name || '—').attr('title', name || '');
}

function poCollectItems() {
    var items = [];
    $('#po-items-body tr').each(function() {
        var $row = $(this);
        if ($row.attr('data-type') === 'group') {
            items.push({
                _key: $row.attr('data-key'),
                parent_key: null,
                category: $row.find('.po-category').val() || null,
            });
            return;
        }
        var currency = $row.find('.po-currency').val();
        var priceCurrency = parseFloat(poToRaw($row.find('.po-price-currency').val())) || 0;
        var price = Math.round(priceCurrency * 100) / 100;
        items.push({
            _key: $row.attr('data-key'),
            parent_key: $row.attr('data-parent') || null,
            category: null,
            goods_request_item_id: $row.attr('data-goods-request-item-id') || null,
            goods_request_id: $row.attr('data-goods-request-id') || null,
            master_product_id: $row.attr('data-master-product-id') || null,
            part_number: $row.find('.po-pn').val(),
            description: $row.find('.po-desc').html(),
            qty: $row.find('.po-qty').val(),
            unit: $row.find('.po-unit').val(),
            currency: currency,
            selling_price_currency: priceCurrency,
            selling_price: price,
            purchase_price_currency: $row.attr('data-purchase-price') || null
        });
    });
    return items;
}

// ── Picker produk master: pilih part number -> isi currency + harga otomatis. ──

let poProductPickerTarget = null;
let poProductPickerInstance = null;
let poProductPool = [];

function poOpenProductPicker(btn) {
    poProductPickerTarget = $(btn).closest('tr');
    $('#po-product-q').val('');
    $('#po-product-results').html('<div class="config-card-empty"><i class="fa-solid fa-inbox"></i>Ketik kata kunci lalu tekan Cari.</div>');
    if (!poProductPickerInstance) {
        poProductPickerInstance = new bootstrap.Modal(document.getElementById('poProductPickerModal'));
    }
    poProductPickerInstance.show();
}

function poSearchProducts() {
    var q = $('#po-product-q').val().trim();
    $('#po-product-results').html('<div class="config-card-empty"><span class="config-spinner"></span>Memuat...</div>');
    poProductPool = [];
    $.get(poSearchProductsUrl, { q: q })
        .done(function(res) {
            var list = res.data || [];
            if (list.length === 0) {
                $('#po-product-results').html('<div class="config-card-empty"><i class="fa-solid fa-inbox"></i>Tidak ditemukan.</div>');
                return;
            }
            var html = '<div class="list-group">';
            list.forEach(function(p) {
                var poolIdx = poProductPool.length;
                poProductPool.push(p);
                html += '<a href="javascript:void(0)" class="list-group-item list-group-item-action po-product-item" data-idx="' + poolIdx + '">';
                html += '<div class="d-flex justify-content-between align-items-center">';
                html += '<strong>' + poEsc(p.code || '') + ' &middot; ' + poEsc(p.name || '') + '</strong>';
                html += '<span class="badge" style="background:var(--accent-soft);color:var(--accent)">' + poEsc(p.currency) + ' ' + poFmt(p.price) + '</span>';
                html += '</div>';
                if (p.brand || p.category) html += '<div class="text-muted" style="font-size:12px">' + poEsc([p.brand, p.category].filter(Boolean).join(' · ')) + '</div>';
                html += '</a>';
            });
            html += '</div>';
            $('#po-product-results').html(html);
        })
        .fail(function() {
            $('#po-product-results').html('<div class="config-card-empty"><i class="fa-solid fa-triangle-exclamation"></i>Gagal memuat produk.</div>');
        });
}

$(document).on('click', '.po-product-item', function() {
    var p = poProductPool[$(this).data('idx')];
    if (!p || !poProductPickerTarget) return;
    var $row = poProductPickerTarget;
    var productCurrency = p.currency || poBaseCurrency();
    var active = poActiveCurrency($row);
    if (active && active !== productCurrency) {
        toastr.error('Produk ini memakai ' + productCurrency + ', sedangkan item lain di PO ini ' + active + '. Satu PO hanya boleh satu mata uang — buat PO terpisah.');
        return;
    }
    $row.attr('data-master-product-id', p.id);
    $row.find('.po-pn').val(p.code || '');
    var $desc = $row.find('.po-desc');
    if (!$.trim($desc.text()) && (p.description || p.name)) {
        $desc.html(poEsc(p.description || p.name));
    }
    var $currency = $row.find('.po-currency');
    $currency.val(productCurrency).attr('data-prev', productCurrency);
    $row.find('.po-price-currency').val(p.price != null ? poFmt(p.price) : '');
    $row.attr('data-purchase-price', p.purchase_price_currency != null ? p.purchase_price_currency : '');
    poRecalc();
    if (poProductPickerInstance) poProductPickerInstance.hide();
    toastr.success('Produk diterapkan.');
});

// ── Ambil dari Permintaan Barang: dikelompokkan per Permintaan Barang, ──
// supaya jelas asal masing-masing item walau lintas divisi/opportunity.
function poBuildPickerGroupsHtml(groups) {
    var html = '';
    // Setelah PO berisi item, hanya item bermata uang sama yang bisa dipilih.
    var poActive = poActiveCurrency();
    groups.forEach(function(gr, gIdx) {
        var group = 'g' + gIdx;
        var projectTitle = gr.project_number ? (gr.project_number + ' — ' + gr.project_name) : (gr.project_name || 'Tanpa Project');
        var title = projectTitle + ' · ' + (gr.division_name || 'Tanpa Divisi') + ' · Permintaan #' + gr.goods_request_id;
        var sourceLabel = (gr.project_number || ('GR#' + gr.goods_request_id)) + ' / ' + (gr.division_name || '-');

        html += '<div class="po-picker-group mb-3" style="border:1px solid var(--card-border);border-radius:var(--radius);overflow:hidden">';
        html += '<div class="d-flex justify-content-between align-items-center px-3 py-2" style="background:var(--bg);border-bottom:1px solid var(--card-border)">';
        html += '<label class="d-flex align-items-center gap-2 mb-0" style="cursor:pointer">';
        html += '<input type="checkbox" class="po-picker-check-group" data-group="' + group + '">';
        html += '<strong style="font-size:13px">' + poEsc(title) + '</strong>';
        html += '</label>';
        html += '<span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:11px">' + gr.items.length + ' item</span>';
        html += '</div>';
        html += '<div class="table-responsive">';
        html += '<table class="table table-custom align-middle mb-0">';
        html += '<thead><tr>';
        html += '<th style="width:36px"></th>';
        html += '<th style="width:18%">Part Number</th>';
        html += '<th>Nama Barang / Deskripsi</th>';
        html += '<th class="text-center" style="width:70px">Qty</th>';
        html += '<th style="width:70px">Unit</th>';
        html += '<th style="width:90px">Mata Uang</th>';
        html += '</tr></thead><tbody>';

        gr.items.forEach(function(it) {
            var poolIdx = poPickerPool.length;
            poPickerPool.push({
                goods_request_item_id: it.goods_request_item_id,
                goods_request_id: gr.goods_request_id,
                master_product_id: it.master_product_id,
                project_name: it.project_name,
                part_number: it.part_number,
                description: it.description,
                qty: it.qty,
                unit: it.unit,
                currency: it.currency,
                source_label: sourceLabel
            });
            var otherCurrency = poActive && it.currency && it.currency !== poActive;

            html += '<tr class="po-picker-item-row" data-group="' + group + '" data-search="' + poEsc(((it.part_number || '') + ' ' + $('<div>').html(it.description || '').text()).toLowerCase()) + '">';
            html += '<td>' + (otherCurrency
                ? '<i class="fa fa-ban" style="color:var(--text-muted)" title="Mata uang berbeda dengan PO ini"></i>'
                : '<input type="checkbox" class="po-picker-check" data-idx="' + poolIdx + '" data-currency="' + poEsc(it.currency || '') + '">') + '</td>';
            html += '<td>' + poEsc(it.part_number || '—') + '</td>';
            {{-- Deskripsi sudah HTML tersanitasi dari server (Quotation::renderDescription) --}}
            html += '<td>' + (it.description || '—') + '</td>';
            html += '<td class="text-center">' + poEsc(it.qty || '') + '</td>';
            html += '<td>' + poEsc(it.unit || '') + '</td>';
            html += '<td>' + poEsc(it.currency || '') + (otherCurrency ? '<div style="font-size:11px;color:var(--text-muted)">PO terpisah</div>' : '') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div></div>';
    });

    return html;
}

$(document).on('change', '.po-picker-check-group', function() {
    var group = $(this).data('group');
    var checked = $(this).is(':checked');
    $('.po-picker-item-row[data-group="' + group + '"]:visible').find('.po-picker-check').prop('checked', checked);
});

function poCurrentGoodsRequestItemIds() {
    var ids = [];
    $('#po-items-body tr').each(function() {
        var id = $(this).attr('data-goods-request-item-id');
        if (id) ids.push(id);
    });
    return ids;
}

function poLoadPicker() {
    if (!poPickerInstance) {
        poPickerInstance = new bootstrap.Modal(document.getElementById('poPickerModal'));
        $('#po-picker-project').select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $('#poPickerModal'),
            placeholder: 'Semua Project',
            allowClear: true
        }).on('change', poFetchPickerItems);
    }
    // Daftar project dimuat ulang tiap modal dibuka (item tersedia bisa berubah).
    poPickerProjectsLoaded = false;
    $('#po-picker-project').val('').trigger('change.select2');
    poPickerInstance.show();

    poFetchPickerItems();
}

function poFetchPickerItems() {
    $('#po-picker-body').html('<div class="config-card-empty"><span class="config-spinner"></span>Memuat...</div>');
    $('#po-picker-check-all').prop('checked', false);
    $('#po-picker-search').val('');
    poPickerPool = [];
    $.get(poFetchAvailableItemsUrl, {
        exclude_goods_request_item_ids: poCurrentGoodsRequestItemIds(),
        project_id: $('#po-picker-project').val() || ''
    })
        .done(function(res) {
            if (!poPickerProjectsLoaded) {
                poPickerProjectsLoaded = true;
                var opts = '<option value="">Semua Project</option>';
                (res.projects || []).forEach(function(p) {
                    opts += '<option value="' + p.id + '">' + poEsc(p.text) + '</option>';
                });
                $('#po-picker-project').html(opts).trigger('change.select2');
            }
            var groups = res.data || [];
            if (groups.length === 0) {
                $('#po-picker-body').html('<div class="config-card-empty"><i class="fa-solid fa-inbox"></i>Tidak ada item Permintaan Barang Approved yang tersedia.</div>');
                return;
            }
            $('#po-picker-body').html(poBuildPickerGroupsHtml(groups));
        })
        .fail(function() {
            $('#po-picker-body').html('<div class="config-card-empty"><i class="fa-solid fa-triangle-exclamation"></i>Gagal memuat item.</div>');
        });
}

function poApplyPicked() {
    var chosen = {};
    $('.po-picker-check:checked').each(function() {
        var c = $(this).data('currency');
        if (c) chosen[c] = true;
    });
    var chosenCurrencies = Object.keys(chosen);
    var active = poActiveCurrency();
    if (chosenCurrencies.length > 1 || (active && chosenCurrencies.length && chosenCurrencies[0] !== active)) {
        toastr.error('Satu PO hanya boleh satu mata uang. Item terpilih: ' + chosenCurrencies.join(', ')
            + (active ? ' (PO ini ' + active + ')' : '') + '. Pilih item dengan mata uang yang sama.');
        return;
    }

    var picked = 0;
    $('.po-picker-check:checked').each(function() {
        var idx = $(this).data('idx');
        var it = poPickerPool[idx];
        if (!it) return;
        // Kelompokkan otomatis per project (nama opportunity) lewat group kategori.
        var groupKey = poEnsureGroup(it.project_name);
        poAddRow(it, groupKey);
        picked++;
    });
    if (picked === 0) {
        toastr.error('Pilih minimal satu item.');
        return;
    }
    toastr.success(picked + ' item ditambahkan.');
    if (poPickerInstance) poPickerInstance.hide();
}

function poSelectedType() {
    return $('input[name="po-type"]:checked').val() || 'manufacture';
}

function poUpdateNumberPreview() {
    var suffix = poTypeSuffixes[poSelectedType()] || '';
    if (poCurrentNumber) {
        var number = String(poCurrentNumber).replace(/-[A-Z]+$/, '') + suffix;
        $('#po-number-preview').html('No. PO: <strong>' + poEsc(number) + '</strong>'
            + (number !== poCurrentNumber ? ' <span>(sebelumnya ' + poEsc(poCurrentNumber) + ')</span>' : ''));
    } else {
        $('#po-number-preview').html('No. PO dibuat otomatis saat disimpan' + (suffix ? ', berakhiran <strong>' + suffix + '</strong>' : '') + '.');
    }

    $('#po-sum-type').text(poTypeLabels[poSelectedType()] || '—');
    if (poCurrentNumber) {
        $('#po-sum-number').removeClass('pending').text(String(poCurrentNumber).replace(/-[A-Z]+$/, '') + suffix);
    } else {
        $('#po-sum-number').addClass('pending').text('Otomatis saat disimpan');
    }
}

function poFail(message, $el) {
    toastr.error(message);
    if ($el && $el.length) {
        $el.addClass('is-invalid');
        $el[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function() { $el.trigger('focus'); }, 300);
    }
    return false;
}

function poValidate() {
    $('#po-items-body .is-invalid').removeClass('is-invalid');

    if (!$('#po-supplier-name').val()) {
        toastr.error('Supplier wajib dipilih.');
        $('#po-supplier-select').select2('open');
        return false;
    }

    var discPct = parseFloat($('#po-discount-percent').val()) || 0;
    var ppnPct = parseFloat($('#po-ppn-percent').val()) || 0;
    if (discPct < 0 || discPct > 100) return poFail('Diskon harus 0 sampai 100%.', $('#po-discount-percent'));
    if (ppnPct < 0 || ppnPct > 100) return poFail('PPN harus 0 sampai 100%.', $('#po-ppn-percent'));

    var currencies = poItemCurrencies();
    if (currencies.length > 1) {
        return poFail('Semua item dalam satu PO harus memakai mata uang yang sama (ditemukan ' + currencies.join(', ') + ').');
    }

    var ok = true;
    var n = 0;
    $('#po-items-body tr').not('[data-type="group"]').each(function() {
        var $row = $(this);
        n++;
        if (!$.trim($row.find('.po-desc').text())) {
            ok = poFail('Nama barang / deskripsi baris ' + n + ' wajib diisi.', $row.find('.po-desc'));
            return false;
        }
        if (!(parseFloat(poToRaw($row.find('.po-qty').val())) > 0)) {
            ok = poFail('Qty baris ' + n + ' harus lebih dari 0.', $row.find('.po-qty'));
            return false;
        }
    });

    return ok;
}

function poSetSaving(saving) {
    $('#po-save-btn').prop('disabled', saving).html(saving
        ? '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...'
        : poSaveLabel);
}

function poSave() {
    if (!poValidate()) return;
    var supplierName = $('#po-supplier-name').val();

    var payload = {
        supplier_name: supplierName,
        supplier_id: $('#po-supplier-id').val() || null,
        supplier_address: $('#po-supplier-address').val(),
        supplier_phone: $('#po-supplier-phone').val(),
        supplier_fax: $('#po-supplier-fax').val(),
        supplier_attn: $('#po-supplier-attn').val(),
        po_type: poSelectedType(),
        date: $('#po-date').val(),
        terms: $('#po-terms').val(),
        delivery: $('#po-delivery').val(),
        your_ref: $('#po-your-ref').val(),
        discount_percent: $('#po-discount-percent').val(),
        ppn_percent: $('#po-ppn-percent').val(),
        notes: $('#po-notes').val(),
        request_by_name: $('#po-request-by').val(),
        finance_name: $('#po-finance-name').val(),
        accounting_name: $('#po-accounting-name').val(),
        items: poCollectItems(),
        _token: '{{ csrf_token() }}'
    };

    var url = poUpdateUrl || poStoreUrl;
    var method = poUpdateUrl ? 'PUT' : 'POST';

    poSetSaving(true);
    $.ajax({ url: url, method: method, data: payload })
        .done(function(res) {
            toastr.success(res.message || 'Tersimpan.');
            setTimeout(function() {
                window.location.href = poUpdateUrl ? poShowUrl : '{{ route("purchase-order.index") }}';
            }, 800);
        })
        .fail(function(xhr) {
            poSetSaving(false);
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan.');
        });
}

$(document).ready(function() {
    (poInitialItems || []).forEach(function(it) { poAddRow(it, it.parent_key); });
    poSyncEmpty();
    poRecalc();

    $('#po-picker-check-all').on('change', function() {
        var checked = $(this).is(':checked');
        $('.po-picker-item-row:visible .po-picker-check, .po-picker-group:visible .po-picker-check-group').prop('checked', checked);
    });

    $(document).on('input change', '.po-qty, .po-price-currency', poRecalc);

    // Ganti mata uang satu baris -> tawarkan menyamakan semua item (satu PO satu mata uang).
    $(document).on('focus', '.po-currency', function() {
        $(this).attr('data-prev', $(this).val());
    });
    $(document).on('change', '.po-currency', function() {
        var $select = $(this);
        var next = $select.val();
        var prev = $select.attr('data-prev') || next;
        var lockedOther = $('#po-items-body .po-currency:disabled').filter(function() { return $(this).val() !== next; });
        if (lockedOther.length) {
            toastr.error('PO ini berisi item Permintaan Barang ber-mata uang ' + lockedOther.first().val() + ' yang tidak bisa diubah. Satu PO hanya boleh satu mata uang.');
            $select.val(prev);
            return;
        }

        var others = $('#po-items-body tr').not('[data-type="group"]').not($select.closest('tr'))
            .find('.po-currency').filter(function() { return $(this).val() !== next; });

        if (!others.length) {
            $select.attr('data-prev', next);
            poRecalc();
            return;
        }

        Swal.fire({
            title: 'Ganti mata uang semua item ke ' + next + '?',
            text: 'Satu PO hanya boleh satu mata uang. Harga satuan tidak dikonversi otomatis — periksa kembali harganya.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, ganti semua',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) {
                $('#po-items-body .po-currency').not(':disabled').val(next).attr('data-prev', next);
            } else {
                $select.val(prev);
            }
            poRecalc();
        });
    });
    $(document).on('input', '.po-category, .po-percent-input', function() { poRecalc(); });
    $(document).on('input', '.po-desc, .po-qty', function() { $(this).removeClass('is-invalid'); });
    poUpdateSupplierSummary();
    $(document).on('change', 'input[name="po-type"]', poUpdateNumberPreview);
    poUpdateNumberPreview();

    $('#po-picker-search').on('input', function() {
        var q = $.trim($(this).val()).toLowerCase();
        $('.po-picker-item-row').each(function() {
            $(this).toggle(!q || String($(this).data('search')).indexOf(q) !== -1);
        });
        $('.po-picker-group').each(function() {
            $(this).toggle($(this).find('.po-picker-item-row:visible').length > 0 || !q);
        });
    });

    $('#po-supplier-select').select2({
        theme: 'bootstrap-5',
        placeholder: 'Cari supplier...',
        allowClear: true,
        width: '100%',
        minimumInputLength: 0,
        ajax: {
            url: '{{ route("supplier.search") }}',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term || '' }; },
            processResults: function(res) { return { results: res.results }; }
        }
    }).on('select2:select', function(e) {
        var s = e.params.data;
        $('#po-supplier-id').val(s.id);
        $('#po-supplier-name').val(s.text);
        $('#po-supplier-address').val(s.address || '');
        $('#po-supplier-phone').val(s.phone || '');
        $('#po-supplier-fax').val(s.fax || '');
        $('#po-supplier-attn').val(s.attn_name || '');
        poUpdateSupplierSummary();
    }).on('select2:clear', function() {
        $('#po-supplier-id').val('');
        $('#po-supplier-name').val('');
        poUpdateSupplierSummary();
    });
});
</script>
@endsection
