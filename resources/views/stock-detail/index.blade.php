@extends('layouts.app')

@section('title', 'Lengkapi Data Stok')
@section('page-title', 'Lengkapi Data Stok')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Lengkapi Data Stok</h1>
        <p class="page-header-sub">Isi SN, batch / No. MFG, kedaluwarsa, dan garansi barang yang sudah masuk stok. Unit barang ber-SN tanpa SN belum bisa dikirim, diretur, atau dipakai.</p>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-body-custom">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="sd-show">Tampilkan</label>
                <select id="sd-show" class="form-select form-select-sm">
                    <option value="missing_serial" @selected($filters['show'] === 'missing_serial')>Unit SN belum diisi</option>
                    <option value="incomplete" @selected($filters['show'] === 'incomplete')>Data belum lengkap (SN / batch / kedaluwarsa / garansi)</option>
                    <option value="all" @selected($filters['show'] === 'all')>Semua stok yang ada</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="sd-warehouse">Gudang</label>
                <select id="sd-warehouse" class="form-select form-select-sm">
                    <option value="">Semua gudang</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" @selected($filters['warehouse_id'] === $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6" style="font-size:12.5px;color:var(--text-muted)">
                @if($receipt)
                    Hanya barang dari penerimaan <strong>{{ $receipt->receipt_number }}</strong> —
                    <a href="{{ route('stock-detail.index') }}">tampilkan semua</a><br>
                @endif
                <i class="fa fa-barcode me-1"></i>Total unit SN belum diisi: <strong id="sd-missing-total">{{ $missingTotal }}</strong>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-barcode me-2" style="color:var(--accent)"></i>Baris Stok</span>
        @if($canCreate)
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary btn-sm" onclick="sdOpenPaste()"><i class="fa fa-paste me-1"></i> Tempel Daftar SN</button>
            <button type="button" class="btn btn-primary btn-sm" id="sd-save" onclick="sdSave()"><i class="fa fa-check me-1"></i> Simpan Perubahan</button>
        </div>
        @endif
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="sd-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Posisi</th>
                        <th class="text-end">Qty</th>
                        <th style="min-width:160px">SN</th>
                        <th style="min-width:140px">Batch / No. MFG</th>
                        <th style="width:140px">Kedaluwarsa</th>
                        <th style="width:140px">Garansi s/d</th>
                        <th style="min-width:160px">Catatan</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
            </table>
        </div>
        <div class="px-2 pt-2" style="font-size:12px;color:var(--text-muted)">
            <i class="fa fa-circle-info me-1"></i>Perubahan SN, batch, kedaluwarsa, dan garansi tercatat di riwayat stok. Data barang yang sudah keluar tidak bisa diubah.
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="sdPasteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa fa-paste me-2" style="color:var(--accent)"></i>Tempel Daftar SN</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p style="font-size:13px;color:var(--text-muted)">Satu SN per baris. SN diisikan berurutan ke unit barang ber-SN yang SN-nya masih kosong di halaman tabel saat ini. Periksa lalu klik <strong>Simpan Perubahan</strong>.</p>
                <textarea id="sd-paste" class="form-control" rows="10" placeholder="SN-0001&#10;SN-0002"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="sdApplyPaste()">Isikan</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
const sdDataUrl = '{{ route("stock-detail.data") }}';
const sdSaveUrl = '{{ route("stock-detail.save") }}';
const sdShowUrl = '{{ route("stock-detail.show", "__ID__") }}';
const sdReceiptId = {{ $filters['goods_receipt_id'] ?? 'null' }};
const sdProductId = {{ $filters['product_id'] ?? 'null' }};
const sdCanEdit = {{ $canCreate ? 'true' : 'false' }};
let sdTable = null;
let sdPasteModal = null;

function sdEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function sdInput(cls, value, type, extra) {
    return '<input type="' + (type || 'text') + '" class="form-control form-control-sm sd-field ' + cls + '" value="' + sdEsc(value || '') + '" data-orig="' + sdEsc(value || '') + '"' + (sdCanEdit ? '' : ' disabled') + (extra || '') + '>';
}

