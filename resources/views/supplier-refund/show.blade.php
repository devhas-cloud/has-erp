@extends('layouts.app')

@section('title', 'Detail Refund Supplier')
@section('page-title', 'Detail Refund Supplier')

@php $money = fn ($v) => \App\Models\SupplierBill::formatMoney((float) $v); @endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Refund {{ $refund->refund_number }}</h1>
        <p class="page-header-sub">{{ $refund->supplier?->name }} &middot; PO <a href="{{ route('purchase-order.show', $refund->purchase_order_id) }}">{{ $refund->purchaseOrder?->po_number }}</a></p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('supplier-refund.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="qt-action-bar d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <span>{!! $refund->statusBadgeHtml() !!}
        @if($refund->status === 'void')<span style="font-size:12.5px;color:var(--text-muted)" class="ms-2">Void{{ $refund->voider ? ' oleh '.$refund->voider->display_name : '' }}: {{ $refund->void_reason }}</span>@endif
        @if($refund->status === 'posted')<span style="font-size:12.5px;color:var(--text-muted)" class="ms-2">Diposting {{ $refund->poster?->display_name }} · {{ $refund->posted_at?->format('d/m/Y H:i') }}</span>@endif
    </span>
    <div class="d-flex gap-2 flex-wrap">
        @if($refund->isDraft())
            @if($canDelete)<button type="button" class="btn btn-outline-danger btn-sm" onclick="srDelete()"><i class="fa fa-trash me-1"></i> Hapus</button>@endif
            @if($canApprove)
                <button type="button" class="btn btn-success btn-sm" id="sr-post-btn" onclick="srPost()"><i class="fa fa-check-double me-1"></i> Posting Refund</button>
            @endif
        @elseif($refund->status === 'posted' && $canApprove)
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="srVoid()"><i class="fa fa-ban me-1"></i> Void</button>
        @endif
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-7">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Nominal</td><td class="fw-semibold">{{ $refund->currency }} {{ $money($refund->amount) }}@if($refund->currency !== $baseCurrency)<div style="font-size:12px;color:var(--text-muted)">≈ {{ $baseCurrency }} {{ $money($refund->amount_base) }}</div>@endif</td></tr>
                    @if($refund->currency !== $baseCurrency)
                    <tr><td>Kurs Refund</td><td>{{ $money($refund->exchange_rate) }}<div style="font-size:12px;color:var(--text-muted)">nilai buku uang muka {{ $baseCurrency }} {{ $money($refund->amount_base_book) }}</div></td></tr>
                    <tr><td>Selisih Kurs</td><td class="{{ $refund->fx_difference > 0 ? 'text-danger' : ($refund->fx_difference < 0 ? 'text-success' : '') }}">{{ $refund->fx_difference > 0 ? 'Rugi' : ($refund->fx_difference < 0 ? 'Laba' : 'Nihil') }} {{ $baseCurrency }} {{ $money(abs($refund->fx_difference)) }}</td></tr>
                    @endif
                    <tr><td>Tanggal</td><td>{{ $refund->refund_date?->format('d/m/Y') }}</td></tr>
                    <tr><td>Metode</td><td>{{ $refund->method_label }}{{ $refund->reference_number ? ' · Ref '.$refund->reference_number : '' }}</td></tr>
                    <tr><td>Rekening Penerima</td><td>{{ $refund->destination_account ?: '—' }}</td></tr>
                    <tr><td>Dibuat</td><td>{{ $refund->creator?->display_name ?? '—' }} · {{ $refund->created_at?->format('d/m/Y H:i') }}</td></tr>
                    <tr><td>Catatan</td><td>{{ $refund->notes ?: '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        @include('partials.purchase-documents', [
            'documents' => $documents,
            'types' => $refund->documentTypes(),
            'uploadUrl' => route('supplier-refund.documents.store', $refund->id),
            'canUpload' => $canCreate,
            'hint' => 'Lampirkan bukti transfer masuk dari supplier.',
        ])
    </div>
</div>
@endsection

@section('scripts')
<script>
const srCsrf = '{{ csrf_token() }}';
function srError(xhr, f) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : f); }
function srPost() {
    Swal.fire({ title: 'Posting refund?', text: 'Pastikan dana refund sudah masuk ke rekening perusahaan.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Posting', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $('#sr-post-btn').prop('disabled', true);
            $.post('{{ route("supplier-refund.approve", $refund->id) }}', { _token: srCsrf })
                .done(function(res) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 700); })
                .fail(function(xhr) { srError(xhr, 'Gagal memposting.'); $('#sr-post-btn').prop('disabled', false); });
        });
}
function srDelete() {
    Swal.fire({ title: 'Hapus draft refund?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $.ajax({ url: '{{ route("supplier-refund.destroy", $refund->id) }}', method: 'DELETE', headers: { 'X-CSRF-TOKEN': srCsrf } })
                .done(function(res) { toastr.success(res.message); setTimeout(function() { location.href = '{{ route("supplier-refund.index") }}'; }, 600); })
                .fail(function(xhr) { srError(xhr, 'Gagal menghapus.'); });
        });
}
function srVoid() {
    Swal.fire({ title: 'Void refund?', input: 'textarea', inputLabel: 'Alasan (wajib)', inputValidator: function(v) { return !v || !v.trim() ? 'Alasan wajib diisi.' : null; }, showCancelButton: true, confirmButtonText: 'Void', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $.post('{{ route("supplier-refund.void", $refund->id) }}', { _token: srCsrf, void_reason: r.value.trim() })
                .done(function(res) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 700); })
                .fail(function(xhr) { srError(xhr, 'Gagal void.'); });
        });
}
</script>
@endsection
