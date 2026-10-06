@extends('layouts.app')

@section('title', $template ? 'Edit Template #'.$template->id : 'Buat Template')
@section('page-title', $template ? 'Edit Template' : 'Buat Template')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $template ? 'Edit Template #'.$template->id : 'Buat Template' }}</h1>
        <p class="page-header-sub">Buat template part instrument sendiri sebelum dipakai, lengkap dengan judul. Item disusun hierarki — baris parent bisa punya child melalui tombol ＋.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $indexUrl }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<form id="tpl-form" autocomplete="off">
    <input type="hidden" id="tpl-edit-id" value="{{ $template?->id }}">

    <div class="card-custom fade-in mb-3">
        <div class="card-header-custom">
            <span><i class="fa-solid fa-bookmark me-2" style="color:var(--accent)"></i>Identitas Template</span>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Judul Template <span class="text-danger">*</span></label>
                    <input type="text" id="tpl-name" class="form-control" placeholder="cth: Standar pH Meter + Flow" value="{{ $template?->name }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Deskripsi</label>
                    <textarea id="tpl-description" class="form-control" rows="2" placeholder="Deskripsi (opsional)">{{ $template?->description }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card-custom fade-in mb-3">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>List Part Instrument</span>
            <div class="d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-primary btn-sm" onclick="openProductPickerAsParent()">
                    <i class="fa fa-plus me-1"></i> Tambah Item
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addItemRow({}, null)">
                    <i class="fa fa-plus me-1"></i> Tambah Baris Manual
                </button>
            </div>
        </div>
        <div class="card-body-custom p-2">
            @include($editorView, [
                'items' => $items,
                'searchUrl' => $searchUrl,
            ])
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ $indexUrl }}" class="btn btn-secondary">Batal</a>
        <button type="button" class="btn-accent" id="btn-save-tpl">
            <i class="fa fa-save me-1"></i> Simpan Template
        </button>
    </div>
</form>

@endsection

@section('scripts')
<script>
const tplStoreUrl = '{{ $storeUrl }}';
const tplUpdateUrl = '{{ $updateUrl }}';
const tplIndexUrl = '{{ $indexUrl }}';

$('#btn-save-tpl').on('click', function() {
    var name = $('#tpl-name').val().trim();
    if (!name) {
        toastr.error('Judul template wajib diisi.');
        return;
    }

    var items = collectItems();
    if (!items) {
        toastr.error('Deskripsi item wajib diisi.');
        return;
    }

    var payload = {
        name: name,
        description: $('#tpl-description').val(),
        items: items
    };

    var id = $('#tpl-edit-id').val();
    var isEdit = !!id;
    var url = isEdit ? tplUpdateUrl.replace('__ID__', id) : tplStoreUrl;
    var method = isEdit ? 'PUT' : 'POST';

    $('#btn-save-tpl').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...');

    $.ajax({
        url: url,
        method: method,
        data: JSON.stringify(payload),
        contentType: 'application/json',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(function(res) {
        toastr.success(res.message || 'Template berhasil disimpan.');
        setTimeout(function() { window.location.href = tplIndexUrl; }, 600);
    }).fail(function(xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Terjadi kesalahan saat menyimpan.';
        toastr.error(msg);
    }).always(function() {
        $('#btn-save-tpl').prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan Template');
    });
});
</script>
@endsection