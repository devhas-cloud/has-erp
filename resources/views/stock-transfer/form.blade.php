@extends('layouts.app')

@section('title', 'Buat Transfer Stok')
@section('page-title', 'Buat Transfer Stok')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Buat Transfer Stok</h1>
        <p class="page-header-sub">Pilih asal (gudang + kepemilikan) untuk melihat stok tersedia, lalu isi qty yang dipindah ke tujuan.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('stock-transfer.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-route me-2" style="color:var(--accent)"></i>Asal &amp; Tujuan</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="fw-semibold mb-2" style="font-size:13px"><i class="fa fa-circle-arrow-up me-1" style="color:var(--danger)"></i>Asal</div>
                        <label class="form-label">Gudang Asal <span class="text-danger">*</span></label>
                        <select id="st-from-warehouse" class="form-select mb-2">
                            <option value="">— Pilih Gudang —</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->type_label }})</option>
                            @endforeach
                        </select>
                        <label class="form-label">Kepemilikan Asal</label>
                        <select id="st-from-project" class="form-select st-project">
                            <option value="">Stok bebas</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}">Project {{ $p->project_number }} — {{ $p->project_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-6">
                        <div class="fw-semibold mb-2" style="font-size:13px"><i class="fa fa-circle-arrow-down me-1" style="color:var(--success, #16a34a)"></i>Tujuan</div>
                        <label class="form-label">Gudang Tujuan <span class="text-danger">*</span></label>
                        <select id="st-to-warehouse" class="form-select mb-2">
                            <option value="">— Pilih Gudang —</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}" data-type="{{ $w->type }}">{{ $w->code }} — {{ $w->name }} ({{ $w->type_label }})</option>
                            @endforeach
                        </select>
                        <label class="form-label">Kepemilikan Tujuan</label>
                        <select id="st-to-project" class="form-select st-project">
                            <option value="">Stok bebas</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}">Project {{ $p->project_number }} — {{ $p->project_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Tanggal Transfer</label>
                        <input type="date" id="st-date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-lg-8">
                        <label class="form-label">Catatan</label>
                        <input type="text" id="st-note" class="form-control" maxlength="1000" placeholder="Opsional, contoh: persiapan kirim ke site">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Stok Tersedia di Asal</span>
                <button type="button" class="btn btn-secondary btn-sm" id="st-fill-all" style="display:none" onclick="stFillAll()"><i class="fa fa-angles-right me-1"></i> Pindahkan Semua</button>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:45px" class="text-center">#</th>
                                <th>Produk</th>
                                <th class="text-center" style="width:110px">Tersedia</th>
                                <th class="text-center" style="width:140px">Qty Transfer</th>
                            </tr>
                        </thead>
                        <tbody id="st-items">
                            <tr><td colspan="4" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih gudang asal terlebih dahulu.</td></tr>
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
                    <tr><td>Jenis</td><td class="text-end"><span id="st-kind" class="status-badge" style="background:var(--accent-soft);color:var(--accent);">—</span></td></tr>
                    <tr><td>Produk</td><td class="text-end fw-semibold" id="st-sum-lines">0</td></tr>
                    <tr><td>Total Qty</td><td class="text-end fw-semibold" id="st-sum-qty">0</td></tr>
                </table>
                <div id="st-kind-hint" style="font-size:12px;color:var(--text-muted);margin-top:8px"></div>
                @if($canCreate)
                <button type="button" class="btn btn-primary w-100 mt-3" id="st-save" onclick="stSave()">
                    <i class="fa fa-check me-1"></i> Posting Transfer
                </button>
                @endif
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Transfer langsung terposting dan tidak bisa diubah. Koreksi dengan transfer balik.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const stAvailableUrl = '{{ route("stock-transfer.available-stock") }}';
const stStoreUrl = '{{ route("stock-transfer.store") }}';
const stShowUrl = '{{ route("stock-transfer.show", "__ID__") }}';
let stItems = [];

function stEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function stLoadAvailable() {
    var warehouseId = $('#st-from-warehouse').val();
    stItems = [];
    $('#st-fill-all').hide();
    if (!warehouseId) {
        $('#st-items').html('<tr><td colspan="4" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih gudang asal terlebih dahulu.</td></tr>');
        stSummary();
        return;
    }
    $('#st-items').html('<tr><td colspan="4" class="text-center py-4"><span class="spinner-border spinner-border-sm me-1"></span> Memuat stok...</td></tr>');
    $.get(stAvailableUrl, { warehouse_id: warehouseId, project_id: $('#st-from-project').val() })
        .done(function(res) {
            stItems = res.items || [];
            if (stItems.length === 0) {
                $('#st-items').html('<tr><td colspan="4" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Tidak ada stok di asal ini.</td></tr>');
                stSummary();
                return;
            }
            var html = '';
            stItems.forEach(function(item, i) {
                html += '<tr data-product-id="' + item.master_product_id + '" data-max="' + item.balance + '">';
                html += '<td class="text-center" style="color:var(--text-muted)">' + (i + 1) + '</td>';
                html += '<td><div class="fw-semibold" style="font-size:13px">' + stEsc(item.name) + '</div><div style="font-size:11px;color:var(--text-muted)">' + stEsc(item.code) + '</div></td>';
                html += '<td class="text-center">' + item.balance + ' ' + stEsc(item.unit || '') + '</td>';
                html += '<td><input type="text" inputmode="numeric" class="form-control form-control-sm text-center st-qty" placeholder="0"></td>';
                html += '</tr>';
            });
            $('#st-items').html(html);
            $('#st-fill-all').show();
            stSummary();
        })
        .fail(function() {
            $('#st-items').html('<tr><td colspan="4" class="text-center py-4" style="color:var(--danger)">Gagal memuat stok.</td></tr>');
        });
}

