@extends('layouts.app')

@section('title', 'Detail Penerimaan Barang')
@section('page-title', 'Detail Penerimaan Barang')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Penerimaan {{ $receipt->receipt_number ?: '#'.$receipt->id }}</h1>
        <p class="page-header-sub">{{ $receipt->sourceNumber() }} &middot; {{ $receipt->supplierName() ?? '—' }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('goods-receipt.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="qt-action-bar">
    <span>{!! $receipt->statusBadgeHtml() !!}</span>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi Penerimaan</span>
    </div>
    <div class="card-body-custom">
        <table class="info-table" style="width:100%">
            <tr>
                <td>Dokumen Sumber</td>
                <td>
                    @if($receipt->purchase_order_id)
                        <a href="{{ route('purchase-order.show', $receipt->purchase_order_id) }}" class="fw-semibold">{{ $receipt->sourceNumber() }}</a>
                    @elseif($receipt->direct_purchase_id)
                        <a href="{{ route('direct-purchase.show', $receipt->direct_purchase_id) }}" class="fw-semibold">{{ $receipt->sourceNumber() }}</a>
                    @else
                        —
                    @endif
                    &middot; {{ $receipt->supplierName() ?? '—' }}
                </td>
            </tr>
            <tr>
                <td>Gudang</td>
                <td>{{ $receipt->warehouse ? $receipt->warehouse->code.' — '.$receipt->warehouse->name.' ('.$receipt->warehouse->type_label.')' : 'Tidak ada (semua langsung pakai)' }}</td>
            </tr>
            <tr>
                <td>Tanggal Terima</td>
                <td>{{ $receipt->received_date?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            <tr>
                <td>Dibuat Oleh</td>
                <td>{{ $receipt->creator?->display_name ?? '—' }} &middot; {{ $receipt->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
            @if($receipt->posted_at)
            <tr>
                <td>Diposting Oleh</td>
                <td>{{ $receipt->postedBy?->display_name ?? '—' }} &middot; {{ $receipt->posted_at?->format('d/m/Y H:i') }}</td>
            </tr>
            @endif
            <tr>
                <td>Catatan</td>
                <td>{{ $receipt->note ?: '—' }}</td>
            </tr>
        </table>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Item Diterima</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Part Number</th>
                        <th>Nama Barang</th>
                        <th>Untuk</th>
                        <th class="text-center">Qty</th>
                        <th>Unit</th>
                        <th>Tujuan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipt->items as $i => $item)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td>{{ $item->masterProduct?->code ?: '—' }}</td>
                            <td>{{ $item->masterProduct?->name ?: '—' }}</td>
                            <td>
                                @if($item->project)
                                    <span class="badge" style="background:#fef3c7;color:#92400e;white-space:normal">{{ $item->project->project_number }} — {{ $item->project->project_name }}</span>
                                @else
                                    <span style="color:var(--text-muted)">Stok bebas</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $item->qty }}</td>
                            <td>{{ $item->unit ?: '—' }}</td>
                            <td>
                                @if($item->destination === 'direct')
                                    <span class="badge" style="background:var(--info-soft);color:#1e40af">Langsung Pakai</span>
                                @else
                                    <span class="badge" style="background:var(--accent-soft);color:var(--accent)">Masuk Gudang</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4" style="color:var(--text-muted)">Belum ada item.</td>
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
    'hint' => 'Opsional — surat jalan / nota supplier. Dokumen hanya bisa dihapus oleh pengunggahnya.',
])
@endsection
