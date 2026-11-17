@extends('layouts.app')

@section('title', 'Terima Barang')
@section('page-title', 'Terima Barang')

@section('styles')
<style>
    .gr-sum { display: grid; grid-template-columns: 1fr auto; gap: 8px 10px; font-size: 13px; }
    .gr-sum dt { color: var(--text-muted); font-weight: 400; }
    .gr-sum dd { margin: 0; text-align: right; font-weight: 600; color: var(--text-primary); font-variant-numeric: tabular-nums; }
    .gr-row-off td { opacity: .5; }
    .gr-detail td { background: #fafbfc; border-top: 0 !important; }
    .gr-toggle { border: 0; background: none; padding: 0; font-size: 11.5px; color: var(--accent-hover); }
    .gr-toggle:hover { text-decoration: underline; }
    .gr-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; font-size: 12px; color: var(--text-muted); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Terima Barang</h1>
        <p class="page-header-sub">Pilih dokumen pembelian, lalu cek barang yang datang.</p>
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
                <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Dokumen &amp; Gudang</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-7">
                        <label class="form-label" for="gr-source">Dokumen Pembelian <span class="text-danger">*</span></label>
                        <select id="gr-source" class="form-select" style="width:100%">
                            <option value="">— Pilih PO / Direct Purchase —</option>
                            @if($purchaseOrders->isNotEmpty())
                                <optgroup label="Purchase Order">
                                    @foreach($purchaseOrders as $po)
                                        <option value="po:{{ $po->id }}" {{ $preselect['type'] === 'po' && $preselect['id'] === $po->id ? 'selected' : '' }}>{{ $po->po_number ?: 'PO #'.$po->id }} — {{ $po->supplier_name }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                            @if($directPurchases->isNotEmpty())
                                <optgroup label="Direct Purchase">
                                    @foreach($directPurchases as $dp)
                                        <option value="dp:{{ $dp->id }}" {{ $preselect['type'] === 'dp' && $preselect['id'] === $dp->id ? 'selected' : '' }}>{{ $dp->dp_number ?: 'DPO #'.$dp->id }} — {{ $dp->supplier_name }} ({{ $dp->purpose_label }})</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label" for="gr-warehouse">Gudang Tujuan <span class="text-danger gr-wh-required">*</span></label>
                        <select id="gr-warehouse" class="form-select" style="width:100%">
                            <option value="">— Pilih Gudang —</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="gr-date">Tanggal Terima</label>
                        <input type="date" id="gr-date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-lg-8">
                        <label class="form-label" for="gr-note">Catatan</label>
                        <input type="text" id="gr-note" class="form-control" maxlength="255" placeholder="Opsional, mis. no. surat jalan supplier">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i>Barang Datang</span>
                <div class="d-flex gap-2" id="gr-bulk" style="display:none !important">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="grSetAllDestination('warehouse')"><i class="fa fa-warehouse me-1"></i> Semua ke Gudang</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="grSetAllDestination('direct')"><i class="fa fa-hand-holding me-1"></i> Semua Langsung Pakai</button>
                </div>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px"><input type="checkbox" class="form-check-input" id="gr-check-all" checked aria-label="Pilih semua"></th>
                                <th>Barang</th>
                                <th>Untuk</th>
                                <th class="text-center" style="width:110px">Belum Datang</th>
                                <th class="text-center" style="width:110px">Qty Datang</th>
                                <th style="width:165px">Tujuan</th>
                            </tr>
                        </thead>
                        <tbody id="gr-items-body">
                            <tr>
                                <td colspan="6" class="text-center py-4" style="color:var(--text-muted)">
                                    <i class="fa-solid fa-inbox me-1"></i> Pilih dokumen pembelian untuk menampilkan barang.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="gr-legend px-2 pt-2">
                    <span><i class="fa fa-warehouse me-1"></i>Masuk Gudang menambah stok</span>
                    <span><i class="fa fa-hand-holding me-1"></i>Langsung Pakai tidak menambah stok</span>
                    <span><i class="fa fa-barcode me-1"></i>SN boleh diisi nanti</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in" style="position:sticky;top:80px">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Ringkasan</span>
            </div>
            <div class="card-body-custom">
                <dl class="gr-sum mb-0">
                    <dt>Supplier</dt><dd id="gr-sum-supplier">—</dd>
                    <dt>Barang dipilih</dt><dd id="gr-sum-lines">0</dd>
                    <dt>Masuk Gudang</dt><dd id="gr-sum-warehouse">0</dd>
                    <dt>Langsung Pakai</dt><dd id="gr-sum-direct">0</dd>
                </dl>
                <button type="button" class="btn btn-primary w-100 mt-3" id="gr-save-btn" onclick="grSave()">
                    <i class="fa fa-check me-1"></i> Terima &amp; Posting
                </button>
                <div class="mt-2 text-center" style="font-size:11.5px;color:var(--text-muted)">
                    <i class="fa-solid fa-lock me-1"></i>Langsung diposting, tidak bisa diubah
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
const grProductUrl = '{{ route("product-management.index") }}';
// Satuan yang wajar untuk barang ber-SN (dicatat per unit).
const grUnitUnits = ['unit', 'units', 'pcs', 'pc', 'ea', 'set', 'buah', 'bh', 'nos', 'no', 'lot'];

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
    return '<select class="form-select form-select-sm gr-dest" aria-label="Tujuan">'
        + '<option value="warehouse"' + (value === 'warehouse' ? ' selected' : '') + '>Masuk Gudang</option>'
        + '<option value="direct"' + (value === 'direct' ? ' selected' : '') + '>Langsung Pakai</option>'
        + '</select>';
}

// Rincian stok per baris: batch / No. MFG, kedaluwarsa, garansi, catatan, dan SN (barang ber-SN).
function grDetailRow(it) {
    var unit = String(it.unit || '').trim().toLowerCase();
    var warn = it.serialized && ((unit && grUnitUnits.indexOf(unit) === -1) || it.remaining > 50)
        ? '<div class="mt-2" style="font-size:12px;color:#b45309"><i class="fa fa-triangle-exclamation me-1"></i>'
          + grEsc(it.product_code || 'Produk ini') + ' tercatat Barang ber-SN (1 baris stok per unit). Kalau dihitung per jumlah, ubah ke Material di <a href="' + grProductUrl + '" target="_blank">master produk</a>.</div>'
        : '';
    var html = '<tr class="gr-detail" data-for="' + it.source_line_id + '" style="display:none"><td></td><td colspan="5">';
    html += '<div class="row g-2" style="font-size:12px">';
    html += '<div class="col-md-3"><label class="form-label mb-0" style="font-size:11.5px">Batch / No. MFG</label><input type="text" class="form-control form-control-sm gr-batch" maxlength="100"></div>';
    html += '<div class="col-md-3"><label class="form-label mb-0" style="font-size:11.5px">Kedaluwarsa</label><input type="date" class="form-control form-control-sm gr-expiry"></div>';
    html += '<div class="col-md-3"><label class="form-label mb-0" style="font-size:11.5px">Garansi s/d</label><input type="date" class="form-control form-control-sm gr-warranty"></div>';
    html += '<div class="col-md-3"><label class="form-label mb-0" style="font-size:11.5px">Catatan</label><input type="text" class="form-control form-control-sm gr-item-note" maxlength="255"></div>';
    if (it.serialized) {
        html += '<div class="col-12"><label class="form-label mb-0" style="font-size:11.5px">SN (<span class="gr-sn-count">0</span>/<span class="gr-sn-need">' + it.remaining + '</span>) — satu per baris</label>'
            + '<textarea class="form-control form-control-sm gr-serials" rows="2" placeholder="Tempel atau scan SN"></textarea></div>';
    }
    html += '</div>' + warn + '</td></tr>';
    return html;
}

function grSerialList(text) {
    return String(text || '').split(/[\r\n,;]+/).map(function(s) { return s.trim(); }).filter(function(s) { return s !== ''; });
}

function grToggleDetail(lineId, show) {
    var $d = $('#gr-items-body tr.gr-detail[data-for="' + lineId + '"]');
    var open = show === undefined ? !$d.is(':visible') : show;
    $d.toggle(open);
    $('#gr-items-body tr[data-line-id="' + lineId + '"] .gr-toggle i').toggleClass('fa-chevron-down', !open).toggleClass('fa-chevron-up', open);
}

function grLoadItems() {
    var source = grSource();
    if (!source) {
        $('#gr-sum-supplier').text('—');
        grEmpty('<i class="fa-solid fa-inbox me-1"></i> Pilih dokumen pembelian untuk menampilkan barang.');
        return;
    }

    grEmpty('<span class="spinner-border spinner-border-sm me-1"></span> Memuat barang...');

    $.get(grFetchItemsUrl, { source_type: source.type, source_id: source.id })
        .done(function(res) {
            $('#gr-sum-supplier').text(res.source && res.source.supplier_name ? res.source.supplier_name : '—');
            var items = res.items || [];
            if (!items.length) {
                grEmpty('<i class="fa-solid fa-circle-check me-1" style="color:var(--accent)"></i> Semua barang sudah diterima.');
                return;
            }
            var html = '';
            items.forEach(function(it) {
                var name = it.product_name || $('<div>').html(it.description || '').text() || '—';
                html += '<tr data-line-id="' + it.source_line_id + '" data-remaining="' + it.remaining + '" data-serialized="' + (it.serialized ? 1 : 0) + '">';
                html += '<td><input type="checkbox" class="form-check-input gr-check" checked aria-label="Terima ' + grEsc(name) + '"></td>';
                html += '<td style="font-size:12.5px"><div class="fw-semibold" style="color:var(--text-primary)">' + grEsc(name) + '</div>'
                    + '<div style="color:var(--text-muted)">' + grEsc(it.part_number || '') + (it.serialized ? ' <span class="badge" style="background:var(--info-soft);color:#4338ca;font-size:10px">SN</span>' : '') + '</div>'
                    + '<button type="button" class="gr-toggle" data-line="' + it.source_line_id + '">Rincian <i class="fa fa-chevron-down" style="font-size:9px"></i></button></td>';
                html += '<td style="font-size:12px">' + (it.project_label
                    ? '<span class="badge" style="background:#fef3c7;color:#92400e;white-space:normal">' + grEsc(it.project_label) + '</span>'
                    : '<span style="color:var(--text-muted)">Stok bebas</span>') + '</td>';
                html += '<td class="text-center"><strong>' + it.remaining + '</strong> <span style="font-size:11px;color:var(--text-muted)">/ ' + it.ordered + ' ' + grEsc(it.unit || '') + '</span></td>';
                html += '<td><input type="text" inputmode="numeric" class="form-control form-control-sm text-center gr-qty" value="' + it.remaining + '" aria-label="Qty datang"></td>';
                html += '<td>' + grDestinationSelect(it.destination) + '</td>';
                html += '</tr>';
                html += grDetailRow(it);
            });
            $('#gr-items-body').html(html);
            $('#gr-items-body .gr-dest').trigger('change');
            // Barang ber-SN: rincian terbuka supaya SN mudah diisi.
            $('#gr-items-body tr[data-serialized="1"]').each(function() {
                if ($(this).find('.gr-dest').val() === 'warehouse') grToggleDetail($(this).attr('data-line-id'), true);
            });
            $('#gr-bulk').attr('style', '');
            $('#gr-check-all').prop('checked', true);
            grSuggestWarehouse();
            grUpdateSummary();
        })
        .fail(function(xhr) {
            grEmpty(grEsc(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat barang.'), 'var(--danger)');
        });
}

// Hanya satu gudang yang bisa dipilih: isi otomatis bila belum dipilih.
function grSuggestWarehouse() {
    if ($('#gr-warehouse').val()) return;
    var $opts = $('#gr-warehouse option').filter(function() { return this.value !== ''; });
    if ($opts.length === 1) $('#gr-warehouse').val($opts.val()).trigger('change');
}

function grSetAllDestination(value) {
    $('#gr-items-body .gr-dest').val(value).trigger('change');
    grUpdateSummary();
}

function grUpdateSummary() {
    var lines = 0, wh = 0, direct = 0;
    $('#gr-items-body tr[data-line-id]').each(function() {
        var on = $(this).find('.gr-check').is(':checked');
        $(this).toggleClass('gr-row-off', !on);
        if (!on) return;
        lines++;
        var qty = parseInt($(this).find('.gr-qty').val(), 10) || 0;
        if ($(this).find('.gr-dest').val() === 'direct') direct += qty; else wh += qty;
    });
    $('#gr-sum-lines').text(lines);
    $('#gr-sum-warehouse').text(wh);
    $('#gr-sum-direct').text(direct);
    $('.gr-wh-required').toggle(wh > 0);
}

function grSave() {
    var source = grSource();
    if (!source) { toastr.error('Pilih dokumen pembelian.'); return; }

    var items = [];
    var ok = true;
    $('#gr-items-body tr[data-line-id]').each(function(i) {
        var $row = $(this);
        if (!$row.find('.gr-check').is(':checked')) return;
        var qty = parseInt($row.find('.gr-qty').val(), 10) || 0;
        var remaining = parseInt($row.attr('data-remaining'), 10) || 0;
        var label = $row.find('.fw-semibold').first().text();
        if (qty < 1) { ok = false; toastr.error(label + ': qty minimal 1 (hapus centang bila belum datang).'); $row.find('.gr-qty').focus(); return false; }
        if (qty > remaining) { ok = false; toastr.error(label + ': qty melebihi yang belum datang (' + remaining + ').'); $row.find('.gr-qty').focus(); return false; }
        var $detail = $('#gr-items-body tr.gr-detail[data-for="' + $row.attr('data-line-id') + '"]');
        var serials = grSerialList($detail.find('.gr-serials').val());
        if (serials.length > qty) { ok = false; toastr.error(label + ': jumlah SN (' + serials.length + ') melebihi qty (' + qty + ').'); grToggleDetail($row.attr('data-line-id'), true); return false; }
        items.push({
            source_line_id: $row.attr('data-line-id'),
            qty: qty,
            destination: $row.find('.gr-dest').val(),
            serial_numbers: serials,
            batch_number: $detail.find('.gr-batch').val() || '',
            expiry_date: $detail.find('.gr-expiry').val() || '',
            warranty_until: $detail.find('.gr-warranty').val() || '',
            note: $detail.find('.gr-item-note').val() || ''
        });
    });
    if (!ok) return;
    if (!items.length) { toastr.error('Centang minimal satu barang yang datang.'); return; }

    var needWarehouse = items.some(function(it) { return it.destination === 'warehouse'; });
    if (needWarehouse && !$('#gr-warehouse').val()) {
        toastr.error('Pilih gudang tujuan.');
        $('#gr-warehouse').select2('open');
        return;
    }

    Swal.fire({
        title: 'Posting penerimaan?',
        text: items.length + ' barang akan diterima' + (needWarehouse ? ' ke ' + $('#gr-warehouse option:selected').text().trim() : '') + '. Penerimaan tidak bisa diubah setelah diposting.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Posting',
        cancelButtonText: 'Batal'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $('#gr-save-btn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Memposting...');
        $.ajax({
            url: grStoreUrl,
            method: 'POST',
            data: {
                source_type: source.type,
                source_id: source.id,
                warehouse_id: needWarehouse ? $('#gr-warehouse').val() : '',
                received_date: $('#gr-date').val(),
                note: $('#gr-note').val(),
                items: items,
                _token: '{{ csrf_token() }}'
            }
        }).done(function(res) {
            toastr.success(res.message || 'Tersimpan.');
            setTimeout(function() { window.location.href = grShowUrl.replace('__ID__', res.id); }, 800);
        }).fail(function(xhr) {
            var msg = 'Gagal menyimpan.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
            toastr.error(msg);
            $('#gr-save-btn').prop('disabled', false).html(grSaveLabel);
        });
    });
}

$(document).ready(function() {
    $('#gr-source').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— Pilih PO / Direct Purchase —', allowClear: true })
        .on('change', grLoadItems);
    $('#gr-warehouse').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— Pilih Gudang —', allowClear: true });

    $('#gr-check-all').on('change', function() {
        $('#gr-items-body .gr-check').prop('checked', $(this).is(':checked')).trigger('change');
    });
    $(document).on('click', '.gr-toggle', function() { grToggleDetail($(this).data('line')); });
    $(document).on('change', '.gr-check, .gr-dest', function() {
        var $row = $(this).closest('tr');
        var lineId = $row.attr('data-line-id');
        var stock = $row.find('.gr-check').is(':checked') && $row.find('.gr-dest').val() === 'warehouse';
        // Rincian stok hanya untuk barang yang masuk gudang.
        $row.find('.gr-toggle').toggle(stock);
        if (!stock) grToggleDetail(lineId, false);
        grUpdateSummary();
    });
    $(document).on('input', '.gr-serials', function() {
        $(this).closest('tr').find('.gr-sn-count').text(grSerialList($(this).val()).length);
    });
    $(document).on('input', '.gr-qty', function() {
        var clean = $(this).val().replace(/\D/g, '');
        if (clean !== $(this).val()) $(this).val(clean);
        $('#gr-items-body tr.gr-detail[data-for="' + $(this).closest('tr').attr('data-line-id') + '"] .gr-sn-need').text(clean || 0);
        grUpdateSummary();
    });

    if (grSource()) grLoadItems();
});
</script>
@endsection
