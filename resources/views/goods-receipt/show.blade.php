@extends('layouts.app')

@section('title', 'Detail Penerimaan Barang')
@section('page-title', 'Detail Penerimaan Barang')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $sourceUrl = $receipt->purchase_order_id
        ? route('purchase-order.show', $receipt->purchase_order_id)
        : ($receipt->direct_purchase_id ? route('direct-purchase.show', $receipt->direct_purchase_id) : null);
    $toStock = $receipt->items->where('destination', '!=', 'direct');
    $direct = $receipt->items->where('destination', 'direct');
    $missingTotal = $toStock->sum(fn ($item) => $item->stocks->filter(fn ($st) => ! $st->canLeave())->count());
@endphp

@section('styles')
<style>
    .gr-stat { border-radius: var(--radius-sm); padding: 12px 14px; background: var(--accent-subtle); border: 1px solid var(--card-border); }
    .gr-stat .label { font-size: 11.5px; color: var(--text-muted); }
    .gr-stat .value { font-size: 20px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.2; }
    .gr-stat.info { background: var(--info-soft); }
    .gr-sn { display: inline-block; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; padding: 1px 6px; margin: 2px 2px 0 0; border-radius: 5px; background: #f1f5f9; color: var(--text-secondary); }
    .gr-detail-row td { border-top: 0 !important; padding-top: 0 !important; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $receipt->receipt_number ?: 'Penerimaan #'.$receipt->id }}</h1>
        <p class="page-header-sub">{{ $receipt->supplierName() ?? '—' }} &middot; {{ $receipt->received_date?->format('d M Y') ?? '—' }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('goods-receipt.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span>
                {!! $receipt->statusBadgeHtml() !!}
            </div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr>
                        <td>Dokumen</td>
                        <td>
                            @if($sourceUrl)<a href="{{ $sourceUrl }}" class="fw-semibold">{{ $receipt->sourceNumber() }}</a>@else{{ $receipt->sourceNumber() }}@endif
                            <span style="color:var(--text-muted)">&middot; {{ $receipt->supplierName() ?? '—' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>Gudang</td>
                        <td>
                            @if($receipt->warehouse)
                                {{ $receipt->warehouse->code }} — {{ $receipt->warehouse->name }}
                                <span style="color:var(--text-muted)">&middot; {{ $receipt->warehouse->branch_label }}</span>
                            @else
                                <span style="color:var(--text-muted)">Semua langsung pakai</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>Tanggal Terima</td>
                        <td>{{ $receipt->received_date?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td>Dicatat</td>
                        <td>{{ $receipt->creator?->display_name ?? '—' }} <span style="color:var(--text-muted)">&middot; {{ $receipt->created_at?->format('d/m/Y H:i') }}</span></td>
                    </tr>
                    @if($receipt->note)
                    <tr>
                        <td>Catatan</td>
                        <td>{{ $receipt->note }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-chart-simple me-2" style="color:var(--accent)"></i>Ringkasan</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="gr-stat">
                            <div class="label">Masuk Gudang</div>
                            <div class="value">{{ $toStock->count() }}</div>
                            <div class="label">barang</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="gr-stat info">
                            <div class="label">Langsung Pakai</div>
                            <div class="value">{{ $direct->count() }}</div>
                            <div class="label">barang</div>
                        </div>
                    </div>
                </div>
                @if($missingTotal > 0)
                    <a href="{{ route('stock-detail.index', ['goods_receipt_id' => $receipt->id]) }}" class="d-flex justify-content-between align-items-center mt-3 px-3 py-2 text-decoration-none"
                       style="border-radius:var(--radius-sm);background:var(--warning-soft);color:#92400e;font-size:13px">
                        <span><i class="fa fa-barcode me-1"></i>{{ $missingTotal }} unit SN belum diisi</span>
                        <span class="fw-semibold">Lengkapi <i class="fa fa-arrow-right ms-1"></i></span>
                    </a>
                @else
                    <div class="mt-3 px-3 py-2" style="border-radius:var(--radius-sm);background:#f8fafc;color:var(--text-muted);font-size:12.5px">
                        <i class="fa fa-circle-check me-1" style="color:var(--accent)"></i>Data barang sudah lengkap
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Barang Diterima</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Barang</th>
                        <th>Untuk</th>
                        <th class="text-end" style="width:110px">Qty</th>
                        <th style="width:140px">Tujuan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipt->items as $i => $item)
                        @php
                            $isStock = $item->destination !== 'direct';
                            $serials = $isStock ? $item->stocks->pluck('serial_number')->filter() : collect();
                            $missing = $isStock ? $item->stocks->filter(fn ($st) => ! $st->canLeave())->count() : 0;
                            $hasDetail = $item->batch_number || $item->expiry_date || $item->warranty_until || $item->note || $serials->isNotEmpty() || $missing;
                        @endphp
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold" style="color:var(--text-primary)">{{ $item->masterProduct?->name ?: '—' }}</div>
                                <div style="font-size:11.5px;color:var(--text-muted)">{{ $item->masterProduct?->code ?: '—' }}</div>
                            </td>
                            <td>
                                @if($item->project)
                                    <span class="badge" style="background:#fef3c7;color:#92400e;white-space:normal">{{ $item->project->project_number }} — {{ $item->project->project_name }}</span>
                                @else
                                    <span style="color:var(--text-muted)">Stok bebas</span>
                                @endif
                            </td>
                            <td class="text-end"><strong>{{ $fmtQty($item->qty) }}</strong> <span style="color:var(--text-muted);font-size:12px">{{ $item->unit }}</span></td>
                            <td>
                                @if($isStock)
                                    <span class="badge" style="background:var(--accent-soft);color:var(--accent-hover)"><i class="fa fa-warehouse me-1"></i>Masuk Gudang</span>
                                @else
                                    <span class="badge" style="background:var(--info-soft);color:#4338ca"><i class="fa fa-hand-holding me-1"></i>Langsung Pakai</span>
                                @endif
                            </td>
                        </tr>
                        @if($hasDetail)
                            <tr class="gr-detail-row">
                                <td></td>
                                <td colspan="4" style="font-size:12px;color:var(--text-secondary)">
                                    @if($item->batch_number)<span class="me-3"><span style="color:var(--text-muted)">Batch</span> {{ $item->batch_number }}</span>@endif
                                    @if($item->expiry_date)<span class="me-3"><span style="color:var(--text-muted)">Exp</span> {{ $item->expiry_date->format('d/m/Y') }}</span>@endif
                                    @if($item->warranty_until)<span class="me-3"><span style="color:var(--text-muted)">Garansi</span> {{ $item->warranty_until->format('d/m/Y') }}</span>@endif
                                    @if($item->note)<span class="me-3"><i class="fa-regular fa-note-sticky me-1" style="color:var(--text-muted)"></i>{{ $item->note }}</span>@endif
                                    @if($serials->isNotEmpty())
                                        <div>@foreach($serials as $sn)<span class="gr-sn">{{ $sn }}</span>@endforeach</div>
                                    @endif
                                    @if($missing > 0)
                                        <div class="mt-1" style="color:#b45309"><i class="fa fa-triangle-exclamation me-1"></i>{{ $missing }} unit SN belum diisi</div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4" style="color:var(--text-muted)">Belum ada barang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('partials.purchase-documents', [
    'documents' => $documents,
    'types' => $receipt->documentTypes(),
    'uploadUrl' => route('goods-receipt.documents.store', $receipt->id),
    'canUpload' => $canCreate,
    'hint' => 'Opsional — surat jalan / nota supplier.',
])
@endsection
