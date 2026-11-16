@extends('layouts.app')

@section('title', 'Branch Transfer '.$transfer->transfer_number)
@section('page-title', 'Branch Transfer')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $receiving = $transfer->isInTransit() && $canReceive && $canCreate;
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Branch Transfer {{ $transfer->transfer_number }}</h1>
        <p class="page-header-sub">{{ $transfer->fromBranch?->name }} &rarr; {{ $transfer->toBranch?->name }} &middot; {{ $transfer->transfer_date?->format('d/m/Y') }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('branch-transfer.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="qt-action-bar">
    <span>{!! $transfer->statusBadgeHtml() !!}</span>
    <div class="d-flex gap-2 flex-wrap">
        @if($transfer->isDraft() && $canSend)
            @if($canUpdate)
                <a href="{{ route('branch-transfer.edit', $transfer->id) }}" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen me-1"></i> Edit</a>
            @endif
            @if($canDelete)
                <button type="button" class="btn btn-danger btn-sm" onclick="btDelete()"><i class="fa-solid fa-trash-can me-1"></i> Hapus</button>
            @endif
            @if($canCreate)
                <button type="button" class="btn btn-primary btn-sm" id="bt-ship-btn" onclick="btShip()"><i class="fa-solid fa-truck-fast me-1"></i> Kirim</button>
            @endif
        @endif
        @unless($transfer->isDraft())
            <a href="{{ route('branch-transfer.pdf', $transfer->id) }}" target="_blank" class="btn btn-secondary btn-sm"><i class="fa-solid fa-print me-1"></i> Cetak Surat Jalan</a>
        @endunless
    </div>
</div>

@if($transfer->isInTransit())
<div class="alert alert-warning py-2" style="font-size:13px">
    <i class="fa-solid fa-truck-fast me-1"></i> Barang dalam pengiriman sejak {{ $transfer->shipped_at?->format('d/m/Y H:i') }}. Stok sudah keluar dari {{ $transfer->fromWarehouse?->name }} dan belum masuk ke gudang mana pun.
    @if($receiving)
        <div class="mt-1">Periksa setiap barang di bawah. Penerimaan hanya bisa diselesaikan bila <strong>semua barang sesuai</strong> (qty sama dan kondisi baik).</div>
    @endif
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-circle-arrow-up me-2" style="color:var(--danger)"></i>Pengirim</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Branch</td><td>{{ $transfer->fromBranch?->label }}</td></tr>
                    <tr><td>Gudang Asal</td><td>{{ $transfer->fromWarehouse ? $transfer->fromWarehouse->code.' — '.$transfer->fromWarehouse->name : '—' }}</td></tr>
                    <tr><td>Ekspedisi</td><td>{{ $transfer->courier ?: '—' }}</td></tr>
                    <tr><td>Kendaraan / Pembawa</td><td>{{ $transfer->vehicle_no ?: '—' }} &middot; {{ $transfer->driver_name ?: '—' }}</td></tr>
                    <tr><td>Dibuat</td><td>{{ $transfer->creator?->display_name ?? '—' }} &middot; {{ $transfer->created_at?->format('d/m/Y H:i') }}</td></tr>
                    @if($transfer->shipped_at)
                        <tr><td>Dikirim</td><td>{{ $transfer->shippedBy?->display_name ?? '—' }} &middot; {{ $transfer->shipped_at->format('d/m/Y H:i') }}</td></tr>
                    @endif
                    <tr><td>Catatan</td><td>{{ $transfer->note ?: '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-circle-arrow-down me-2" style="color:var(--success, #16a34a)"></i>Penerima</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Branch</td><td>{{ $transfer->toBranch?->label }}</td></tr>
                    <tr><td>Gudang Tujuan</td>
                        <td>
                            @if($receiving)
                                <select id="bt-to-warehouse" class="form-select form-select-sm" aria-label="Gudang tujuan">
                                    <option value="">— Pilih gudang tujuan —</option>
                                    @foreach($destinationWarehouses as $w)
                                        <option value="{{ $w->id }}" @selected($transfer->to_warehouse_id === $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                                    @endforeach
                                </select>
                                @if($destinationWarehouses->isEmpty())
                                    <small class="text-danger">{{ $transfer->toBranch?->name }} belum punya gudang aktif. Tambahkan di menu Gudang.</small>
                                @endif
                            @else
                                {{ $transfer->toWarehouse ? $transfer->toWarehouse->code.' — '.$transfer->toWarehouse->name : 'Dipilih saat diterima' }}
                            @endif
                        </td>
                    </tr>
                    @if($transfer->received_at)
                        <tr><td>Diterima</td><td>{{ $transfer->receivedBy?->display_name ?? '—' }} &middot; {{ $transfer->received_at->format('d/m/Y H:i') }}</td></tr>
                    @endif
                    <tr><td>Catatan Penerimaan</td>
                        <td>
                            @if($receiving)
                                <input type="text" id="bt-receive-note" class="form-control form-control-sm" maxlength="2000" value="{{ $transfer->receive_note }}">
                            @else
                                {{ $transfer->receive_note ?: '—' }}
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i>Barang</span>
        @if($receiving)
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-secondary btn-sm" onclick="btFillAll()"><i class="fa fa-check-double me-1"></i> Semua Sesuai</button>
                <button type="button" class="btn btn-secondary btn-sm" id="bt-inspect-btn" onclick="btInspect(false)"><i class="fa fa-save me-1"></i> Simpan Pemeriksaan</button>
                <button type="button" class="btn btn-primary btn-sm" id="bt-receive-btn" onclick="btInspect(true)"><i class="fa fa-box-open me-1"></i> Selesaikan Penerimaan</button>
            </div>
        @endif
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:40px" class="text-center">#</th>
                        <th>Barang</th>
                        <th>SN / Batch</th>
                        <th class="text-center" style="width:110px">Dikirim</th>
                        @unless($transfer->isDraft())
                            <th class="text-center" style="width:130px">Diterima</th>
                            <th class="text-center" style="width:110px">Kondisi Baik</th>
                            <th style="min-width:180px">Catatan &amp; Foto</th>
                        @endunless
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfer->items as $i => $item)
                        @php $stock = $item->stock; $state = $item->checkState(); @endphp
                        <tr data-item-id="{{ $item->id }}" data-qty="{{ $item->qty }}">
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold" style="font-size:13px">{{ $stock?->masterProduct?->name ?? '—' }}</div>
                                <div style="font-size:11px;color:var(--text-muted)">{{ $stock?->masterProduct?->code }}{{ $stock?->project_id ? ' · milik project' : '' }}</div>
                            </td>
                            <td style="font-size:12.5px">
                                @if($stock)<a href="{{ route('stock-detail.show', $stock->id) }}">{{ $stock->identity_label }}</a>@endif
                                @if($item->received_stock_id)<div style="font-size:11px"><a href="{{ route('stock-detail.show', $item->received_stock_id) }}">di tujuan #{{ $item->received_stock_id }}</a></div>@endif
                            </td>
                            <td class="text-center fw-semibold">{{ $fmtQty($item->qty) }} {{ $item->unit }}</td>
                            @unless($transfer->isDraft())
                                <td class="text-center">
                                    @if($receiving)
                                        <input type="text" inputmode="decimal" class="form-control form-control-sm text-center bt-received" value="{{ $item->received_qty !== null ? $item->received_qty : '' }}" placeholder="—" aria-label="Qty diterima">
                                    @else
                                        {{ $item->received_qty !== null ? $fmtQty($item->received_qty) : '—' }}
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($receiving)
                                        <input type="checkbox" class="form-check-input bt-condition" @checked($item->condition_ok) aria-label="Kondisi baik">
                                    @else
                                        {!! $item->condition_ok === null ? '—' : ($item->condition_ok ? '<span style="color:var(--accent)">Ya</span>' : '<span class="text-danger">Tidak</span>') !!}
                                    @endif
                                    <div class="bt-state" style="font-size:11px">
                                        @if($state === 'matched')<span style="color:var(--accent)"><i class="fa fa-circle-check"></i> Sesuai</span>
                                        @elseif($state === 'mismatch')<span class="text-danger"><i class="fa fa-circle-xmark"></i> Tidak sesuai</span>
                                        @else<span style="color:var(--text-muted)">Belum diperiksa</span>@endif
                                    </div>
                                </td>
                                <td>
                                    @if($receiving)
                                        <input type="text" class="form-control form-control-sm bt-note mb-1" maxlength="255" value="{{ $item->receive_note }}" placeholder="Catatan (opsional)" aria-label="Catatan barang">
                                        <label class="btn btn-secondary btn-sm mb-1" style="font-size:11.5px">
                                            <i class="fa fa-camera me-1"></i> Tambah Foto
                                            <input type="file" class="d-none bt-photo" accept="image/*" capture="environment" multiple data-url="{{ route('branch-transfer.photos.store', [$transfer->id, $item->id]) }}">
                                        </label>
                                    @else
                                        <div style="font-size:12px">{{ $item->receive_note ?: '' }}</div>
                                    @endif
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($item->photos as $photo)
                                            @php $photoUrl = route('branch-transfer.photos.show', [$transfer->id, $item->id, $photo->id]); @endphp
                                            <span class="position-relative">
                                                <a href="{{ $photoUrl }}" target="_blank" title="{{ $photo->uploader?->display_name }} · {{ $photo->created_at?->format('d/m/Y H:i') }}">
                                                    <img src="{{ $photoUrl }}" alt="Foto {{ $stock?->masterProduct?->code }}" style="width:52px;height:52px;object-fit:cover;border-radius:6px;border:1px solid var(--border-color, #e5e7eb)">
                                                </a>
                                                @if($receiving && (int) $photo->uploaded_by === (int) auth()->id())
                                                    <button type="button" class="btn-icon danger position-absolute" style="top:-6px;right:-6px;width:18px;height:18px;font-size:9px" title="Hapus foto" onclick="btDeletePhoto('{{ route('branch-transfer.photos.destroy', [$transfer->id, $item->id, $photo->id]) }}', this)"><i class="fa fa-xmark"></i></button>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            @endunless
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const btCsrf = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' };

function btFail(xhr, fallback) {
    var msg = fallback;
    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
    if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
    toastr.error(msg);
}

function btShip() {
    Swal.fire({ title: 'Kirim barang?', text: 'Stok keluar dari gudang asal dan tercatat dalam pengiriman. Dokumen tidak bisa diubah lagi.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Kirim', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $('#bt-ship-btn').prop('disabled', true);
            $.ajax({ url: '{{ route("branch-transfer.ship", $transfer->id) }}', method: 'POST', headers: btCsrf })
                .done(function(res) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 700); })
                .fail(function(xhr) { btFail(xhr, 'Gagal mengirim.'); $('#bt-ship-btn').prop('disabled', false); });
        });
}

function btDelete() {
    Swal.fire({ title: 'Hapus draft?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $.ajax({ url: '{{ route("branch-transfer.destroy", $transfer->id) }}', method: 'DELETE', headers: btCsrf })
                .done(function(res) { toastr.success(res.message); setTimeout(function() { location.href = '{{ route("branch-transfer.index") }}'; }, 600); })
                .fail(function(xhr) { btFail(xhr, 'Gagal menghapus.'); });
        });
}

function btFillAll() {
    $('tr[data-item-id]').each(function() {
        $(this).find('.bt-received').val($(this).data('qty'));
        $(this).find('.bt-condition').prop('checked', true);
    });
}

function btInspect(thenReceive) {
    var items = [];
    $('tr[data-item-id]').each(function() {
        var raw = $(this).find('.bt-received').val();
        items.push({
            id: $(this).data('item-id'),
            received_qty: raw === '' ? null : (Num.parseOrNull(raw) || 0),
            condition_ok: $(this).find('.bt-condition').is(':checked') ? 1 : 0,
            receive_note: $(this).find('.bt-note').val() || null
        });
    });
    var $btn = thenReceive ? $('#bt-receive-btn') : $('#bt-inspect-btn');
    $btn.prop('disabled', true);
    $.ajax({
        url: '{{ route("branch-transfer.inspect", $transfer->id) }}', method: 'POST', headers: btCsrf, contentType: 'application/json',
        data: JSON.stringify({ to_warehouse_id: $('#bt-to-warehouse').val() || null, receive_note: $('#bt-receive-note').val(), items: items })
    }).done(function(res) {
        if (!thenReceive) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 700); return; }
        Swal.fire({ title: 'Selesaikan penerimaan?', text: 'Semua barang masuk ke gudang tujuan. Dokumen tidak bisa diubah lagi.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Terima', cancelButtonText: 'Batal' })
            .then(function(r) {
                if (!r.isConfirmed) { $btn.prop('disabled', false); return; }
                $.ajax({ url: '{{ route("branch-transfer.receive", $transfer->id) }}', method: 'POST', headers: btCsrf })
                    .done(function(res2) { toastr.success(res2.message); setTimeout(function() { location.reload(); }, 700); })
                    .fail(function(xhr) { btFail(xhr, 'Gagal menerima.'); $btn.prop('disabled', false); });
            });
    }).fail(function(xhr) { btFail(xhr, 'Gagal menyimpan pemeriksaan.'); $btn.prop('disabled', false); });
}

