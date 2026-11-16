@extends('layouts.app')

@php $isEdit = (bool) $opname; @endphp

@section('title', $isEdit ? 'Edit Stock Opname' : 'Buat Stock Opname')
@section('page-title', $isEdit ? 'Edit Stock Opname' : 'Buat Stock Opname')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Edit Stock Opname '.$opname->opname_number : 'Buat Stock Opname' }}</h1>
        <p class="page-header-sub">Hitung per baris stok. Baris yang dikosongkan tidak ikut diopname. Angka sistem dicatat saat qty fisik disimpan; selisih menjadi penyesuaian saat diposting approver.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $isEdit ? route('stock-opname.show', $opname->id) : route('stock-opname.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-5">
                        <label class="form-label" for="so-warehouse">Gudang <span class="text-danger">*</span></label>
                        <select id="so-warehouse" class="form-select" {{ $isEdit ? 'disabled' : '' }}>
                            <option value="">— Pilih Gudang —</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}" {{ ($opname->warehouse_id ?? null) === $w->id ? 'selected' : '' }}>{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="so-date">Tanggal Opname <span class="text-danger">*</span></label>
                        <input type="date" id="so-date" class="form-control" value="{{ $opname?->opname_date?->toDateString() ?? now()->toDateString() }}">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="so-note">Catatan</label>
                        <input type="text" id="so-note" class="form-control" maxlength="2000" value="{{ $opname->note ?? '' }}" placeholder="Contoh: opname akhir bulan">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-list-check me-2" style="color:var(--accent)"></i>Hitung Fisik</span>
                <div class="d-flex gap-2 align-items-center" id="so-actions" style="display:none !important">
                    <input type="search" id="so-q" class="form-control form-control-sm" style="max-width:220px" placeholder="Saring kode, nama, SN, batch">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="soFillSystem()"><i class="fa fa-equals me-1"></i> Isi Kosong = Sistem</button>
                </div>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>SN / Batch</th>
                                <th>Kepemilikan</th>
                                <th class="text-center" style="width:100px">Sistem</th>
                                <th class="text-center" style="width:120px">Fisik</th>
                                <th class="text-center" style="width:90px">Selisih</th>
                                <th style="width:180px">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="so-items">
                            <tr><td colspan="7" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih gudang terlebih dahulu.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-2 pt-2" style="font-size:12px;color:var(--text-muted)"><i class="fa fa-circle-info me-1"></i>Barang ber-SN cukup dicentang bila unitnya ada. Saringan hanya menyembunyikan baris; isian yang sudah diisi tetap ikut tersimpan.</div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-magnifying-glass-plus me-2" style="color:var(--accent)"></i>Barang Temuan (belum ada di sistem)</span>
                <button type="button" class="btn btn-soft btn-sm" onclick="soOpenPicker()"><i class="fa fa-plus me-1"></i> Tambah Barang Temuan</button>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th style="width:200px">Kepemilikan</th>
                                <th style="width:150px">SN</th>
                                <th style="width:140px">Batch / No. MFG</th>
                                <th style="width:140px">Kedaluwarsa</th>
                                <th class="text-center" style="width:100px">Qty</th>
                                <th style="width:90px">Satuan</th>
                                <th style="width:40px"></th>
                            </tr>
                        </thead>
                        <tbody id="so-found">
                            <tr class="so-found-empty"><td colspan="8" class="text-center py-3" style="color:var(--text-muted);font-size:13px">Tidak ada barang temuan.</td></tr>
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
                    <tr><td>Baris dihitung</td><td class="text-end fw-semibold" id="so-sum-lines">0</td></tr>
                    <tr><td>Selisih (+)</td><td class="text-end fw-semibold" id="so-sum-plus">0</td></tr>
                    <tr><td>Selisih (−)</td><td class="text-end fw-semibold" id="so-sum-minus">0</td></tr>
                    <tr><td>Barang temuan</td><td class="text-end fw-semibold" id="so-sum-found">0</td></tr>
                </table>
                <button type="button" class="btn btn-primary w-100 mt-3" id="so-save" onclick="soSave()">
                    <i class="fa fa-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Draft' }}
                </button>
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Barang yang keluar/masuk setelah dihitung tetap terhitung benar saat posting.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="soPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-box me-2" style="color:var(--accent)"></i>Tambah Barang Temuan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="so-product-q" placeholder="Cari kode / nama / brand produk">
                    <button type="button" class="btn btn-primary" onclick="soSearchProducts()"><i class="fa fa-search me-1"></i>Cari</button>
                </div>
                <div id="so-product-results"><div class="config-card-empty"><i class="fa-solid fa-inbox"></i>Ketik kata kunci lalu tekan Cari.</div></div>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
