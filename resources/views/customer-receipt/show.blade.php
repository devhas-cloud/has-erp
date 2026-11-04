@extends('layouts.app')

@section('title', 'Detail Penerimaan Customer')
@section('page-title', 'Detail Penerimaan Customer')

@php
    $invoice = $receipt->invoice;
    $project = $invoice?->project;
@endphp

@section('styles')
<style>
    .cr-muted { color: var(--text-muted); font-size: 12px; }
    .cr-hero { background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); padding: 16px 18px; margin-bottom: 14px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .cr-number { font-size: 20px; font-weight: 700; color: var(--text-primary); margin: 4px 0 2px; }
    .cr-amount { text-align: right; }
    .cr-amount .val { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
    @media (max-width: 575.98px) { .cr-amount { text-align: left; } }
    .cr-next { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; border: 1px solid; border-radius: var(--radius); padding: 12px 16px; margin-bottom: 14px; font-size: 13px; }
    .cr-next .txt { flex: 1; min-width: 220px; }
    .cr-next .txt strong { display: block; font-size: 14px; }
    .cr-next.info { background: #eff6ff; border-color: #bfdbfe; color: #1e3a8a; }
    .cr-next.ok { background: #f0fdf4; border-color: #bbf7d0; color: #14532d; }
    .cr-next.muted { background: #f9fafb; border-color: var(--card-border); color: #374151; }
    .cr-dl { display: grid; grid-template-columns: 120px 1fr; gap: 7px 10px; margin: 0; font-size: 13px; }
    .cr-dl dt { color: var(--text-muted); font-weight: 500; font-size: 12px; }
    .cr-dl dd { margin: 0; word-break: break-word; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div><h1 class="page-header-title">Detail Penerimaan Customer</h1></div>
    <div class="page-header-actions">
        <a href="{{ route('customer-receipt.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="cr-hero fade-in">
    <div>
        {!! $receipt->statusBadgeHtml() !!}
        <div class="cr-number">{{ $receipt->receipt_number }}</div>
        <div class="cr-muted">{{ $receipt->company?->account_name ?? '—' }} &middot; {{ $receipt->receipt_date?->format('d/m/Y') }} &middot; {{ $receipt->method_label }}{{ $receipt->reference_number ? ' · Ref '.$receipt->reference_number : '' }}</div>
    </div>
    <div class="cr-amount">
        <div class="cr-muted">Nominal Diterima</div>
        <div class="val">{{ \App\Models\Invoice::formatMoney($receipt->amount) }}</div>
    </div>
</div>

@switch($receipt->status)
    @case('draft')
        <div class="cr-next info">
            <div class="txt"><strong>Draft — invoice belum Lunas</strong>Lampirkan bukti transfer, lalu approver Finance memposting penerimaan ini setelah dana dicek di mutasi bank.</div>
            <div class="d-flex gap-2 flex-wrap">
                @if($canUpdate)<a href="{{ route('customer-receipt.edit', $receipt->id) }}" class="btn btn-secondary btn-sm"><i class="fa fa-pen me-1"></i> Edit</a>@endif
                @if($canDelete)<button type="button" class="btn btn-outline-danger btn-sm" onclick="crDelete()"><i class="fa fa-trash me-1"></i> Hapus</button>@endif
                @if($canApprove)
                    <button type="button" class="btn btn-success btn-sm" id="cr-post-btn" onclick="crPost()"><i class="fa fa-check-double me-1"></i> Posting Penerimaan</button>
                @endif
            </div>
        </div>
        @break
    @case('posted')
        <div class="cr-next ok">
            <div class="txt"><strong><i class="fa fa-circle-check me-1"></i>Diposting{{ $receipt->poster ? ' oleh '.$receipt->poster->display_name : '' }} · {{ $receipt->posted_at?->format('d/m/Y H:i') }}</strong>Invoice {{ $invoice?->invoice_number }} sudah Lunas.</div>
            @if($canApprove)<button type="button" class="btn btn-outline-danger btn-sm" onclick="crOpenVoid()"><i class="fa fa-ban me-1"></i> Void</button>@endif
        </div>
        @break
    @case('void')
        <div class="cr-next muted"><div class="txt"><strong>Void{{ $receipt->voider ? ' oleh '.$receipt->voider->display_name : '' }} · {{ $receipt->voided_at?->format('d/m/Y H:i') }}</strong>{{ $receipt->void_reason }} — invoice kembali Belum Dibayar.</div></div>
        @break
@endswitch

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-file-invoice-dollar me-2" style="color:var(--accent)"></i>Invoice Dibayar</span></div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead><tr><th>Invoice</th><th>Project / Termin</th><th>Jatuh Tempo</th><th class="text-end">Total</th><th class="text-end">Status Invoice</th></tr></thead>
                        <tbody>
                            <tr>
                                <td><a href="{{ route('invoice.show', $receipt->invoice_id) }}" class="fw-semibold">{{ $invoice?->invoice_number }}</a><div class="cr-muted">{{ $invoice?->invoice_date?->format('d/m/Y') }}</div></td>
                                <td>
                                    @if($project)<a href="{{ route('project.show', $project->id) }}" class="text-decoration-none">{{ $project->project_number }}</a> <span class="cr-muted">{{ $project->project_name }}</span>@else — @endif
                                    <div class="cr-muted">{{ $invoice?->noted ?: ($invoice?->billingTerm?->name ?? 'Pembayaran') }} ({{ \App\Models\Invoice::formatNumber($invoice?->percent_payment) }}%){{ $invoice?->isOutsideTimeline() ? ' · di luar timeline' : '' }}</div>
                                </td>
                                <td>{{ $invoice?->due_date?->format('d/m/Y') ?? '—' }}</td>
                                <td class="text-end fw-semibold" style="white-space:nowrap">{{ \App\Models\Invoice::formatMoney($invoice?->total_payment) }}</td>
                                <td class="text-end">{!! $invoice?->statusBadgeHtml() !!}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @include('partials.purchase-documents', [
            'documents' => $documents,
            'types' => $receipt->documentTypes(),
            'uploadUrl' => route('customer-receipt.documents.store', $receipt->id),
            'canUpload' => $canCreate,
            'hint' => 'Lampirkan bukti transfer / mutasi bank. Setelah diposting, dokumen hanya bisa ditambah.',
        ])
    </div>
    <div class="col-xl-4">
        <div class="card-custom fade-in">
            <div class="card-header-custom"><span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span></div>
            <div class="card-body-custom">
                <dl class="cr-dl">
                    <dt>Customer</dt><dd class="fw-semibold">{{ $receipt->company?->account_name ?? '—' }}</dd>
                    <dt>Rekening Penerimaan</dt><dd>{{ $receipt->deposit_account ?: '—' }}</dd>
                    <dt>Metode</dt><dd>{{ $receipt->method_label }}</dd>
                    <dt>Referensi</dt><dd>{{ $receipt->reference_number ?: '—' }}</dd>
                    <dt>Dibuat</dt><dd>{{ $receipt->creator?->display_name ?? '—' }}<div class="cr-muted">{{ $receipt->created_at?->format('d/m/Y H:i') }}</div></dd>
                    @if($receipt->notes)<dt>Catatan</dt><dd>{{ $receipt->notes }}</dd>@endif
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="crVoidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Void Penerimaan {{ $receipt->receipt_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="cr-muted">Penerimaan dibatalkan dan invoice {{ $invoice?->invoice_number }} kembali Belum Dibayar.</p>
                <label class="form-label" for="cr-void-reason">Alasan <span class="text-danger">*</span></label>
                <textarea id="cr-void-reason" class="form-control" rows="3" placeholder="Contoh: salah pilih invoice / dana belum masuk"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-danger btn-sm" id="cr-void-btn" onclick="crVoid()"><i class="fa fa-ban me-1"></i> Void Penerimaan</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
const crCsrf = '{{ csrf_token() }}';
let crVoidModal = null;
function crError(xhr, fallback) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : fallback); }

function crPost() {
    Swal.fire({ title: 'Posting penerimaan?', text: 'Pastikan dana sudah masuk di mutasi bank. Invoice akan ditandai Lunas.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Posting', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $('#cr-post-btn').prop('disabled', true);
            $.post('{{ route("customer-receipt.approve", $receipt->id) }}', { _token: crCsrf })
                .done(function(res) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 700); })
                .fail(function(xhr) { crError(xhr, 'Gagal memposting.'); $('#cr-post-btn').prop('disabled', false); });
        });
}

function crDelete() {
    Swal.fire({ title: 'Hapus draft penerimaan?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $.ajax({ url: '{{ route("customer-receipt.destroy", $receipt->id) }}', method: 'DELETE', headers: { 'X-CSRF-TOKEN': crCsrf } })
                .done(function(res) { toastr.success(res.message); setTimeout(function() { location.href = '{{ route("customer-receipt.index") }}'; }, 600); })
                .fail(function(xhr) { crError(xhr, 'Gagal menghapus.'); });
        });
}

function crOpenVoid() {
    $('#cr-void-reason').val('');
    if (!crVoidModal) crVoidModal = new bootstrap.Modal(document.getElementById('crVoidModal'));
    crVoidModal.show();
}

function crVoid() {
    var reason = $('#cr-void-reason').val().trim();
    if (!reason) { toastr.error('Alasan void wajib diisi.'); return; }
    $('#cr-void-btn').prop('disabled', true);
    $.post('{{ route("customer-receipt.void", $receipt->id) }}', { _token: crCsrf, void_reason: reason })
        .done(function(res) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 700); })
        .fail(function(xhr) { crError(xhr, 'Gagal void.'); $('#cr-void-btn').prop('disabled', false); });
}
</script>
@endsection
