@extends('layouts.app')

@section('title', 'Detail Retur Material')
@section('page-title', 'Detail Retur Material')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $fmtMoney = fn ($v) => \App\Support\StockCost::format($v);
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $return->return_number }}</h1>
        <p class="page-header-sub">{{ $return->return_date?->format('d/m/Y') }} &middot; {{ $return->branch?->name }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2 flex-wrap">
        <a href="{{ route('material-return.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
        @if($canDecide)
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="returReject()"><i class="fa fa-xmark me-1"></i> Tolak</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="returApprove()"><i class="fa fa-check me-1"></i> Setujui</button>
        @endif
    </div>
</div>

@if($return->status === \App\Models\MaterialReturn::STATUS_REJECTED)
<div class="alert alert-danger py-2" style="font-size:13px">
    <i class="fa fa-circle-xmark me-1"></i>Ditolak oleh {{ $return->rejecter?->display_name ?? '—' }} ({{ $return->rejected_at?->format('d/m/Y H:i') }}): {{ $return->reject_reason }}
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span>Informasi</span>
                {!! $return->statusBadgeHtml() !!}
            </div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>No. Retur</td><td class="fw-semibold" style="font-variant-numeric:tabular-nums">{{ $return->return_number }}</td></tr>
                    <tr><td>Branch</td><td>{{ $return->branch?->name }}</td></tr>
                    <tr><td>Tanggal</td><td>{{ $return->return_date?->format('d/m/Y') }}</td></tr>
                    <tr><td>Dicatat oleh</td><td>{{ $return->creator?->display_name ?? '—' }} <span style="color:var(--text-muted)">&middot; {{ $return->created_at?->format('d/m/Y H:i') }}</span></td></tr>
                    @if($return->approver)
                    <tr><td>Disetujui</td><td>{{ $return->approver->display_name }} <span style="color:var(--text-muted)">&middot; {{ $return->approved_at?->format('d/m/Y H:i') }}</span></td></tr>
                    @endif
                    @if($return->note)
                    <tr><td>Catatan</td><td>{{ $return->note }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Jumlah item</td><td class="text-end fw-semibold">{{ $return->items->count() }}</td></tr>
                    <tr><td>Total nilai</td><td class="text-end fw-semibold">{{ $fmtMoney($return->totalValue()) }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span>Material Diretur</span>
        <span style="font-size:12.5px;color:var(--text-muted)">Nilai {{ $fmtMoney($return->totalValue()) }}</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Barang</th>
                        <th>SN / Batch</th>
                        <th>Project Asal</th>
                        <th>Dari Gudang</th>
                        <th class="text-end" style="width:110px">Qty</th>
                        <th class="text-end" style="width:110px">Harga</th>
                        <th class="text-end" style="width:130px">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($return->items as $i => $item)
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold" style="color:var(--text-primary)">{{ $item->masterProduct?->code }}</div>
                                <div style="font-size:11.5px;color:var(--text-muted)">{{ $item->masterProduct?->name }}</div>
                            </td>
                            <td style="font-size:12.5px">{{ $item->stock?->serial_number ?: ($item->stock?->batch_number ?: '—') }}</td>
                            <td style="font-size:12.5px">{{ $item->project ? $item->project->project_number.' — '.$item->project->project_name : '—' }}</td>
                            <td>{{ $item->warehouse?->code }} — {{ $item->warehouse?->name }}</td>
                            <td class="text-end" style="font-variant-numeric:tabular-nums">{{ $fmtQty($item->qty) }} <span style="color:var(--text-muted);font-size:12px">{{ $item->unit }}</span></td>
                            <td class="text-end" style="font-variant-numeric:tabular-nums">{{ $fmtMoney($item->unit_cost) }}</td>
                            <td class="text-end" style="font-variant-numeric:tabular-nums">{{ $fmtMoney($item->value()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-2 pt-2" style="font-size:12px;color:var(--text-muted)">Harga dari harga modal saat diserahkan. Material kembali ke gudang asal sebagai stok bebas.</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function returPost(url, method, data, okMessage) {
    return $.ajax({ url: url, method: method, data: data || {}, headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
        .done(function(res) {
            toastr.success(res.message || okMessage);
            setTimeout(function() { window.location.reload(); }, 600);
        })
        .fail(function(xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memproses.';
            if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
            toastr.error(msg);
        });
}

function returApprove() {
    Swal.fire({ title: 'Setujui Retur Material?', text: 'Material akan kembali menjadi stok bebas di gudang asal.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Setujui', cancelButtonText: 'Batal' })
        .then(function(r) { if (r.isConfirmed) returPost('{{ route("material-return.approve", $return->id) }}', 'POST', {}, 'Disetujui.'); });
}

function returReject() {
    Swal.fire({ title: 'Tolak Retur Material?', input: 'text', inputLabel: 'Alasan penolakan', inputAttributes: { maxlength: 255 }, icon: 'warning', showCancelButton: true, confirmButtonText: 'Tolak', cancelButtonText: 'Batal',
        inputValidator: function(v) { if (!v) return 'Alasan wajib diisi.'; } })
        .then(function(r) { if (r.isConfirmed) returPost('{{ route("material-return.reject", $return->id) }}', 'POST', { reason: r.value }, 'Ditolak.'); });
}
</script>
@endsection
