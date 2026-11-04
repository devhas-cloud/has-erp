@extends('layouts.app')

@section('title', 'Terima Barang')
@section('page-title', 'Terima Barang')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Terima Barang</h1>
        <p class="page-header-sub">Pilih PO atau Direct Purchase, lalu konfirmasi qty yang datang. Per baris pilih <strong>Masuk Gudang</strong> atau <strong>Langsung Pakai</strong>.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('goods-receipt.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi Penerimaan</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-7">
                        <label class="form-label">Dokumen Sumber <span class="text-danger">*</span></label>
                        <select id="gr-source" class="form-select" style="width:100%">
                            <option value="">— Pilih PO / Direct Purchase —</option>
                            @if($purchaseOrders->isNotEmpty())
                                <optgroup label="Purchase Order (Approved / Diterima Sebagian)">
                                    @foreach($purchaseOrders as $po)
                                        <option value="po:{{ $po->id }}" {{ $preselect['type'] === 'po' && $preselect['id'] === $po->id ? 'selected' : '' }}>PO {{ $po->po_number ?: '#'.$po->id }} — {{ $po->supplier_name }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                            @if($directPurchases->isNotEmpty())
                                <optgroup label="Direct Purchase (Approved)">
                                    @foreach($directPurchases as $dp)
                                        <option value="dp:{{ $dp->id }}" {{ $preselect['type'] === 'dp' && $preselect['id'] === $dp->id ? 'selected' : '' }}>DP {{ $dp->dp_number ?: '#'.$dp->id }} — {{ $dp->supplier_name }} ({{ $dp->purpose_label }})</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                        <small style="color:var(--text-muted)">Hanya dokumen yang masih punya sisa belum diterima.</small>
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label">Gudang Tujuan <span class="text-danger gr-wh-required">*</span></label>
                        <select id="gr-warehouse" class="form-select" style="width:100%">
                            <option value="">— Pilih Gudang —</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}" data-type="{{ $w->type }}">{{ $w->code }} — {{ $w->name }} ({{ $w->type_label }})</option>
                            @endforeach
                        </select>
                        <small style="color:var(--text-muted)" id="gr-warehouse-hint">Wajib bila ada baris Masuk Gudang.</small>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Tanggal Terima</label>
                        <input type="date" id="gr-date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-lg-8">
                        <label class="form-label">Catatan</label>
                        <input type="text" id="gr-note" class="form-control" maxlength="255" placeholder="Opsional, contoh: No. surat jalan supplier">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Item Diterima</span>
                <div class="d-flex gap-2" id="gr-bulk" style="display:none !important">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="grSetAllDestination('warehouse')"><i class="fa fa-warehouse me-1"></i> Semua Masuk Gudang</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="grSetAllDestination('direct')"><i class="fa fa-hand-holding me-1"></i> Semua Langsung Pakai</button>
                </div>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px"><input type="checkbox" id="gr-check-all" checked></th>
                                <th>Produk</th>
                                <th>Untuk</th>
                                <th class="text-center" style="width:110px">Sisa</th>
                                <th class="text-center" style="width:120px">Qty Diterima</th>
                                <th style="width:170px">Tujuan</th>
                            </tr>
                        </thead>
                        <tbody id="gr-items-body">
                            <tr>
                                <td colspan="6" class="text-center py-4" style="color:var(--text-muted)">
                                    <i class="fa-solid fa-inbox me-1"></i> Pilih dokumen sumber untuk memuat item.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-2 pt-2" style="font-size:12px;color:var(--text-muted)">
                    <i class="fa fa-circle-info me-1"></i>
                    Barang project tercatat sebagai stok milik project (umumnya di gudang transit) sampai dikirim. <strong>Langsung Pakai</strong> hanya konfirmasi terima, tidak menambah stok. Qty tidak boleh melebihi sisa.
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Ringkasan</span>
            </div>
            <div class="card-body-custom">
                <div class="d-flex justify-content-between mb-1" style="font-size:13px"><span style="color:var(--text-muted)">Supplier</span><strong id="gr-sum-supplier">—</strong></div>
                <div class="d-flex justify-content-between mb-1" style="font-size:13px"><span style="color:var(--text-muted)">Masuk Gudang</span><strong id="gr-sum-warehouse">0</strong></div>
                <div class="d-flex justify-content-between mb-1" style="font-size:13px"><span style="color:var(--text-muted)">Langsung Pakai</span><strong id="gr-sum-direct">0</strong></div>
                <button type="button" class="btn btn-primary w-100 mt-3" id="gr-save-btn" onclick="grSave()">
                    <i class="fa fa-check me-1"></i> Terima &amp; Posting
                </button>
                <div style="margin-top:10px;font-size:12px;line-height:1.5;color:var(--text-muted)">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Saat disimpan, penerimaan langsung diposting: stok gudang bertambah dan status PO diperbarui.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const grFetchItemsUrl = '{{ route("goods-receipt.fetch-source-items") }}';
const grStoreUrl = '{{ route("goods-receipt.store") }}';
const grShowUrl = '{{ route("goods-receipt.show", "__ID__") }}';
const grSaveLabel = '<i class="fa fa-check me-1"></i> Terima &amp; Posting';

function grEsc(s) {
    return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function grSource() {
    var parts = String($('#gr-source').val() || '').split(':');
    return parts.length === 2 ? { type: parts[0], id: parts[1] } : null;
}

function grEmpty(message, color) {
    $('#gr-items-body').html('<tr><td colspan="6" class="text-center py-4" style="color:' + (color || 'var(--text-muted)') + '">' + message + '</td></tr>');
    $('#gr-bulk').attr('style', 'display:none !important');
    grUpdateSummary();
}

function grDestinationSelect(value) {
    return '<select class="form-select form-select-sm gr-dest">'
        + '<option value="warehouse"' + (value === 'warehouse' ? ' selected' : '') + '>Masuk Gudang</option>'
        + '<option value="direct"' + (value === 'direct' ? ' selected' : '') + '>Langsung Pakai</option>'
        + '</select>';
}

function grLoadItems() {
    var source = grSource();
    if (!source) {
        $('#gr-sum-supplier').text('—');
        grEmpty('<i class="fa-solid fa-inbox me-1"></i> Pilih dokumen sumber untuk memuat item.');
        return;
    }

    grEmpty('<span class="config-spinner"></span> Memuat item...');

    $.get(grFetchItemsUrl, { source_type: source.type, source_id: source.id })
        .done(function(res) {
            $('#gr-sum-supplier').text(res.source && res.source.supplier_name ? res.source.supplier_name : '—');
            var items = res.items || [];
            if (!items.length) {
                grEmpty('<i class="fa-solid fa-inbox me-1"></i> Semua item sudah diterima.');
                return;
            }
            var html = '';
            var hasProject = false;
            items.forEach(function(it) {
                if (it.project_id) hasProject = true;
                html += '<tr data-line-id="' + it.source_line_id + '" data-remaining="' + it.remaining + '">';
                html += '<td><input type="checkbox" class="gr-check" checked></td>';
                html += '<td style="font-size:12.5px"><div class="fw-semibold">' + grEsc(it.part_number || '—') + '</div><div style="color:var(--text-muted)">' + grEsc(it.product_name || $('<div>').html(it.description || '').text()) + '</div></td>';
                html += '<td style="font-size:12px">' + (it.project_label
                    ? '<span class="badge" style="background:#fef3c7;color:#92400e;white-space:normal">' + grEsc(it.project_label) + '</span>'
                    : '<span style="color:var(--text-muted)">Stok bebas</span>') + '</td>';
                html += '<td class="text-center">' + it.remaining + ' <span style="font-size:11px;color:var(--text-muted)">/ ' + it.ordered + ' ' + grEsc(it.unit || '') + '</span></td>';
                html += '<td><input type="text" inputmode="numeric" class="form-control form-control-sm text-center gr-qty" value="' + it.remaining + '"></td>';
                html += '<td>' + grDestinationSelect(it.destination) + '</td>';
                html += '</tr>';
            });
            $('#gr-items-body').html(html);
            $('#gr-bulk').attr('style', '');
            $('#gr-check-all').prop('checked', true);
            grSuggestWarehouse(hasProject);
            grUpdateSummary();
        })
        .fail(function(xhr) {
            grEmpty(grEsc(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat item.'), 'var(--danger)');
        });
}

// Barang project disarankan masuk gudang transit (bila ada); hanya jika gudang belum dipilih.
function grSuggestWarehouse(hasProject) {
    if ($('#gr-warehouse').val()) return;
    var type = hasProject ? 'transit' : 'stock';
    var $opt = $('#gr-warehouse option[data-type="' + type + '"]').first();
    if ($opt.length) {
        $('#gr-warehouse').val($opt.val()).trigger('change');
        $('#gr-warehouse-hint').text(hasProject ? 'Disarankan gudang transit untuk barang project — bisa diganti.' : 'Wajib bila ada baris Masuk Gudang.');
    }
}

function grSetAllDestination(value) {
    $('#gr-items-body .gr-dest').val(value);
    grUpdateSummary();
}

function grUpdateSummary() {
    var wh = 0, direct = 0;
    $('#gr-items-body tr[data-line-id]').each(function() {
        if (!$(this).find('.gr-check').is(':checked')) return;
        var qty = parseInt($(this).find('.gr-qty').val(), 10) || 0;
        if ($(this).find('.gr-dest').val() === 'direct') direct += qty; else wh += qty;
    });
    $('#gr-sum-warehouse').text(wh);
    $('#gr-sum-direct').text(direct);
    $('.gr-wh-required').toggle(wh > 0);
}

function grSave() {
    var source = grSource();
    if (!source) { toastr.error('Pilih dokumen sumber.'); return; }

    var items = [];
    var ok = true;
    $('#gr-items-body tr[data-line-id]').each(function(i) {
        var $row = $(this);
        if (!$row.find('.gr-check').is(':checked')) return;
        var qty = parseInt($row.find('.gr-qty').val(), 10) || 0;
        var remaining = parseInt($row.attr('data-remaining'), 10) || 0;
        if (qty < 1) { ok = false; toastr.error('Qty baris ' + (i + 1) + ' minimal 1 (hapus centang bila belum datang).'); return false; }
        if (qty > remaining) { ok = false; toastr.error('Qty baris ' + (i + 1) + ' melebihi sisa (' + remaining + ').'); return false; }
        items.push({ source_line_id: $row.attr('data-line-id'), qty: qty, destination: $row.find('.gr-dest').val() });
    });
    if (!ok) return;
    if (!items.length) { toastr.error('Centang minimal satu baris yang diterima.'); return; }

    var needWarehouse = items.some(function(it) { return it.destination === 'warehouse'; });
    if (needWarehouse && !$('#gr-warehouse').val()) {
        toastr.error('Pilih gudang tujuan untuk barang yang masuk gudang.');
        $('#gr-warehouse').select2('open');
        return;
    }

    var payload = {
        source_type: source.type,
        source_id: source.id,
        warehouse_id: needWarehouse ? $('#gr-warehouse').val() : '',
        received_date: $('#gr-date').val(),
        note: $('#gr-note').val(),
        items: items,
        _token: '{{ csrf_token() }}'
    };

    $('#gr-save-btn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Memposting...');

    $.ajax({ url: grStoreUrl, method: 'POST', data: payload })
        .done(function(res) {
            toastr.success(res.message || 'Tersimpan.');
            setTimeout(function() { window.location.href = grShowUrl.replace('__ID__', res.id); }, 800);
        })
        .fail(function(xhr) {
            var msg = 'Gagal menyimpan.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
            toastr.error(msg);
            $('#gr-save-btn').prop('disabled', false).html(grSaveLabel);
        });
}

$(document).ready(function() {
    $('#gr-source').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: '— Pilih PO / Direct Purchase —',
        allowClear: true
    }).on('change', grLoadItems);

    $('#gr-warehouse').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: '— Pilih Gudang —',
        allowClear: true
    });

    $('#gr-check-all').on('change', function() {
        $('#gr-items-body .gr-check').prop('checked', $(this).is(':checked'));
        grUpdateSummary();
    });
    $(document).on('change', '.gr-check, .gr-dest', grUpdateSummary);
    $(document).on('input', '.gr-qty', function() {
        var clean = $(this).val().replace(/\D/g, '');
        if (clean !== $(this).val()) $(this).val(clean);
        grUpdateSummary();
    });

    if (grSource()) grLoadItems();
});
</script>
@endsection