function stFillAll() {
    $('#st-items tr[data-product-id]').each(function() {
        $(this).find('.st-qty').val($(this).data('max')).removeClass('is-invalid');
    });
    stSummary();
}

function stKind() {
    var from = $('#st-from-project').val(), to = $('#st-to-project').val();
    if (!from && to) return ['Alokasi ke Project', 'Stok bebas menjadi milik project tujuan (umumnya ke gudang transit sebelum Delivery Order).'];
    if (from && !to) return ['Lepas Alokasi', 'Stok project dikembalikan menjadi stok bebas.'];
    if (from && to && from !== to) return ['Pindah Project', 'Stok berpindah kepemilikan dari satu project ke project lain.'];
    return ['Pindah Gudang', 'Kepemilikan tetap, hanya lokasi gudang yang berubah.'];
}

function stCollect() {
    var rows = [];
    $('#st-items tr[data-product-id]').each(function() {
        var qty = parseInt($(this).find('.st-qty').val(), 10) || 0;
        if (qty > 0) rows.push({ master_product_id: $(this).data('product-id'), qty: qty, max: $(this).data('max'), $el: $(this).find('.st-qty') });
    });
    return rows;
}

function stSummary() {
    var rows = stCollect();
    var kind = stKind();
    $('#st-kind').text(kind[0]);
    $('#st-kind-hint').text(kind[1]);
    $('#st-sum-lines').text(rows.length);
    $('#st-sum-qty').text(rows.reduce(function(sum, r) { return sum + r.qty; }, 0));
}

$(document).on('change', '#st-from-warehouse, #st-from-project', stLoadAvailable);
$(document).on('change', '#st-to-project', function() {
    // Alokasi ke project: sarankan gudang transit bila tujuan belum dipilih.
    if ($(this).val() && !$('#st-to-warehouse').val()) {
        var $transit = $('#st-to-warehouse option[data-type="transit"]').first();
        if ($transit.length) $('#st-to-warehouse').val($transit.val());
    }
    stSummary();
});
$(document).on('input', '.st-qty', function() {
    this.value = this.value.replace(/\D/g, '');
    $(this).removeClass('is-invalid');
    stSummary();
});

function stSave() {
    var fromWh = $('#st-from-warehouse').val(), toWh = $('#st-to-warehouse').val();
    var fromProject = $('#st-from-project').val(), toProject = $('#st-to-project').val();
    if (!fromWh) { toastr.error('Pilih gudang asal.'); return; }
    if (!toWh) { toastr.error('Pilih gudang tujuan.'); return; }
    if (fromWh === toWh && fromProject === toProject) { toastr.error('Asal dan tujuan sama. Ubah gudang atau kepemilikan tujuan.'); return; }

    var rows = stCollect();
    if (rows.length === 0) { toastr.error('Isi qty minimal satu produk.'); return; }
    for (var i = 0; i < rows.length; i++) {
        if (rows[i].qty > rows[i].max) {
            rows[i].$el.addClass('is-invalid')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            toastr.error('Qty melebihi stok tersedia (' + rows[i].max + ').');
            return;
        }
    }

    Swal.fire({
        title: 'Posting Transfer?',
        text: stKind()[0] + ' — ' + rows.length + ' produk. Stok langsung berpindah.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Posting',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $('#st-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Memproses...');
        $.ajax({
            url: stStoreUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                from_warehouse_id: fromWh,
                from_project_id: fromProject || null,
                to_warehouse_id: toWh,
                to_project_id: toProject || null,
                transfer_date: $('#st-date').val(),
                note: $('#st-note').val(),
                items: rows.map(function(r) { return { master_product_id: r.master_product_id, qty: r.qty }; })
            }),
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        }).done(function(res) {
            toastr.success(res.message || 'Transfer diposting.');
            setTimeout(function() { window.location.href = stShowUrl.replace('__ID__', res.id); }, 600);
        }).fail(function(xhr) {
            var msg = 'Gagal memposting transfer.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
            toastr.error(msg);
            $('#st-save').prop('disabled', false).html('<i class="fa fa-check me-1"></i> Posting Transfer');
        });
    });
}

$(document).ready(function() {
    $('.st-project').select2({ width: '100%' });
    stSummary();
});
</script>
@endsection
