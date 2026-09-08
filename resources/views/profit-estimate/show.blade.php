@extends('layouts.app')

@php
    use App\Models\ProfitEstimate as PL;
    $q = $estimate->quotation;
    $fx = $estimate->foreignCurrenciesUsed();
    $rates = $estimate->rates ?? [];
    $sub = fn (string $section) => $estimate->linesOf($section)->sum('amount_idr');
@endphp

@section('title', 'Estimasi PL '.($q?->quotation_number ?? '#'.$estimate->id))
@section('page-title', 'Detail Estimasi PL')

@section('styles')
<style>
    .pl-card { margin-bottom: 18px; }
    .pl-card .card-body-custom { padding: 14px 16px; }
    .info-table td { padding: 6px 0; vertical-align: top; font-size: 13px; }
    .info-table td:first-child { color: var(--text-muted); width: 170px; font-size: 12px; font-weight: 600; }
    .info-table tr + tr td { border-top: 1px solid var(--card-border); }
    .pl-summary td { padding: 6px 10px; font-size: 13px; }
    .pl-summary td.k { color: var(--text-muted); font-weight: 600; }
    .pl-summary td.v { text-align: right; font-weight: 700; white-space: nowrap; }
    .pl-summary tr.total td { background: var(--accent-soft); color: var(--accent); }
    .pl-summary tr.profit td { background: #dcfce7; color: #166534; }
    .pl-summary tr.loss td { background: var(--danger-soft); color: #7f1d1d; }
    .pl-action-bar { display: flex; gap: 8px; flex-wrap: wrap; background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); padding: 12px 16px; margin-bottom: 18px; align-items: center; }
    .pl-action-bar .spacer { flex: 1; }
    table.table-custom td.num { text-align: right; white-space: nowrap; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Estimasi PL &mdash; {{ $q?->quotation_number ?? 'Quotation #'.$estimate->quotation_id }}</h1>
        <p class="page-header-sub">{{ $estimate->project_name ?? '—' }} &middot; {{ PL::formatDateId($estimate->date) }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('profit-estimate.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
        @if($q)
            <a href="{{ route('quotation.show', $q->id) }}" class="btn btn-secondary btn-sm"><i class="fa fa-file-invoice me-1"></i> Quotation</a>
        @endif
        <a href="{{ route('profit-estimate.pdf', $estimate->id) }}" target="_blank" class="btn-accent"><i class="fa fa-file-pdf me-1"></i> <span>View PDF</span></a>
    </div>
</div>

@if($estimate->is_outdated)
<div class="alert alert-warning" style="font-size:13px">
    <i class="fa fa-triangle-exclamation me-1"></i>
    <strong>Outdated.</strong> Quotation sumber berubah pada {{ $estimate->outdated_at?->format('d/m/Y H:i') }}.
    @if($currentQuotation && $currentQuotation->id !== $estimate->quotation_id)
        Quotation sudah direvisi menjadi versi {{ $currentQuotation->version }} ({{ $currentQuotation->quotation_number }}).
        @if($currentQuotation->profitEstimate)
            <a href="{{ route('profit-estimate.show', $currentQuotation->profitEstimate->id) }}">Lihat PL versi terbaru</a>.
        @elseif($canCreate)
            <a href="{{ route('profit-estimate.create', ['quotation_id' => $currentQuotation->id]) }}">Buat PL untuk versi terbaru</a> (input manual akan disalin dari PL ini).
        @endif
    @elseif($canCreate)
        Klik <strong>Sinkronkan</strong> untuk membuka form yang diisi ulang dari quotation (nilai dan daftar item); vendor/amount item, koreksi HPP, biaya, persentase, dan kurs dipertahankan.
    @endif
</div>
@endif

<div class="pl-action-bar">
    <span>{!! $estimate->is_outdated
        ? '<span class="status-badge" style="background:#fef3c7;color:#92400e;">Outdated</span>'
        : '<span class="status-badge status-active">Up to date</span>' !!}</span>
    <span style="font-size:12px;color:var(--text-muted)">Dibuat {{ $estimate->creator?->username ?? '—' }} &middot; {{ $estimate->created_at?->format('d/m/Y H:i') }}
        @if($estimate->updater) &middot; Diupdate {{ $estimate->updater->username }} {{ $estimate->updated_at?->format('d/m/Y H:i') }} @endif
    </span>
    <div class="spacer"></div>
    @if($estimate->is_outdated && $canCreate && (! $currentQuotation || $currentQuotation->id === $estimate->quotation_id))
        <a href="{{ route('profit-estimate.sync', $estimate->id) }}" class="btn btn-primary btn-sm"><i class="fa fa-rotate me-1"></i> Sinkronkan</a>
    @endif
    @if($canUpdate)
        <a href="{{ route('profit-estimate.edit', $estimate->id) }}" class="btn btn-secondary btn-sm"><i class="fa fa-pen me-1"></i> Edit</a>
    @endif
    @if($canDelete)
        <button type="button" class="btn btn-danger btn-sm" onclick="deletePl()"><i class="fa fa-trash me-1"></i> Hapus</button>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-custom pl-card">
            <div class="card-header-custom"><span><i class="fa-solid fa-coins me-2" style="color:var(--accent)"></i>Nilai Project</span></div>
            <div class="card-body-custom p-2">
                <table class="w-100 pl-summary">
                    <tr><td class="k">Project / Company</td><td class="v">{{ $estimate->project_name ?? '—' }}</td></tr>
                    <tr><td class="k">Nilai Awal</td><td class="v">{{ PL::formatMoney($estimate->nilai_awal) }}</td></tr>
                    <tr><td class="k">PPN</td><td class="v">{{ PL::formatMoney($estimate->ppn_amount) }}</td></tr>
                    <tr><td class="k">Sub Total I</td><td class="v">{{ PL::formatMoney($estimate->subtotal_1) }}</td></tr>
                    <tr><td class="k">Discount</td><td class="v">{{ PL::formatMoney($estimate->discount_amount) }}</td></tr>
                    <tr><td class="k">Sub Total II</td><td class="v">{{ PL::formatMoney($estimate->subtotal_2) }}</td></tr>
                    <tr><td class="k">Referen {{ number_format($estimate->referral_percent, 2) }}%</td><td class="v">{{ PL::formatMoney($estimate->referral_amount) }}</td></tr>
                    <tr class="total"><td class="k">Nilai Real Project</td><td class="v">{{ PL::formatMoney($estimate->real_project_value) }}</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-custom pl-card">
            <div class="card-header-custom"><span><i class="fa-solid fa-money-bill-transfer me-2" style="color:var(--accent)"></i>Kurs (snapshot)</span></div>
            <div class="card-body-custom">
                <table class="info-table w-100">
                    @forelse($rates as $code => $rate)
                        <tr><td>Kurs {{ $code }}</td><td>{{ PL::formatMoney($rate) }}</td></tr>
                    @empty
                        <tr><td colspan="2" style="color:var(--text-muted)">Tidak ada kurs tersimpan.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
        <div class="card-custom pl-card">
            <div class="card-header-custom"><span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Daftar Item</span></div>
            <div class="card-body-custom p-2">
                <table class="table table-custom align-middle mb-0">
                    <thead><tr><th>Item</th><th class="text-center" style="width:90px">Qty</th><th style="width:140px">Vendor</th><th class="text-end" style="width:130px">Amount</th><th class="text-end" style="width:130px">Rp</th></tr></thead>
                    <tbody>
                    @forelse($estimate->linesOf(PL::SECTION_PRODUCT) as $l)
                        <tr>
                            <td>{{ $l->label }}</td>
                            <td class="text-center">{{ $l->qty + 0 }} {{ $l->unit }}</td>
                            <td>{{ $l->vendor ?? '—' }}</td>
                            <td class="num">{{ $l->currency }} {{ PL::formatMoney($l->amount) }}</td>
                            <td class="num">{{ PL::formatMoney($l->amount_idr) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center" style="color:var(--text-muted)">Belum ada item.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card-custom pl-card">
    <div class="card-header-custom"><span><i class="fa-solid fa-table me-2" style="color:var(--accent)"></i>Biaya</span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Deskripsi</th>
                        @foreach($fx as $code)<th class="text-end" style="width:140px">{{ $code }}</th>@endforeach
                        <th class="text-end" style="width:170px">Rp</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="background:var(--bg)"><td colspan="{{ 2 + count($fx) }}" style="font-weight:700">Harga Pokok Product (FOB, TT)</td></tr>
                    @foreach($estimate->linesOf(PL::SECTION_HPP) as $l)
                        <tr>
                            <td style="padding-left:24px">{{ $l->vendor ?: $l->label }}
                                @if($l->is_manual)<span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px">manual</span>@endif
                            </td>
                            @foreach($fx as $code)<td class="num">{{ $l->currency === $code ? PL::formatMoney($l->amount) : '' }}</td>@endforeach
                            <td class="num">{{ PL::formatMoney($l->amount_idr) }}</td>
                        </tr>
                    @endforeach
                    <tr style="background:var(--bg)"><td colspan="{{ 2 + count($fx) }}" style="font-weight:700">Operasional Cost &mdash; 1. Yang Telah Dikeluarkan</td></tr>
                    @foreach($estimate->linesOf(PL::SECTION_COST_SPENT) as $l)
                        <tr>
                            <td style="padding-left:24px">{{ chr(97 + $loop->index) }}. {{ $l->label }}</td>
                            @foreach($fx as $code)<td class="num">{{ $l->currency === $code ? PL::formatMoney($l->amount) : '' }}</td>@endforeach
                            <td class="num">{{ PL::formatMoney($l->amount_idr) }}</td>
                        </tr>
                    @endforeach
                    <tr style="background:var(--bg)"><td colspan="{{ 2 + count($fx) }}" style="font-weight:700">Operasional Cost &mdash; 2. Yang Akan Dikeluarkan</td></tr>
                    @foreach($estimate->linesOf(PL::SECTION_COST_PLANNED) as $l)
                        <tr>
                            <td style="padding-left:24px">{{ $l->label }}</td>
                            @foreach($fx as $code)<td class="num">{{ $l->currency === $code ? PL::formatMoney($l->amount) : '' }}</td>@endforeach
                            <td class="num">{{ PL::formatMoney($l->amount_idr) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td style="font-weight:700">Company Investment &amp; Development {{ number_format($estimate->investment_percent, 2) }}%</td>
                        @foreach($fx as $code)<td></td>@endforeach
                        <td class="num">{{ PL::formatMoney($estimate->investment_amount) }}</td>
                    </tr>
                    <tr style="background:var(--accent-soft);color:var(--accent);font-weight:700">
                        <td>Total Biaya Yang Dikeluarkan</td>
                        @foreach($fx as $code)
                            <td class="num">{{ PL::formatMoney($estimate->lines->where('section', '!=', PL::SECTION_PRODUCT)->where('currency', $code)->sum('amount')) }}</td>
                        @endforeach
                        <td class="num">{{ PL::formatMoney($estimate->total_cost) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-custom pl-card">
            <div class="card-header-custom"><span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Ringkasan Profit</span></div>
            <div class="card-body-custom p-2">
                <table class="w-100 pl-summary">
                    <tr><td class="k">Nilai Real Project</td><td class="v">{{ PL::formatMoney($estimate->real_project_value) }}</td><td></td></tr>
                    <tr><td class="k">Nilai Biaya Yang Dikeluarkan</td><td class="v">{{ PL::formatMoney($estimate->total_cost) }}</td><td></td></tr>
                    <tr class="{{ $estimate->estimated_profit >= 0 ? 'profit' : 'loss' }}"><td class="k">Estimasi Nilai Profit</td><td class="v">{{ PL::formatMoney($estimate->estimated_profit) }}</td><td class="v">{{ number_format($estimate->profit_percent, 2) }} %</td></tr>
                    <tr><td class="k">Fee Marketing {{ number_format($estimate->marketing_fee_percent, 2) }}%</td><td class="v">{{ PL::formatMoney($estimate->marketing_fee_amount) }}</td><td></td></tr>
                    <tr><td class="k">Fee PM {{ number_format($estimate->pm_fee_percent, 2) }}%</td><td class="v">{{ PL::formatMoney($estimate->pm_fee_amount) }}</td><td></td></tr>
                    <tr class="{{ $estimate->real_profit >= 0 ? 'profit' : 'loss' }}"><td class="k">Real Profit</td><td class="v">{{ PL::formatMoney($estimate->real_profit) }}</td><td class="v">{{ number_format($estimate->real_profit_percent, 2) }} %</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-custom pl-card">
            <div class="card-header-custom"><span><i class="fa-solid fa-signature me-2" style="color:var(--accent)"></i>Tanda Tangan &amp; Catatan</span></div>
            <div class="card-body-custom">
                <table class="info-table w-100">
                    <tr><td>Sales Person</td><td>{{ $estimate->sales_person_name ?? '—' }}</td></tr>
                    <tr><td>Finance Dept</td><td>{{ $estimate->finance_name ?? '—' }}</td></tr>
                    <tr><td>Accounting</td><td>{{ $estimate->accounting_name ?? '—' }}</td></tr>
                    <tr><td>Catatan</td><td>{!! $estimate->notes ? nl2br(e($estimate->notes)) : '—' !!}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function deletePl() {
    Swal.fire({
        title: 'Hapus Estimasi PL?',
        text: 'Estimasi PL ini akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: '{{ route('profit-estimate.destroy', $estimate->id) }}',
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Dihapus.');
            setTimeout(function() { window.location.href = '{{ route('profit-estimate.index') }}'; }, 600);
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}
</script>
@endsection
