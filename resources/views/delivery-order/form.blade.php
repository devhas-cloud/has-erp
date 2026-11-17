@extends('layouts.app')

@php $isEdit = (bool) $order; @endphp

@section('title', $isEdit ? 'Edit Delivery Order' : 'Buat Delivery Order')
@section('page-title', $isEdit ? 'Edit Delivery Order' : 'Buat Delivery Order')

@section('styles')
<style>
    .do-mode { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    @media (max-width: 767.98px) { .do-mode { grid-template-columns: 1fr; } }
    .do-mode input { display: none; }
    .do-mode label { margin: 0; cursor: pointer; padding: 9px 12px; height: 100%; border: 1px solid var(--card-border); border-radius: var(--radius-sm); font-size: 12.5px; color: var(--text-muted); }
    .do-mode label strong { display: block; color: var(--text-primary); font-size: 13.5px; margin-bottom: 1px; }
    .do-mode input:checked + label { border-color: var(--accent); background: var(--accent-subtle); box-shadow: 0 0 0 1px var(--accent) inset; }
    .do-pl-item { border: 1px solid var(--card-border); border-radius: var(--radius-sm); padding: 8px 12px; margin-bottom: 6px; cursor: pointer; display: flex; gap: 10px; align-items: flex-start; }
    .do-pl-item.checked { border-color: var(--accent); background: var(--accent-subtle); }
    #do-pl-lines textarea { font-size: 13px; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Edit Delivery Order '.$order->do_number : 'Buat Delivery Order' }}</h1>
        <p class="page-header-sub">Pilih project, gudang, dan jalur pengiriman. Stok baru berkurang saat DO diposting.</p>
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
            <div class="card-header-custom"><span><i class="fa-solid fa-diagram-project me-2" style="color:var(--accent)"></i>Project &amp; Gudang</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="do-project">Project <span class="text-danger">*</span></label>
                        <select id="do-project" class="form-select" {{ $isEdit ? 'disabled' : '' }}>
                            <option value="">— Pilih Project —</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" @selected((int) ($order->project_id ?? $preselectProjectId) === $p->id)>{{ $p->project_number }} — {{ $p->project_name }}</option>
                            @endforeach
                        </select>
                        <small style="color:var(--text-muted)">Project aktif yang punya stok milik project atau Packing List Siap Kirim.</small>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="do-warehouse">Gudang Asal <span class="text-danger">*</span></label>
                        <select id="do-warehouse" class="form-select"><option value="">— Pilih project dulu —</option></select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="do-date">Tanggal DO <span class="text-danger">*</span></label>
                        <input type="date" id="do-date" class="form-control" value="{{ $order?->delivery_date?->toDateString() ?? now()->toDateString() }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Jalur Pengiriman <span class="text-danger">*</span></label>
                        <div class="do-mode">
                            <div>
                                <input type="radio" name="do-mode" id="do-mode-direct" value="0" @checked(! ($order->uses_packing_list ?? false))>
                                <label for="do-mode-direct"><strong><i class="fa-solid fa-truck me-1"></i>Langsung</strong>Barang sedikit: tiap baris DO langsung berisi barang dari stok (SN tercetak di DO).</label>
                            </div>
                            <div>
                                <input type="radio" name="do-mode" id="do-mode-packing" value="1" @checked($order->uses_packing_list ?? false)>
                                <label for="do-mode-packing"><strong><i class="fa-solid fa-boxes-stacked me-1"></i>Dengan Packing List</strong>Barang banyak: muat Packing List Siap Kirim; baris DO terisi dari koli.</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-location-dot me-2" style="color:var(--accent)"></i>Penerima &amp; Dokumen</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="do-recipient">To <span class="text-danger">*</span></label>
                        <input type="text" id="do-recipient" class="form-control" maxlength="150" value="{{ $order->recipient_name ?? '' }}">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="do-phone">Phone</label>
                        <input type="text" id="do-phone" class="form-control" maxlength="50" value="{{ $order->recipient_phone ?? '' }}">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="do-attn">Attn</label>
                        <input type="text" id="do-attn" class="form-control" maxlength="150" value="{{ $order->recipient_attn ?? '' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="do-address">Address <span class="text-danger">*</span></label>
                        <textarea id="do-address" class="form-control" rows="2" maxlength="2000">{{ $order->shipping_address ?? '' }}</textarea>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="do-ref">PO/SPK No</label>
                        <input type="text" id="do-ref" class="form-control" maxlength="100" value="{{ $order->customer_ref ?? '' }}">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="do-ref-date">Tanggal PO</label>
                        <input type="date" id="do-ref-date" class="form-control" value="{{ $order?->customer_ref_date?->toDateString() }}">
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label" for="do-courier">Ekspedisi / Kendaraan</label>
                        <input type="text" id="do-courier" class="form-control" maxlength="150" value="{{ $order->courier ?? '' }}">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="do-checked">Cek By</label>
                        <input type="text" id="do-checked" class="form-control" maxlength="150" value="{{ $order->checked_by_name ?? auth()->user()->display_name }}">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="do-ack">Acknowledge</label>
                        <input type="text" id="do-ack" class="form-control" maxlength="255" value="{{ $order->acknowledged_by_name ?? '' }}" placeholder="Boleh lebih dari satu nama">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="do-sent">Send by</label>
                        <input type="text" id="do-sent" class="form-control" maxlength="150" value="{{ $order->sent_by_name ?? '' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="do-note">Catatan internal</label>
                        <input type="text" id="do-note" class="form-control" maxlength="2000" value="{{ $order->note ?? '' }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- Jalur Langsung --}}
        <div class="card-custom fade-in" id="do-direct-card">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Barang Dikirim</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="doEditor.openQuotation()"><i class="fa fa-file-invoice me-1"></i> Dari Quotation</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="doEditor.addLine({})"><i class="fa fa-plus me-1"></i> Baris Kosong</button>
                </div>
            </div>
            <div class="card-body-custom p-2">
                <div id="do-lines"></div>
                <div class="px-1" style="font-size:12px;color:var(--text-muted)"><i class="fa fa-circle-info me-1"></i>Setiap baris wajib berisi minimal satu barang dari stok project. SN tercetak di kolom Serial Number surat jalan.</div>
            </div>
        </div>

        {{-- Jalur Dengan Packing List --}}
        <div id="do-packing-card" style="display:none">
            <div class="card-custom fade-in mb-3">
                <div class="card-header-custom"><span><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i>Packing List Siap Kirim</span></div>
                <div class="card-body-custom" id="do-pl-list"><div style="color:var(--text-muted);font-size:13px">Pilih project dan gudang terlebih dahulu.</div></div>
            </div>
            <div class="card-custom fade-in">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Baris Surat Jalan</span>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="doPlAddLine({})"><i class="fa fa-plus me-1"></i> Baris</button>
                </div>
                <div class="card-body-custom p-2">
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead><tr><th>Description</th><th style="width:110px" class="text-end">Qty</th><th style="width:100px">Satuan</th><th style="width:200px">Serial Number / Ref</th><th style="width:44px"></th></tr></thead>
                            <tbody id="do-pl-lines"></tbody>
                        </table>
                    </div>
                    <div class="px-1 pt-2" style="font-size:12px;color:var(--text-muted)"><i class="fa fa-circle-info me-1"></i>Terisi otomatis dari koli Packing List yang dicentang (koli dengan item Quotation sama digabung). Boleh diubah.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in" style="position:sticky;top:80px">
            <div class="card-header-custom"><span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Jalur</td><td class="text-end fw-semibold" id="do-sum-mode">—</td></tr>
                    <tr><td>Baris</td><td class="text-end fw-semibold" id="do-sum-lines">0</td></tr>
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
@include('partials.stock-pick')
@include('partials.shipment-lines')
<script>
const doProjectUrl = '{{ route("delivery-order.project-stock") }}';
const doPackingUrl = '{{ route("delivery-order.packing-lists") }}';
const doSaveUrl = '{{ $isEdit ? route("delivery-order.update", $order->id) : route("delivery-order.store") }}';
const doSaveMethod = '{{ $isEdit ? "PUT" : "POST" }}';
const doShowUrl = '{{ route("delivery-order.show", "__ID__") }}';
const doIsEdit = {{ $isEdit ? 'true' : 'false' }};
const doOrderId = @json($order?->id);
const doInitialLines = @json($initialLines);
let doInitialPacking = @json($initialPackingLists);
let doInitialWarehouse = @json($order?->warehouse_id);
let doPackingRows = [];
const doSaveLabel = $('#do-save').html();

function doEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function doUsesPacking() { return $('input[name="do-mode"]:checked').val() === '1'; }

const doEditor = ShipmentLines({
    el: '#do-lines',
    boxMode: false,
    stockUrl: '{{ route("delivery-order.stock-options") }}',
    params: function() { return { project_id: $('#do-project').val(), warehouse_id: $('#do-warehouse').val() }; },
    onChange: doSummary
});

function doSummary() {
    $('#do-sum-mode').text(doUsesPacking() ? 'Packing List' : 'Langsung');
    $('#do-sum-lines').text(doUsesPacking() ? $('#do-pl-lines tr').length : (typeof doEditor !== 'undefined' ? doEditor.count() : 0));
}

function doSyncMode() {
    $('#do-direct-card').toggle(!doUsesPacking());
    $('#do-packing-card').toggle(doUsesPacking());
    if (doUsesPacking()) doLoadPacking(); else doEditor.reloadStocks(false);
    doSummary();
}

function doLoadProject() {
    var projectId = $('#do-project').val();
    $('#do-warehouse').html('<option value="">— Pilih project dulu —</option>');
    if (!projectId) return;

    $.get(doProjectUrl, { project_id: projectId }).done(function(res) {
        var opts = '<option value="">— Pilih Gudang —</option>';
        (res.warehouses || []).forEach(function(w) { opts += '<option value="' + w.id + '">' + doEsc(w.label) + '</option>'; });
        $('#do-warehouse').html(opts);
        var wh = doInitialWarehouse || ((res.warehouses || []).length === 1 ? res.warehouses[0].id : '');
        if (wh) $('#do-warehouse').val(String(wh));
        doInitialWarehouse = null;

        doEditor.setQuotationItems(res.quotation_items || []);
        var r = res.recipient || {};
        if (!doIsEdit) {
            $('#do-recipient').val(r.name || '');
            $('#do-phone').val(r.phone || '');
            $('#do-address').val(r.address || '');
            $('#do-ref').val(r.customer_ref || '');
            $('#do-ref-date').val(r.customer_ref_date || '');
        }

        if (doIsEdit && doInitialLines.length) {
            if (doUsesPacking()) doInitialLines.forEach(doPlAddLine);
            else doInitialLines.forEach(function(l) { doEditor.addLine(l); });
            doInitialLines.length = 0;
        }
        doSyncMode();
    });
}

// ── Jalur Dengan Packing List ──
function doLoadPacking() {
    var projectId = $('#do-project').val(), wh = $('#do-warehouse').val();
    if (!projectId || !wh) { $('#do-pl-list').html('<div style="color:var(--text-muted);font-size:13px">Pilih project dan gudang terlebih dahulu.</div>'); return; }
    $.get(doPackingUrl, { project_id: projectId, warehouse_id: wh, order_id: doOrderId || '' }).done(function(res) {
        doPackingRows = res.data || [];
        if (!doPackingRows.length) {
            $('#do-pl-list').html('<div style="color:var(--text-muted);font-size:13px"><i class="fa-solid fa-inbox me-1"></i>Tidak ada Packing List Siap Kirim untuk project dan gudang ini.</div>');
            return;
        }
        var checked = {};
        (doInitialPacking || []).forEach(function(id) { checked[id] = true; });
        var html = '';
        doPackingRows.forEach(function(pl) {
            html += '<label class="do-pl-item' + (checked[pl.id] ? ' checked' : '') + '"><input type="checkbox" class="form-check-input do-pl-check mt-1" value="' + pl.id + '"' + (checked[pl.id] ? ' checked' : '') + '>'
                + '<span><strong>' + doEsc(pl.packing_number) + '</strong> <span style="color:var(--text-muted)">· ' + doEsc(pl.packing_date) + ' · ' + pl.box_count + ' koli · ' + Num.format(pl.weight, 2) + ' kg</span>'
                + '<div style="font-size:12px;color:var(--text-muted)">' + pl.boxes.map(function(b) { return doEsc(b.description.split('\n')[0]); }).join(' · ') + '</div></span></label>';
        });
        $('#do-pl-list').html(html);
        doInitialPacking = null;
    });
}

function doPlAddLine(l) {
    l = l || {};
    var $tr = $('<tr>'
        + '<td><textarea class="form-control form-control-sm dpl-desc" rows="2" maxlength="5000">' + doEsc(l.description || '') + '</textarea></td>'
        + '<td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end dpl-qty" value="' + (l.qty != null ? l.qty : '') + '"></td>'
        + '<td><input type="text" class="form-control form-control-sm dpl-unit" maxlength="50" value="' + doEsc(l.unit || '') + '"></td>'
        + '<td><input type="text" class="form-control form-control-sm dpl-ref" maxlength="255" value="' + doEsc(l.reference || '') + '"></td>'
        + '<td class="text-center"><button type="button" class="btn-icon danger" onclick="$(this).closest(\'tr\').remove(); doSummary();"><i class="fa fa-trash"></i></button></td></tr>');
    $tr.data('qid', l.quotation_item_id || null);
    $('#do-pl-lines').append($tr);
    doSummary();
}

// Baris dari koli PKL tercentang: koli dengan item Quotation sama (dalam satu PKL) digabung.
function doRebuildPlLines() {
    $('#do-pl-lines').empty();
    $('.do-pl-check:checked').each(function() {
        var pl = doPackingRows.find(function(p) { return p.id === parseInt(this.value, 10); }, this);
        if (!pl) return;
        var groups = [];
        pl.boxes.forEach(function(b) {
            var g = b.quotation_item_id ? groups.find(function(x) { return x.qid === b.quotation_item_id && x.unit === b.unit; }) : null;
            if (g) { g.qty += b.qty; g.nos.push(b.no); }
            else groups.push({ qid: b.quotation_item_id, description: b.description, qty: b.qty, unit: b.unit, nos: [b.no] });
        });
        groups.forEach(function(g) {
            var nos = g.nos.length > 1 ? g.nos[0] + '-' + g.nos[g.nos.length - 1] : g.nos[0];
            doPlAddLine({ quotation_item_id: g.qid, description: g.description, qty: Math.round(g.qty * 10000) / 10000, unit: g.unit, reference: 'Box ' + nos + ' · ' + pl.packing_number });
        });
    });
}

$(document).on('change', '.do-pl-check', function() {
    $(this).closest('.do-pl-item').toggleClass('checked', this.checked);
    doRebuildPlLines();
});
$(document).on('change', '#do-project', doLoadProject);
$(document).on('change', '#do-warehouse', function() {
    if (doUsesPacking()) { $('#do-pl-lines').empty(); doLoadPacking(); } else doEditor.reloadStocks(true);
    doSummary();
});
$(document).on('change', 'input[name="do-mode"]', function() {
    $('#do-pl-lines').empty();
    doEditor.clear();
    doSyncMode();
});

function doSave() {
    if (!$('#do-project').val()) { toastr.error('Pilih project.'); return; }
    if (!$('#do-warehouse').val()) { toastr.error('Pilih gudang asal.'); return; }
    if (!$.trim($('#do-recipient').val())) { toastr.error('Isi penerima (To).'); return; }
    if (!$.trim($('#do-address').val())) { toastr.error('Isi alamat.'); return; }

    var payload = {
        project_id: $('#do-project').val(),
        warehouse_id: $('#do-warehouse').val(),
        uses_packing_list: doUsesPacking(),
        delivery_date: $('#do-date').val(),
        recipient_name: $.trim($('#do-recipient').val()),
        recipient_phone: $('#do-phone').val(),
        recipient_attn: $('#do-attn').val(),
        shipping_address: $.trim($('#do-address').val()),
        customer_ref: $('#do-ref').val(),
        customer_ref_date: $('#do-ref-date').val() || null,
        courier: $('#do-courier').val(),
        note: $('#do-note').val(),
        checked_by_name: $('#do-checked').val(),
        acknowledged_by_name: $('#do-ack').val(),
        sent_by_name: $('#do-sent').val()
    };

    if (doUsesPacking()) {
        payload.packing_list_ids = $('.do-pl-check:checked').map(function() { return parseInt(this.value, 10); }).get();
        if (!payload.packing_list_ids.length) { toastr.error('Pilih minimal satu Packing List Siap Kirim.'); return; }
        payload.lines = $('#do-pl-lines tr').map(function() {
            return { quotation_item_id: $(this).data('qid') || null, description: $.trim($(this).find('.dpl-desc').val()), qty: Num.parseOrNull($(this).find('.dpl-qty').val()), unit: $(this).find('.dpl-unit').val(), reference: $(this).find('.dpl-ref').val() };
        }).get();
    } else {
        payload.lines = doEditor.lines();
        for (var i = 0; i < payload.lines.length; i++) {
            if (!payload.lines[i].stocks.length) { toastr.error('Baris ' + (i + 1) + ': pilih minimal satu barang dari stok.'); return; }
        }
    }
    if (!payload.lines.length) { toastr.error('Tambahkan minimal satu baris.'); return; }
    for (var j = 0; j < payload.lines.length; j++) {
        if (!payload.lines[j].description) { toastr.error('Baris ' + (j + 1) + ': isi deskripsi.'); return; }
        if (!payload.lines[j].qty || payload.lines[j].qty <= 0) { toastr.error('Baris ' + (j + 1) + ': isi qty.'); return; }
    }

    $('#do-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: doSaveUrl, method: doSaveMethod, contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        data: JSON.stringify(payload)
    }).done(function(res) {
        toastr.success(res.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = doShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        $('#do-save').prop('disabled', false).html(doSaveLabel);
    });
}

$(document).ready(function() {
    $('#do-project').select2({ theme: 'bootstrap-5', width: '100%' });
    if ($('#do-project').val()) doLoadProject(); else doSyncMode();
});
</script>
@endsection