const soSystemUrl = '{{ route("stock-opname.system-stock") }}';
const soSearchUrl = '{{ route("stock-opname.search-products") }}';
const soSaveUrl = '{{ $isEdit ? route("stock-opname.update", $opname->id) : route("stock-opname.store") }}';
const soSaveMethod = '{{ $isEdit ? "PUT" : "POST" }}';
const soShowUrl = '{{ route("stock-opname.show", "__ID__") }}';
const soSaveLabel = $('#so-save').html();
const soProjects = @json($projects->map(fn ($p) => ['id' => $p->id, 'label' => $p->project_number.' — '.$p->project_name])->values());
let soInitialItems = @json($items);
let soPickerInstance = null;
let soProductPool = [];

function soEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function soFmt(n) { return Num.format(n, 4); }

function soRowHtml(line, pre) {
    var counted = pre && pre.counted_qty != null ? pre.counted_qty : '';
    var html = '<tr data-stock-id="' + line.id + '" data-system="' + line.qty + '" data-serialized="' + (line.serialized ? 1 : 0) + '" data-search="' + soEsc((line.product_code + ' ' + line.product_name + ' ' + (line.serial_number || '') + ' ' + (line.batch_number || '')).toLowerCase()) + '">';
    html += '<td><div class="fw-semibold" style="font-size:13px">' + soEsc(line.product_name) + '</div><div style="font-size:11px;color:var(--text-muted)">' + soEsc(line.product_code) + '</div></td>';
    html += '<td style="font-size:12px">' + soEsc(line.identity) + (line.note ? '<div style="color:var(--text-muted)">' + soEsc(line.note) + '</div>' : '') + '</td>';
    html += '<td style="font-size:12px">' + (line.project ? '<span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px">Project</span> ' + soEsc(line.project) : 'Stok bebas') + '</td>';
    html += '<td class="text-center">' + soEsc(line.qty_label) + ' ' + soEsc(line.unit || '') + '</td>';
    if (line.serialized) {
        var checked = counted === '' ? '' : (parseFloat(counted) >= 1 ? 'yes' : 'no');
        html += '<td class="text-center"><select class="form-select form-select-sm so-counted"><option value=""' + (checked === '' ? ' selected' : '') + '>—</option><option value="1"' + (checked === 'yes' ? ' selected' : '') + '>Ada</option><option value="0"' + (checked === 'no' ? ' selected' : '') + '>Tidak ada</option></select></td>';
    } else {
        html += '<td><input type="text" inputmode="decimal" class="form-control form-control-sm text-center so-counted" placeholder="—" value="' + counted + '"></td>';
    }
    html += '<td class="text-center so-diff">—</td>';
    html += '<td><input type="text" class="form-control form-control-sm so-remark" maxlength="255" value="' + soEsc(pre && pre.remark ? pre.remark : '') + '"></td>';
    return html + '</tr>';
}

function soFoundRowHtml(p, pre) {
    pre = pre || {};
    var projectOpts = '<option value="">Stok bebas</option>' + soProjects.map(function(o) {
        return '<option value="' + o.id + '"' + (String(pre.project_id || '') === String(o.id) ? ' selected' : '') + '>' + soEsc(o.label) + '</option>';
    }).join('');
    var html = '<tr data-product-id="' + p.id + '" data-serialized="' + (p.serialized ? 1 : 0) + '">';
    html += '<td><div class="fw-semibold" style="font-size:13px">' + soEsc(p.name) + '</div><div style="font-size:11px;color:var(--text-muted)">' + soEsc(p.code) + ' &middot; ' + (p.serialized ? 'Barang ber-SN' : 'Material') + '</div></td>';
    html += '<td><select class="form-select form-select-sm so-f-project">' + projectOpts + '</select></td>';
    html += '<td>' + (p.serialized ? '<input type="text" class="form-control form-control-sm so-f-serial" maxlength="100" placeholder="Boleh menyusul" value="' + soEsc(pre.serial_number || '') + '">' : '<span style="color:var(--text-muted);font-size:12px">—</span>') + '</td>';
    html += '<td><input type="text" class="form-control form-control-sm so-f-batch" maxlength="100" value="' + soEsc(pre.batch_number || '') + '"></td>';
    html += '<td><input type="date" class="form-control form-control-sm so-f-expiry" value="' + soEsc(pre.expiry_date || '') + '"></td>';
    html += '<td>' + (p.serialized ? '<input type="hidden" class="so-f-qty" value="1"><div class="text-center">1</div>' : '<input type="text" inputmode="decimal" class="form-control form-control-sm text-center so-f-qty" value="' + (pre.counted_qty || 1) + '">') + '</td>';
    html += '<td><input type="text" class="form-control form-control-sm so-f-unit" maxlength="50" value="' + soEsc(pre.unit || 'Unit') + '"></td>';
    html += '<td class="text-center"><button type="button" class="btn-icon danger" title="Hapus" onclick="soRemoveFound(this)"><i class="fa fa-trash-can"></i></button></td>';
    return html + '</tr>';
}

