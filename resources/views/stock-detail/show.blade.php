@extends('layouts.app')

@section('title', 'Riwayat Stok')
@section('page-title', 'Riwayat Stok')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $product = $stock->masterProduct;
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $product?->code }} &middot; {{ $stock->identity_label }}</h1>
        <p class="page-header-sub">{{ $product?->name }} — {{ $stock->isSerialized() ? 'Barang ber-SN' : 'Material (dihitung per jumlah)' }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('stock-detail.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-box me-2" style="color:var(--accent)"></i>Posisi Sekarang</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Sisa</td><td><strong>{{ $fmtQty($stock->qty) }}</strong> {{ $stock->unit }} <span style="color:var(--text-muted)">dari {{ $fmtQty($stock->qty_in) }} saat masuk</span>
                        @unless($stock->is_open)<span class="badge bg-secondary ms-1">Sudah keluar</span>@endunless</td></tr>
                    <tr><td>Gudang</td><td>{{ $stock->warehouse?->code }} — {{ $stock->warehouse?->name }}</td></tr>
                    <tr><td>Kepemilikan</td><td>{{ $stock->project ? 'Project '.$stock->project->project_number.' — '.$stock->project->project_name : 'Stok bebas' }}</td></tr>
                    <tr><td>Catatan</td><td>{{ $stock->note ?: '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-barcode me-2" style="color:var(--accent)"></i>Identitas</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>SN</td><td>{{ $stock->serial_number ?: ($stock->isSerialized() ? 'Belum diisi' : '—') }}</td></tr>
                    <tr><td>Batch / No. MFG</td><td>{{ $stock->batch_number ?: '—' }}</td></tr>
                    <tr><td>Tanggal masuk</td><td>{{ $stock->received_date?->format('d/m/Y') }}</td></tr>
                    <tr><td>Kedaluwarsa</td><td>{{ $stock->expiry_date?->format('d/m/Y') ?? '—' }}</td></tr>
                    <tr><td>Garansi supplier</td><td>{{ $stock->warranty_until ? 's/d '.$stock->warranty_until->format('d/m/Y') : '—' }}{{ $stock->supplier ? ' · '.$stock->supplier->name : '' }}</td></tr>
                    @if($lineage->isNotEmpty())
                        <tr><td>Asal pecahan</td><td>
                            @foreach($lineage as $origin)
                                <a href="{{ route('stock-detail.show', $origin->id) }}">#{{ $origin->id }}</a>@if(! $loop->last) → @endif
                            @endforeach
                        </td></tr>
                    @endif
                    @if($stock->children->isNotEmpty())
                        <tr><td>Dipecah ke</td><td>
                            @foreach($stock->children as $child)
                                <a href="{{ route('stock-detail.show', $child->id) }}">#{{ $child->id }}</a> ({{ $fmtQty($child->qty_in) }}, {{ $child->warehouse?->code }}{{ $child->project ? ' · '.$child->project->project_number : '' }})@if(! $loop->last), @endif
                            @endforeach
                        </td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--accent)"></i>Riwayat</span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kejadian</th>
                        <th>Dokumen</th>
                        <th>Gudang</th>
                        <th>Kepemilikan</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Sisa</th>
                        <th>Keterangan</th>
                        <th>Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->log_date?->format('d/m/Y') }}</td>
                            <td>{{ $log->type_label }}@if($log->stock_id !== $stock->id) <span style="font-size:11px;color:var(--text-muted)">(baris #{{ $log->stock_id }})</span>@endif</td>
                            <td>
                                @php [$docLabel, $docUrl] = $log->ref_document; @endphp
                                @if($docUrl)<a href="{{ $docUrl }}">{{ $docLabel }}</a>@else{{ $docLabel }}@endif
                            </td>
                            <td>{{ $log->warehouse?->code }}</td>
                            <td>{{ $log->project ? $log->project->project_number : 'Bebas' }}</td>
                            <td class="text-end {{ $log->qty < 0 ? 'text-danger' : '' }}">{{ $log->qty == 0 ? '—' : ($log->qty > 0 ? '+' : '').$fmtQty($log->qty) }}</td>
                            <td class="text-end">{{ $fmtQty($log->qty_after) }}</td>
                            <td style="font-size:12px">{{ $log->note }}</td>
                            <td style="font-size:12px">{{ $log->creator?->display_name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-4" style="color:var(--text-muted)">Belum ada riwayat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
