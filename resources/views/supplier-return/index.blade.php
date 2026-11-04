@extends('layouts.app')

@section('title', 'Retur ke Supplier')
@section('page-title', 'Retur ke Supplier')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Retur ke Supplier</h1>
        <p class="page-header-sub">Barang yang sudah diterima tapi dikembalikan ke supplier (rusak / salah kirim). Stok berkurang, barang retur tidak bisa ditagih, dan PO kembali menunggu barang pengganti.</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('supplier-return.create') }}" class="btn-accent"><i class="fa fa-plus"></i><span>Buat Retur</span></a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-truck-arrow-right me-2" style="color:var(--accent)"></i>Daftar Retur <span style="font-size:12px;color:var(--text-muted)">(200 terakhir)</span></span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead><tr><th>No. Retur</th><th>Tanggal</th><th>PO</th><th>Supplier</th><th>Gudang</th><th class="text-center">Qty</th><th>Alasan</th></tr></thead>
                <tbody>
                    @forelse($returns as $return)
                        <tr>
                            <td><a href="{{ route('supplier-return.show', $return->id) }}" class="fw-semibold">{{ $return->return_number }}</a></td>
                            <td>{{ $return->return_date?->format('d/m/Y') }}</td>
                            <td><a href="{{ route('purchase-order.show', $return->purchase_order_id) }}">{{ $return->purchaseOrder?->po_number }}</a></td>
                            <td>{{ $return->supplier?->name }}</td>
                            <td>{{ $return->warehouse?->name ?? 'Langsung pakai' }}</td>
                            <td class="text-center">{{ (int) $return->items_sum_qty }}</td>
                            <td style="font-size:12.5px;max-width:320px">{{ \Illuminate\Support\Str::limit($return->reason, 90) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Belum ada retur.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
