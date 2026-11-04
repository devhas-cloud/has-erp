@extends('layouts.app')

@php $isEdit = (bool) $opname; @endphp

@section('title', $isEdit ? 'Edit Stock Opname' : 'Buat Stock Opname')
@section('page-title', $isEdit ? 'Edit Stock Opname' : 'Buat Stock Opname')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Edit Stock Opname '.$opname->opname_number : 'Buat Stock Opname' }}</h1>
        <p class="page-header-sub">Isi qty hitung fisik. Baris yang dikosongkan tidak ikut diopname. Selisih baru menjadi penyesuaian saat diposting approver.</p>
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
                        <label class="form-label">Gudang <span class="text-danger">*</span></label>
                        <select id="so-warehouse" class="form-select" {{ $isEdit ? 'disabled' : '' }}>
                            <option value="">— Pilih Gudang —</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}" {{ ($opname->warehouse_id ?? null) === $w->id ? 'selected' : '' }}>{{ $w->code }} — {{ $w->name }} ({{ $w->type_label }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Tanggal Opname <span class="text-danger">*</span></label>
                        <input type="date" id="so-date" class="form-control" value="{{ $opname?->opname_date?->toDateString() ?? now()->toDateString() }}">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Catatan</label>
                        <input type="text" id="so-note" class="form-control" maxlength="2000" value="{{ $opname->note ?? '' }}" placeholder="Contoh: opname akhir bulan">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-list-check me-2" style="color:var(--accent)"></i>Hitung Fisik</span>
                <div class="d-flex gap-2" id="so-actions" style="display:none !important">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="soFillSystem()"><i class="fa fa-equals me-1"></i> Isi Kosong = Sistem</button>
                    <button type="button" class="btn btn-soft btn-sm" onclick="soOpenPicker()"><i class="fa fa-plus me-1"></i> Tambah Produk</button>
                </div>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Kepemilikan</th>
                                <th class="text-center" style="width:90px">Sistem</th>
                                <th class="text-center" style="width:110px">Fisik</th>
                                <th class="text-center" style="width:90px">Selisih</th>
                                <th style="width:200px">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="so-items">
                            <tr><td colspan="6" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih gudang terlebih dahulu.</td></tr>
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
                </table>
                <button type="button" class="btn btn-primary w-100 mt-3" id="so-save" onclick="soSave()">
                    <i class="fa fa-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Draft' }}
                </button>
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Posting segera setelah penghitungan selesai — qty sistem dihitung ulang saat posting.</div>
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
                <h6 class="modal-title"><i class="fa-solid fa-box me-2" style="color:var(--accent)"></i>Tambah Produk (stok bebas, belum ada di sistem)</h6>
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
let soInitialItems = @json($items);
let soPickerInstance = null;
let soProductPool = [];

function soEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function soKey(productId, projectId) { return productId + ':' + (projectId || ''); }

function soRowHtml(line, pre) {
    var counted = pre && pre.counted_qty != null ? pre.counted_qty : '';
    var html = '<tr data-product-id="' + line.master_product_id + '" data-project-id="' + (line.project_id || '') + '" data-system="' + line.system_qty + '">';
    html += '<td><div class="fw-semibold" style="font-size:13px">' + soEsc(line.name) + '</div><div style="font-size:11px;color:var(--text-muted)">' + soEsc(line.code) + '</div></td>';
    html += '<td style="font-size:12px">' + (line.project_id ? '<span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px">Project</span> ' : '') + soEsc(line.ownership) + '</td>';
    html += '<td class="text-center">' + line.system_qty + ' ' + soEsc(line.unit || '') + '</td>';
    html += '<td><input type="text" inputmode="numeric" class="form-control form-control-sm text-center so-counted" value="' + counted + '" placeholder="—"></td>';
    html += '<td class="text-center fw-semibold so-diff">—</td>';
    html += '<td><input type="text" class="form-control form-control-sm so-remark" maxlength="255" value="' + soEsc(pre && pre.remark || '') + '"></td>';
    return html + '</tr>';
}

function soLoad() {
    var warehouseId = $('#so-warehouse').val();
    $('#so-actions').attr('style', 'display:none !important');
    if (!warehouseId) {
        $('#so-items').html('<tr><td colspan="6" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih gudang terlebih dahulu.</td></tr>');
        soSummary();
        return;
    }
    $('#so-items').html('<tr><td colspan="6" class="text-center py-4"><span class="spinner-border spinner-border-sm me-1"></span> Memuat stok sistem...</td></tr>');
    $.get(soSystemUrl, { warehouse_id: warehouseId }).done(function(res) {
        var initial = {};
        (soInitialItems || []).forEach(function(i) { initial[soKey(i.master_product_id, i.project_id)] = i; });
        var html = '';
        (res.items || []).forEach(function(line) {
            var key = soKey(line.master_product_id, line.project_id);
            html += soRowHtml(line, initial[key]);
            delete initial[key];
        });
        // Baris draft yang tidak ada di sistem (produk tambahan, sistem 0).
        Object.keys(initial).forEach(function(key) {
            var i = initial[key];
            html += soRowHtml({ master_product_id: i.master_product_id, project_id: i.project_id, name: i.name || ('Produk #' + i.master_product_id), code: i.code || '', ownership: 'Stok bebas', system_qty: 0, unit: '' }, i);
        });
        soInitialItems = null;
        $('#so-items').html(html || '<tr class="so-empty"><td colspan="6" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Belum ada stok di gudang ini. Tambah produk bila ditemukan barang.</td></tr>');
        $('#so-actions').attr('style', '');
        soRefreshDiffs();
    });
}

