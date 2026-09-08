@extends('layouts.app')

@section('title', 'Buat Estimasi PL')
@section('page-title', 'Buat Estimasi PL')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Pilih Quotation</h1>
        <p class="page-header-sub">Estimasi PL dibuat dari quotation. Hanya quotation versi terakhir yang belum memiliki PL yang ditampilkan.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('profit-estimate.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Quotation Tersedia</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Nomor Quotation</th>
                        <th>To (Company)</th>
                        <th>Opportunity</th>
                        <th>Tanggal</th>
                        <th class="text-end">Grand Total</th>
                        <th>Status</th>
                        <th class="text-center" style="width:140px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($quotations as $i => $q)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td><strong>{{ $q->quotation_number ?? '#'.$q->id }}</strong>
                            @if($q->version > 1)<span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:10px">v{{ $q->version }}</span>@endif
                        </td>
                        <td>{{ $q->to_name ?? ($q->opportunity?->accountCompany?->account_name ?? '—') }}</td>
                        <td>{{ $q->opportunity?->opportunity_name ?? '—' }}</td>
                        <td>{{ $q->date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-end">{{ \App\Models\Quotation::formatMoney($q->grand_total) }}</td>
                        <td>{!! $q->statusBadgeHtml() !!}</td>
                        <td class="text-center">
                            <a href="{{ route('profit-estimate.create', ['quotation_id' => $q->id]) }}" class="btn-accent" style="font-size:12px;padding:6px 12px">
                                <i class="fa fa-chart-line me-1"></i> Buat PL
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center" style="color:var(--text-muted);padding:24px">
                            Tidak ada quotation yang bisa dibuatkan Estimasi PL.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
