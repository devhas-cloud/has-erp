@extends('layouts.app')

@section('title', 'Detail Serah Terima')
@section('page-title', 'Detail Serah Terima')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $fmtMoney = fn ($v) => \App\Support\StockCost::format($v);
    $mr = $handover->materialRequest;
    $openItems = $mr ? $mr->items->filter(fn ($item) => $item->remainingQty() > 0) : collect();
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $handover->handover_number }}</h1>
        <p class="page-header-sub">{{ $mr?->request_number }} &middot; {{ $mr?->project?->project_number }} — {{ $mr?->project?->project_name }} &middot; {{ $handover->handover_date?->format('d/m/Y') }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2 flex-wrap">
        <a href="{{ route('material-handover.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
        <a href="{{ route('material-handover.pdf', $handover->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">Cetak Bukti Serah Terima</a>
        @if($canContinue)
            <a href="{{ route('material-handover.create', ['material_request_id' => $mr->id]) }}" class="btn btn-primary btn-sm">Serah Terima Lagi</a>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span>Informasi</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Material Request</td><td>@if($mr)<a href="{{ route('material-request.show', $mr->id) }}" class="fw-semibold">{{ $mr->request_number }}</a> <span style="color:var(--text-muted)">&middot; peminta {{ $mr->creator?->display_name ?? '—' }}</span>@endif</td></tr>
                    <tr><td>Project</td><td>{{ $mr?->project?->project_number }} — {{ $mr?->project?->project_name }}</td></tr>
                    <tr><td>Branch</td><td>{{ $mr?->branch?->name }}</td></tr>
                    <tr><td>Tanggal</td><td>{{ $handover->handover_date?->format('d/m/Y') }}</td></tr>
                    <tr><td>Diserahkan oleh</td><td>{{ $handover->creator?->display_name ?? '—' }} <span style="color:var(--text-muted)">&middot; {{ $handover->created_at?->format('d/m/Y H:i') }}</span></td></tr>
                    <tr><td>Diterima oleh</td><td class="fw-semibold">{{ $handover->received_by_name ?: '—' }}</td></tr>
                    @if($handover->note)
                    <tr><td>Catatan</td><td>{{ $handover->note }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span>Sisa Permintaan</span>
                @if($mr){!! $mr->statusBadgeHtml() !!}@endif
            </div>
            <div class="card-body-custom p-2">
                @forelse($openItems as $item)
                    <div class="d-flex justify-content-between align-items-start px-2 py-1" style="border-bottom:1px solid var(--card-border);font-size:13px">
                        <span><span class="fw-semibold">{{ $item->masterProduct?->code }}</span> <span style="color:var(--text-muted)">{{ $item->masterProduct?->name }}</span></span>
                        <span class="text-nowrap" style="font-variant-numeric:tabular-nums">{{ $fmtQty($item->remainingQty()) }} <span style="color:var(--text-muted);font-size:12px">{{ $item->unit }}</span></span>
                    </div>
                @empty
                    <div class="px-2 py-3" style="font-size:13px;color:var(--text-muted)">Semua material Material Request ini sudah diserahkan.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span>Material Diserahkan</span>
        <span style="font-size:12.5px;color:var(--text-muted)">Nilai {{ $fmtMoney($handover->totalValue()) }}</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Barang</th>
                        <th>SN / Batch</th>
                        <th>Dari Gudang</th>
                        <th class="text-end" style="width:110px">Qty</th>
                        <th class="text-end" style="width:130px">Nilai</th>
                        <th class="text-end" style="width:120px">Sisa di Produksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($handover->items as $i => $item)
                        @php $left = $item->remainingQty(); @endphp
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold" style="color:var(--text-primary)">{{ $item->stock?->masterProduct?->code }}</div>
                                <div style="font-size:11.5px;color:var(--text-muted)">{{ $item->stock?->masterProduct?->name }}</div>
                            </td>
                            <td style="font-size:12.5px">{{ $item->stock?->serial_number ?: ($item->stock?->batch_number ?: '—') }}</td>
                            <td>{{ $item->stock?->warehouse?->code }} — {{ $item->stock?->warehouse?->name }}</td>
                            <td class="text-end" style="font-variant-numeric:tabular-nums">{{ $fmtQty($item->qty) }} <span style="color:var(--text-muted);font-size:12px">{{ $item->stock?->unit }}</span></td>
                            <td class="text-end" style="font-variant-numeric:tabular-nums">{{ $fmtMoney($item->value()) }}</td>
                            <td class="text-end">@if($left > 0){{ $fmtQty($left) }}@else<span style="color:var(--text-muted)">Terpakai</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-2 pt-2" style="font-size:12px;color:var(--text-muted)">Nilai dari harga modal saat diserahkan. Stok bebas yang diserahkan tercatat sebagai biaya project.</div>
    </div>
</div>
@endsection
