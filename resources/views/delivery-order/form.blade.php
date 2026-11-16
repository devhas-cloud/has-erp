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
                <input type="search" id="do-q" class="form-control form-control-sm" style="max-width:260px" placeholder="Cari kode, nama, SN, batch">
            </div>
            <div class="card-body-custom p-2">
                <div id="do-picker"><div class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih project dan gudang terlebih dahulu.</div></div>
                <div class="px-2 pt-2" style="font-size:12px;color:var(--text-muted)"><i class="fa fa-circle-info me-1"></i>Centang baris stok yang dikirim. Material boleh dikirim sebagian. Unit ber-SN yang SN-nya belum diisi tidak bisa dikirim — lengkapi dulu di <a href="{{ route('stock-detail.index') }}" target="_blank">Lengkapi Data Stok</a>. SN tercetak otomatis di surat jalan.</div>
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
                    <tr><td>Baris stok</td><td class="text-end fw-semibold" id="do-sum-lines">0</td></tr>
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
@include('partials.stock-pick')
<script>
const doProjectStockUrl = '{{ route("delivery-order.project-stock") }}';
const doStockOptionsUrl = '{{ route("delivery-order.stock-options") }}';
const doSaveUrl = '{{ $isEdit ? route("delivery-order.update", $order->id) : route("delivery-order.store") }}';
const doSaveMethod = '{{ $isEdit ? "PUT" : "POST" }}';
const doShowUrl = '{{ route("delivery-order.show", "__ID__") }}';
const doIsEdit = {{ $isEdit ? 'true' : 'false' }};
let doInitialWarehouse = @json($order?->warehouse_id);
let doSelected = @json((object) $selected->all());
const doSaveLabel = $('#do-save').html();

function doEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function doLoadProject() {
    var projectId = $('#do-project').val();
    $('#do-warehouse').html('<option value="">— Pilih project dulu —</option>');
    doRenderItems();
    if (!projectId) return;

    $.get(doProjectStockUrl, { project_id: projectId }).done(function(res) {
        var list = res.warehouses || [];
        var opts = '<option value="">— Pilih Gudang —</option>';
        list.forEach(function(w) { opts += '<option value="' + w.id + '">' + doEsc(w.label) + '</option>'; });
        $('#do-warehouse').html(opts);
        if (list.length === 0) toastr.warning('Project ini belum punya stok teralokasi di gudang mana pun.');

        var wh = doInitialWarehouse || (list.length === 1 ? list[0].id : '');
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
    var projectId = $('#do-project').val(), wh = $('#do-warehouse').val();
    if (!projectId || !wh) {
        $('#do-picker').html('<div class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih project dan gudang terlebih dahulu.</div>');
        doSummary();
        return;
    }
    StockPick.load($('#do-picker'), doStockOptionsUrl, { project_id: projectId, warehouse_id: wh, q: $('#do-q').val() }, {
        requireSerial: true,
        selected: doSelected,
        onChange: doSummary
    });
}

// Pilihan yang sedang tampil + pilihan sebelumnya yang tersembunyi oleh pencarian.
function doRemember() {
    $('#do-picker tr[data-stock-id]').each(function() { delete doSelected[$(this).data('stock-id')]; });
    StockPick.picks($('#do-picker')).forEach(function(p) { doSelected[p.stock_id] = p.qty; });
}

function doCollect() {
    var picks = StockPick.picks($('#do-picker'));
    var visible = {};
    $('#do-picker tr[data-stock-id]').each(function() { visible[$(this).data('stock-id')] = true; });
    Object.keys(doSelected).forEach(function(id) {
        if (!visible[id]) picks.push({ stock_id: parseInt(id, 10), qty: doSelected[id] });
    });
    return picks;
}

function doSummary() {
    var picks = doCollect();
    $('#do-sum-lines').text(picks.length);
    $('#do-sum-qty').text(Num.format(picks.reduce(function(sum, p) { return sum + p.qty; }, 0), 4));
}

$(document).on('change', '#do-project', doLoadProject);
$(document).on('change', '#do-warehouse', function() { doSelected = {}; doRenderItems(); });
let doSearchTimer = null;
$(document).on('input', '#do-q', function() {
    // Simpan pilihan saat ini agar tidak hilang ketika daftar disaring ulang.
    doRemember();
    clearTimeout(doSearchTimer);
    doSearchTimer = setTimeout(doRenderItems, 350);
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
    if (rows.length === 0) { doFail('Pilih minimal satu baris stok yang dikirim.'); return; }

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
            items: rows
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
