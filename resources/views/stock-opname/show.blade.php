@extends('layouts.app')

@section('title', 'Detail Stock Opname')
@section('page-title', 'Detail Stock Opname')

@php
    $fmt = fn ($v) => \App\Support\StockValuation::format($v);
    $plusValue = $opname->items->sum(fn ($i) => max($i->difference(), 0) * (float) $i->unit_cost);
    $minusValue = $opname->items->sum(fn ($i) => max(-$i->difference(), 0) * (float) $i->unit_cost);
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Stock Opname {{ $opname->opname_number }}</h1>
        <p class="page-header-sub">{{ $opname->warehouse?->code }} — {{ $opname->warehouse?->name }} &middot; {{ $opname->opname_date?->format('d/m/Y') }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('stock-opname.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="qt-action-bar">
    <span>{!! $opname->statusBadgeHtml() !!}</span>
    @if($opname->isDraft())
    <div class="d-flex gap-2 flex-wrap">
        @if($canUpdate)
            <a href="{{ route('stock-opname.edit', $opname->id) }}" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen me-1"></i> Edit</a>
        @endif
        @if($canApprove)
            <button type="button" class="btn btn-primary btn-sm" id="so-post-btn" onclick="soPost()"><i class="fa fa-check-double me-1"></i> Posting Penyesuaian</button>
        @endif
    </div>
    @endif
</div>

@if($opname->isDraft())
<div class="alert alert-info py-2" style="font-size:13px">
    <i class="fa fa-circle-info me-1"></i> Qty sistem dan harga di bawah adalah kondisi <strong>saat ini</strong> dan dihitung ulang ketika diposting.
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Gudang</td><td>{{ $opname->warehouse ? $opname->warehouse->code.' — '.$opname->warehouse->name.' ('.$opname->warehouse->type_label.')' : '—' }}</td></tr>
                    <tr><td>Dibuat Oleh</td><td>{{ $opname->creator?->display_name ?? '—' }} &middot; {{ $opname->created_at?->format('d/m/Y H:i') }}</td></tr>
                    @if($opname->posted_at)
                    <tr><td>Diposting Oleh</td><td>{{ $opname->postedBy?->display_name ?? '—' }} &middot; {{ $opname->posted_at->format('d/m/Y H:i') }}</td></tr>
                    @endif
                    <tr><td>Catatan</td><td>{{ $opname->note ?: '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-scale-balanced me-2" style="color:var(--accent)"></i>Nilai Selisih ({{ \App\Models\Currency::baseName() }})</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Lebih (+)</td><td class="text-end">{{ $fmt($plusValue) }}</td></tr>
                    <tr><td>Kurang (−)</td><td class="text-end">{{ $fmt($minusValue) }}</td></tr>
                    <tr><td class="fw-semibold">Bersih</td><td class="text-end fw-semibold" style="color:{{ $plusValue - $minusValue < 0 ? 'var(--danger)' : 'inherit' }}">{{ $fmt($plusValue - $minusValue) }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-list-check me-2" style="color:var(--accent)"></i>Hasil Hitung</span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Kepemilikan</th>
                        <th class="text-center">Sistem</th>
                        <th class="text-center">Fisik</th>
                        <th class="text-center">Selisih</th>
                        <th class="text-end">Harga Rata-rata</th>
                        <th class="text-end">Nilai Selisih</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($opname->items as $item)
                        @php $diff = $item->difference(); @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold" style="font-size:13px">{{ $item->masterProduct?->name ?? '—' }}</div>
                                <div style="font-size:11px;color:var(--text-muted)">{{ $item->masterProduct?->code }}</div>
                            </td>
                            <td style="font-size:12px">{{ $item->project ? $item->project->project_number.' — '.$item->project->project_name : 'Stok bebas' }}</td>
                            <td class="text-center">{{ $item->system_qty }}</td>
                            <td class="text-center">{{ $item->counted_qty }}</td>
                            <td class="text-center fw-semibold" style="color:{{ $diff > 0 ? 'var(--accent)' : ($diff < 0 ? 'var(--danger)' : 'inherit') }}">{{ $diff > 0 ? '+'.$diff : $diff }}</td>
                            <td class="text-end">{{ $fmt($item->unit_cost) }}</td>
                            <td class="text-end">{{ $fmt($diff * (float) $item->unit_cost) }}</td>
                            <td>{{ $item->remark ?: '—' }}</td>
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
function soPost() {
    Swal.fire({
        title: 'Posting Penyesuaian?',
        text: 'Selisih hitung fisik akan dicatat sebagai penyesuaian stok. Dokumen tidak bisa diubah lagi.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Posting',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $('#so-post-btn').prop('disabled', true);
        $.ajax({ url: '{{ route("stock-opname.approve", $opname->id) }}', method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
            .done(function(res) { toastr.success(res.message || 'Diposting.'); setTimeout(function() { window.location.reload(); }, 700); })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memposting.');
                $('#so-post-btn').prop('disabled', false);
            });
    });
}
</script>
@endsection
