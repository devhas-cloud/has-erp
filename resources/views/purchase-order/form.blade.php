@extends('layouts.app')

@section('title', $purchaseOrder ? 'Edit Purchase Order' : 'Buat Purchase Order')
@section('page-title', $purchaseOrder ? 'Edit Purchase Order' : 'Buat Purchase Order')

@section('styles')
<style>
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
    .po-desc[contenteditable="true"]:focus {
        outline: none;
        border-color: var(--accent);
    }
    .po-desc[contenteditable="true"]:empty::before {
        content: attr(data-placeholder);
        color: #999;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $purchaseOrder ? 'Edit Purchase Order' : 'Buat Purchase Order' }}</h1>
        <p class="page-header-sub">Disusun dari item Permintaan Barang yang sudah Approved — bisa menggabungkan item dari beberapa Permintaan Barang lintas divisi selama tujuannya satu supplier yang sama.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('purchase-order.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Informasi Purchase Order</span>
    </div>
    <div class="card-body-custom">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Supplier <span class="text-danger">*</span></label>
                <select id="po-supplier-select" class="form-select" style="width:100%">
                    @if($purchaseOrder && $purchaseOrder->supplier_id)
                        <option value="{{ $purchaseOrder->supplier_id }}" selected>{{ $purchaseOrder->supplier_name }}</option>
                    @endif
                </select>
                <input type="hidden" id="po-supplier-id" value="{{ $purchaseOrder?->supplier_id }}">
                <input type="hidden" id="po-supplier-name" value="{{ $purchaseOrder?->supplier_name }}">
                @if($purchaseOrder && ! $purchaseOrder->supplier_id)
                    <small style="color:var(--text-muted)">PO ini dibuat sebelum modul Supplier ada ("{{ $purchaseOrder->supplier_name }}") — pilih ulang dari master untuk menautkan.</small>
                @else
                    <small style="color:var(--text-muted)">Belum ada di daftar? Tambahkan dulu lewat menu Supplier.</small>
                @endif
            </div>
            <div class="col-md-2">
                <label class="form-label">No. PO</label>
                <input type="text" class="form-control" id="po-number" placeholder="Nomor PO (opsional)" value="{{ $purchaseOrder?->po_number }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tanggal</label>
                <input type="date" class="form-control" id="po-date" value="{{ $purchaseOrder?->date?->format('Y-m-d') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Terms</label>
                <input type="text" class="form-control" id="po-terms" placeholder="Contoh: TT" value="{{ $purchaseOrder?->terms }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Catatan</label>
                <textarea id="po-notes" class="form-control" rows="1" placeholder="Catatan (opsional)">{{ $purchaseOrder?->notes }}</textarea>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label class="form-label">Alamat Supplier</label>
                <textarea id="po-supplier-address" class="form-control" rows="2" placeholder="Terisi otomatis saat memilih supplier, bisa disesuaikan">{{ $purchaseOrder?->supplier_address }}</textarea>
            </div>
            <div class="col-md-2">
                <label class="form-label">Telepon</label>
                <input type="text" class="form-control" id="po-supplier-phone" value="{{ $purchaseOrder?->supplier_phone }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Fax</label>
                <input type="text" class="form-control" id="po-supplier-fax" value="{{ $purchaseOrder?->supplier_fax }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Attn</label>
                <input type="text" class="form-control" id="po-supplier-attn" value="{{ $purchaseOrder?->supplier_attn }}">
            </div>
        </div>
        <div class="row g-3 mt-1">
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

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Daftar Item</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary btn-sm" onclick="poLoadPicker()">
                <i class="fa fa-dolly me-1"></i> Ambil dari Permintaan Barang
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="poAddGroup()">
                <i class="fa fa-folder-open me-1"></i> Tambah Group (Kategori)
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="poAddBlankRow()">
                <i class="fa fa-plus me-1"></i> Tambah Baris Manual
            </button>
        </div>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0" id="po-items-table">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="width:12%">Asal</th>
                        <th style="width:14%">Part Number</th>
                        <th>Nama Barang / Deskripsi</th>
                        <th style="width:7%">Qty</th>
                        <th style="width:7%">Unit</th>
                        <th style="width:9%">Mata Uang</th>
                        <th style="width:11%" class="text-end">Harga Satuan</th>
                        <th style="width:12%" class="text-end">Subtotal (Rp)</th>
                        <th class="text-center" style="width:6%">Aksi</th>
                    </tr>
                </thead>
                <tbody id="po-items-body"></tbody>
                <tfoot>
                    <tr>
                        <td colspan="8" class="text-end fw-bold">Grand Total (Rp)</td>
                        <td class="text-end fw-bold" id="po-grand-total">0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="config-card-empty" id="po-items-empty">
            <i class="fa-solid fa-inbox"></i> Belum ada item. Tambahkan lewat "Ambil dari Permintaan Barang" atau "Tambah Baris Manual".
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mb-4">
    <button type="button" class="btn btn-primary" onclick="poSave()">
        <i class="fa fa-save me-1"></i> Simpan {{ $purchaseOrder ? 'Perubahan' : 'Draft' }}
    </button>
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
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <label class="d-flex align-items-center gap-2 mb-0" style="font-size:12px;color:var(--text-muted);cursor:pointer">
                        <input type="checkbox" id="po-picker-check-all"> Pilih Semua
                    </label>
                    <small style="color:var(--text-muted)">Item dikelompokkan per Permintaan Barang (bisa lintas divisi) — hanya menampilkan item Approved yang belum dipakai di PO manapun.</small>
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

function poRateOf(code) {
    for (var i = 0; i < poCurrencies.length; i++) {
        if (poCurrencies[i].name === code) return poCurrencies[i].is_base ? 1 : (Number(poCurrencies[i].rate) || 0);
    }
    return 1;
}

function poCurrencySelect(selected) {
    var base = poCurrencies.length ? poCurrencies[0].name : 'IDR';
    var html = '<select class="form-select form-select-sm po-currency">';
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
    html += ' data-master-product-id="' + (item.master_product_id || '') + '">';
    html += '<td class="text-center po-row-num"></td>';
    html += '<td>' + sourceBadge + '</td>';
    html += '<td><div class="d-flex gap-1">';
    html += '<input type="text" class="form-control form-control-sm po-pn" value="' + poEsc(item.part_number || '') + '">';
    html += '<button type="button" class="btn-icon" title="Pilih produk master" onclick="poOpenProductPicker(this)"><i class="fa fa-search"></i></button>';
    html += '</div></td>';
    html += '<td><div class="form-control form-control-sm po-desc" contenteditable="true" style="margin-left:' + (depth * 18) + 'px" data-placeholder="Nama barang / deskripsi">' + (item.description || '') + '</div></td>';
    html += '<td><input type="text" inputmode="decimal" class="form-control form-control-sm po-qty" value="' + (item.qty != null ? item.qty : '') + '"></td>';
    html += '<td><input type="text" class="form-control form-control-sm po-unit" value="' + poEsc(item.unit || '') + '"></td>';
    html += '<td>' + poCurrencySelect(item.currency) + '</td>';
    html += '<td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end po-price-currency" value="' + (item.price_currency != null ? poFmt(item.price_currency) : '') + '"></td>';
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
        var price = poRateOf(currency) * priceCurrency;
        var amount = qty * price;
        grand += amount;
        $row.find('.po-amount').text(poFmt(amount));
    });
    $('#po-grand-total').text(poFmt(grand));
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
        var price = Math.round(poRateOf(currency) * priceCurrency * 100) / 100;
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
            price_currency: priceCurrency,
            price: price
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
    $row.attr('data-master-product-id', p.id);
    $row.find('.po-pn').val(p.code || '');
    var $desc = $row.find('.po-desc');
    if (!$.trim($desc.text()) && (p.description || p.name)) {
        $desc.html(poEsc(p.description || p.name));
    }
    var $currency = $row.find('.po-currency');
    $currency.val(p.currency || (poCurrencies.length ? poCurrencies[0].name : 'IDR'));
    $row.find('.po-price-currency').val(p.price != null ? poFmt(p.price) : '');
    poRecalc();
    if (poProductPickerInstance) poProductPickerInstance.hide();
    toastr.success('Produk diterapkan.');
});

