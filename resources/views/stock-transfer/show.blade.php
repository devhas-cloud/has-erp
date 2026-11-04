@extends('layouts.app')

@section('title', 'Detail Transfer Stok')
@section('page-title', 'Detail Transfer Stok')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Transfer {{ $transfer->transfer_number }}</h1>
        <p class="page-header-sub">{{ $transfer->kindLabel() }} &middot; {{ $transfer->transfer_date?->format('d/m/Y') }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('stock-transfer.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach([
        ['Asal', 'fa-circle-arrow-up', 'var(--danger)', $transfer->fromWarehouse, $transfer->fromProject],
        ['Tujuan', 'fa-circle-arrow-down', 'var(--success, #16a34a)', $transfer->toWarehouse, $transfer->toProject],
    ] as [$label, $icon, $color, $warehouse, $project])
    <div class="col-lg-6">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa {{ $icon }} me-2" style="color:{{ $color }}"></i>{{ $label }}</span>
            </div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Gudang</td><td>{{ $warehouse ? $warehouse->code.' — '.$warehouse->name.' ('.$warehouse->type_label.')' : '—' }}</td></tr>
                    <tr>
                        <td>Kepemilikan</td>
                        <td>
                            @if($project)
                                <a href="{{ route('project.show', $project->id) }}" class="fw-semibold">{{ \App\Models\StockTransfer::ownershipLabel($project) }}</a>
                            @else
                                Stok bebas
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span>
    </div>
    <div class="card-body-custom">
        <table class="info-table" style="width:100%">
            <tr><td>Jenis</td><td><span class="status-badge" style="background:var(--accent-soft);color:var(--accent);">{{ $transfer->kindLabel() }}</span></td></tr>
            <tr><td>Dibuat Oleh</td><td>{{ $transfer->creator?->display_name ?? '—' }} &middot; {{ $transfer->created_at?->format('d/m/Y H:i') }}</td></tr>
            <tr><td>Catatan</td><td>{{ $transfer->note ?: '—' }}</td></tr>
        </table>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Item Ditransfer</span>
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
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfer->items as $i => $item)
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
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
