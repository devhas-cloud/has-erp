@extends('layouts.app')

@section('title', $goodsRequest ? 'Edit Permintaan Barang' : 'Buat Permintaan Barang')
@section('page-title', $goodsRequest ? 'Edit Permintaan Barang' : 'Buat Permintaan Barang')

@section('styles')
<style>
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
    .gr-desc[contenteditable="true"]:focus {
        outline: none;
        border-color: var(--accent);
    }
    .gr-desc[contenteditable="true"]:empty::before {
        content: attr(data-placeholder);
        color: #999;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $goodsRequest ? 'Edit Permintaan Barang' : 'Buat Permintaan Barang' }}</h1>
        <p class="page-header-sub">Terikat pada quotation yang PO Supplier Approval-nya sudah disetujui 2 approver. Harga tidak perlu diisi di sini — ditentukan saat Purchase Order ke supplier.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('goods-request.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Informasi Permintaan</span>
    </div>
    <div class="card-body-custom">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Quotation <span class="text-danger">*</span></label>
                <select id="gr-quotation-id" class="form-select" @if($goodsRequest) disabled @endif>
                    <option value="">— Pilih Quotation —</option>
                    @foreach($quotations as $q)
                        <option value="{{ $q->id }}" data-opportunity-id="{{ $q->opportunity_id }}" @selected(optional($preselected)->id === $q->id)>
                            {{ $q->quotation_number ?? '#'.$q->id }} — {{ $q->to_name }} ({{ $q->opportunity?->opportunity_name ?? '—' }})
                        </option>
                    @endforeach
                </select>
                @if($goodsRequest)
                    <input type="hidden" id="gr-quotation-id-hidden" value="{{ $goodsRequest->quotation_id }}">
                    <input type="hidden" id="gr-opportunity-id-hidden" value="{{ $goodsRequest->opportunity_id }}">
                    <small style="color:var(--text-muted)">Quotation tidak bisa diganti setelah permintaan barang dibuat.</small>
                @else
                    <small style="color:var(--text-muted)">Hanya menampilkan quotation Finish yang PO Supplier Approval-nya sudah disetujui 2 approver. Item "Ambil dari Configuration" mengikuti opportunity dari quotation terpilih.</small>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label">Catatan</label>
                <textarea id="gr-notes" class="form-control" rows="1" placeholder="Catatan (opsional)">{{ $goodsRequest?->notes }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Daftar Item</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary btn-sm" id="gr-btn-pick" disabled>
                <i class="fa fa-cart-plus me-1"></i> Ambil dari Configuration
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="grOpenProductPicker()">
                <i class="fa fa-plus me-1"></i> Tambah Baris Manual
            </button>
        </div>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0" id="gr-items-table">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width:18%">Part Number</th>
                        <th>Nama Barang / Deskripsi</th>
                        <th style="width:10%">Qty</th>
                        <th style="width:10%">Unit</th>
                        <th class="text-center" style="width:8%">Aksi</th>
                    </tr>
                </thead>
                <tbody id="gr-items-body"></tbody>
            </table>
        </div>
        <div class="config-card-empty" id="gr-items-empty">
            <i class="fa-solid fa-inbox"></i> Belum ada item. Tambahkan lewat "Ambil dari Configuration" atau "Tambah Baris Manual".
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mb-4">
    <button type="button" class="btn btn-primary" onclick="grSave()">
        <i class="fa fa-save me-1"></i> Simpan {{ $goodsRequest ? 'Perubahan' : 'Draft' }}
    </button>
</div>
@endsection

@push('modals')
<div class="modal fade" id="grPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-box-open me-2" style="color:var(--accent)"></i>Pilih Product</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm" id="gr-product-search" placeholder="Cari nama, kode, brand, atau kategori...">
                </div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px"><input type="checkbox" id="gr-product-check-all"></th>
                                <th style="width:110px">Divisi</th>
                                <th style="width:140px">Part Number</th>
                                <th>Nama Barang / Deskripsi</th>
                            </tr>
                        </thead>
                        <tbody id="gr-product-body">
                            <tr><td colspan="4" class="config-card-empty"><span class="config-spinner"></span>Memuat...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-soft btn-sm" onclick="grAddBlankRow()">
                    <i class="fa fa-file me-1"></i> Tambah Baris Kosong (di luar katalog)
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="grApplyProductPicked()">
                        <i class="fa fa-plus me-1"></i> Tambahkan Terpilih
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

const grFetchItemsUrl = '{{ route("goods-request.fetch-config-items") }}';
const grSearchProductsUrl = '{{ route("goods-request.search-products") }}';
const grStoreUrl = '{{ route("goods-request.store") }}';
const grUpdateUrl = '{{ $goodsRequest ? route("goods-request.update", $goodsRequest->id) : "" }}';
const grShowUrl = '{{ $goodsRequest ? route("goods-request.show", $goodsRequest->id) : "" }}';
const grInitialItems = @json($items ?? []);

function grNewKey() {
    return 'row-' + (++grKeySeq);
}

function grSyncEmpty() {
    $('#gr-items-empty').toggle($('#gr-items-body tr').length === 0);
}

function grRowHtml(item) {
    var key = grNewKey();
    var html = '<tr data-key="' + key + '" data-config-item-id="' + (item.quote_configuration_item_id || '') + '" data-product-id="' + (item.master_product_id || '') + '">';
    html += '<td class="text-center gr-row-num"></td>';
    html += '<td><input type="text" class="form-control form-control-sm gr-pn" value="' + (item.part_number || '') + '"></td>';
    html += '<td><div class="form-control form-control-sm gr-desc" contenteditable="true" data-placeholder="Nama barang / deskripsi">' + (item.description || item.name || '') + '</div></td>';
    html += '<td><input type="text" inputmode="decimal" class="form-control form-control-sm gr-qty" value="' + (item.qty != null ? item.qty : '') + '"></td>';
    html += '<td><input type="text" class="form-control form-control-sm gr-unit" value="' + (item.unit || '') + '"></td>';
    html += '<td class="text-center"><button type="button" class="btn-icon text-danger" title="Hapus" onclick="grRemoveRow(this)"><i class="fa fa-trash"></i></button></td>';
    html += '</tr>';
    return html;
}

function grAddRow(item) {
    $('#gr-items-body').append(grRowHtml(item || {}));
    grRenumber();
    grSyncEmpty();
}

function grAddBlankRow() {
    grAddRow({});
    if (grProductPickerInstance) grProductPickerInstance.hide();
}

function grRemoveRow(btn) {
    $(btn).closest('tr').remove();
    grRenumber();
    grSyncEmpty();
}

function grRenumber() {
    $('#gr-items-body tr').each(function(i) { $(this).find('.gr-row-num').text(i + 1); });
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
            unit: $(this).find('.gr-unit').val()
        });
    });
    return items;
}