// ── Ambil dari Permintaan Barang: dikelompokkan per Permintaan Barang, ──
// supaya jelas asal masing-masing item walau lintas divisi/opportunity.
function poBuildPickerGroupsHtml(groups) {
    var html = '';
    groups.forEach(function(gr, gIdx) {
        var group = 'g' + gIdx;
        var title = (gr.quotation_number || ('Permintaan #' + gr.goods_request_id)) + ' — ' + (gr.division_name || 'Tanpa Divisi');
        var sourceLabel = (gr.quotation_number || ('GR#' + gr.goods_request_id)) + ' / ' + (gr.division_name || '-');

        html += '<div class="po-picker-group mb-3" style="border:1px solid var(--card-border);border-radius:var(--radius);overflow:hidden">';
        html += '<div class="d-flex justify-content-between align-items-center px-3 py-2" style="background:var(--bg);border-bottom:1px solid var(--card-border)">';
        html += '<label class="d-flex align-items-center gap-2 mb-0" style="cursor:pointer">';
        html += '<input type="checkbox" class="po-picker-check-group" data-group="' + group + '">';
        html += '<strong style="font-size:13px">' + poEsc(title) + '</strong>';
        if (gr.opportunity_name) {
            html += '<span style="font-size:11px;color:var(--text-muted)">(' + poEsc(gr.opportunity_name) + ')</span>';
        }
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
                source_label: sourceLabel
            });

            html += '<tr class="po-picker-item-row" data-group="' + group + '">';
            html += '<td><input type="checkbox" class="po-picker-check" data-idx="' + poolIdx + '"></td>';
            html += '<td>' + poEsc(it.part_number || '—') + '</td>';
            {{-- Deskripsi sudah HTML tersanitasi dari server (Quotation::renderDescription) --}}
            html += '<td>' + (it.description || '—') + '</td>';
            html += '<td class="text-center">' + poEsc(it.qty || '') + '</td>';
            html += '<td>' + poEsc(it.unit || '') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div></div>';
    });

    return html;
}

