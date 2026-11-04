@extends('layouts.app')

@php $isEdit = (bool) $order; @endphp

@section('title', $isEdit ? 'Edit Delivery Order' : 'Buat Delivery Order')
@section('page-title', $isEdit ? 'Edit Delivery Order' : 'Buat Delivery Order')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Edit Delivery Order '.$order->do_number : 'Buat Delivery Order' }}</h1>
        <p class="page-header-sub">Pilih project dan gudang asal, lalu isi qty barang milik project yang dikirim. Stok baru berkurang saat DO diposting.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $isEdit ? route('delivery-order.show', $order->id) : route('delivery-order.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-diagram-project me-2" style="color:var(--accent)"></i>Project &amp; Gudang</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-7">
                        <label class="form-label">Project <span class="text-danger">*</span></label>
                        <select id="do-project" class="form-select" {{ $isEdit ? 'disabled' : '' }}>
                            <option value="">— Pilih Project —</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" {{ (int) ($order->project_id ?? $preselectProjectId) === $p->id ? 'selected' : '' }}>{{ $p->project_number }} — {{ $p->project_name }}</option>
                            @endforeach
                        </select>
                        <small style="color:var(--text-muted)">Hanya project aktif yang punya stok teralokasi. Stok bebas dialokasikan dulu lewat Pemenuhan dari Stok / Transfer Stok.</small>
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label">Gudang Asal <span class="text-danger">*</span></label>
                        <select id="do-warehouse" class="form-select">
                            <option value="">— Pilih project dulu —</option>
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Tanggal Kirim <span class="text-danger">*</span></label>
                        <input type="date" id="do-date" class="form-control" value="{{ $order?->delivery_date?->toDateString() ?? now()->toDateString() }}">
                    </div>
                    <div class="col-lg-8">
                        <label class="form-label">Ekspedisi / Kendaraan</label>
                        <input type="text" id="do-courier" class="form-control" maxlength="150" value="{{ $order->courier ?? '' }}" placeholder="Contoh: JNE Trucking / B 1234 XYZ">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-location-dot me-2" style="color:var(--accent)"></i>Penerima</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-7">
                        <label class="form-label">Nama Penerima <span class="text-danger">*</span></label>
                        <input type="text" id="do-recipient" class="form-control" maxlength="150" value="{{ $order->recipient_name ?? '' }}">
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label">Telepon</label>
                        <input type="text" id="do-phone" class="form-control" maxlength="50" value="{{ $order->recipient_phone ?? '' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Alamat Kirim <span class="text-danger">*</span></label>
                        <textarea id="do-address" class="form-control" rows="2" maxlength="2000">{{ $order->shipping_address ?? '' }}</textarea>
                        <small style="color:var(--text-muted)">Terisi otomatis dari alamat customer project; ubah bila dikirim ke site.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan</label>
                        <input type="text" id="do-note" class="form-control" maxlength="2000" value="{{ $order->note ?? '' }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Barang Dikirim</span>
                <button type="button" class="btn btn-secondary btn-sm" id="do-fill-all" style="display:none" onclick="doFillAll()"><i class="fa fa-angles-right me-1"></i> Kirim Semua</button>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:45px" class="text-center">#</th>
                                <th>Produk</th>
                                <th class="text-center" style="width:110px">Stok Project</th>
                                <th class="text-center" style="width:120px">Qty Kirim</th>
                                <th style="width:220px">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="do-items">
                            <tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih project dan gudang terlebih dahulu.</td></tr>
                        </tbody>
                    </table>
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
                <table class="info-table" style="width:100%">
                    <tr><td>Produk</td><td class="text-end fw-semibold" id="do-sum-lines">0</td></tr>
                    <tr><td>Total Qty</td><td class="text-end fw-semibold" id="do-sum-qty">0</td></tr>
                </table>
                <button type="button" class="btn btn-primary w-100 mt-3" id="do-save" onclick="doSave()">
                    <i class="fa fa-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Draft' }}
                </button>
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Setelah disimpan, posting DO dari halaman detail saat barang dikirim.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const doProjectStockUrl = '{{ route("delivery-order.project-stock") }}';
const doSaveUrl = '{{ $isEdit ? route("delivery-order.update", $order->id) : route("delivery-order.store") }}';
const doSaveMethod = '{{ $isEdit ? "PUT" : "POST" }}';
const doShowUrl = '{{ route("delivery-order.show", "__ID__") }}';
const doIsEdit = {{ $isEdit ? 'true' : 'false' }};
let doInitialWarehouse = @json($order?->warehouse_id);
let doInitialItems = @json($items);
const doSaveLabel = $('#do-save').html();
let doStock = [];

function doEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function doLoadProject() {
    var projectId = $('#do-project').val();
    doStock = [];
    $('#do-warehouse').html('<option value="">— Pilih project dulu —</option>');
    doRenderItems();
    if (!projectId) return;

    $.get(doProjectStockUrl, { project_id: projectId }).done(function(res) {
        doStock = res.items || [];
        var seen = {}, opts = '<option value="">— Pilih Gudang —</option>';
        doStock.forEach(function(s) {
            if (seen[s.warehouse_id]) return;
            seen[s.warehouse_id] = true;
            opts += '<option value="' + s.warehouse_id + '">' + doEsc(s.warehouse) + '</option>';
        });
        $('#do-warehouse').html(opts);
        if (doStock.length === 0) toastr.warning('Project ini belum punya stok teralokasi di gudang mana pun.');

        var wh = doInitialWarehouse || (Object.keys(seen).length === 1 ? Object.keys(seen)[0] : '');
        if (wh) $('#do-warehouse').val(String(wh));
        doInitialWarehouse = null;

        if (!doIsEdit && res.recipient) {
            if (!$('#do-recipient').val()) $('#do-recipient').val(res.recipient.name || '');
            if (!$('#do-phone').val()) $('#do-phone').val(res.recipient.phone || '');
            if (!$('#do-address').val()) $('#do-address').val(res.recipient.address || '');
        }
        doRenderItems();
    });
}

function doRenderItems() {
    var wh = parseInt($('#do-warehouse').val(), 10);
    var rows = doStock.filter(function(s) { return s.warehouse_id === wh; });
    $('#do-fill-all').toggle(rows.length > 0);
    if (!wh || rows.length === 0) {
        $('#do-items').html('<tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> ' + (wh ? 'Tidak ada stok project di gudang ini.' : 'Pilih project dan gudang terlebih dahulu.') + '</td></tr>');
        doSummary();
        return;
    }
    var initial = {};
    (doInitialItems || []).forEach(function(i) { initial[i.master_product_id] = i; });
    doInitialItems = null;

    var html = '';
    rows.forEach(function(s, i) {
        var pre = initial[s.master_product_id] || {};
        html += '<tr data-product-id="' + s.master_product_id + '" data-max="' + s.balance + '">';
        html += '<td class="text-center" style="color:var(--text-muted)">' + (i + 1) + '</td>';
        html += '<td><div class="fw-semibold" style="font-size:13px">' + doEsc(s.name) + '</div><div style="font-size:11px;color:var(--text-muted)">' + doEsc(s.code) + '</div></td>';
        html += '<td class="text-center">' + s.balance + ' ' + doEsc(s.unit || '') + '</td>';
        html += '<td><input type="text" inputmode="numeric" class="form-control form-control-sm text-center do-qty" placeholder="0" value="' + (pre.qty || '') + '"></td>';
        html += '<td><input type="text" class="form-control form-control-sm do-remark" maxlength="255" value="' + doEsc(pre.remark || '') + '" placeholder="Opsional, mis. S/N"></td>';
        html += '</tr>';
    });
    $('#do-items').html(html);
    doSummary();
}

function doFillAll() {
    $('#do-items tr[data-product-id]').each(function() { $(this).find('.do-qty').val($(this).data('max')).removeClass('is-invalid'); });
    doSummary();
}

function doCollect() {
    var rows = [];
    $('#do-items tr[data-product-id]').each(function() {
        var qty = parseInt($(this).find('.do-qty').val(), 10) || 0;
        if (qty > 0) rows.push({ master_product_id: $(this).data('product-id'), qty: qty, remark: $(this).find('.do-remark').val().trim(), max: $(this).data('max'), $el: $(this).find('.do-qty') });
    });
    return rows;
}

function doSummary() {
    var rows = doCollect();
    $('#do-sum-lines').text(rows.length);
    $('#do-sum-qty').text(rows.reduce(function(sum, r) { return sum + r.qty; }, 0));
}

$(document).on('change', '#do-project', doLoadProject);
$(document).on('change', '#do-warehouse', doRenderItems);
$(document).on('input', '.do-qty', function() {
    this.value = this.value.replace(/\D/g, '');
    $(this).removeClass('is-invalid');
    doSummary();
});

function doFail(message, $el) {
    toastr.error(message);
    if ($el && $el.length) { $el.addClass('is-invalid'); $el[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); }
}

function doSave() {
    if (!$('#do-project').val()) { doFail('Pilih project.'); return; }
    if (!$('#do-warehouse').val()) { doFail('Pilih gudang asal.'); return; }
    if (!$('#do-date').val()) { doFail('Isi tanggal kirim.', $('#do-date')); return; }
    if (!$('#do-recipient').val().trim()) { doFail('Nama penerima wajib diisi.', $('#do-recipient')); return; }
    if (!$('#do-address').val().trim()) { doFail('Alamat kirim wajib diisi.', $('#do-address')); return; }

    var rows = doCollect();
    if (rows.length === 0) { doFail('Isi qty minimal satu barang.'); return; }
    for (var i = 0; i < rows.length; i++) {
        if (rows[i].qty > rows[i].max) { doFail('Qty melebihi stok project (' + rows[i].max + ').', rows[i].$el); return; }
    }

    $('#do-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: doSaveUrl,
        method: doSaveMethod,
        contentType: 'application/json',
        data: JSON.stringify({
            project_id: $('#do-project').val(),
            warehouse_id: $('#do-warehouse').val(),
            delivery_date: $('#do-date').val(),
            courier: $('#do-courier').val(),
            recipient_name: $('#do-recipient').val().trim(),
            recipient_phone: $('#do-phone').val(),
            shipping_address: $('#do-address').val().trim(),
            note: $('#do-note').val(),
            items: rows.map(function(r) { return { master_product_id: r.master_product_id, qty: r.qty, remark: r.remark }; })
        }),
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).done(function(res) {
        toastr.success(res.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = doShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        $('#do-save').prop('disabled', false).html(doSaveLabel);
    });
}

$(document).ready(function() {
    $('#do-project').select2({ width: '100%' });
    if ($('#do-project').val()) doLoadProject();
});
</script>
@endsection