function grCurrentOpportunityId() {
    var selected = $('#gr-quotation-id option:selected');
    var fromSelect = selected.length ? selected.data('opportunity-id') : null;
    return fromSelect || $('#gr-opportunity-id-hidden').val() || '';
}

function grLoadPicker() {
    var opportunityId = grCurrentOpportunityId();
    if (!opportunityId) {
        toastr.error('Pilih quotation terlebih dahulu.');
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

function grEsc(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
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
        html += '</tr></thead><tbody>';

        d.items.forEach(function(idx) {
            var it = pool[idx];
            var pad = (it.depth || 0) * 18;
            html += '<tr class="gr-picker-item-row" data-group="' + divGroup + '">';
            html += '<td><input type="checkbox" class="gr-picker-check" data-idx="' + idx + '"></td>';
            html += '<td>' + grEsc(it.item_no || '') + '</td>';
            html += '<td style="padding-left:' + pad + 'px">' + grEsc(it.part_number || '—') + '</td>';
            {{-- Deskripsi sudah HTML tersanitasi dari server (Quotation::renderDescription) --}}
            html += '<td style="padding-left:' + pad + 'px">' + (it.description || it.name || '—') + '</td>';
            html += '<td class="text-center">' + grEsc(it.qty || '') + '</td>';
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
        var idx = $(this).data('idx');
        var item = grPickerPool[idx];
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

// ── Tambah Baris Manual: pilih dari katalog Master Product (tanpa harga) ──

function grRenderProductPool() {
    if (grProductPool.length === 0) {
        $('#gr-product-body').html('<tr><td colspan="4" class="config-card-empty"><i class="fa-solid fa-inbox"></i>Product tidak ditemukan.</td></tr>');
        return;
    }
    var html = '';
    grProductPool.forEach(function(p, idx) {
        html += '<tr>';
        html += '<td><input type="checkbox" class="gr-product-check" data-idx="' + idx + '"></td>';
        html += '<td>' + (p.division_name || '—') + '</td>';
        html += '<td>' + (p.code || '—') + '</td>';
        html += '<td>' + (p.name || p.description) + '</td>';
        html += '</tr>';
    });
    $('#gr-product-body').html(html);
}

function grLoadProducts(searchValue) {
    $('#gr-product-body').html('<tr><td colspan="4" class="config-card-empty"><span class="config-spinner"></span>Memuat...</td></tr>');
    $.get(grSearchProductsUrl, { 'search[value]': searchValue || '' })
        .done(function(res) {
            grProductPool = res.data || [];
            grRenderProductPool();
        })
        .fail(function() {
            $('#gr-product-body').html('<tr><td colspan="4" class="config-card-empty"><i class="fa-solid fa-triangle-exclamation"></i>Gagal memuat product.</td></tr>');
        });
}

function grOpenProductPicker() {
    $('#gr-product-search').val('');
    if (!grProductPickerInstance) {
        grProductPickerInstance = new bootstrap.Modal(document.getElementById('grProductPickerModal'));
    }
    grProductPickerInstance.show();
    grLoadProducts('');
}

function grApplyProductPicked() {
    var picked = 0;
    $('.gr-product-check:checked').each(function() {
        var idx = $(this).data('idx');
        var p = grProductPool[idx];
        if (p) {
            grAddRow({
                master_product_id: p.id,
                part_number: p.code,
                description: p.description || p.name,
                qty: '1',
            });
            picked++;
        }
    });
    if (picked === 0) {
        toastr.error('Pilih minimal satu product.');
        return;
    }
    toastr.success(picked + ' item ditambahkan.');
    if (grProductPickerInstance) grProductPickerInstance.hide();
}

function grSave() {
    var quotationId = $('#gr-quotation-id').val() || $('#gr-quotation-id-hidden').val();
    if (!quotationId) {
        toastr.error('Pilih quotation terlebih dahulu.');
        return;
    }

    var payload = {
        quotation_id: quotationId,
        notes: $('#gr-notes').val(),
        items: grCollectItems(),
        _token: '{{ csrf_token() }}'
    };

    var url = grUpdateUrl || grStoreUrl;
    var method = grUpdateUrl ? 'PUT' : 'POST';

    $.ajax({ url: url, method: method, data: payload })
        .done(function(res) {
            toastr.success(res.message || 'Tersimpan.');
            setTimeout(function() {
                window.location.href = grUpdateUrl ? grShowUrl : '{{ route("goods-request.index") }}';
            }, 800);
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan.');
        });
}

$(document).ready(function() {
    (grInitialItems || []).forEach(function(it) { grAddRow(it); });
    grSyncEmpty();

    $('#gr-btn-pick').prop('disabled', !grCurrentOpportunityId());
    $('#gr-btn-pick').on('click', grLoadPicker);

    $('#gr-quotation-id').on('change', function() {
        $('#gr-btn-pick').prop('disabled', !grCurrentOpportunityId());
    });

    $('#gr-picker-check-all').on('change', function() {
        var checked = $(this).is(':checked');
        $('.gr-picker-check, .gr-picker-check-div').prop('checked', checked);
    });

    $('#gr-product-check-all').on('change', function() {
        $('.gr-product-check').prop('checked', $(this).is(':checked'));
    });

    $('#gr-product-search').on('input', function() {
        var val = $(this).val();
        clearTimeout(grProductSearchTimer);
        grProductSearchTimer = setTimeout(function() { grLoadProducts(val); }, 350);
    });
});
</script>
@endsection
