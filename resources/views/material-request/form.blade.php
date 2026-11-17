@extends('layouts.app')

@php
    $isEdit = (bool) $mr;
    $initialItems = $mr
        ? $mr->items->map(fn ($item) => [
            'master_product_id' => $item->master_product_id,
            'text' => ($item->masterProduct?->code ?? '').' — '.($item->masterProduct?->name ?? ''),
            'unit' => $item->masterProduct?->unit ?? $item->unit,
            'is_material' => (bool) $item->masterProduct?->is_material,
            'qty' => $item->qty,
            'note' => $item->note,
        ])->values()
        : collect();
@endphp

@section('title', $isEdit ? 'Ubah Material Request' : 'Buat Material Request')
@section('page-title', $isEdit ? 'Ubah Material Request' : 'Buat Material Request')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Ubah '.$mr->request_number : 'Buat Material Request' }}</h1>
        <p class="page-header-sub">
            @if($isEdit && $mr->status === \App\Models\MaterialRequest::STATUS_REJECTED)
                Perbaiki lalu simpan — Material Request akan diajukan ulang untuk approval.
            @else
                Material yang dibutuhkan project untuk produksi. Disimpan langsung diajukan untuk approval.
            @endif
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $isEdit ? route('material-request.show', $mr->id) : route('material-request.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

@if($isEdit && $mr->reject_reason)
<div class="alert alert-danger py-2" style="font-size:13px"><i class="fa fa-circle-xmark me-1"></i>Ditolak: {{ $mr->reject_reason }}</div>
@endif

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-diagram-project me-2" style="color:var(--accent)"></i>Project &amp; Branch</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="mr-project">Project <span class="text-danger">*</span></label>
                        <select id="mr-project" class="form-select">
                            <option value="">— Pilih Project —</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" @selected(($mr->project_id ?? null) === $p->id)>{{ $p->project_number }} — {{ $p->project_name }}</option>
                            @endforeach
                        </select>
                        <small style="color:var(--text-muted)">Hanya project aktif.</small>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="mr-branch">Branch <span class="text-danger">*</span></label>
                        <select id="mr-branch" class="form-select">
                            <option value="">— Pilih Branch —</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" @selected($defaultBranchId === $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                        <small style="color:var(--text-muted)">Tempat produksi.</small>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="mr-date">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" id="mr-date" class="form-control" value="{{ $mr?->request_date?->toDateString() ?? now()->toDateString() }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="mr-note">Catatan</label>
                        <input type="text" id="mr-note" class="form-control" maxlength="2000" value="{{ $mr->note ?? '' }}" placeholder="Opsional">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i>Material</span>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="mrAddRow()"><i class="fa fa-plus me-1"></i>Tambah</button>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:40px" class="text-center">#</th>
                                <th>Produk</th>
                                <th style="width:140px" class="text-end">Qty</th>
                                <th style="width:90px">Satuan</th>
                                <th style="width:220px">Catatan</th>
                                <th style="width:44px"></th>
                            </tr>
                        </thead>
                        <tbody id="mr-rows"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in" style="position:sticky;top:80px">
            <div class="card-header-custom"><span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Material</td><td class="text-end fw-semibold" id="mr-sum-lines">0</td></tr>
                </table>
                <button type="button" class="btn btn-primary w-100 mt-3" id="mr-save" onclick="mrSave()">
                    <i class="fa fa-paper-plane me-1"></i> {{ $isEdit ? 'Simpan & Ajukan' : 'Ajukan' }}
                </button>
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Satuan mengikuti master produk. Setelah disetujui, gudang menyerahkan material lewat Serah Terima Material.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const mrSearchUrl = '{{ route("material-request.search-products") }}';
const mrSaveUrl = '{{ $isEdit ? route("material-request.update", $mr->id) : route("material-request.store") }}';
const mrSaveMethod = '{{ $isEdit ? "PUT" : "POST" }}';
const mrShowUrl = '{{ route("material-request.show", "__ID__") }}';
const mrSaveLabel = $('#mr-save').html();
const mrInitial = @json($initialItems);

function mrEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function mrRenumber() {
    $('#mr-rows tr').each(function(i) { $(this).find('.mr-no').text(i + 1); });
    $('#mr-sum-lines').text($('#mr-rows tr').length);
}

function mrAddRow(data) {
    data = data || {};
    var $tr = $('<tr>'
        + '<td class="text-center mr-no" style="color:var(--text-muted)"></td>'
        + '<td><select class="form-select form-select-sm mr-product" style="width:100%"></select><div class="mr-kind" style="font-size:11px;color:var(--text-muted)"></div></td>'
        + '<td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end mr-qty" value="' + (data.qty != null ? data.qty : '') + '"></td>'
        + '<td class="mr-unit" style="color:var(--text-secondary)">' + mrEsc(data.unit || '—') + '</td>'
        + '<td><input type="text" class="form-control form-control-sm mr-item-note" maxlength="255" value="' + mrEsc(data.note || '') + '"></td>'
        + '<td class="text-center"><button type="button" class="btn-icon danger" title="Hapus" onclick="$(this).closest(\'tr\').remove(); mrRenumber();"><i class="fa fa-trash"></i></button></td>'
        + '</tr>');
    $('#mr-rows').append($tr);

    var $select = $tr.find('.mr-product');
    if (data.master_product_id) {
        $select.append(new Option(data.text, data.master_product_id, true, true));
        $tr.find('.mr-kind').text(data.is_material ? 'Material' : 'Barang ber-SN — qty bilangan bulat');
    }
    $select.select2({
        theme: 'bootstrap-5',
        placeholder: 'Cari kode / nama produk',
        minimumInputLength: 0,
        ajax: { url: mrSearchUrl, dataType: 'json', delay: 250, data: function(p) { return { q: p.term || '' }; } }
    }).on('select2:select', function(e) {
        var d = e.params.data;
        $tr.find('.mr-unit').text(d.unit || '—');
        $tr.find('.mr-kind').text(d.is_material ? 'Material' : 'Barang ber-SN — qty bilangan bulat');
        $tr.find('.mr-qty').trigger('focus');
    });
    mrRenumber();
}

$(document).on('input', '.mr-qty', function() { this.value = this.value.replace(/[^\d.]/g, ''); });

function mrSave() {
    if (!$('#mr-project').val()) { toastr.error('Pilih project.'); return; }
    if (!$('#mr-branch').val()) { toastr.error('Pilih branch.'); return; }
    var items = [], invalid = false;
    $('#mr-rows tr').each(function() {
        var pid = $(this).find('.mr-product').val();
        var qty = Num.parseOrNull($(this).find('.mr-qty').val());
        if (!pid && !qty) return;
        if (!pid || !qty || qty <= 0) { invalid = true; return; }
        items.push({ master_product_id: pid, qty: qty, note: $(this).find('.mr-item-note').val() });
    });
    if (invalid) { toastr.error('Lengkapi produk dan qty setiap baris.'); return; }
    if (!items.length) { toastr.error('Tambahkan minimal satu material.'); return; }

    $('#mr-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: mrSaveUrl, method: mrSaveMethod, contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        data: JSON.stringify({
            project_id: $('#mr-project').val(),
            branch_id: $('#mr-branch').val(),
            request_date: $('#mr-date').val(),
            note: $('#mr-note').val(),
            items: items
        })
    }).done(function(res) {
        toastr.success(res.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = mrShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        $('#mr-save').prop('disabled', false).html(mrSaveLabel);
    });
}

$(document).ready(function() {
    $('#mr-project').select2({ theme: 'bootstrap-5', width: '100%' });
    if (mrInitial.length) mrInitial.forEach(mrAddRow); else mrAddRow();
});
</script>
@endsection
