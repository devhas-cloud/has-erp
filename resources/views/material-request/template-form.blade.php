@extends('layouts.app')

@php $isEdit = (bool) $template; @endphp

@section('title', $isEdit ? 'Ubah Template Material' : 'Buat Template Material')
@section('page-title', $isEdit ? 'Ubah Template Material' : 'Buat Template Material')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Ubah Template: '.$template->name : 'Buat Template Material' }}</h1>
        <p class="page-header-sub">Daftar material bawaan untuk mengisi Material Request. Qty = kebutuhan per 1 set; saat diambil ke MR bisa dikalikan jumlah set.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('material-request.index') }}#templates" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-layer-group me-2" style="color:var(--accent)"></i>Informasi Template</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <label class="form-label" for="tp-name">Nama Template <span class="text-danger">*</span></label>
                        <input type="text" id="tp-name" class="form-control" maxlength="150" value="{{ $template->name ?? '' }}" placeholder="mis. Panel Kontrol Standar">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="tp-status">Status</label>
                        <select id="tp-status" class="form-select">
                            <option value="Active" @selected(($template->status ?? 'Active') === 'Active')>Aktif — muncul di form MR</option>
                            <option value="Inactive" @selected(($template->status ?? '') === 'Inactive')>Nonaktif — disembunyikan</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="tp-desc">Keterangan</label>
                        <input type="text" id="tp-desc" class="form-control" maxlength="2000" value="{{ $template->description ?? '' }}" placeholder="Opsional — mis. untuk produk / project tipe apa">
                    </div>
                </div>
            </div>
        </div>

        @include('material-request.partials.items-editor', ['editorTitle' => 'Material per 1 Set'])
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in" style="position:sticky;top:80px">
            <div class="card-header-custom"><span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Jumlah material</td><td class="text-end fw-semibold" id="tp-sum-lines">0</td></tr>
                    <tr><td><span class="mr-kind mr-kind-material">Material</span></td><td class="text-end" id="tp-sum-material">0</td></tr>
                    <tr><td><span class="mr-kind mr-kind-serial">Ber-SN</span></td><td class="text-end" id="tp-sum-serial">0</td></tr>
                    <tr><td>Qty belum diisi</td><td class="text-end" id="tp-sum-empty">0</td></tr>
                </table>
                <button type="button" class="btn btn-primary w-100 mt-3" id="tp-save" onclick="tpSave()">
                    <i class="fa fa-check me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Template' }}
                </button>
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Template dipakai bersama semua user. Satuan mengikuti master produk.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const tpSaveUrl = '{{ $isEdit ? route("material-request.template-update", $template->id) : route("material-request.template-store") }}';
const tpSaveMethod = '{{ $isEdit ? "PUT" : "POST" }}';
const tpIndexUrl = '{{ route("material-request.index") }}#templates';
const tpInitial = @json($initialItems);
let tpSaveLabel = '';

function tpSummary(items) {
    var material = 0, serial = 0, empty = 0;
    items.forEach(function(it) {
        if (it.is_material) material++; else serial++;
        if (!(parseFloat(it.qty) > 0)) empty++;
    });
    $('#tp-sum-lines').text(items.length);
    $('#tp-sum-material').text(material);
    $('#tp-sum-serial').text(serial);
    $('#tp-sum-empty').html(empty ? '<span class="text-danger fw-semibold">' + empty + '</span>' : '0');
}

function tpSave() {
    var name = $('#tp-name').val().trim();
    if (!name) { toastr.error('Nama template wajib diisi.'); $('#tp-name').trigger('focus'); return; }
    var res = MrItems.collect();
    if (res.error) { toastr.error(res.error); return; }

    $('#tp-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: tpSaveUrl, method: tpSaveMethod, contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        data: JSON.stringify({ name: name, description: $('#tp-desc').val(), status: $('#tp-status').val(), items: res.items })
    }).done(function(r) {
        toastr.success(r.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = tpIndexUrl; }, 600);
    }).fail(function(xhr) {
        var j = xhr.responseJSON || {};
        toastr.error(j.errors ? j.errors[Object.keys(j.errors)[0]][0] : (j.message || 'Gagal menyimpan.'));
        $('#tp-save').prop('disabled', false).html(tpSaveLabel);
    });
}

$(document).ready(function() {
    tpSaveLabel = $('#tp-save').html();
    MrItems.init(tpInitial, tpSummary);
});
</script>
@endsection