// Foto langsung tampil tanpa memuat ulang halaman (isian pemeriksaan yang belum disimpan tidak hilang).
$(document).on('change', '.bt-photo', function() {
    if (!this.files.length) return;
    var input = this;
    var $gallery = $(this).closest('td').find('.d-flex.flex-wrap');
    var fd = new FormData();
    Array.prototype.forEach.call(this.files, function(f) { fd.append('files[]', f); });
    toastr.info('Mengunggah foto...');
    $.ajax({ url: $(this).data('url'), method: 'POST', headers: btCsrf, data: fd, processData: false, contentType: false })
        .done(function(res) {
            toastr.success(res.message);
            (res.photos || []).forEach(function(p) {
                $gallery.append('<span class="position-relative"><a href="' + p.url + '" target="_blank"><img src="' + p.url + '" alt="Foto barang" style="width:52px;height:52px;object-fit:cover;border-radius:6px;border:1px solid var(--border-color, #e5e7eb)"></a>'
                    + '<button type="button" class="btn-icon danger position-absolute" style="top:-6px;right:-6px;width:18px;height:18px;font-size:9px" title="Hapus foto" onclick="btDeletePhoto(\'' + p.delete_url + '\', this)"><i class="fa fa-xmark"></i></button></span>');
            });
        })
        .fail(function(xhr) { btFail(xhr, 'Gagal mengunggah foto.'); })
        .always(function() { input.value = ''; });
});

function btDeletePhoto(url, btn) {
    $.ajax({ url: url, method: 'DELETE', headers: btCsrf })
        .done(function(res) { toastr.success(res.message); $(btn).closest('span.position-relative').remove(); })
        .fail(function(xhr) { btFail(xhr, 'Gagal menghapus foto.'); });
}
</script>
@endsection
