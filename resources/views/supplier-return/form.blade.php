@extends('layouts.app')

@section('title', 'Buat Retur ke Supplier')
@section('page-title', 'Buat Retur ke Supplier')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Buat Retur ke Supplier</h1>
        <p class="page-header-sub">Hanya barang yang sudah diterima dan <strong>belum ditagih</strong> yang bisa diretur. Barang yang sudah ditagih: void tagihannya dulu.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('supplier-return.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom"><span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi Retur</span></div>
    <div class="card-body-custom">
        <div class="row g-3">
            <div class="col-lg-6">
                <label class="form-label" for="rt-po">Purchase Order <span class="text-danger">*</span></label>
                <select id="rt-po" class="form-select" style="width:100%">
                    <option value="">— Pilih PO —</option>
                    @foreach($purchaseOrders as $po)
                        <option value="{{ $po->id }}" @selected($preselectPoId === $po->id)>{{ $po->po_number }} — {{ $po->supplier_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="rt-warehouse">Gudang Asal Barang</label>
                <select id="rt-warehouse" class="form-select">
                    <option value="">— Pilih Gudang —</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->type_label }})</option>
                    @endforeach
                </select>
                <small style="color:var(--text-muted)">Wajib bila barang dulu masuk gudang.</small>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="rt-date">Tanggal <span class="text-danger">*</span></label>
                <input type="date" id="rt-date" class="form-control" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="rt-reason">Alasan Retur <span class="text-danger">*</span></label>
                <input type="text" id="rt-reason" class="form-control" maxlength="2000" placeholder="Contoh: 2 unit sensor pecah saat diterima, supplier kirim ulang">
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Barang Diretur</span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead><tr><th>Barang</th><th style="width:220px">Diterima / Ditagih</th><th class="text-center" style="width:120px">Qty Retur</th></tr></thead>
                <tbody id="rt-lines"><tr><td colspan="3" class="text-center py-4" style="color:var(--text-muted)">Pilih PO terlebih dahulu.</td></tr></tbody>
            </table>
        </div>
        <div class="px-2 py-2">
            <button type="button" class="btn btn-primary" id="rt-save" onclick="rtSave()"><i class="fa fa-check me-1"></i> Posting Retur</button>
            <span style="font-size:12px;color:var(--text-muted)" class="ms-2">Retur langsung terposting dan tidak bisa diubah.</span>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function rtEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function rtLoad() {
    var poId = $('#rt-po').val();
    if (!poId) { $('#rt-lines').html('<tr><td colspan="3" class="text-center py-4" style="color:var(--text-muted)">Pilih PO terlebih dahulu.</td></tr>'); return; }
    $.get('{{ route("supplier-return.returnable-lines") }}', { purchase_order_id: poId }).done(function(res) {
        var html = '';
        res.lines.forEach(function(l) {
            var locked = l.returnable <= 0;
            html += '<tr data-id="' + l.purchase_order_item_id + '" data-max="' + l.returnable + '"' + (locked ? ' style="opacity:.55"' : '') + '>';
            html += '<td><div class="fw-semibold" style="font-size:13px">' + rtEsc(l.part_number || '—') + '</div><div style="font-size:12px;color:var(--text-muted)">' + (l.description || '') + '</div></td>';
            html += '<td style="font-size:12.5px">Diterima ' + l.received + ' · Ditagih ' + l.billed + '<div class="fw-semibold" style="color:' + (locked ? 'var(--text-muted)' : 'var(--accent)') + '">' + (locked ? 'Tidak bisa diretur' : 'Bisa diretur ' + l.returnable + ' ' + rtEsc(l.unit || '')) + '</div></td>';
            html += '<td><input type="text" inputmode="numeric" class="form-control form-control-sm text-center rt-qty" placeholder="0"' + (locked ? ' disabled' : '') + ' aria-label="Qty retur ' + rtEsc(l.part_number) + '"></td>';
            html += '</tr>';
        });
        $('#rt-lines').html(html);
    }).fail(function(xhr) {
        $('#rt-lines').html('<tr><td colspan="3" class="text-center py-4 text-danger">' + rtEsc(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat.') + '</td></tr>');
    });
}

$(document).on('change', '#rt-po', rtLoad);
$(document).on('input', '.rt-qty', function() { this.value = this.value.replace(/\D/g, ''); });

function rtSave() {
    if (!$('#rt-po').val()) { toastr.error('Pilih PO.'); return; }
    if (!$('#rt-reason').val().trim()) { toastr.error('Alasan retur wajib diisi.'); $('#rt-reason').focus(); return; }
    var items = [];
    var invalid = false;
    $('#rt-lines tr[data-id]').each(function() {
        var qty = parseInt($(this).find('.rt-qty').val(), 10) || 0;
        if (!qty) return;
        if (qty > parseInt($(this).data('max'), 10)) invalid = true;
        items.push({ purchase_order_item_id: $(this).data('id'), qty: qty });
    });
    if (invalid) { toastr.error('Qty retur melebihi yang bisa diretur.'); return; }
    if (!items.length) { toastr.error('Isi qty minimal satu barang.'); return; }

    Swal.fire({ title: 'Posting retur?', text: 'Stok berkurang dan PO kembali menunggu barang pengganti. Retur tidak bisa diubah.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Posting', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $('#rt-save').prop('disabled', true);
            $.ajax({
                url: '{{ route("supplier-return.store") }}', method: 'POST', contentType: 'application/json',
                data: JSON.stringify({ purchase_order_id: $('#rt-po').val(), warehouse_id: $('#rt-warehouse').val() || null, return_date: $('#rt-date').val(), reason: $('#rt-reason').val().trim(), items: items }),
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
            }).done(function(res) {
                toastr.success(res.message);
                setTimeout(function() { location.href = '{{ route("supplier-return.show", "__ID__") }}'.replace('__ID__', res.id); }, 700);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memposting retur.');
                $('#rt-save').prop('disabled', false);
            });
        });
}

$(document).ready(function() {
    $('#rt-po').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— Pilih PO —' });
    if ($('#rt-po').val()) rtLoad();
});
</script>
@endsection
