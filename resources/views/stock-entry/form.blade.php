@extends('layouts.app')

@section('title', 'Buat Masuk Stok Lain-lain')
@section('page-title', 'Buat Masuk Stok Lain-lain')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Buat Masuk Stok Lain-lain</h1>
        <p class="page-header-sub">Untuk barang masuk tanpa PO / Direct Purchase. Pembelian tetap lewat Penerimaan Barang.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('stock-entry.index') }}" class="btn btn-secondary btn-sm">
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
                    <div class="col-lg-6">
                        <label class="form-label">Gudang <span class="text-danger">*</span></label>
                        <select id="se-warehouse" class="form-select">
                            <option value="">— Pilih Gudang —</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">Kepemilikan</label>
                        <select id="se-project" class="form-select">
                            <option value="">Stok bebas</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}">Project {{ $p->project_number }} — {{ $p->project_name }}</option>
                            @endforeach
                        </select>
                        <small style="color:var(--text-muted)">Pilih project bila barang milik project (mis. retur barang project).</small>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Alasan <span class="text-danger">*</span></label>
                        <select id="se-reason" class="form-select">
                            @foreach(\App\Models\StockEntry::REASON_LABELS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" id="se-date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label">Keterangan <span class="text-danger" id="se-note-required" style="display:none">*</span></label>
                        <input type="text" id="se-note" class="form-control" maxlength="1000" placeholder="Contoh: retur dari site Gresik">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Barang Masuk</span>
                <button type="button" class="btn btn-soft btn-sm" onclick="seOpenPicker()"><i class="fa fa-plus me-1"></i> Tambah dari Katalog</button>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:45px" class="text-center">#</th>
                                <th>Produk</th>
                                <th class="text-center" style="width:120px">Qty</th>
                                <th style="width:120px">Satuan</th>
                                <th class="text-end" style="width:170px">Harga Satuan ({{ \App\Models\Currency::baseName() }})</th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="se-items">
                            <tr class="se-empty"><td colspan="6" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Belum ada barang. Klik "Tambah dari Katalog".</td></tr>
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
                    <tr><td>Produk</td><td class="text-end fw-semibold" id="se-sum-lines">0</td></tr>
                    <tr><td>Total Qty</td><td class="text-end fw-semibold" id="se-sum-qty">0</td></tr>
                </table>
                @if($canCreate)
                <button type="button" class="btn btn-primary w-100 mt-3" id="se-save" onclick="seSave()">
                    <i class="fa fa-check me-1"></i> Posting Masuk Stok
                </button>
                @endif
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Dokumen langsung terposting dan tidak bisa diubah. Harga satuan menjadi harga modal baris stok (default: harga beli master). Barang ber-SN dicatat per unit; SN boleh menyusul di <a href="{{ route('stock-detail.index') }}">Lengkapi Data Stok</a>. SN yang pernah keluar (retur dari customer) kembali ke riwayat SN-nya.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="sePickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-box me-2" style="color:var(--accent)"></i>Pilih Produk Master</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="se-product-q" placeholder="Cari kode / nama / brand / kategori produk">
                    <button type="button" class="btn btn-primary" onclick="seSearchProducts()"><i class="fa fa-search me-1"></i>Cari</button>
                </div>
                <div id="se-product-results">
                    <div class="config-card-empty"><i class="fa-solid fa-inbox"></i>Ketik kata kunci lalu tekan Cari.</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
const seSearchUrl = '{{ route("stock-entry.search-products") }}';
const seStoreUrl = '{{ route("stock-entry.store") }}';
const seShowUrl = '{{ route("stock-entry.show", "__ID__") }}';
const seReasonOther = '{{ \App\Models\StockEntry::REASON_OTHER }}';
const seSupplierOptions = @json($suppliers->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values());
let seRowSeq = 0;
let sePickerInstance = null;
let seProductPool = [];

function seEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function seFmt(n) { return Num.format(n, 2); }
// "1,250,000.5" -> 1250000.5; kosong -> null (server memakai harga beli master).
function seParse(v) { return Num.parseOrNull(v); }

function seOpenPicker() {
    $('#se-product-q').val('');
    $('#se-product-results').html('<div class="config-card-empty"><i class="fa-solid fa-inbox"></i>Ketik kata kunci lalu tekan Cari.</div>');
    if (!sePickerInstance) sePickerInstance = new bootstrap.Modal(document.getElementById('sePickerModal'));
    sePickerInstance.show();
}

function seSearchProducts() {
    $('#se-product-results').html('<div class="config-card-empty"><span class="config-spinner"></span>Memuat...</div>');
    seProductPool = [];
    $.get(seSearchUrl, { q: $('#se-product-q').val().trim() })
        .done(function(res) {
            var list = res.data || [];
            if (list.length === 0) {
                $('#se-product-results').html('<div class="config-card-empty"><i class="fa-solid fa-inbox"></i>Tidak ditemukan.</div>');
                return;
            }
            var html = '<div class="list-group">';
            list.forEach(function(p) {
                var added = $('#se-items tr[data-product-id="' + p.id + '"]').length > 0;
                html += '<a href="javascript:void(0)" class="list-group-item list-group-item-action se-product-item" data-idx="' + seProductPool.length + '">';
                html += '<div class="d-flex justify-content-between align-items-center"><strong>' + seEsc(p.code || '') + ' &middot; ' + seEsc(p.name || '') + '</strong>';
                html += '<span>' + (p.serialized ? '<span class="badge bg-info text-dark me-1">Barang ber-SN</span>' : '<span class="badge bg-light text-dark me-1">Material</span>')
                    + (added ? '<span class="badge bg-secondary">Sudah ada di daftar</span>' : '') + '</span>';
                html += '</div>';
                if (p.brand || p.category) html += '<div class="text-muted" style="font-size:12px">' + seEsc([p.brand, p.category].filter(Boolean).join(' · ')) + '</div>';
                html += '</a>';
                seProductPool.push(p);
            });
            $('#se-product-results').html(html + '</div>');
        })
        .fail(function() {
            $('#se-product-results').html('<div class="config-card-empty"><i class="fa-solid fa-triangle-exclamation"></i>Gagal memuat produk.</div>');
        });
}

$(document).on('keydown', '#se-product-q', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); seSearchProducts(); }
});

