@extends('layouts.app')

@section('title', 'Detail Serah Terima')
@section('page-title', 'Detail Serah Terima')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $fmtMoney = fn ($v) => \App\Support\StockCost::format($v);
    $mr = $handover->materialRequest;
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $handover->handover_number }}</h1>
        <p class="page-header-sub">{{ $mr?->project?->project_number }} — {{ $mr?->project?->project_name }} &middot; {{ $mr?->branch?->name }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('material-handover.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Material Request</td><td>@if($mr)<a href="{{ route('material-request.show', $mr->id) }}" class="fw-semibold">{{ $mr->request_number }}</a>@endif</td></tr>
                    <tr><td>Project</td><td>{{ $mr?->project?->project_number }} — {{ $mr?->project?->project_name }}</td></tr>
                    <tr><td>Branch</td><td>{{ $mr?->branch?->name }}</td></tr>
                    <tr><td>Tanggal</td><td>{{ $handover->handover_date?->format('d/m/Y') }}</td></tr>
                    <tr><td>Diserahkan</td><td>{{ $handover->creator?->display_name ?? '—' }} <span style="color:var(--text-muted)">&middot; {{ $handover->created_at?->format('d/m/Y H:i') }}</span></td></tr>
                    @if($handover->note)
                    <tr><td>Catatan</td><td>{{ $handover->note }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-chart-simple me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Baris stok</td><td class="text-end fw-semibold">{{ $handover->items->count() }}</td></tr>
                    <tr><td>Nilai material</td><td class="text-end fw-semibold">{{ $fmtMoney($handover->totalValue()) }}</td></tr>
                </table>
                <div class="mt-2" style="font-size:12px;color:var(--text-muted)">Nilai dari harga modal baris stok. Stok bebas yang diserahkan tercatat sebagai biaya project.</div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Material Diserahkan</span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Barang</th>
                        <th>Dari Gudang</th>
                        <th class="text-end" style="width:110px">Qty</th>
                        <th class="text-end" style="width:130px">Nilai</th>
                        <th class="text-end" style="width:120px">Sisa di Produksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($handover->items as $i => $item)
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold" style="color:var(--text-primary)">{{ $item->stock?->masterProduct?->name }}</div>
                                <div style="font-size:11.5px;color:var(--text-muted)">{{ $item->stock?->masterProduct?->code }} &middot; {{ $item->stock?->identity_label }}</div>
                            </td>
                            <td>{{ $item->stock?->warehouse?->code }} — {{ $item->stock?->warehouse?->name }}</td>
                            <td class="text-end">{{ $fmtQty($item->qty) }} <span style="color:var(--text-muted);font-size:12px">{{ $item->stock?->unit }}</span></td>
                            <td class="text-end">{{ $fmtMoney($item->value()) }}</td>
                            <td class="text-end">
                                @php $left = $item->remainingQty(); @endphp
                                @if($left > 0){{ $fmtQty($left) }}@else<span style="color:var(--text-muted)">Terpakai</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
