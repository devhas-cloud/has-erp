@extends('layouts.app')

@section('title', 'Detail Pembayaran Supplier')
@section('page-title', 'Detail Pembayaran Supplier')

@php $money = fn ($v) => \App\Models\SupplierBill::formatMoney((float) $v); @endphp

@section('styles')
<style>
    .sp-muted { color: var(--text-muted); font-size: 12px; }
    .sp-hero { background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); padding: 16px 18px; margin-bottom: 14px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .sp-number { font-size: 20px; font-weight: 700; color: var(--text-primary); margin: 4px 0 2px; }
    .sp-amount { text-align: right; }
    .sp-amount .val { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
    @media (max-width: 575.98px) { .sp-amount { text-align: left; } }
    .sp-next { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; border: 1px solid; border-radius: var(--radius); padding: 12px 16px; margin-bottom: 14px; font-size: 13px; }
    .sp-next .txt { flex: 1; min-width: 220px; }
    .sp-next .txt strong { display: block; font-size: 14px; }
    .sp-next.info { background: #eff6ff; border-color: #bfdbfe; color: #1e3a8a; }
    .sp-next.ok { background: #f0fdf4; border-color: #bbf7d0; color: #14532d; }
    .sp-next.muted { background: #f9fafb; border-color: var(--card-border); color: #374151; }
    .sp-dl { display: grid; grid-template-columns: 110px 1fr; gap: 7px 10px; margin: 0; font-size: 13px; }
    .sp-dl dt { color: var(--text-muted); font-weight: 500; font-size: 12px; }
    .sp-dl dd { margin: 0; word-break: break-word; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div><h1 class="page-header-title">Detail Pembayaran Supplier</h1></div>
    <div class="page-header-actions">
        <a href="{{ route('supplier-payment.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="sp-hero fade-in">
    <div>
        {!! $payment->statusBadgeHtml() !!}
        <div class="sp-number">{{ $payment->payment_number }}</div>
        <div class="sp-muted">{{ $payment->supplier?->name }} &middot; {{ $payment->payment_date?->format('d/m/Y') }} &middot; {{ $payment->method_label }}{{ $payment->reference_number ? ' · Ref '.$payment->reference_number : '' }}</div>
    </div>
    <div class="sp-amount">
        <div class="sp-muted">Total Bayar</div>
        <div class="val">{{ $payment->currency }} {{ $money($payment->amount) }}</div>
        @php $fxTotal = round($payment->allocations->sum('fx_difference'), 2); @endphp
        @if($payment->currency !== $baseCurrency)
            <div class="sp-muted">{{ $baseCurrency }} {{ $money($payment->amount_base) }} didebit (kurs {{ rtrim(rtrim(number_format($payment->exchange_rate, 6, ',', '.'), '0'), ',') }})</div>
            @if(abs($fxTotal) >= 0.01)<div class="sp-muted {{ $fxTotal > 0 ? 'text-danger' : 'text-success' }}">{{ $fxTotal > 0 ? 'Rugi' : 'Laba' }} selisih kurs {{ $baseCurrency }} {{ $money(abs($fxTotal)) }}</div>@endif
        @endif
        @if($payment->bank_charge > 0)<div class="sp-muted">Biaya bank {{ $baseCurrency }} {{ $money($payment->bank_charge) }}</div>@endif
    </div>
</div>

@switch($payment->status)
    @case('draft')
        <div class="sp-next info">
            <div class="txt"><strong>Draft — belum mengurangi hutang</strong>Lampirkan bukti transfer, lalu approver Finance memposting pembayaran ini.</div>
            <div class="d-flex gap-2 flex-wrap">
                @if($canUpdate)<a href="{{ route('supplier-payment.edit', $payment->id) }}" class="btn btn-secondary btn-sm"><i class="fa fa-pen me-1"></i> Edit</a>@endif
                @if($canDelete)<button type="button" class="btn btn-outline-danger btn-sm" onclick="spDelete()"><i class="fa fa-trash me-1"></i> Hapus</button>@endif
                @if($canApprove)
                    <button type="button" class="btn btn-success btn-sm" id="sp-post-btn" onclick="spPost()"><i class="fa fa-check-double me-1"></i> Posting Pembayaran</button>
                @endif
            </div>
        </div>
        @break
    @case('posted')
        <div class="sp-next ok">
            <div class="txt"><strong><i class="fa fa-circle-check me-1"></i>Diposting{{ $payment->poster ? ' oleh '.$payment->poster->display_name : '' }} · {{ $payment->posted_at?->format('d/m/Y H:i') }}</strong>Hutang tagihan di bawah sudah berkurang.</div>
            @if($canApprove)<button type="button" class="btn btn-outline-danger btn-sm" onclick="spOpenVoid()"><i class="fa fa-ban me-1"></i> Void</button>@endif
        </div>
        @break
    @case('void')
        <div class="sp-next muted"><div class="txt"><strong>Void{{ $payment->voider ? ' oleh '.$payment->voider->display_name : '' }} · {{ $payment->voided_at?->format('d/m/Y H:i') }}</strong>{{ $payment->void_reason }} — sisa hutang tagihan dibuka kembali.</div></div>
        @break
@endswitch

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Tagihan Dibayar ({{ $payment->allocations->count() }})</span></div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        @php $isForeign = $payment->currency !== $baseCurrency; @endphp
                        <thead><tr><th>Tagihan</th><th>Sumber</th><th>Jatuh Tempo</th><th class="text-end">Dibayar</th>@if($isForeign)<th class="text-end">Kurs Tagihan → Bayar</th><th class="text-end">Selisih Kurs</th>@endif<th class="text-end">Status Tagihan</th></tr></thead>
                        <tbody>
                            @foreach($payment->allocations as $allocation)
                                <tr>
                                    <td><a href="{{ route('supplier-bill.show', $allocation->supplier_bill_id) }}" class="fw-semibold">{{ $allocation->bill?->bill_number }}</a><div class="sp-muted">Inv. {{ $allocation->bill?->supplier_invoice_number }}</div></td>
                                    <td class="sp-muted">{{ $allocation->bill?->sourceNumber() }}</td>
                                    <td>{{ $allocation->bill?->due_date?->format('d/m/Y') }}</td>
                                    <td class="text-end fw-semibold" style="white-space:nowrap">{{ $payment->currency }} {{ $money($allocation->amount) }}@if($isForeign)<div class="sp-muted" style="font-weight:400">{{ $baseCurrency }} {{ $money($allocation->amount_base_paid) }}</div>@endif</td>
                                    @if($isForeign)
                                        <td class="text-end sp-muted" style="white-space:nowrap">{{ $money($allocation->bill?->exchange_rate) }} → {{ $money($payment->exchange_rate) }}@if($allocation->bill?->rate_estimated)<div><span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px">kurs tagihan perkiraan</span></div>@endif</td>
                                        <td class="text-end" style="white-space:nowrap"><span class="{{ $allocation->fx_difference > 0 ? 'text-danger' : ($allocation->fx_difference < 0 ? 'text-success' : 'sp-muted') }}">{{ $allocation->fx_difference > 0 ? 'Rugi ' : ($allocation->fx_difference < 0 ? 'Laba ' : '') }}{{ $money(abs($allocation->fx_difference)) }}</span></td>
                                    @endif
                                    <td class="text-end">{!! $allocation->bill?->statusBadgeHtml() !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @include('partials.purchase-documents', [
            'documents' => $documents,
            'types' => $payment->documentTypes(),
            'uploadUrl' => route('supplier-payment.documents.store', $payment->id),
            'canUpload' => $canCreate,
            'hint' => 'Lampirkan bukti transfer. Setelah diposting, dokumen hanya bisa ditambah.',
        ])
    </div>
    <div class="col-xl-4">
        <div class="card-custom fade-in">
            <div class="card-header-custom"><span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span></div>
            <div class="card-body-custom">
                <dl class="sp-dl">
                    <dt>Supplier</dt><dd class="fw-semibold">{{ $payment->supplier?->name }}</dd>
                    <dt>Rekening Tujuan</dt><dd>@if($payment->bank_account_no){{ $payment->bank_name }} {{ $payment->bank_account_no }}<div class="sp-muted">a.n. {{ $payment->bank_account_name }}</div>@else — @endif</dd>
                    @if($payment->source_account)<dt>Rekening Sumber</dt><dd>{{ $payment->source_account }}</dd>@endif
                    <dt>Metode</dt><dd>{{ $payment->method_label }}</dd>
                    <dt>Referensi</dt><dd>{{ $payment->reference_number ?: '—' }}</dd>
                    @if($payment->bank_charge > 0)<dt>Biaya Bank</dt><dd>{{ $baseCurrency }} {{ $money($payment->bank_charge) }}<div class="sp-muted">Di luar hutang supplier</div></dd>@endif
                    <dt>Dibuat</dt><dd>{{ $payment->creator?->display_name ?? '—' }}<div class="sp-muted">{{ $payment->created_at?->format('d/m/Y H:i') }}</div></dd>
                    @if($payment->notes)<dt>Catatan</dt><dd>{{ $payment->notes }}</dd>@endif
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="spVoidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Void Pembayaran {{ $payment->payment_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="sp-muted">Pembayaran dibatalkan dan sisa hutang tagihan terkait dibuka kembali.</p>
                <label class="form-label" for="sp-void-reason">Alasan <span class="text-danger">*</span></label>
                <textarea id="sp-void-reason" class="form-control" rows="3" placeholder="Contoh: transfer gagal / salah nominal"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-danger btn-sm" id="sp-void-btn" onclick="spVoid()"><i class="fa fa-ban me-1"></i> Void Pembayaran</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
const spCsrf = '{{ csrf_token() }}';
let spVoidModal = null;
function spError(xhr, fallback) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : fallback); }

function spPost() {
    Swal.fire({ title: 'Posting pembayaran?', text: 'Pastikan dana sudah ditransfer. Hutang tagihan terkait akan berkurang.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Posting', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $('#sp-post-btn').prop('disabled', true);
            $.post('{{ route("supplier-payment.approve", $payment->id) }}', { _token: spCsrf })
                .done(function(res) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 700); })
                .fail(function(xhr) { spError(xhr, 'Gagal memposting.'); $('#sp-post-btn').prop('disabled', false); });
        });
}

function spDelete() {
    Swal.fire({ title: 'Hapus draft pembayaran?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $.ajax({ url: '{{ route("supplier-payment.destroy", $payment->id) }}', method: 'DELETE', headers: { 'X-CSRF-TOKEN': spCsrf } })
                .done(function(res) { toastr.success(res.message); setTimeout(function() { location.href = '{{ route("supplier-payment.index") }}'; }, 600); })
                .fail(function(xhr) { spError(xhr, 'Gagal menghapus.'); });
        });
}

function spOpenVoid() {
    $('#sp-void-reason').val('');
    if (!spVoidModal) spVoidModal = new bootstrap.Modal(document.getElementById('spVoidModal'));
    spVoidModal.show();
}

function spVoid() {
    var reason = $('#sp-void-reason').val().trim();
    if (!reason) { toastr.error('Alasan void wajib diisi.'); return; }
    $('#sp-void-btn').prop('disabled', true);
    $.post('{{ route("supplier-payment.void", $payment->id) }}', { _token: spCsrf, void_reason: reason })
        .done(function(res) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 700); })
        .fail(function(xhr) { spError(xhr, 'Gagal void.'); $('#sp-void-btn').prop('disabled', false); });
}
</script>
@endsection