function sdSave() {
    var rows = [];
    $('#sd-table tbody tr[data-id]').each(function() {
        var $tr = $(this), changed = false, row = { id: $tr.data('id') };
        $tr.find('.sd-field').each(function() {
            var key = $(this).data('key');
            row[key] = $(this).val().trim();
            if (row[key] !== String($(this).data('orig'))) changed = true;
        });
        if (changed) rows.push(row);
    });
    if (!rows.length) { toastr.info('Belum ada perubahan.'); return; }

    $('#sd-save').prop('disabled', true);
    $.ajax({
        url: sdSaveUrl, method: 'POST', contentType: 'application/json',
        data: JSON.stringify({ rows: rows }),
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).done(function(res) {
        toastr.success(res.message || 'Tersimpan.');
        $('#sd-missing-total').text(res.missing_total);
        sdTable.ajax.reload(null, false);
    }).fail(function(xhr) {
        var msg = 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
    }).always(function() { $('#sd-save').prop('disabled', false); });
}

function sdOpenPaste() {
    $('#sd-paste').val('');
    if (!sdPasteModal) sdPasteModal = new bootstrap.Modal(document.getElementById('sdPasteModal'));
    sdPasteModal.show();
}

function sdApplyPaste() {
    var list = String($('#sd-paste').val() || '').split(/[\r\n,;]+/).map(function(s) { return s.trim(); }).filter(Boolean);
    var filled = 0;
    $('#sd-table tbody input.sd-serial').each(function() {
        if (!list.length) return false;
        if ($(this).val().trim() === '') { $(this).val(list.shift()).addClass('is-valid'); filled++; }
    });
    sdPasteModal.hide();
    toastr[filled ? 'success' : 'warning'](filled ? filled + ' SN diisikan. Klik Simpan Perubahan.' : 'Tidak ada unit SN kosong di halaman ini.');
    if (list.length) toastr.warning(list.length + ' SN tersisa karena unit kosong di halaman ini sudah habis.');
}

$(document).ready(function() {
    sdTable = $('#sd-table').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 50,
        ordering: false,
        ajax: {
            url: sdDataUrl,
            data: function(d) {
                d.show = $('#sd-show').val();
                d.warehouse_id = $('#sd-warehouse').val();
                if (sdReceiptId) d.goods_receipt_id = sdReceiptId;
                if (sdProductId) d.product_id = sdProductId;
            }
        },
        createdRow: function(row, data) { $(row).attr('data-id', data.id); },
        columns: [
            { data: 'product_code', render: function(d, t, r) {
                return '<div class="fw-semibold" style="font-size:13px">' + sdEsc(d) + '</div><div style="font-size:11.5px;color:var(--text-muted)">' + sdEsc(r.product_name) + ' &middot; ' + (r.serialized ? 'Barang ber-SN' : 'Material') + '</div>';
            } },
            { data: 'warehouse', render: function(d, t, r) {
                return '<div style="font-size:12.5px">' + sdEsc(d) + '</div><div style="font-size:11.5px;color:var(--text-muted)">' + (r.project ? 'Project ' + sdEsc(r.project) : 'Stok bebas') + ' &middot; masuk ' + sdEsc(r.received_date) + '</div>';
            } },
            { data: 'qty', className: 'text-end' },
            { data: 'serial_number', render: function(d, t, r) {
                return r.serialized ? sdInput('sd-serial', d, 'text', ' data-key="serial_number" maxlength="100" placeholder="SN belum diisi"') : '<span style="color:var(--text-muted);font-size:12px">— material</span>';
            } },
            { data: 'batch_number', render: function(d) { return sdInput('', d, 'text', ' data-key="batch_number" maxlength="100"'); } },
            { data: 'expiry_date', render: function(d) { return sdInput('', d, 'date', ' data-key="expiry_date"'); } },
            { data: 'warranty_until', render: function(d) { return sdInput('', d, 'date', ' data-key="warranty_until"'); } },
            { data: 'note', render: function(d) { return sdInput('', d, 'text', ' data-key="note" maxlength="255"'); } },
            { data: 'id', className: 'text-center', render: function(d) {
                return '<a href="' + sdShowUrl.replace('__ID__', d) + '" class="btn-icon" title="Riwayat"><i class="fa fa-clock-rotate-left"></i></a>';
            } }
        ]
    });

    $('#sd-show, #sd-warehouse').on('change', function() { sdTable.ajax.reload(); });
});
</script>
@endsection
