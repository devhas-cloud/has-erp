@extends('layouts.app')

@section('title', 'Detail Masuk Stok Lain-lain')
@section('page-title', 'Detail Masuk Stok Lain-lain')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Masuk Stok {{ $entry->entry_number }}</h1>
        <p class="page-header-sub">{{ $entry->reason_label }} &middot; {{ $entry->entry_date?->format('d/m/Y') }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('stock-entry.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span>
    </div>
    <div class="card-body-custom">
        <table class="info-table" style="width:100%">
            <tr><td>Alasan</td><td><span class="status-badge" style="background:var(--accent-soft);color:var(--accent);">{{ $entry->reason_label }}</span></td></tr>
            <tr><td>Gudang</td><td>{{ $entry->warehouse ? $entry->warehouse->code.' — '.$entry->warehouse->name.' ('.$entry->warehouse->type_label.')' : '—' }}</td></tr>
            <tr>
                <td>Kepemilikan</td>
                <td>
                    @if($entry->project)
                        <a href="{{ route('project.show', $entry->project_id) }}" class="fw-semibold">{{ $entry->project->project_number }} — {{ $entry->project->project_name }}</a>
                    @else
                        Stok bebas
                    @endif
                </td>
            </tr>
            <tr><td>Dibuat Oleh</td><td>{{ $entry->creator?->display_name ?? '—' }} &middot; {{ $entry->created_at?->format('d/m/Y H:i') }}</td></tr>
            <tr><td>Keterangan</td><td>{{ $entry->note ?: '—' }}</td></tr>
        </table>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Barang Masuk</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Kode</th>
                        <th>Nama Produk</th>
                        <th class="text-center" style="width:100px">Qty</th>
                        <th style="width:100px">Satuan</th>
                        <th class="text-end" style="width:150px">Harga Satuan</th>
                        <th class="text-end" style="width:160px">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entry->items as $i => $item)
                    <tr>
                        <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                        <td>
                            @if($item->masterProduct)
                                <a href="{{ route('stock-report.card', $item->master_product_id) }}" title="Kartu stok">{{ $item->masterProduct->code }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $item->masterProduct?->name ?? '—' }}</td>
                        <td class="text-center fw-semibold">{{ $item->qty }}</td>
                        <td>{{ $item->unit ?: '—' }}</td>
                        <td class="text-end">{{ \App\Support\StockValuation::format($item->unit_cost) }}</td>
                        <td class="text-end">{{ \App\Support\StockValuation::format($item->unit_cost * $item->qty) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