$(document).on('click', '.se-product-item', function() {
    var p = seProductPool[$(this).data('idx')];
    if (!p) return;
    $('#se-items .se-empty').remove();
    var key = ++seRowSeq;
    var html = '<tr data-product-id="' + p.id + '" data-row="' + key + '" data-serialized="' + (p.serialized ? 1 : 0) + '">';
    html += '<td class="text-center se-no" style="color:var(--text-muted)"></td>';
    html += '<td><div class="fw-semibold" style="font-size:13px">' + seEsc(p.name) + '</div><div style="font-size:11px;color:var(--text-muted)">' + seEsc(p.code) + ' &middot; ' + (p.serialized ? 'Barang ber-SN' : 'Material') + '</div></td>';
    html += '<td><input type="text" inputmode="decimal" class="form-control form-control-sm text-center se-qty" value="1"></td>';
    html += '<td><input type="text" class="form-control form-control-sm se-unit" maxlength="50" value="Unit"></td>';
    html += '<td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end se-cost" value="' + (p.cost == null ? '' : seFmt(p.cost)) + '" placeholder="Harga beli master"></td>';
    html += '<td class="text-center"><button type="button" class="btn-icon danger" title="Hapus" onclick="seRemoveRow(this)"><i class="fa fa-trash-can"></i></button></td>';
    html += '</tr>';
    var sup = '<option value="">— Supplier garansi —</option>' + seSupplierOptions.map(function(o) { return '<option value="' + o.id + '">' + seEsc(o.name) + '</option>'; }).join('');
    html += '<tr class="se-detail" data-for="' + key + '"><td></td><td colspan="5" style="padding-top:0"><div class="row g-2" style="font-size:12px">';
    html += '<div class="col-md-3"><label class="form-label mb-0" style="font-size:11.5px">Batch / No. MFG</label><input type="text" class="form-control form-control-sm se-batch" maxlength="100" placeholder="Opsional"></div>';
    html += '<div class="col-md-2"><label class="form-label mb-0" style="font-size:11.5px">Kedaluwarsa</label><input type="date" class="form-control form-control-sm se-expiry"></div>';
    html += '<div class="col-md-3"><label class="form-label mb-0" style="font-size:11.5px">Supplier garansi</label><select class="form-select form-select-sm se-supplier">' + sup + '</select></div>';
    html += '<div class="col-md-2"><label class="form-label mb-0" style="font-size:11.5px">Garansi s/d</label><input type="date" class="form-control form-control-sm se-warranty"></div>';
    html += '<div class="col-md-2"><label class="form-label mb-0" style="font-size:11.5px">Catatan barang</label><input type="text" class="form-control form-control-sm se-item-note" maxlength="255"></div>';
    if (p.serialized) {
        html += '<div class="col-12"><label class="form-label mb-0" style="font-size:11.5px">SN — satu per baris, boleh dikosongkan (<span class="se-sn-count">0</span> / qty)</label><textarea class="form-control form-control-sm se-serials" rows="2" placeholder="Tempel atau scan SN"></textarea></div>';
    }
    html += '</div></td></tr>';
    $('#se-items').append(html);
    seRenumber();
    sePickerInstance.hide();
    toastr.success('Barang ditambahkan.');
});

