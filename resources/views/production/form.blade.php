@extends('layouts.app')

@section('title', 'Catat Produksi')
@section('page-title', 'Catat Produksi')

@section('styles')
<style>
    .mo-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 4px 8px 10px; }
    .mo-toolbar .mo-search { flex: 1 1 220px; max-width: 320px; }
    .mo-toolbar select { width: auto; max-width: 220px; font-size: 12.5px; }
    .mo-toolbar .mo-count { font-size: 12.5px; color: var(--text-muted); margin-left: auto; white-space: nowrap; }
    @media (max-width: 991.98px) { .mo-toolbar select, .mo-toolbar .mo-search { max-width: none; flex: 1 1 45%; } .mo-toolbar .mo-count { margin-left: 0; } }
    .mo-pool td, .mo-pool th { font-size: 12.5px; vertical-align: middle; }
    .mo-pool .mo-qty { width: 96px; }
    .mo-pool .mo-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .mo-sn { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; padding: 1px 6px; border-radius: 5px; background: #f1f5f9; color: var(--text-secondary); }
    .mo-kind { display: inline-block; font-size: 10.5px; font-weight: 600; padding: 0 7px; border-radius: 999px; }
    .mo-kind.material { background: #e0f2fe; color: #075985; }
    .mo-kind.serial { background: #fef3c7; color: #92400e; }
    /* Skin pemilih: klik baris untuk memilih; hover & terpilih seperti picker lain. */
    .mo-pool tbody tr[data-id] { cursor: pointer; user-select: none; }
    .mo-pool tbody tr[data-id] td { transition: background-color .12s ease; }
    .mo-pool tbody tr[data-id]:hover td { background-color: #d1fae5 !important; color: #065f46; }
    .mo-pool tbody tr.is-selected td { background-color: #ecfdf5 !important; }
    .mo-pool .mo-check, #mo-check-all { width: 16px; height: 16px; cursor: pointer; vertical-align: middle; }
    .mo-pool .mo-qty { cursor: text; user-select: text; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Catat Produksi</h1>
        <p class="page-header-sub">Pilih branch dan material di produksi, tentukan barang jadi beserta project-nya, lalu simpan. Barang jadi masuk ke gudang sebagai stok milik project.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('production.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span>1. Branch</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <label class="form-label" for="mo-branch">Branch <span class="text-danger">*</span></label>
                        <select id="mo-branch" class="form-select">
                            <option value="">— Pilih Branch —</option>
                            @foreach($groups as $g)
                                <option value="{{ $g['branch_id'] }}">{{ $g['label'] }} ({{ $g['lines'] }} baris)</option>
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
            <div class="card-header-custom"><span>2. Material Dipakai</span></div>
            <div class="card-body-custom p-2">
                <div class="mo-toolbar">
                    <input type="search" id="mo-filter" class="form-control form-control-sm mo-search" placeholder="Cari kode, nama, SN, serah terima…" autocomplete="off" aria-label="Cari material">
                    <select id="mo-f-project" class="form-select form-select-sm" aria-label="Project asal"><option value="">Semua project asal</option></select>
                    <select id="mo-f-kind" class="form-select form-select-sm" aria-label="Jenis barang">
                        <option value="">Semua jenis</option>
                        <option value="material">Material</option>
                        <option value="serial">Barang ber-SN</option>
                    </select>
                    <select id="mo-f-handover" class="form-select form-select-sm" aria-label="Serah terima"><option value="">Semua serah terima</option></select>
                    <select id="mo-f-picked" class="form-select form-select-sm" aria-label="Tampilan">
                        <option value="">Semua baris</option>
                        <option value="1">Hanya yang dipilih</option>
                    </select>
                    <span class="mo-count"><span id="mo-count">0</span> dipilih <button type="button" class="btn btn-link btn-sm p-0 ms-1" id="mo-clear" style="display:none">Hapus pilihan</button></span>
                </div>
                <div id="mo-pool"><div class="text-center py-4" style="color:var(--text-muted)">Pilih branch terlebih dahulu.</div></div>
                <div class="px-2 pt-2" style="font-size:12px;color:var(--text-muted)">Klik baris untuk memilih, Shift+klik untuk rentang. Pilihan tetap tersimpan saat mencari atau mengganti filter. Material boleh dipakai sebagian; barang ber-SN dipakai per SN. Material project lain bisa dipakai, biayanya dialihkan ke project barang jadi.</div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom"><span>3. Barang Jadi</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="mo-project-target">Project Barang Jadi <span class="text-danger">*</span></label>
                        <select id="mo-project-target" class="form-select">
                            <option value="">— Pilih branch dulu —</option>
                        </select>
                        <small style="color:var(--text-muted)">Hanya project yang mengajukan Material Request di branch ini.</small>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="mo-warehouse">Gudang Barang Jadi <span class="text-danger">*</span></label>
                        <select id="mo-warehouse" class="form-select"><option value="">— Pilih branch dulu —</option></select>
                    </div>
                    <div class="col-12">
                        <div class="d-flex gap-3" style="font-size:13px">
                            <label class="form-check mb-0"><input type="radio" class="form-check-input" name="mo-mode" value="existing" checked> Produk yang sudah ada</label>
                            <label class="form-check mb-0"><input type="radio" class="form-check-input" name="mo-mode" value="new"> Buat produk baru</label>
                        </div>
                    </div>
                    <div class="col-lg-8 mo-existing">
                        <label class="form-label" for="mo-product">Produk Barang Jadi <span class="text-danger">*</span></label>
                        <select id="mo-product" class="form-select" style="width:100%"></select>
                        <div id="mo-product-warn" class="mt-1" style="font-size:12px;color:#b45309;display:none">Produk ini Barang ber-SN. Barang jadi tidak ber-SN — ubah ke Material di Master Produk dulu, atau buat produk baru.</div>
                    </div>
                    <div class="col-lg-3 mo-new" style="display:none">
                        <label class="form-label" for="mo-new-code">Kode <span class="text-danger">*</span></label>
                        <input type="text" id="mo-new-code" class="form-control" maxlength="50">
                    </div>
                    <div class="col-lg-5 mo-new" style="display:none">
                        <label class="form-label" for="mo-new-name">Nama <span class="text-danger">*</span></label>
                        <input type="text" id="mo-new-name" class="form-control" maxlength="150">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="mo-qty">Qty Barang Jadi <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" inputmode="decimal" id="mo-qty" class="form-control text-end" value="1">
                            <span class="input-group-text" id="mo-unit">—</span>
                        </div>
                    </div>
                    <div class="col-lg-4 mo-new" style="display:none">
                        <label class="form-label" for="mo-new-unit">Satuan</label>
                        <input type="text" id="mo-new-unit" class="form-control" maxlength="50" list="mo-units">
                        <datalist id="mo-units">@foreach($units as $u)<option value="{{ $u }}">@endforeach</datalist>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="mo-note">Catatan</label>
                        <input type="text" id="mo-note" class="form-control" maxlength="2000" placeholder="Opsional">
                    </div>
                </div>
                <div class="mt-2" style="font-size:12px;color:var(--text-muted)">Produk baru dibuat di master produk sebagai Material tanpa harga.</div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in" style="position:sticky;top:80px">
            <div class="card-header-custom"><span>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Material</td><td class="text-end fw-semibold" id="mo-sum-lines">0</td></tr>
                    <tr><td>Total biaya</td><td class="text-end fw-semibold" id="mo-sum-cost">0</td></tr>
                    <tr><td>Harga per unit</td><td class="text-end fw-semibold" id="mo-sum-unit">0</td></tr>
                </table>
                <button type="button" class="btn btn-primary w-100 mt-3" id="mo-save" onclick="moSave()">Simpan Produksi</button>
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
let moRows = [];
let moLastIdx = null;

function moEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function moMode() { return $('input[name="mo-mode"]:checked').val(); }
function moRow(id) { return moRows.find(function(r) { return r.id === id; }); }

// Pilihan disimpan di moRows (bukan di DOM), jadi tidak hilang saat mencari / filter.
function moPicks() {
    return moRows.filter(function(r) { return r.checked && r.qty > 0; })
        .map(function(r) { return { material_handover_item_id: r.id, qty: r.qty, cost: r.qty * r.unit_cost }; });
}

function moSummary() {
    var picks = moPicks();
    var total = picks.reduce(function(s, p) { return s + p.cost; }, 0);
    var qty = Num.parseOrNull($('#mo-qty').val()) || 0;
    var selected = moRows.filter(function(r) { return r.checked; }).length;
    $('#mo-sum-lines').text(picks.length);
    $('#mo-sum-cost').text(Num.format(total, 0));
    $('#mo-sum-unit').text(qty > 0 ? Num.format(total / qty, 2) : '—');
    $('#mo-count').text(selected);
    $('#mo-clear').toggle(selected > 0);
}

function moFillOptions($select, label, values) {
    var current = $select.val();
    var html = '<option value="">' + label + '</option>';
    values.forEach(function(v) { html += '<option value="' + moEsc(v[0]) + '">' + moEsc(v[1]) + '</option>'; });
    $select.html(html).val(current && values.some(function(v) { return String(v[0]) === current; }) ? current : '');
}

function moVisibleRows() {
    var q = ($('#mo-filter').val() || '').trim().toLowerCase();
    var project = $('#mo-f-project').val(), kind = $('#mo-f-kind').val(), handover = $('#mo-f-handover').val(), picked = $('#mo-f-picked').val();
    return moRows.filter(function(r) {
        if (project && String(r.project_id) !== project) return false;
        if (kind && (kind === 'serial') !== !!r.serialized) return false;
        if (handover && r.handover_number !== handover) return false;
        if (picked && !r.checked) return false;
        if (!q) return true;
        return [r.product_code, r.product_name, r.identity, r.handover_number, r.project_label].some(function(v) {
            return (v || '').toString().toLowerCase().indexOf(q) !== -1;
        });
    });
}

function moRenderPool() {
    if (!moRows.length) return;
    var rows = moVisibleRows();
    if (!rows.length) {
        $('#mo-pool').html('<div class="text-center py-3" style="color:var(--text-muted)">Tidak ada material yang cocok dengan pencarian / filter.</div>');
        return;
    }

    var html = '<div class="table-responsive" style="max-height:380px;overflow:auto"><table class="table table-sm mo-pool mb-0"><thead><tr>'
        + '<th style="width:34px" class="text-center"><input type="checkbox" id="mo-check-all" aria-label="Pilih semua yang tampil"></th>'
        + '<th>Material</th><th>Serah Terima</th><th>Project Asal</th><th class="mo-num">Sisa</th><th class="mo-num">Pakai</th></tr></thead><tbody>';
    rows.forEach(function(r) {
        html += '<tr data-id="' + r.id + '"' + (r.checked ? ' class="is-selected"' : '') + '>';
        html += '<td class="text-center"><input type="checkbox" class="form-check-input mo-check" aria-label="Pilih ' + moEsc(r.product_code) + '"' + (r.checked ? ' checked' : '') + '></td>';
        html += '<td><div class="fw-semibold">' + moEsc(r.product_code) + ' <span style="font-weight:400;color:var(--text-muted)">' + moEsc(r.product_name) + '</span></div>'
            + '<span class="mo-kind ' + (r.serialized ? 'serial' : 'material') + '">' + (r.serialized ? 'Ber-SN' : 'Material') + '</span> '
            + (r.serialized ? '<span class="mo-sn">' + moEsc(r.identity) + '</span>' : '') + '</td>';
        html += '<td style="color:var(--text-muted)">' + moEsc(r.handover_number) + '</td>';
        html += '<td style="color:var(--text-muted);font-size:12px">' + moEsc(r.project_label) + '</td>';
        html += '<td class="mo-num">' + moEsc(r.remaining_label) + ' <span style="color:var(--text-muted)">' + moEsc(r.unit || '') + '</span></td>';
        html += '<td class="mo-num">' + (r.serialized
            ? '<span style="color:var(--text-muted)">1 unit</span>'
            : '<input type="text" inputmode="decimal" class="form-control form-control-sm text-end mo-qty d-inline-block" aria-label="Qty pakai ' + moEsc(r.product_code) + '" value="' + r.qty + '">') + '</td>';
        html += '</tr>';
    });
    $('#mo-pool').html(html + '</tbody></table></div>');
    moSyncCheckAll();
}

function moSyncCheckAll() {
    var rows = moVisibleRows(), on = rows.filter(function(r) { return r.checked; }).length;
    $('#mo-check-all').prop('checked', rows.length > 0 && on === rows.length).prop('indeterminate', on > 0 && on < rows.length);
}

function moSetChecked(r, checked) {
    r.checked = checked;
    if (checked && !(r.qty > 0)) r.qty = r.remaining;
    $('#mo-pool tr[data-id="' + r.id + '"]').toggleClass('is-selected', checked).find('.mo-check').prop('checked', checked);
}

function moLoadPool() {
    var branchId = $('#mo-branch').val();
    moRows = [];
    moLastIdx = null;
    $('#mo-filter').val('');
    $('#mo-f-project, #mo-f-kind, #mo-f-handover, #mo-f-picked').val('');
    if (!branchId) {
        $('#mo-pool').html('<div class="text-center py-4" style="color:var(--text-muted)">Pilih branch terlebih dahulu.</div>');
        $('#mo-warehouse').html('<option value="">— Pilih branch dulu —</option>');
        $('#mo-project-target').html('<option value="">— Pilih branch dulu —</option>');
        moSummary();
        return;
    }
    $('#mo-pool').html('<div class="text-center py-3" style="color:var(--text-muted)"><span class="spinner-border spinner-border-sm me-1"></span>Memuat material...</div>');
    $.get(moPoolUrl, { branch_id: branchId }).done(function(res) {
        var wh = '<option value="">— Pilih Gudang —</option>';
        (res.warehouses || []).forEach(function(w) { wh += '<option value="' + w.id + '">' + moEsc(w.code + ' — ' + w.name) + '</option>'; });
        $('#mo-warehouse').html(wh);

        var ph = '<option value="">— Pilih Project —</option>';
        (res.projects || []).forEach(function(p) { ph += '<option value="' + p.id + '">' + moEsc(p.label) + '</option>'; });
        $('#mo-project-target').html(ph);

        moRows = (res.data || []).map(function(r) { r.qty = r.remaining; r.checked = false; return r; });

        var projects = {}, handovers = {};
        moRows.forEach(function(r) { if (r.project_id) projects[r.project_id] = r.project_label; if (r.handover_number) handovers[r.handover_number] = r.handover_number; });
        moFillOptions($('#mo-f-project'), 'Semua project asal', Object.keys(projects).map(function(k) { return [k, projects[k]]; }).sort(function(a, b) { return a[1].localeCompare(b[1]); }));
        moFillOptions($('#mo-f-handover'), 'Semua serah terima', Object.keys(handovers).sort().map(function(k) { return [k, k]; }));

        if (!moRows.length) {
            $('#mo-pool').html('<div class="text-center py-3" style="color:var(--text-muted)">Tidak ada sisa material di branch ini.</div>');
        } else {
            moRenderPool();
        }
        moSummary();
    }).fail(function(xhr) {
        $('#mo-pool').html('<div class="text-danger py-2" style="font-size:13px">' + moEsc(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat material.') + '</div>');
    });
}

$(document).on('change', '#mo-branch', moLoadPool);
$(document).on('input', '#mo-filter', function() { moLastIdx = null; moRenderPool(); });
$(document).on('change', '#mo-f-project, #mo-f-kind, #mo-f-handover, #mo-f-picked', function() { moLastIdx = null; moRenderPool(); });

// Klik baris = pilih / batal; Shift+klik = rentang. Kolom qty tetap bisa diketik.
$(document).on('click', '.mo-pool tbody tr[data-id]', function(e) {
    if ($(e.target).is('.mo-qty')) return;
    var r = moRow($(this).data('id'));
    if (!r) return;
    var onBox = $(e.target).is('.mo-check');
    var checked = onBox ? $(e.target).prop('checked') : !r.checked;
    var $rows = $('.mo-pool tbody tr[data-id]');
    var idx = $rows.index(this);
    if (e.shiftKey && moLastIdx !== null && moLastIdx !== idx) {
        $rows.slice(Math.min(moLastIdx, idx), Math.max(moLastIdx, idx) + 1).each(function() { moSetChecked(moRow($(this).data('id')), checked); });
        window.getSelection && window.getSelection().removeAllRanges();
    } else {
        moSetChecked(r, checked);
    }
    moLastIdx = idx;
    moSyncCheckAll();
    moSummary();
    if ($('#mo-f-picked').val() && !checked) moRenderPool();
});
$(document).on('change', '#mo-check-all', function() {
    var checked = this.checked;
    moVisibleRows().forEach(function(r) { moSetChecked(r, checked); });
    moLastIdx = null;
    moRenderPool();
    moSummary();
});
$(document).on('click', '#mo-clear', function() {
    moRows.forEach(function(r) { r.checked = false; });
    moRenderPool();
    moSummary();
});
$(document).on('input', '.mo-qty', function() {
    this.value = this.value.replace(/[^\d.]/g, '');
    var r = moRow($(this).closest('tr[data-id]').data('id'));
    if (!r) return;
    var val = Num.parseOrNull(this.value) || 0;
    if (val > r.remaining) { val = r.remaining; this.value = r.remaining; }
    r.qty = val;
    moSetChecked(r, val > 0);
    moSyncCheckAll();
    moSummary();
});
$(document).on('input', '#mo-qty', function() { this.value = this.value.replace(/[^\d.]/g, ''); moSummary(); });
$(document).on('change', 'input[name="mo-mode"]', function() {
    var isNew = moMode() === 'new';
    $('.mo-new').toggle(isNew);
    $('.mo-existing').toggle(!isNew);
    $('#mo-unit').text(isNew ? ($('#mo-new-unit').val() || '—') : ($('#mo-product').data('unit') || '—'));
});
$(document).on('input', '#mo-new-unit', function() { $('#mo-unit').text(this.value || '—'); });

function moSave() {
    var branchId = $('#mo-branch').val();
    var projectId = $('#mo-project-target').val();
    if (!branchId) { toastr.error('Pilih branch.'); return; }
    var picks = moPicks();
    if (!picks.length) { toastr.error('Pilih minimal satu material yang dipakai.'); return; }
    if (!projectId) { toastr.error('Pilih project barang jadi.'); $('#mo-project-target').addClass('is-invalid').focus(); return; }
    var payload = {
        project_id: projectId,
        branch_id: branchId,
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
    if (!payload.output_qty || payload.output_qty <= 0) { toastr.error('Isi qty barang jadi.'); $('#mo-qty').addClass('is-invalid').focus(); return; }
    if (!payload.output_warehouse_id) { toastr.error('Pilih gudang barang jadi.'); $('#mo-warehouse').addClass('is-invalid').focus(); return; }

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
$(document).on('change input', '#mo-project-target, #mo-warehouse, #mo-qty', function() { $(this).removeClass('is-invalid'); });

$(document).ready(function() {
    $('#mo-branch').select2({ theme: 'bootstrap-5', width: '100%' });
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
