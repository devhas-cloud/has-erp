@extends('layouts.app')

@section('title', 'Detail Delivery Order')
@section('page-title', 'Detail Delivery Order')

@php $fmtQty = fn ($v) => \App\Support\StockCost::qty($v); @endphp

@section('styles')
<style>
    .do-sn { display: inline-block; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; padding: 1px 6px; margin: 2px 2px 0 0; border-radius: 5px; background: #f1f5f9; color: var(--text-secondary); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Delivery Order {{ $order->do_number }}</h1>
        <p class="page-header-sub">{{ $order->project?->project_number }} — {{ $order->project?->project_name }} &middot; {{ $order->delivery_date?->format('d/m/Y') }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('delivery-order.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="qt-action-bar">
    <span>{!! $order->statusBadgeHtml() !!} <span class="badge ms-1" style="background:#f1f5f9;color:var(--text-secondary)">{{ $order->uses_packing_list ? 'Dengan Packing List' : 'Langsung' }}</span></span>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('delivery-order.pdf', $order->id) }}" target="_blank" class="btn btn-secondary btn-sm"><i class="fa fa-file-pdf me-1"></i> Delivery Order</a>
        @foreach($order->packingLists as $pl)
            <a href="{{ route('packing-list.pdf', $pl->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa fa-file-pdf me-1"></i> {{ $pl->packing_number }}</a>
        @endforeach
        @if($order->isDraft())
            @if($canUpdate)
                <a href="{{ route('delivery-order.edit', $order->id) }}" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen me-1"></i> Edit</a>
            @endif
            @if($canDelete)
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="doDelete()"><i class="fa fa-trash me-1"></i> Hapus</button>
            @endif
            @if($canCreate)
                <button type="button" class="btn btn-primary btn-sm" id="do-post-btn" onclick="doPost()"><i class="fa fa-truck-fast me-1"></i> Posting (Barang Dikirim)</button>
            @endif
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Project</td><td>@if($order->project)<a href="{{ route('project.show', $order->project_id) }}" class="fw-semibold">{{ $order->project->project_number }} — {{ $order->project->project_name }}</a>@else — @endif</td></tr>
                    <tr><td>PO/SPK No</td><td>{{ $order->customer_ref ?: '—' }} <span style="color:var(--text-muted)">&middot; {{ $order->customer_ref_date?->format('d/m/Y') ?? '—' }}</span></td></tr>
                    <tr><td>Gudang Asal</td><td>{{ $order->warehouse ? $order->warehouse->code.' — '.$order->warehouse->name.' ('.$order->warehouse->branch_label.')' : '—' }}</td></tr>
                    <tr><td>Ekspedisi</td><td>{{ $order->courier ?: '—' }}</td></tr>
                    <tr><td>Cek By / Acknowledge / Send by</td><td>{{ $order->checked_by_name ?: '—' }} &middot; {{ $order->acknowledged_by_name ?: '—' }} &middot; {{ $order->sent_by_name ?: '—' }}</td></tr>
                    <tr><td>Dibuat Oleh</td><td>{{ $order->creator?->display_name ?? '—' }} &middot; {{ $order->created_at?->format('d/m/Y H:i') }}</td></tr>
                    @if($order->posted_at)
                    <tr><td>Diposting Oleh</td><td>{{ $order->postedBy?->display_name ?? '—' }} &middot; {{ $order->posted_at->format('d/m/Y H:i') }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-location-dot me-2" style="color:var(--accent)"></i>Penerima</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>To</td><td class="fw-semibold">{{ $order->recipient_name }}</td></tr>
                    <tr><td>Phone / Attn</td><td>{{ $order->recipient_phone ?: '—' }} &middot; {{ $order->recipient_attn ?: '—' }}</td></tr>
                    <tr><td>Address</td><td style="white-space:pre-line">{{ $order->shipping_address }}</td></tr>
                    <tr><td>Catatan</td><td>{{ $order->note ?: '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

@if($order->uses_packing_list)
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom"><span><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i>Packing List Dimuat</span></div>
    <div class="card-body-custom p-2">
        @forelse($order->packingLists as $pl)
            <a href="{{ route('packing-list.show', $pl->id) }}" class="d-flex justify-content-between align-items-center px-2 py-2 text-decoration-none" style="border-bottom:1px solid var(--card-border)">
                <span><strong style="color:var(--text-primary)">{{ $pl->packing_number }}</strong> <span style="font-size:12px;color:var(--text-muted)">&middot; {{ $pl->boxes->count() }} koli &middot; {{ $fmtQty($pl->totalWeight()) }} kg</span></span>
                {!! $pl->statusBadgeHtml() !!}
            </a>
        @empty
            <div class="text-center py-3" style="color:var(--text-muted);font-size:13px">Belum ada Packing List.</div>
        @endforelse
    </div>
</div>
@endif

<div class="card-custom fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Barang Dikirim</span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">No</th>
                        <th>Description</th>
                        <th class="text-end" style="width:110px">Qty</th>
                        <th style="width:240px">Serial Number / Part Number</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $i => $item)
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td style="white-space:pre-line">{{ $item->description }}</td>
                            <td class="text-end fw-semibold">{{ $fmtQty($item->qty) }} {{ $item->unit }}</td>
                            <td style="font-size:12.5px">
                                @if($order->uses_packing_list)
                                    {{ $item->reference ?: '—' }}
                                @else
                                    @foreach($item->stocks as $row)
                                        <div>
                                            <a href="{{ route('stock-detail.show', $row->stock_id) }}" class="do-sn text-decoration-none">{{ $row->stock?->serial_number ?: $row->stock?->masterProduct?->code }}</a>
                                            <span style="color:var(--text-muted)">{{ $fmtQty($row->qty) }} {{ $row->unit }}</span>
                                            @if($order->isDraft() && (! $row->stock || ! $row->stock->is_open || $row->stock->qty + 0.00005 < $row->qty))
                                                <span class="badge" style="background:var(--danger-soft);color:#7f1d1d">stok kurang</span>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
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
function doPost() {
    Swal.fire({
        title: 'Posting Delivery Order?',
        text: 'Barang dianggap sudah dikirim — stok project di gudang berkurang dan DO tidak bisa diubah lagi.',
        icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Posting', cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $('#do-post-btn').prop('disabled', true);
        $.ajax({ url: '{{ route("delivery-order.post", $order->id) }}', method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
            .done(function(res) { toastr.success(res.message || 'Diposting.'); setTimeout(function() { window.location.reload(); }, 700); })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memposting.');
                $('#do-post-btn').prop('disabled', false);
            });
    });
}

function doDelete() {
    Swal.fire({ title: 'Hapus Delivery Order?', text: 'Packing List yang dimuat kembali Siap Kirim.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $.ajax({ url: '{{ route("delivery-order.destroy", $order->id) }}', method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
                .done(function(res) { toastr.success(res.message || 'Dihapus.'); setTimeout(function() { window.location.href = '{{ route("delivery-order.index") }}'; }, 600); })
                .fail(function(xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.'); });
        });
}
</script>
@endsection