function soRefreshDiffs() {
    $('#so-items tr[data-product-id]').each(function() {
        var v = $(this).find('.so-counted').val();
        var $d = $(this).find('.so-diff');
        if (v === '') { $d.text('—').css('color', ''); return; }
        var diff = parseInt(v, 10) - parseInt($(this).data('system'), 10);
        $d.text(diff > 0 ? '+' + diff : diff).css('color', diff > 0 ? 'var(--accent)' : (diff < 0 ? 'var(--danger)' : ''));
    });
    soSummary();
}

function soCollect() {
    var rows = [];
    $('#so-items tr[data-product-id]').each(function() {
        var v = $(this).find('.so-counted').val();
        if (v === '') return;
        rows.push({
            master_product_id: $(this).data('product-id'),
            project_id: $(this).data('project-id') || null,
            counted_qty: parseInt(v, 10),
            remark: $(this).find('.so-remark').val().trim(),
            system: parseInt($(this).data('system'), 10)
        });
    });
    return rows;
}

function soSummary() {
    var rows = soCollect(), plus = 0, minus = 0;
    rows.forEach(function(r) { var d = r.counted_qty - r.system; if (d > 0) plus += d; else minus -= d; });
    $('#so-sum-lines').text(rows.length);
    $('#so-sum-plus').text(plus);
    $('#so-sum-minus').text(minus);
}

function soFillSystem() {
    $('#so-items tr[data-product-id]').each(function() {
        var $c = $(this).find('.so-counted');
        if ($c.val() === '') $c.val($(this).data('system'));
    });
    soRefreshDiffs();
}

$(document).on('change', '#so-warehouse', soLoad);
$(document).on('input', '.so-counted', function() {
    this.value = this.value.replace(/\D/g, '');
    soRefreshDiffs();
});

function soOpenPicker() {
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
        if (list.length === 0) { $('#so-product-results').html('<div class="config-card-empty"><i class="fa-solid fa-inbox"></i>Tidak ditemukan.</div>'); return; }
        var html = '<div class="list-group">';
        list.forEach(function(p) {
            var exists = $('#so-items tr[data-product-id="' + p.id + '"][data-project-id=""]').length > 0;
            html += '<a href="javascript:void(0)" class="list-group-item list-group-item-action so-product-item' + (exists ? ' disabled' : '') + '" data-idx="' + soProductPool.length + '">';
            html += '<strong>' + soEsc(p.code || '') + ' &middot; ' + soEsc(p.name || '') + '</strong>' + (exists ? ' <span class="badge bg-secondary">Sudah ada</span>' : '');
            html += '</a>';
            soProductPool.push(p);
        });
        $('#so-product-results').html(html + '</div>');
    });
}

$(document).on('keydown', '#so-product-q', function(e) { if (e.key === 'Enter') { e.preventDefault(); soSearchProducts(); } });

$(document).on('click', '.so-product-item:not(.disabled)', function() {
    var p = soProductPool[$(this).data('idx')];
    if (!p) return;
    $('#so-items .so-empty').remove();
    $('#so-items').append(soRowHtml({ master_product_id: p.id, project_id: null, name: p.name, code: p.code, ownership: 'Stok bebas', system_qty: 0, unit: '' }, { counted_qty: 1 }));
    soRefreshDiffs();
    soPickerInstance.hide();
});

function soSave() {
    if (!$('#so-warehouse').val()) { toastr.error('Pilih gudang.'); return; }
    if (!$('#so-date').val()) { toastr.error('Isi tanggal opname.'); return; }
    var rows = soCollect();
    if (rows.length === 0) { toastr.error('Isi qty fisik minimal satu baris.'); return; }

    $('#so-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: soSaveUrl,
        method: soSaveMethod,
        contentType: 'application/json',
        data: JSON.stringify({
            warehouse_id: $('#so-warehouse').val(),
            opname_date: $('#so-date').val(),
            note: $('#so-note').val(),
            items: rows.map(function(r) { return { master_product_id: r.master_product_id, project_id: r.project_id, counted_qty: r.counted_qty, remark: r.remark }; })
        }),
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
    if ($('#so-warehouse').val()) soLoad();
});
</script>
@endsection
