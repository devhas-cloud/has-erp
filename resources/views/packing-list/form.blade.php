@extends('layouts.app')

@php $isEdit = (bool) $pl; @endphp

@section('title', $isEdit ? 'Ubah Packing List' : 'Buat Packing List')
@section('page-title', $isEdit ? 'Ubah Packing List' : 'Buat Packing List')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Ubah '.$pl->packing_number : 'Buat Packing List' }}</h1>
        <p class="page-header-sub">Satu baris = satu koli. Isi deskripsi (dari item Quotation atau sendiri), dimensi, berat, lalu pilih barang dari stok project yang masuk ke koli.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $isEdit ? route('packing-list.show', $pl->id) : route('packing-list.index') }}" class="btn btn-secondary btn-sm">
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
                        <label class="form-label" for="pkl-project">Project <span class="text-danger">*</span></label>
                        <select id="pkl-project" class="form-select" {{ $isEdit ? 'disabled' : '' }}>
                            <option value="">— Pilih Project —</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" @selected((int) ($pl->project_id ?? $preselectProjectId) === $p->id)>{{ $p->project_number }} — {{ $p->project_name }}</option>
                            @endforeach
                        </select>
                        <small style="color:var(--text-muted)">Project aktif yang punya stok milik project.</small>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="pkl-warehouse">Gudang <span class="text-danger">*</span></label>
                        <select id="pkl-warehouse" class="form-select"><option value="">— Pilih project dulu —</option></select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="pkl-date">Tanggal Pengiriman <span class="text-danger">*</span></label>
                        <input type="date" id="pkl-date" class="form-control" value="{{ $pl?->packing_date?->toDateString() ?? now()->toDateString() }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-location-dot me-2" style="color:var(--accent)"></i>Penerima &amp; Dokumen</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="pkl-recipient">To <span class="text-danger">*</span></label>
                        <input type="text" id="pkl-recipient" class="form-control" maxlength="150" value="{{ $pl->recipient_name ?? '' }}">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="pkl-phone">Phone</label>
                        <input type="text" id="pkl-phone" class="form-control" maxlength="100" value="{{ $pl->recipient_phone ?? '' }}">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="pkl-attn">Attn</label>
                        <input type="text" id="pkl-attn" class="form-control" maxlength="150" value="{{ $pl->recipient_attn ?? '' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="pkl-address">Address</label>
                        <textarea id="pkl-address" class="form-control" rows="2" maxlength="2000">{{ $pl->recipient_address ?? '' }}</textarea>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="pkl-ref">PO/SPK No</label>
                        <input type="text" id="pkl-ref" class="form-control" maxlength="100" value="{{ $pl->customer_ref ?? '' }}">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="pkl-ref-date">Tanggal PO</label>
                        <input type="date" id="pkl-ref-date" class="form-control" value="{{ $pl?->customer_ref_date?->toDateString() }}">
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label" for="pkl-marking">Marking</label>
                        <input type="text" id="pkl-marking" class="form-control" maxlength="150" value="{{ $pl->marking ?? '' }}">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="pkl-signer">Shipper (nama)</label>
                        <input type="text" id="pkl-signer" class="form-control" maxlength="150" value="{{ $pl->signer_name ?? auth()->user()->display_name }}">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="pkl-position">Jabatan</label>
                        <input type="text" id="pkl-position" class="form-control" maxlength="150" value="{{ $pl->signer_position ?? '' }}" placeholder="Contoh: Logistics & Warehouse Manager">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="pkl-note">Catatan</label>
                        <input type="text" id="pkl-note" class="form-control" maxlength="2000" value="{{ $pl->note ?? '' }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i>Koli</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="pklEditor.openQuotation()"><i class="fa fa-file-invoice me-1"></i> Dari Quotation</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="pklEditor.addLine({})"><i class="fa fa-plus me-1"></i> Koli Kosong</button>
                </div>
            </div>
            <div class="card-body-custom p-2">
                <div id="pkl-lines"></div>
                <div class="px-1" style="font-size:12px;color:var(--text-muted)"><i class="fa fa-circle-info me-1"></i>Setiap koli wajib berisi minimal satu barang dari stok project. Unit ber-SN yang SN-nya belum diisi tidak bisa dipilih.</div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in" style="position:sticky;top:80px">
            <div class="card-header-custom"><span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Total koli</td><td class="text-end fw-semibold" id="pkl-sum-boxes">0</td></tr>
                    <tr><td>Total berat</td><td class="text-end fw-semibold" id="pkl-sum-kg">0 kg</td></tr>
                </table>
                <button type="button" class="btn btn-primary w-100 mt-3" id="pkl-save" onclick="pklSave()">
                    <i class="fa fa-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Draft' }}
                </button>
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Setelah disimpan, tandai <strong>Siap Kirim</strong> di halaman detail. Barangnya dipesan sampai dikirim lewat Delivery Order.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@include('partials.stock-pick')
@include('partials.shipment-lines')
<script>
const pklProjectUrl = '{{ route("packing-list.project-data") }}';
const pklSaveUrl = '{{ $isEdit ? route("packing-list.update", $pl->id) : route("packing-list.store") }}';
const pklSaveMethod = '{{ $isEdit ? "PUT" : "POST" }}';
const pklShowUrl = '{{ route("packing-list.show", "__ID__") }}';
const pklIsEdit = {{ $isEdit ? 'true' : 'false' }};
const pklInitialBoxes = @json($initialBoxes);
let pklInitialWarehouse = @json($pl?->warehouse_id);
const pklSaveLabel = $('#pkl-save').html();

function pklEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

const pklEditor = ShipmentLines({
    el: '#pkl-lines',
    boxMode: true,
    stockUrl: '{{ route("packing-list.stock-options") }}',
    params: function() { return { project_id: $('#pkl-project').val(), warehouse_id: $('#pkl-warehouse').val() }; },
    onChange: function() {
        $('#pkl-sum-boxes').text(pklEditor ? pklEditor.count() : 0);
        $('#pkl-sum-kg').text(Num.format(pklEditor ? pklEditor.totalWeight() : 0, 2) + ' kg');
    }
});

function pklLoadProject() {
    var projectId = $('#pkl-project').val();
    $('#pkl-warehouse').html('<option value="">— Pilih project dulu —</option>');
    if (!projectId) { pklEditor.setQuotationItems([]); pklEditor.reloadStocks(true); return; }

    $.get(pklProjectUrl, { project_id: projectId }).done(function(res) {
        var opts = '<option value="">— Pilih Gudang —</option>';
        (res.warehouses || []).forEach(function(w) { opts += '<option value="' + w.id + '">' + pklEsc(w.label) + '</option>'; });
        $('#pkl-warehouse').html(opts);
        var wh = pklInitialWarehouse || ((res.warehouses || []).length === 1 ? res.warehouses[0].id : '');
        if (wh) $('#pkl-warehouse').val(String(wh));
        pklInitialWarehouse = null;

        pklEditor.setQuotationItems(res.quotation_items || []);
        var d = res.defaults || {};
        if (!pklIsEdit) {
            $('#pkl-recipient').val(d.name || '');
            $('#pkl-phone').val(d.phone || '');
            $('#pkl-address').val(d.address || '');
            $('#pkl-ref').val(d.customer_ref || '');
            $('#pkl-ref-date').val(d.customer_ref_date || '');
            $('#pkl-marking').val(d.marking || '');
        }
        if (pklEditor.count() === 0 && pklInitialBoxes.length) pklInitialBoxes.forEach(function(b) { pklEditor.addLine(b); });
        else pklEditor.reloadStocks(!pklIsEdit);
    });
}

$(document).on('change', '#pkl-project', pklLoadProject);
$(document).on('change', '#pkl-warehouse', function() { pklEditor.reloadStocks(true); });

function pklSave() {
    if (!$('#pkl-project').val()) { toastr.error('Pilih project.'); return; }
    if (!$('#pkl-warehouse').val()) { toastr.error('Pilih gudang.'); return; }
    if (!$.trim($('#pkl-recipient').val())) { toastr.error('Isi penerima (To).'); return; }
    var boxes = pklEditor.lines();
    if (!boxes.length) { toastr.error('Tambahkan minimal satu koli.'); return; }
    for (var i = 0; i < boxes.length; i++) {
        if (!boxes[i].description) { toastr.error('Koli ' + (i + 1) + ': isi deskripsi.'); return; }
        if (!boxes[i].qty || boxes[i].qty <= 0) { toastr.error('Koli ' + (i + 1) + ': isi qty kirim.'); return; }
        if (!boxes[i].stocks.length) { toastr.error('Koli ' + (i + 1) + ': pilih minimal satu barang dari stok.'); return; }
    }

    $('#pkl-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: pklSaveUrl, method: pklSaveMethod, contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        data: JSON.stringify({
            project_id: $('#pkl-project').val(),
            warehouse_id: $('#pkl-warehouse').val(),
            packing_date: $('#pkl-date').val(),
            recipient_name: $.trim($('#pkl-recipient').val()),
            recipient_phone: $('#pkl-phone').val(),
            recipient_attn: $('#pkl-attn').val(),
            recipient_address: $('#pkl-address').val(),
            customer_ref: $('#pkl-ref').val(),
            customer_ref_date: $('#pkl-ref-date').val() || null,
            marking: $('#pkl-marking').val(),
            signer_name: $('#pkl-signer').val(),
            signer_position: $('#pkl-position').val(),
            note: $('#pkl-note').val(),
            boxes: boxes
        })
    }).done(function(res) {
        toastr.success(res.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = pklShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        $('#pkl-save').prop('disabled', false).html(pklSaveLabel);
    });
}

$(document).ready(function() {
    $('#pkl-project').select2({ theme: 'bootstrap-5', width: '100%' });
    if ($('#pkl-project').val()) pklLoadProject();
});
</script>
@endsection
