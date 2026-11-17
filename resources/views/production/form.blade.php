@extends('layouts.app')

@section('title', 'Catat Produksi')
@section('page-title', 'Catat Produksi')

@section('styles')
<style>
    .mo-pool td, .mo-pool th { font-size: 12.5px; vertical-align: middle; }
    .mo-pool .mo-qty { width: 96px; }
    .mo-sn { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; padding: 1px 6px; border-radius: 5px; background: #f1f5f9; color: var(--text-secondary); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Catat Produksi</h1>
        <p class="page-header-sub">Pilih material dari Material di Produksi project, lalu tentukan barang jadi dan gudangnya.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('production.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-diagram-project me-2" style="color:var(--accent)"></i>Project &amp; Branch</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <label class="form-label" for="mo-group">Material di Produksi <span class="text-danger">*</span></label>
                        <select id="mo-group" class="form-select">
                            <option value="">— Pilih Project &amp; Branch —</option>
                            @foreach($groups as $g)
                                <option value="{{ $g['project_id'] }}-{{ $g['branch_id'] }}" data-project="{{ $g['project_id'] }}" data-branch="{{ $g['branch_id'] }}">{{ $g['label'] }} ({{ $g['lines'] }} baris)</option>
                            @endforeach
                        </select>
                        @if($groups->isEmpty())
                            <small style="color:var(--text-muted)">Belum ada Material di Produksi. Material masuk lewat Serah Terima Material.</small>
                        @endif
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="mo-date">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" id="mo-date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i>Material Dipakai</span></div>
            <div class="card-body-custom p-2">
                <div id="mo-pool"><div class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih project &amp; branch terlebih dahulu.</div></div>
                <div class="px-2 pt-2" style="font-size:12px;color:var(--text-muted)"><i class="fa fa-circle-info me-1"></i>Material boleh dipakai sebagian; sisanya bisa dipakai produksi berikutnya. Barang ber-SN dipakai per SN.</div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom"><span><i class="fa-solid fa-box me-2" style="color:var(--accent)"></i>Barang Jadi</span></div>
            <div class="card-body-custom">
                <div class="d-flex gap-3 mb-3" style="font-size:13px">
                    <label class="form-check"><input type="radio" class="form-check-input" name="mo-mode" value="existing" checked> Produk yang sudah ada</label>
                    <label class="form-check"><input type="radio" class="form-check-input" name="mo-mode" value="new"> Buat produk baru</label>
                </div>
                <div class="row g-3">
                    <div class="col-lg-8 mo-existing">
                        <label class="form-label" for="mo-product">Produk <span class="text-danger">*</span></label>
                        <select id="mo-product" class="form-select" style="width:100%"></select>
                        <div id="mo-product-warn" class="mt-1" style="font-size:12px;color:#b45309;display:none"><i class="fa fa-triangle-exclamation me-1"></i>Produk ini Barang ber-SN. Barang jadi tidak ber-SN — ubah ke Material di Master Produk dulu, atau buat produk baru.</div>
                    </div>
                    <div class="col-lg-3 mo-new" style="display:none">
                        <label class="form-label" for="mo-new-code">Kode <span class="text-danger">*</span></label>
                        <input type="text" id="mo-new-code" class="form-control" maxlength="50">
                    </div>
                    <div class="col-lg-5 mo-new" style="display:none">
                        <label class="form-label" for="mo-new-name">Nama <span class="text-danger">*</span></label>
                        <input type="text" id="mo-new-name" class="form-control" maxlength="150">
                    </div>
                    <div class="col-lg-4 mo-new" style="display:none">
                        <label class="form-label" for="mo-new-unit">Satuan</label>
                        <input type="text" id="mo-new-unit" class="form-control" maxlength="50" list="mo-units">
                        <datalist id="mo-units">@foreach($units as $u)<option value="{{ $u }}">@endforeach</datalist>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="mo-qty">Qty Barang Jadi <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" inputmode="decimal" id="mo-qty" class="form-control text-end" value="1">
                            <span class="input-group-text" id="mo-unit">—</span>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="mo-warehouse">Gudang Barang Jadi <span class="text-danger">*</span></label>
                        <select id="mo-warehouse" class="form-select"><option value="">— Pilih project dulu —</option></select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="mo-note">Catatan</label>
                        <input type="text" id="mo-note" class="form-control" maxlength="2000" placeholder="Opsional">
                    </div>
                </div>
                <div class="mt-2" style="font-size:12px;color:var(--text-muted)">Produk baru dibuat di master produk sebagai Material tanpa harga. Barang jadi tercatat sebagai stok milik project.</div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in" style="position:sticky;top:80px">
            <div class="card-header-custom"><span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Material</td><td class="text-end fw-semibold" id="mo-sum-lines">0</td></tr>
                    <tr><td>Total biaya</td><td class="text-end fw-semibold" id="mo-sum-cost">0</td></tr>
                    <tr><td>Harga per unit</td><td class="text-end fw-semibold" id="mo-sum-unit">0</td></tr>
                </table>
                <button type="button" class="btn btn-primary w-100 mt-3" id="mo-save" onclick="moSave()"><i class="fa fa-save me-1"></i> Simpan Produksi</button>
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Biaya dari harga modal material saat diserahkan. Produksi bisa dibatalkan selama barang jadinya belum dipakai.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const moPoolUrl = '{{ route("production.pool") }}';
const moShowUrl = '{{ route("production.show", "__ID__") }}';
const moSaveLabel = $('#mo-save').html();
let moSerializedProduct = false;

function moEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function moMode() { return $('input[name="mo-mode"]:checked').val(); }

function moPicks() {
    var out = [];
    $('#mo-pool tr[data-id]').each(function() {
        if (!$(this).find('.mo-check').is(':checked')) return;
        var qty = Num.parseOrNull($(this).find('.mo-qty').val()) || 0;
        if (qty > 0) out.push({ material_handover_item_id: $(this).data('id'), qty: qty, cost: qty * parseFloat($(this).data('cost')) });
    });
    return out;
}

function moSummary() {
    var picks = moPicks();
    var total = picks.reduce(function(s, p) { return s + p.cost; }, 0);
    var qty = Num.parseOrNull($('#mo-qty').val()) || 0;
    $('#mo-sum-lines').text(picks.length);
    $('#mo-sum-cost').text(Num.format(total, 0));
    $('#mo-sum-unit').text(qty > 0 ? Num.format(total / qty, 2) : '—');
}

function moLoadPool() {
    var $opt = $('#mo-group option:selected');
    if (!$opt.val()) {
        $('#mo-pool').html('<div class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih project &amp; branch terlebih dahulu.</div>');
        $('#mo-warehouse').html('<option value="">— Pilih project dulu —</option>');
        moSummary();
        return;
    }
    $('#mo-pool').html('<div class="text-center py-3" style="color:var(--text-muted)"><span class="spinner-border spinner-border-sm me-1"></span>Memuat material...</div>');
    $.get(moPoolUrl, { project_id: $opt.data('project'), branch_id: $opt.data('branch') }).done(function(res) {
        var wh = '<option value="">— Pilih Gudang —</option>';
        (res.warehouses || []).forEach(function(w) { wh += '<option value="' + w.id + '">' + moEsc(w.code + ' — ' + w.name) + '</option>'; });
        $('#mo-warehouse').html(wh);

        var rows = res.data || [];
        if (!rows.length) { $('#mo-pool').html('<div class="text-center py-3" style="color:var(--text-muted)">Tidak ada sisa material.</div>'); moSummary(); return; }
        var html = '<div class="table-responsive" style="max-height:380px;overflow:auto"><table class="table table-sm mo-pool mb-0"><thead><tr>'
            + '<th style="width:30px"></th><th>Material</th><th>Serah Terima</th><th class="text-end">Sisa</th><th class="text-end">Pakai</th></tr></thead><tbody>';
        rows.forEach(function(r) {
            html += '<tr data-id="' + r.id + '" data-max="' + r.remaining + '" data-cost="' + r.unit_cost + '">';
            html += '<td><input type="checkbox" class="form-check-input mo-check"></td>';
            html += '<td><div class="fw-semibold">' + moEsc(r.product_code) + ' <span style="font-weight:400;color:var(--text-muted)">' + moEsc(r.product_name) + '</span></div>'
                + (r.serialized ? '<span class="mo-sn">' + moEsc(r.identity) + '</span>' : '<span style="color:var(--text-muted)">' + moEsc(r.identity) + '</span>') + '</td>';
            html += '<td style="color:var(--text-muted)">' + moEsc(r.handover_number) + '</td>';
            html += '<td class="text-end">' + moEsc(r.remaining_label) + ' ' + moEsc(r.unit || '') + '</td>';
            html += '<td class="text-end">' + (r.serialized
                ? '<span style="color:var(--text-muted)">1 unit</span><input type="hidden" class="mo-qty" value="' + r.remaining + '">'
                : '<input type="text" inputmode="decimal" class="form-control form-control-sm text-end mo-qty d-inline-block" value="' + r.remaining + '">') + '</td>';
            html += '</tr>';
        });
        $('#mo-pool').html(html + '</tbody></table></div>');
        moSummary();
    }).fail(function(xhr) {
        $('#mo-pool').html('<div class="text-danger py-2" style="font-size:13px">' + moEsc(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat material.') + '</div>');
    });
}

$(document).on('change', '#mo-group', moLoadPool);
$(document).on('change', '.mo-check', moSummary);
$(document).on('input', '.mo-qty, #mo-qty', function() {
    this.value = this.value.replace(/[^\d.]/g, '');
    var $tr = $(this).closest('tr[data-id]');
    if ($tr.length) {
        var max = parseFloat($tr.data('max')) || 0;
        if ((Num.parseOrNull(this.value) || 0) > max) this.value = max;
        $tr.find('.mo-check').prop('checked', (Num.parseOrNull(this.value) || 0) > 0);
    }
    moSummary();
});
$(document).on('change', 'input[name="mo-mode"]', function() {
    var isNew = moMode() === 'new';
    $('.mo-new').toggle(isNew);
    $('.mo-existing').toggle(!isNew);
    $('#mo-unit').text(isNew ? ($('#mo-new-unit').val() || '—') : ($('#mo-product').data('unit') || '—'));
});
$(document).on('input', '#mo-new-unit', function() { $('#mo-unit').text(this.value || '—'); });

function moSave() {
    var $opt = $('#mo-group option:selected');
    if (!$opt.val()) { toastr.error('Pilih project & branch.'); return; }
    var picks = moPicks();
    if (!picks.length) { toastr.error('Pilih minimal satu material yang dipakai.'); return; }
    var payload = {
        project_id: $opt.data('project'),
        branch_id: $opt.data('branch'),
        production_date: $('#mo-date').val(),
        note: $('#mo-note').val(),
        output_qty: Num.parseOrNull($('#mo-qty').val()),
        output_warehouse_id: $('#mo-warehouse').val(),
        items: picks.map(function(p) { return { material_handover_item_id: p.material_handover_item_id, qty: p.qty }; })
    };
    if (moMode() === 'new') {
        if (!$('#mo-new-code').val() || !$('#mo-new-name').val()) { toastr.error('Isi kode dan nama produk baru.'); return; }
        payload.new_product = { code: $('#mo-new-code').val(), name: $('#mo-new-name').val(), unit: $('#mo-new-unit').val() };
    } else {
        if (!$('#mo-product').val()) { toastr.error('Pilih produk barang jadi.'); return; }
        if (moSerializedProduct) { toastr.error('Produk Barang ber-SN tidak bisa menjadi barang jadi.'); return; }
        payload.output_product_id = $('#mo-product').val();
    }
    if (!payload.output_qty || payload.output_qty <= 0) { toastr.error('Isi qty barang jadi.'); return; }
    if (!payload.output_warehouse_id) { toastr.error('Pilih gudang barang jadi.'); return; }

    $('#mo-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: '{{ route("production.store") }}', method: 'POST', contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        data: JSON.stringify(payload)
    }).done(function(res) {
        toastr.success(res.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = moShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        $('#mo-save').prop('disabled', false).html(moSaveLabel);
    });
}

$(document).ready(function() {
    $('#mo-group').select2({ theme: 'bootstrap-5', width: '100%' });
    $('#mo-product').select2({
        theme: 'bootstrap-5',
        placeholder: 'Cari kode / nama produk',
        ajax: { url: '{{ route("production.search-products") }}', dataType: 'json', delay: 250, data: function(p) { return { q: p.term || '' }; } }
    }).on('select2:select', function(e) {
        var d = e.params.data;
        moSerializedProduct = !d.is_material;
        $(this).data('unit', d.unit);
        $('#mo-unit').text(d.unit || '—');
        $('#mo-product-warn').toggle(moSerializedProduct);
    });
});
</script>
@endsection