function soLoadSystem() {
    var warehouseId = $('#so-warehouse').val();
    if (!warehouseId) {
        $('#so-actions').attr('style', 'display:none !important');
        $('#so-items').html('<tr><td colspan="7" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih gudang terlebih dahulu.</td></tr>');
        soSummary();
        return;
    }
    $('#so-items').html('<tr><td colspan="7" class="text-center py-4"><span class="spinner-border spinner-border-sm me-1"></span> Memuat stok...</td></tr>');
    var include = (soInitialItems || []).filter(function(i) { return i.stock_id; }).map(function(i) { return i.stock_id; });
    $.get(soSystemUrl, { warehouse_id: warehouseId, include: include }).done(function(res) {
        var lines = res.data || [];
        var pre = {};
        (soInitialItems || []).forEach(function(i) { if (i.stock_id) pre[i.stock_id] = i; });
        if (!lines.length) {
            $('#so-items').html('<tr><td colspan="7" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Gudang ini belum punya stok. Barang yang ditemukan bisa ditambahkan sebagai barang temuan.</td></tr>');
        } else {
            $('#so-items').html(lines.map(function(l) { return soRowHtml(l, pre[l.id]); }).join(''));
            $('#so-items tr[data-stock-id]').each(function() { soUpdateDiff($(this)); });
        }
        $('#so-actions').attr('style', '');
        soSummary();
    }).fail(function() {
        $('#so-items').html('<tr><td colspan="7" class="text-center py-4 text-danger">Gagal memuat stok.</td></tr>');
    });
}

function soUpdateDiff($tr) {
    var raw = $tr.find('.so-counted').val();
    if (raw === '' || raw == null) { $tr.find('.so-diff').text('—').css('color', ''); return; }
    var diff = (Num.parseOrNull(raw) || 0) - parseFloat($tr.data('system'));
    diff = Math.round(diff * 10000) / 10000;
    $tr.find('.so-diff').text(diff > 0 ? '+' + soFmt(diff) : soFmt(diff)).css('color', diff > 0 ? 'var(--accent)' : (diff < 0 ? 'var(--danger)' : ''));
}

function soFillSystem() {
    $('#so-items tr[data-stock-id]:visible').each(function() {
        var $c = $(this).find('.so-counted');
        if ($c.val() === '' || $c.val() == null) {
            $c.val($(this).data('serialized') === 1 ? '1' : $(this).data('system'));
            soUpdateDiff($(this));
        }
    });
    soSummary();
}

function soSummary() {
    var lines = 0, plus = 0, minus = 0;
    $('#so-items tr[data-stock-id]').each(function() {
        var raw = $(this).find('.so-counted').val();
        if (raw === '' || raw == null) return;
        lines++;
        var diff = (Num.parseOrNull(raw) || 0) - parseFloat($(this).data('system'));
        if (diff > 0) plus += diff; else minus -= diff;
    });
    $('#so-sum-lines').text(lines);
    $('#so-sum-plus').text(soFmt(plus));
    $('#so-sum-minus').text(soFmt(minus));
    $('#so-sum-found').text($('#so-found tr[data-product-id]').length);
}

function soOpenPicker() {
    if (!$('#so-warehouse').val()) { toastr.error('Pilih gudang terlebih dahulu.'); return; }
    $('#so-product-q').val('');
    $('#so-product-results').html('<div class="config-card-empty"><i class="fa-solid fa-inbox"></i>Ketik kata kunci lalu tekan Cari.</div>');
    if (!soPickerInstance) soPickerInstance = new bootstrap.Modal(document.getElementById('soPickerModal'));
    soPickerInstance.show();
}