function seSerialList(text) {
    return String(text || '').split(/[\r\n,;]+/).map(function(s) { return s.trim(); }).filter(function(s) { return s !== ''; });
}

$(document).on('input', '.se-serials', function() {
    $(this).closest('tr').find('.se-sn-count').text(seSerialList($(this).val()).length);
});

function seRemoveRow(btn) {
    var $row = $(btn).closest('tr');
    $('#se-items tr.se-detail[data-for="' + $row.data('row') + '"]').remove();
    $row.remove();
    if ($('#se-items tr[data-product-id]').length === 0) {
        $('#se-items').html('<tr class="se-empty"><td colspan="6" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Belum ada barang. Klik "Tambah dari Katalog".</td></tr>');
    }
    seRenumber();
}

function seRenumber() {
    var lines = 0, qty = 0;
    $('#se-items tr[data-product-id]').each(function(i) {
        $(this).find('.se-no').text(i + 1);
        lines++;
        qty += Num.parseOrNull($(this).find('.se-qty').val()) || 0;
    });
    $('#se-sum-lines').text(lines);
    $('#se-sum-qty').text(Num.format(qty, 4));
}

$(document).on('input', '.se-qty', function() {
    var serialized = $(this).closest('tr').data('serialized') === 1;
    this.value = serialized ? this.value.replace(/\D/g, '') : this.value.replace(/[^\d.]/g, '');
    $(this).removeClass('is-invalid');
    seRenumber();
});

$(document).on('input', '.se-cost', function() {
    this.value = this.value.replace(/[^\d.,]/g, '');
});

$(document).on('change', '#se-reason', function() {
    $('#se-note-required').toggle($(this).val() === seReasonOther);
});

function seSave() {
    if (!$('#se-warehouse').val()) { toastr.error('Pilih gudang.'); return; }
    if ($('#se-reason').val() === seReasonOther && !$('#se-note').val().trim()) { toastr.error('Keterangan wajib diisi untuk alasan Lain-lain.'); return; }

    var items = [], invalid = null;
    $('#se-items tr[data-product-id]').each(function() {
        var qty = Num.parseOrNull($(this).find('.se-qty').val()) || 0;
        if (qty <= 0 && !invalid) invalid = $(this).find('.se-qty');
        var cost = seParse($(this).find('.se-cost').val());
        var $d = $('#se-items tr.se-detail[data-for="' + $(this).data('row') + '"]');
        var serials = seSerialList($d.find('.se-serials').val());
        if (serials.length > qty && !invalid) invalid = $d.find('.se-serials');
        items.push({
            master_product_id: $(this).data('product-id'),
            qty: qty,
            unit: $(this).find('.se-unit').val().trim(),
            unit_cost: cost,
            batch_number: $d.find('.se-batch').val() || null,
            expiry_date: $d.find('.se-expiry').val() || null,
            supplier_id: $d.find('.se-supplier').val() || null,
            warranty_until: $d.find('.se-warranty').val() || null,
            note: $d.find('.se-item-note').val() || null,
            serial_numbers: serials
        });
    });
    if (items.length === 0) { toastr.error('Tambahkan minimal satu barang.'); return; }
    if (invalid) { invalid.addClass('is-invalid')[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); toastr.error('Qty harus lebih dari 0 dan jumlah SN tidak boleh melebihi qty.'); return; }

    Swal.fire({
        title: 'Posting Masuk Stok?',
        text: items.length + ' produk akan menambah stok gudang.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Posting',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $('#se-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Memproses...');
        $.ajax({
            url: seStoreUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                warehouse_id: $('#se-warehouse').val(),
                project_id: $('#se-project').val() || null,
                reason: $('#se-reason').val(),
                entry_date: $('#se-date').val(),
                note: $('#se-note').val(),
                items: items
            }),
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        }).done(function(res) {
            toastr.success(res.message || 'Masuk stok diposting.');
            setTimeout(function() { window.location.href = seShowUrl.replace('__ID__', res.id); }, 600);
        }).fail(function(xhr) {
            var msg = 'Gagal memposting.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
            toastr.error(msg);
            $('#se-save').prop('disabled', false).html('<i class="fa fa-check me-1"></i> Posting Masuk Stok');
        });
    });
}

$(document).ready(function() {
    $('#se-project').select2({ width: '100%' });
});
</script>
@endsection
