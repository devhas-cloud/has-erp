@extends('layouts.app')

@section('title', 'Branch Transfer '.$transfer->transfer_number)
@section('page-title', 'Branch Transfer')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $receiving = $transfer->isInTransit() && $canReceive && $canCreate;
    $isMaterial = fn ($item) => (bool) $item->stock?->masterProduct?->isMaterial();
    $materialCount = $transfer->items->filter($isMaterial)->count();
    $serialCount = $transfer->items->count() - $materialCount;
    // Kartu stok saldo material di gudang tujuan (setelah diterima).
    $cardUrl = fn ($item) => route('stock-report.card', $item->stock->master_product_id).'?'.http_build_query([
        'warehouse_id' => $transfer->to_warehouse_id, 'ownership' => $item->stock->project_id ?: 'free',
    ]);
@endphp

@section('styles')
<style>
    .bt-kind { font-size: 10px; font-weight: 600; padding: 1px 7px; border-radius: 999px; white-space: nowrap; }
    .bt-kind-material { background: #e0f2fe; color: #075985; }
    .bt-kind-serial { background: #fef3c7; color: #92400e; }
    .bt-sub { font-size: 11.5px; color: var(--text-muted); }
    .bt-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; font-size: 12px; color: var(--text-muted); padding: 8px 6px 0; }

    /* Langkah status */
    .bt-steps { display: flex; align-items: flex-start; gap: 0; margin-bottom: 16px; }
    .bt-stepper-item { flex: 1; display: flex; align-items: flex-start; gap: 10px; position: relative; }
    .bt-stepper-item:not(:last-child)::after { content: ''; position: absolute; top: 14px; left: 38px; right: 10px; height: 2px; background: var(--card-border, #e5e7eb); }
    .bt-stepper-item.done:not(:last-child)::after { background: var(--accent); }
    .bt-dot { width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; background: var(--card, #fff); border: 2px solid var(--card-border, #e5e7eb); color: var(--text-muted); position: relative; z-index: 1; }
    .bt-stepper-item.done .bt-dot { background: var(--accent); border-color: var(--accent); color: #fff; }
    .bt-stepper-item.current .bt-dot { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 0 4px var(--accent-subtle, #ecfdf5); }
    .bt-step-text { background: var(--bg, #f8fafc); padding-right: 8px; position: relative; z-index: 1; }
    .bt-step-title { font-size: 13px; font-weight: 600; }
    .bt-step-meta { font-size: 11.5px; color: var(--text-muted); }
    @media (max-width: 767.98px) { .bt-steps { flex-direction: column; gap: 10px; } .bt-stepper-item::after { display: none; } }

    /* Rute ringkas */
    .bt-strip { border: 1px solid var(--card-border, #e5e7eb); border-radius: 10px; background: var(--card, #fff); padding: 8px 14px; }
    .bt-strip-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-size: 13px; }
    .bt-strip-side { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .bt-strip-label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); font-weight: 600; }
    .bt-strip-arrow { color: var(--accent); }
    .bt-strip-select { width: auto; min-width: 220px; }
    .bt-strip-select:invalid, .bt-strip-select.needs-value { border-color: #f59e0b; }
    .bt-strip-toggle { font-size: 12px; text-decoration: none; white-space: nowrap; }
    .bt-strip-toggle .fa-chevron-down { transition: transform .2s; font-size: 10px; }
    .bt-strip-toggle.collapsed .fa-chevron-down { transform: rotate(-90deg); }
    .bt-dot-mini { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--accent); vertical-align: middle; }
    .bt-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px 16px; margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--card-border, #e5e7eb); }
    @media (max-width: 991.98px) { .bt-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .bt-fact .k { font-size: 11px; color: var(--text-muted); }
    .bt-fact .v { font-size: 12.5px; }

    /* Tabel */
    .bt-items th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    .bt-items tr.is-matched td { background: #f0fdf4; }
    .bt-items tr.is-mismatch td { background: #fef2f2; }

    /* Bilah aksi penerimaan */
    .bt-actionbar { position: sticky; bottom: 0; z-index: 20; margin-top: 16px; background: var(--card, #fff); border: 1px solid var(--card-border, #e5e7eb); border-radius: 10px; box-shadow: 0 -6px 18px rgba(15,23,42,.08); padding: 10px 14px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bt-ab-stat { text-align: center; min-width: 70px; }
    .bt-ab-stat .n { font-size: 17px; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .bt-ab-stat .l { font-size: 11px; color: var(--text-muted); }
    .bt-ab-actions { display: flex; gap: 8px; margin-left: auto; flex-wrap: wrap; }
</style>
@endsection

@php
    $steps = [
        ['title' => 'Draft', 'meta' => ($transfer->creator?->display_name ?? '—').' · '.$transfer->created_at?->format('d/m/Y H:i'), 'done' => true],
        ['title' => 'Dikirim', 'meta' => $transfer->shipped_at ? ($transfer->shippedBy?->display_name ?? '—').' · '.$transfer->shipped_at->format('d/m/Y H:i') : 'Menunggu dikirim pengirim', 'done' => (bool) $transfer->shipped_at],
        ['title' => 'Diterima', 'meta' => $transfer->received_at ? ($transfer->receivedBy?->display_name ?? '—').' · '.$transfer->received_at->format('d/m/Y H:i') : ($transfer->shipped_at ? 'Menunggu diperiksa penerima' : '—'), 'done' => (bool) $transfer->received_at],
    ];
    $currentStep = $transfer->received_at ? -1 : ($transfer->shipped_at ? 2 : 1);
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title d-flex align-items-center gap-2 flex-wrap">Branch Transfer {{ $transfer->transfer_number }} {!! $transfer->statusBadgeHtml() !!}</h1>
        <p class="page-header-sub">{{ $transfer->fromBranch?->name }} &rarr; {{ $transfer->toBranch?->name }} &middot; tanggal kirim {{ $transfer->transfer_date?->format('d/m/Y') }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2 flex-wrap">
        <a href="{{ route('branch-transfer.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
        @unless($transfer->isDraft())
            <a href="{{ route('branch-transfer.pdf', $transfer->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-print me-1"></i> Surat Jalan</a>
        @endunless
        @if($transfer->isDraft() && $canSend)
            @if($canUpdate || $canDelete)
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis me-1"></i> Lainnya</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @if($canUpdate)<li><a class="dropdown-item" href="{{ route('branch-transfer.edit', $transfer->id) }}"><i class="fa-solid fa-pen me-2"></i>Edit draft</a></li>@endif
                        @if($canDelete)<li><button type="button" class="dropdown-item text-danger" onclick="btDelete()"><i class="fa-solid fa-trash-can me-2"></i>Hapus draft</button></li>@endif
                    </ul>
                </div>
            @endif
            @if($canCreate)
                <button type="button" class="btn btn-primary btn-sm" id="bt-ship-btn" onclick="btShip()"><i class="fa-solid fa-truck-fast me-1"></i> Kirim Barang</button>
            @endif
        @endif
    </div>
</div>

{{-- Langkah status --}}
<div class="bt-steps fade-in">
    @foreach($steps as $n => $step)
        <div class="bt-stepper-item {{ $step['done'] ? 'done' : '' }} {{ $currentStep === $n ? 'current' : '' }}">
            <div class="bt-dot">{!! $step['done'] ? '<i class="fa fa-check"></i>' : $n + 1 !!}</div>
            <div class="bt-step-text">
                <div class="bt-step-title">{{ $step['title'] }}</div>
                <div class="bt-step-meta">{{ $step['meta'] }}</div>
            </div>
        </div>
    @endforeach
</div>

@if($transfer->isDraft() && $canSend)
<div class="alert alert-info py-2 mb-3" style="font-size:13px">
    <i class="fa fa-circle-info me-1"></i> Masih draft — stok belum keluar. Periksa barang di bawah, lalu klik <strong>Kirim Barang</strong> saat barang berangkat dan cetak surat jalan.
</div>
@elseif($transfer->isInTransit() && ! $receiving)
<div class="alert alert-warning py-2 mb-3" style="font-size:13px">
    <i class="fa-solid fa-truck-fast me-1"></i> Dalam pengiriman sejak {{ $transfer->shipped_at?->format('d/m/Y H:i') }}. Stok sudah keluar dari {{ $transfer->fromWarehouse?->name }} dan menunggu diperiksa {{ $transfer->toBranch?->name }}.
</div>
@endif

{{-- Rute ringkas: Dari -> Ke (gudang tujuan dipilih di sini saat menerima); detail pengiriman dilipat. --}}
@php $hasDocInfo = (bool) ($transfer->courier || $transfer->vehicle_no || $transfer->driver_name || $transfer->note || $transfer->receive_note); @endphp
<div class="bt-strip fade-in mb-3">
    <div class="bt-strip-row">
        <div class="bt-strip-side">
            <span class="bt-strip-label">Dari</span>
            <strong>{{ $transfer->fromBranch?->name }}</strong>
            <span class="bt-sub">· {{ $transfer->fromWarehouse?->code ?? '—' }}</span>
        </div>
        <i class="fa-solid fa-arrow-right-long bt-strip-arrow"></i>
        <div class="bt-strip-side">
            <span class="bt-strip-label">Ke</span>
            <strong>{{ $transfer->toBranch?->name }}</strong>
            @if($receiving)
                <select id="bt-to-warehouse" class="form-select form-select-sm bt-strip-select" aria-label="Gudang tujuan">
                    <option value="">— Pilih gudang tujuan —</option>
                    @foreach($destinationWarehouses as $w)
                        <option value="{{ $w->id }}" @selected($transfer->to_warehouse_id === $w->id)>{{ $w->code }} — {{ $w->name }}</option>
                    @endforeach
                </select>
                @if($destinationWarehouses->isEmpty())
                    <small class="text-danger">belum punya gudang aktif</small>
                @endif
            @else
                <span class="bt-sub">· {{ $transfer->toWarehouse?->code ?? 'gudang dipilih saat diterima' }}</span>
            @endif
        </div>
        <a class="bt-strip-toggle ms-auto collapsed" data-bs-toggle="collapse" href="#bt-docinfo" role="button" aria-expanded="false">
            Detail pengiriman @if($hasDocInfo)<span class="bt-dot-mini"></span>@endif <i class="fa fa-chevron-down ms-1"></i>
        </a>
    </div>
    <div class="collapse" id="bt-docinfo">
        <div class="bt-facts">
            <div class="bt-fact"><div class="k">Ekspedisi</div><div class="v">{{ $transfer->courier ?: '—' }}</div></div>
            <div class="bt-fact"><div class="k">No. Kendaraan</div><div class="v">{{ $transfer->vehicle_no ?: '—' }}</div></div>
            <div class="bt-fact"><div class="k">Pengemudi / Pembawa</div><div class="v">{{ $transfer->driver_name ?: '—' }}</div></div>
            <div class="bt-fact"><div class="k">Catatan pengirim</div><div class="v">{{ $transfer->note ?: '—' }}</div></div>
            <div class="bt-fact" style="grid-column:1/-1">
                <div class="k">Catatan penerimaan</div>
                @if($receiving)
                    <input type="text" id="bt-receive-note" class="form-control form-control-sm" maxlength="2000" value="{{ $transfer->receive_note }}" placeholder="Opsional">
                @else
                    <div class="v">{{ $transfer->receive_note ?: '—' }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i>Barang
            <span class="bt-sub ms-2">
                @if($materialCount)<span class="bt-kind bt-kind-material">{{ $materialCount }} material</span>@endif
                @if($serialCount)<span class="bt-kind bt-kind-serial">{{ $serialCount }} unit SN</span>@endif
            </span>
        </span>
        @if($receiving)
            <span class="bt-sub ms-auto me-2 d-none d-lg-inline">Isi qty diterima &amp; centang kondisi baik per barang — selesaikan bila semua sesuai</span>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="btFillAll()" title="Isi qty diterima = qty dikirim dan centang kondisi baik untuk semua barang"><i class="fa fa-check-double me-1"></i> Tandai Semua Sesuai</button>
        @endif
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0 bt-items">
                <thead>
                    <tr>
                        <th style="width:40px" class="text-center">#</th>
                        <th>Barang</th>
                        <th style="width:220px">Stok</th>
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
                        <tr data-item-id="{{ $item->id }}" data-qty="{{ $item->qty }}" class="{{ $state === 'matched' ? 'is-matched' : ($state === 'mismatch' ? 'is-mismatch' : '') }}">
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold" style="font-size:13px">{{ $stock?->masterProduct?->name ?? '—' }}</div>
                                <div class="bt-sub">{{ $stock?->masterProduct?->code }}
                                    @if($stock)<span class="bt-kind {{ $isMaterial($item) ? 'bt-kind-material' : 'bt-kind-serial' }} ms-1">{{ $isMaterial($item) ? 'Material' : 'Ber-SN' }}</span>@endif
                                </div>
                                <div class="bt-sub">{{ $stock?->project_id ? 'Milik project '.$stock->project?->project_number : 'Stok bebas' }}</div>
                            </td>
                            <td style="font-size:12.5px">
                                @if($stock && $isMaterial($item))
                                    {{-- Material: diambil dari saldo gudang asal, masuk ke saldo gudang tujuan. --}}
                                    <div>Dari saldo {{ $transfer->fromWarehouse?->code }}</div>
                                    @if($item->received_stock_id && $transfer->to_warehouse_id)
                                        <div class="bt-sub"><i class="fa fa-arrow-right me-1" style="color:var(--accent)"></i>Masuk saldo {{ $transfer->toWarehouse?->code }} · <a href="{{ $cardUrl($item) }}">kartu stok</a></div>
                                    @elseif(! $transfer->isDraft())
                                        <div class="bt-sub">Menambah saldo gudang tujuan saat diterima</div>
                                    @endif
                                @elseif($stock)
                                    <a href="{{ route('stock-detail.show', $stock->id) }}">{{ $stock->serial_number ? 'SN '.$stock->serial_number : $stock->identity_label }}</a>
                                    @if(! $stock->serial_number)<div style="font-size:11px;color:#b45309">SN belum diisi</div>@endif
                                    @if($item->received_stock_id)<div class="bt-sub">unit yang sama tercatat di {{ $transfer->toWarehouse?->code }}</div>@endif
                                @endif
                            </td>
                            <td class="text-center fw-semibold">{{ $fmtQty($item->qty) }} <span class="bt-sub">{{ $item->unit }}</span></td>
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
        <div class="bt-legend">
            <span><span class="bt-kind bt-kind-material">Material</span> diambil dari saldo gudang asal dan menambah saldo gudang tujuan (harga saat dikirim)</span>
            <span><span class="bt-kind bt-kind-serial">Ber-SN</span> unit yang sama pindah gudang</span>
        </div>
    </div>
</div>

@if($receiving)
{{-- Bilah aksi pemeriksaan --}}
<div class="bt-actionbar" id="bt-actionbar">
    <div class="d-flex gap-3">
        <div class="bt-ab-stat"><div class="n" id="bt-ab-checked">0/{{ $transfer->items->count() }}</div><div class="l">Diperiksa</div></div>
        <div class="bt-ab-stat"><div class="n" id="bt-ab-ok" style="color:var(--accent)">0</div><div class="l">Sesuai</div></div>
        <div class="bt-ab-stat"><div class="n" id="bt-ab-bad" style="color:var(--danger)">0</div><div class="l">Tidak sesuai</div></div>
    </div>
    <div class="bt-sub" id="bt-ab-hint"></div>
    <div class="bt-ab-actions">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="bt-inspect-btn" onclick="btInspect(false)"><i class="fa fa-save me-1"></i> Simpan Pemeriksaan</button>
        <button type="button" class="btn btn-primary btn-sm" id="bt-receive-btn" onclick="btInspect(true)"><i class="fa fa-box-open me-1"></i> Selesaikan Penerimaan</button>
    </div>
</div>
@endif
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
    btLiveState();
}

// Status pemeriksaan langsung terlihat (sebelum disimpan): sesuai = qty sama & kondisi baik.
function btLiveState() {
    var total = 0, checked = 0, ok = 0, bad = 0;
    $('tr[data-item-id]').each(function() {
        var $input = $(this).find('.bt-received');
        if (!$input.length) return;
        total++;
        var raw = $input.val();
        var $state = $(this).find('.bt-state');
        $(this).removeClass('is-matched is-mismatch');
        if (raw === '') { $state.html('<span style="color:var(--text-muted)">Belum diperiksa</span>'); return; }
        checked++;
        var qty = Num.parseOrNull(raw) || 0;
        var match = Math.abs(qty - parseFloat($(this).data('qty'))) < 0.00005 && $(this).find('.bt-condition').is(':checked');
        if (match) { ok++; $(this).addClass('is-matched'); $state.html('<span style="color:var(--accent)"><i class="fa fa-circle-check"></i> Sesuai</span>'); }
        else { bad++; $(this).addClass('is-mismatch'); $state.html('<span class="text-danger"><i class="fa fa-circle-xmark"></i> Tidak sesuai</span>'); }
    });
    $('#bt-to-warehouse').toggleClass('needs-value', !$('#bt-to-warehouse').val());
    $('#bt-ab-checked').text(checked + '/' + total);
    $('#bt-ab-ok').text(ok);
    $('#bt-ab-bad').text(bad);
    var ready = total > 0 && ok === total && $('#bt-to-warehouse').val();
    $('#bt-receive-btn').toggleClass('btn-primary', !!ready).toggleClass('btn-outline-primary', !ready);
    $('#bt-ab-hint').html(!$('#bt-to-warehouse').val() ? '<i class="fa fa-warehouse me-1"></i>Pilih gudang tujuan dulu'
        : (bad ? '<span class="text-danger">' + bad + ' barang tidak sesuai — penerimaan belum bisa diselesaikan</span>'
        : (checked < total ? (total - checked) + ' barang belum diperiksa' : '<span style="color:var(--accent)"><i class="fa fa-circle-check me-1"></i>Semua sesuai, siap diterima</span>')));
}

$(document).on('input change', '.bt-received, .bt-condition, #bt-to-warehouse', btLiveState);
$(function() { if ($('#bt-actionbar').length) btLiveState(); });

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
        Swal.fire({ title: 'Selesaikan penerimaan?', text: 'Semua barang masuk ke gudang tujuan: material menambah saldo gudang tujuan, barang ber-SN tercatat di gudang tujuan. Dokumen tidak bisa diubah lagi.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Terima', cancelButtonText: 'Batal' })
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
