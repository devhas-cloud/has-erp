@extends('layouts.app')

@section('title', 'Detail Delivery Order')
@section('page-title', 'Detail Delivery Order')

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
    <span>{!! $order->statusBadgeHtml() !!}</span>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('delivery-order.pdf', $order->id) }}" target="_blank" class="btn btn-secondary btn-sm"><i class="fa fa-file-pdf me-1"></i> Surat Jalan</a>
        @if($order->isDraft())
            @if($canUpdate)
                <a href="{{ route('delivery-order.edit', $order->id) }}" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen me-1"></i> Edit</a>
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
            <div class="card-header-custom">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span>
            </div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Project</td><td>@if($order->project)<a href="{{ route('project.show', $order->project_id) }}" class="fw-semibold">{{ $order->project->project_number }} — {{ $order->project->project_name }}</a>@else — @endif</td></tr>
                    <tr><td>Customer</td><td>{{ $order->project?->company?->account_name ?? '—' }}</td></tr>
                    <tr><td>Gudang Asal</td><td>{{ $order->warehouse ? $order->warehouse->code.' — '.$order->warehouse->name.' ('.$order->warehouse->type_label.')' : '—' }}</td></tr>
                    <tr><td>Ekspedisi</td><td>{{ $order->courier ?: '—' }}</td></tr>
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
            <div class="card-header-custom">
                <span><i class="fa-solid fa-location-dot me-2" style="color:var(--accent)"></i>Penerima</span>
            </div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Nama</td><td class="fw-semibold">{{ $order->recipient_name }}</td></tr>
                    <tr><td>Telepon</td><td>{{ $order->recipient_phone ?: '—' }}</td></tr>
                    <tr><td>Alamat</td><td style="white-space:pre-line">{{ $order->shipping_address }}</td></tr>
                    <tr><td>Catatan</td><td>{{ $order->note ?: '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Barang Dikirim</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Kode</th>
                        <th>Nama Produk</th>
                        <th class="text-center" style="width:90px">Qty</th>
                        <th style="width:90px">Satuan</th>
                        <th>Keterangan</th>
                        @if($order->isDraft())<th class="text-center" style="width:120px">Stok Project</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $i => $item)
                        @php $available = $balances[$item->master_product_id]['balance'] ?? 0; @endphp
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td>{{ $item->masterProduct?->code ?? '—' }}</td>
                            <td>{{ $item->masterProduct?->name ?? '—' }}</td>
                            <td class="text-center fw-semibold">{{ $item->qty }}</td>
                            <td>{{ $item->unit ?: '—' }}</td>
                            <td>{{ $item->remark ?: '—' }}</td>
                            @if($order->isDraft())
                                <td class="text-center">
                                    @if($available >= $item->qty)
                                        <span class="badge" style="background:var(--accent-soft);color:var(--accent)">{{ $available }}</span>
                                    @else
                                        <span class="badge" style="background:var(--danger-soft);color:#7f1d1d" title="Stok tidak cukup untuk posting">{{ $available }} — kurang</span>
                                    @endif
                                </td>
                            @endif
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
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Posting',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $('#do-post-btn').prop('disabled', true);
        $.ajax({ url: '{{ route("delivery-order.post", $order->id) }}', method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
            .done(function(res) { toastr.success(res.message || 'Diposting.'); setTimeout(function() { window.location.reload(); }, 700); })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memposting.');
                $('#do-post-btn').prop('disabled', false);
            });
    });
}
</script>
@endsection
