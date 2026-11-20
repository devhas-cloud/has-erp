@extends('layouts.app')

@section('title', 'Detail Produksi')
@section('page-title', 'Detail Produksi')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $fmtMoney = fn ($v) => \App\Support\StockCost::format($v);
    $total = $production->totalCost();
    $stock = $outputStock ?? $production->outputStock;
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $production->production_number }}</h1>
        <p class="page-header-sub">{{ $production->project?->project_number }} — {{ $production->project?->project_name }} &middot; {{ $production->outputWarehouse?->branch_label }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a href="{{ route('production.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
        @if($canVoid && $canApprove)
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="moVoid()"><i class="fa fa-ban me-1"></i> Batalkan</button>
        @endif
    </div>
</div>

@if(! $production->isPosted())
<div class="alert alert-danger py-2" style="font-size:13px">
    <i class="fa fa-ban me-1"></i>Dibatalkan oleh {{ $production->canceller?->display_name ?? '—' }} ({{ $production->cancelled_at?->format('d/m/Y H:i') }}): {{ $production->cancel_reason }}
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span>
                {!! $production->statusBadgeHtml() !!}
            </div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Barang Jadi</td><td><strong>{{ $production->outputProduct?->code }}</strong> — {{ $production->outputProduct?->name }}</td></tr>
                    <tr><td>Qty</td><td>{{ $fmtQty($production->output_qty) }} {{ $production->outputProduct?->unit }}</td></tr>
                    <tr><td>Gudang</td><td>{{ $production->outputWarehouse?->code }} — {{ $production->outputWarehouse?->name }} <span style="color:var(--text-muted)">&middot; {{ $production->outputWarehouse?->branch_label }}</span></td></tr>
                    <tr><td>Project</td><td>{{ $production->project?->project_number }} — {{ $production->project?->project_name }}</td></tr>
                    <tr><td>Tanggal</td><td>{{ $production->production_date?->format('d/m/Y') }}</td></tr>
                    <tr><td>Dicatat</td><td>{{ $production->creator?->display_name ?? '—' }} <span style="color:var(--text-muted)">&middot; {{ $production->created_at?->format('d/m/Y H:i') }}</span></td></tr>
                    @if($production->note)
                    <tr><td>Catatan</td><td>{{ $production->note }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Biaya</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Total material</td><td class="text-end fw-semibold">{{ $fmtMoney($total) }}</td></tr>
                    <tr><td>Harga per unit</td><td class="text-end fw-semibold">{{ $fmtMoney($production->output_qty > 0 ? round($total / $production->output_qty, 2) : 0) }}</td></tr>
                    @if($stock && $production->isPosted())
                    <tr><td>{{ $stock->isSerialized() ? 'Stok tersisa' : 'Saldo di gudang' }}</td><td class="text-end">{{ $fmtQty($stock->qty) }} {{ $stock->unit }}</td></tr>
                    @endif
                </table>
                @if($production->isPosted() && ! $canVoid)
                    <div class="mt-2" style="font-size:12px;color:var(--text-muted)"><i class="fa fa-lock me-1"></i>Barang jadi sudah dipakai atau dipindah, jadi produksi tidak bisa dibatalkan.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Material Dipakai</span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Material</th>
                        <th>Serah Terima</th>
                        <th class="text-end" style="width:110px">Qty</th>
                        <th class="text-end" style="width:130px">Harga Modal</th>
                        <th class="text-end" style="width:130px">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($production->items as $i => $item)
                        @php $source = $item->handoverItem; @endphp
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold" style="color:var(--text-primary)">{{ $source?->stock?->masterProduct?->name }}</div>
                                <div style="font-size:11.5px;color:var(--text-muted)">{{ $source?->stock?->masterProduct?->code }} &middot; {{ $source?->stock?->identity_label }}</div>
                            </td>
                            <td>@if($source?->materialHandover)<a href="{{ route('material-handover.show', $source->materialHandover->id) }}">{{ $source->materialHandover->handover_number }}</a>@endif</td>
                            <td class="text-end">{{ $fmtQty($item->qty) }} <span style="color:var(--text-muted);font-size:12px">{{ $source?->stock?->unit }}</span></td>
                            <td class="text-end">{{ $fmtMoney($source?->unitCost()) }}</td>
                            <td class="text-end">{{ $fmtMoney($item->value()) }}</td>
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
function moVoid() {
    Swal.fire({ title: 'Batalkan produksi?', text: 'Barang jadi keluar dari stok dan material kembali menjadi Material di Produksi.', input: 'text', inputLabel: 'Alasan pembatalan', inputAttributes: { maxlength: 255 },
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Batalkan Produksi', cancelButtonText: 'Tutup',
        inputValidator: function(v) { if (!v) return 'Alasan wajib diisi.'; } })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $.ajax({ url: '{{ route("production.void", $production->id) }}', method: 'POST', data: { reason: r.value }, headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
                .done(function(res) { toastr.success(res.message || 'Dibatalkan.'); setTimeout(function() { window.location.reload(); }, 600); })
                .fail(function(xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal membatalkan.'); });
        });
}
</script>
@endsection