function soSearchProducts() {
    $('#so-product-results').html('<div class="config-card-empty"><span class="config-spinner"></span>Memuat...</div>');
    soProductPool = [];
    $.get(soSearchUrl, { q: $('#so-product-q').val().trim() }).done(function(res) {
        var list = res.data || [];
        if (!list.length) { $('#so-product-results').html('<div class="config-card-empty"><i class="fa-solid fa-inbox"></i>Tidak ditemukan.</div>'); return; }
        var html = '<div class="list-group">';
        list.forEach(function(p) {
            html += '<a href="javascript:void(0)" class="list-group-item list-group-item-action so-product-item" data-idx="' + soProductPool.length + '"><strong>' + soEsc(p.code || '') + ' &middot; ' + soEsc(p.name || '') + '</strong> <span class="badge bg-light text-dark">' + (p.serialized ? 'Barang ber-SN' : 'Material') + '</span></a>';
            soProductPool.push(p);
        });
        $('#so-product-results').html(html + '</div>');
    });
}

function soRemoveFound(btn) {
    $(btn).closest('tr').remove();
    if (!$('#so-found tr[data-product-id]').length) $('#so-found').html('<tr class="so-found-empty"><td colspan="8" class="text-center py-3" style="color:var(--text-muted);font-size:13px">Tidak ada barang temuan.</td></tr>');
    soSummary();
}

$(document).on('keydown', '#so-product-q', function(e) { if (e.key === 'Enter') { e.preventDefault(); soSearchProducts(); } });
$(document).on('click', '.so-product-item', function() {
    var p = soProductPool[$(this).data('idx')];
    if (!p) return;
    $('#so-found .so-found-empty').remove();
    $('#so-found').append(soFoundRowHtml(p));
    soPickerInstance.hide();
    soSummary();
});
$(document).on('change', '#so-warehouse', soLoadSystem);
$(document).on('input change', '.so-counted', function() {
    if (this.tagName === 'INPUT') this.value = this.value.replace(/[^\d.]/g, '');
    soUpdateDiff($(this).closest('tr'));
    soSummary();
});
$(document).on('input', '.so-f-qty', function() { this.value = this.value.replace(/[^\d.]/g, ''); });
$(document).on('input', '#so-q', function() {
    var q = $(this).val().trim().toLowerCase();
    $('#so-items tr[data-stock-id]').each(function() { $(this).toggle(!q || String($(this).data('search')).indexOf(q) !== -1); });
});

function soSave() {
    if (!$('#so-warehouse').val()) { toastr.error('Pilih gudang.'); return; }
    if (!$('#so-date').val()) { toastr.error('Isi tanggal opname.'); return; }

    var items = [];
    $('#so-items tr[data-stock-id]').each(function() {
        var raw = $(this).find('.so-counted').val();
        if (raw === '' || raw == null) return;
        items.push({ stock_id: $(this).data('stock-id'), counted_qty: Num.parseOrNull(raw) || 0, remark: $(this).find('.so-remark').val().trim() });
    });
    var invalid = false;
    $('#so-found tr[data-product-id]').each(function() {
        var qty = Num.parseOrNull($(this).find('.so-f-qty').val()) || 0;
        if (qty <= 0) invalid = true;
        items.push({
            master_product_id: $(this).data('product-id'),
            project_id: $(this).find('.so-f-project').val() || null,
            serial_number: $(this).find('.so-f-serial').val() || null,
            batch_number: $(this).find('.so-f-batch').val() || null,
            expiry_date: $(this).find('.so-f-expiry').val() || null,
            counted_qty: qty,
            unit: $(this).find('.so-f-unit').val() || null
        });
    });
    if (invalid) { toastr.error('Qty barang temuan harus lebih dari 0.'); return; }
    if (!items.length) { toastr.error('Isi qty fisik minimal satu baris atau tambahkan barang temuan.'); return; }

    $('#so-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: soSaveUrl,
        method: soSaveMethod,
        contentType: 'application/json',
        data: JSON.stringify({ warehouse_id: $('#so-warehouse').val(), opname_date: $('#so-date').val(), note: $('#so-note').val(), items: items }),
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).done(function(res) {
        toastr.success(res.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = soShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        $('#so-save').prop('disabled', false).html(soSaveLabel);
    });
}

$(document).ready(function() {
    (soInitialItems || []).filter(function(i) { return !i.stock_id; }).forEach(function(i) {
        $('#so-found .so-found-empty').remove();
        $('#so-found').append(soFoundRowHtml({ id: i.master_product_id, code: i.code, name: i.name, serialized: i.serialized }, i));
    });
    if ($('#so-warehouse').val()) soLoadSystem();
});
</script>
@endsection