$(document).on('change', '.po-picker-check-group', function() {
    var group = $(this).data('group');
    var checked = $(this).is(':checked');
    $('.po-picker-item-row[data-group="' + group + '"]').find('.po-picker-check').prop('checked', checked);
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
    $('#po-picker-body').html('<div class="config-card-empty"><span class="config-spinner"></span>Memuat...</div>');
    $('#po-picker-check-all').prop('checked', false);
    if (!poPickerInstance) {
        poPickerInstance = new bootstrap.Modal(document.getElementById('poPickerModal'));
    }
    poPickerInstance.show();

    poPickerPool = [];
    $.get(poFetchAvailableItemsUrl, { exclude_goods_request_item_ids: poCurrentGoodsRequestItemIds() })
        .done(function(res) {
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

function poSave() {
    var supplierName = $('#po-supplier-name').val();
    if (!supplierName) {
        toastr.error('Nama supplier wajib diisi.');
        return;
    }

    var payload = {
        supplier_name: supplierName,
        supplier_id: $('#po-supplier-id').val() || null,
        supplier_address: $('#po-supplier-address').val(),
        supplier_phone: $('#po-supplier-phone').val(),
        supplier_fax: $('#po-supplier-fax').val(),
        supplier_attn: $('#po-supplier-attn').val(),
        po_number: $('#po-number').val(),
        date: $('#po-date').val(),
        terms: $('#po-terms').val(),
        notes: $('#po-notes').val(),
        request_by_name: $('#po-request-by').val(),
        finance_name: $('#po-finance-name').val(),
        accounting_name: $('#po-accounting-name').val(),
        items: poCollectItems(),
        _token: '{{ csrf_token() }}'
    };

    var url = poUpdateUrl || poStoreUrl;
    var method = poUpdateUrl ? 'PUT' : 'POST';

    $.ajax({ url: url, method: method, data: payload })
        .done(function(res) {
            toastr.success(res.message || 'Tersimpan.');
            setTimeout(function() {
                window.location.href = poUpdateUrl ? poShowUrl : '{{ route("purchase-order.index") }}';
            }, 800);
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan.');
        });
}

$(document).ready(function() {
    (poInitialItems || []).forEach(function(it) { poAddRow(it, it.parent_key); });
    poSyncEmpty();
    poRecalc();

    $('#po-picker-check-all').on('change', function() {
        var checked = $(this).is(':checked');
        $('.po-picker-check, .po-picker-check-group').prop('checked', checked);
    });

    $(document).on('input change', '.po-qty, .po-price-currency, .po-currency', poRecalc);

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
    }).on('select2:clear', function() {
        $('#po-supplier-id').val('');
        $('#po-supplier-name').val('');
    });
});
</script>
@endsection
