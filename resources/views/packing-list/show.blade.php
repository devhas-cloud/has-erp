@extends('layouts.app')

@section('title', 'Detail Packing List')
@section('page-title', 'Detail Packing List')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $boxCount = $pl->boxes->count();
@endphp

@section('styles')
<style>
    .pkl-sn { display: inline-block; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; padding: 1px 6px; margin: 2px 2px 0 0; border-radius: 5px; background: #f1f5f9; color: var(--text-secondary); }
    .pkl-detail td { border-top: 0 !important; padding-top: 0 !important; font-size: 12px; color: var(--text-secondary); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $pl->packing_number }}</h1>
        <p class="page-header-sub">{{ $pl->project?->project_number }} — {{ $pl->project?->project_name }} &middot; {{ $pl->packing_date?->format('d/m/Y') }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2 flex-wrap">
        <a href="{{ route('packing-list.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
        <div class="btn-group">
            <a href="{{ route('packing-list.pdf', $pl->id) }}" target="_blank" class="btn btn-secondary btn-sm"><i class="fa fa-file-pdf me-1"></i> PDF</a>
            <a href="{{ route('packing-list.pdf', [$pl->id, 'detail' => 1]) }}" target="_blank" class="btn btn-outline-secondary btn-sm" title="Dengan lampiran rincian SN / batch per koli">+ Rincian SN</a>
        </div>
        @if($pl->isDraft())
            @if($canUpdate)<a href="{{ route('packing-list.edit', $pl->id) }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-pen me-1"></i> Ubah</a>@endif
            @if($canDelete)<button type="button" class="btn btn-outline-danger btn-sm" onclick="pklAction('{{ route('packing-list.destroy', $pl->id) }}', 'DELETE', 'Hapus Packing List?', '{{ route('packing-list.index') }}')"><i class="fa fa-trash me-1"></i> Hapus</button>@endif
            @if($canCreate)<button type="button" class="btn btn-primary btn-sm" onclick="pklAction('{{ route('packing-list.ready', $pl->id) }}', 'POST', 'Tandai Siap Kirim? Barang di koli dipesan sampai dikirim lewat Delivery Order.')"><i class="fa fa-check me-1"></i> Siap Kirim</button>@endif
        @elseif($pl->isReady() && ! $pl->delivery_order_id && $canCreate)
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="pklAction('{{ route('packing-list.reopen', $pl->id) }}', 'POST', 'Buka kembali menjadi Draft? Pesanan stok dilepas.')"><i class="fa fa-rotate-left me-1"></i> Buka Kembali</button>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span>
                {!! $pl->statusBadgeHtml() !!}
            </div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>To</td><td class="fw-semibold">{{ $pl->recipient_name }}</td></tr>
                    <tr><td>Address</td><td style="white-space:pre-line">{{ $pl->recipient_address ?: '—' }}</td></tr>
                    <tr><td>Phone / Attn</td><td>{{ $pl->recipient_phone ?: '—' }} &middot; {{ $pl->recipient_attn ?: '—' }}</td></tr>
                    <tr><td>PO/SPK No</td><td>{{ $pl->customer_ref ?: '—' }} <span style="color:var(--text-muted)">&middot; {{ $pl->customer_ref_date?->format('d/m/Y') ?? '—' }}</span></td></tr>
                    <tr><td>Gudang</td><td>{{ $pl->warehouse?->code }} — {{ $pl->warehouse?->name }}</td></tr>
                    <tr><td>Delivery Order</td><td>@if($pl->deliveryOrder)<a href="{{ route('delivery-order.show', $pl->delivery_order_id) }}" class="fw-semibold">{{ $pl->deliveryOrder->do_number }}</a>@else<span style="color:var(--text-muted)">Belum dimuat</span>@endif</td></tr>
                    <tr><td>Dibuat</td><td>{{ $pl->creator?->display_name ?? '—' }} <span style="color:var(--text-muted)">&middot; {{ $pl->created_at?->format('d/m/Y H:i') }}</span></td></tr>
                    @if($pl->ready_at)
                    <tr><td>Siap Kirim</td><td>{{ $pl->readyBy?->display_name ?? '—' }} <span style="color:var(--text-muted)">&middot; {{ $pl->ready_at->format('d/m/Y H:i') }}</span></td></tr>
                    @endif
                    @if($pl->note)<tr><td>Catatan</td><td>{{ $pl->note }}</td></tr>@endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-chart-simple me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Total Package</td><td class="text-end fw-semibold">{{ $boxCount }} koli</td></tr>
                    <tr><td>Total berat</td><td class="text-end fw-semibold">{{ $fmtQty($pl->totalWeight()) }} kg</td></tr>
                    <tr><td>Marking</td><td class="text-end">{{ $pl->marking ?: '—' }}</td></tr>
                    <tr><td>Shipper</td><td class="text-end">{{ $pl->signer_name ?: '—' }}@if($pl->signer_position)<div style="font-size:11.5px;color:var(--text-muted)">{{ $pl->signer_position }}</div>@endif</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i>Koli</span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">No</th>
                        <th class="text-end" style="width:100px">Qty Order</th>
                        <th>Description</th>
                        <th class="text-center" style="width:90px">Box No.</th>
                        <th class="text-end" style="width:110px">Qty Kirim</th>
                        <th class="text-center" style="width:130px">Dimensi (cm)</th>
                        <th class="text-end" style="width:90px">Berat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pl->boxes as $i => $box)
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td class="text-end">{{ $box->qty_order !== null ? $fmtQty($box->qty_order).' '.$box->unit : '—' }}</td>
                            <td style="white-space:pre-line">{{ $box->description }}</td>
                            <td class="text-center">{{ $i + 1 }} of {{ $boxCount }}</td>
                            <td class="text-end fw-semibold">{{ $fmtQty($box->qty) }} {{ $box->unit }}</td>
                            <td class="text-center">{{ $box->dimensionLabel() ?? '—' }}</td>
                            <td class="text-end">{{ $box->weight_kg !== null ? $fmtQty($box->weight_kg).' kg' : '—' }}</td>
                        </tr>
                        <tr class="pkl-detail">
                            <td></td>
                            <td colspan="6">
                                <i class="fa fa-box-open me-1" style="color:var(--text-muted)"></i>
                                @foreach($box->items as $item)
                                    <span class="me-2">{{ $item->stock?->masterProduct?->code }} {{ $fmtQty($item->qty) }} {{ $item->unit }}@if($item->stock?->serial_number) <span class="pkl-sn">{{ $item->stock->serial_number }}</span>@elseif($item->stock?->batch_number) <span class="pkl-sn">{{ $item->stock->batch_number }}</span>@endif</span>
                                @endforeach
                            </td>
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
function pklAction(url, method, question, redirect) {
    Swal.fire({ title: question, icon: 'question', showCancelButton: true, confirmButtonText: 'Ya', cancelButtonText: 'Batal' }).then(function(r) {
        if (!r.isConfirmed) return;
        $.ajax({ url: url, method: method, headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
            .done(function(res) { toastr.success(res.message || 'Berhasil.'); setTimeout(function() { window.location.href = redirect || window.location.href; }, 600); })
            .fail(function(xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memproses.'); });
    });
}
</script>
@endsection
