@extends('layouts.app')

@section('title', 'Direct Purchase Detail')
@section('page-title', 'Direct Purchase Detail')

@section('styles')
<style>
    .qt-action-bar {
        display: flex; gap: 8px; flex-wrap: wrap;
        background: #fff;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        padding: 14px 16px;
        margin-bottom: 20px;
        align-items: center;
    }
    .qt-action-bar .spacer { flex: 1; }
    .info-table td { padding: 7px 0; vertical-align: top; line-height: 1.45; }
    .info-table td:first-child {
        color: var(--text-muted);
        width: 160px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        padding-right: 12px;
    }
    .info-table td:last-child { font-size: 13px; color: var(--text-primary); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Direct Purchase #{{ $directPurchase->id }}</h1>
        <p class="page-header-sub">
            {{ $directPurchase->dp_number ?: 'No Document yet' }} &middot; {{ $directPurchase->supplier_name }}
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('direct-purchase.pdf', $directPurchase->id) }}" target="_blank" class="btn btn-secondary btn-sm">
            <i class="fa fa-print me-1"></i> Print PDF
        </a>
        <a href="{{ route('direct-purchase.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="qt-action-bar">
    <span>{!! $directPurchase->statusBadgeHtml() !!}</span>
    <div class="spacer"></div>
    @if($directPurchase->status === 'draft' && (auth()->id() === $directPurchase->created_by || auth()->user()->role === 'Admin'))
        <a href="{{ route('direct-purchase.edit', $directPurchase->id) }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-pen me-1"></i> Edit
        </a>
        <button type="button" class="btn btn-primary btn-sm" onclick="dpSubmit()">
            <i class="fa fa-paper-plane me-1"></i> Submit Approval
        </button>
    @endif
    @if($directPurchase->status === 'waiting_approval' && $canApprove)
        <button type="button" class="btn btn-success btn-sm" onclick="dpApprove()">
            <i class="fa fa-check me-1"></i> Approve
        </button>
        <button type="button" class="btn btn-danger btn-sm" onclick="dpOpenReject()">
            <i class="fa fa-xmark me-1"></i> Reject
        </button>
    @endif
    @if($directPurchase->isReceivable())
        <a href="{{ route('goods-receipt.create', ['source_type' => 'dp', 'source_id' => $directPurchase->id]) }}" class="btn btn-primary btn-sm">
            <i class="fa fa-truck-ramp-box me-1"></i> Receive Goods
        </a>
    @endif
</div>

@if($directPurchase->status === 'rejected' && $directPurchase->approval_note)
<div class="qt-action-bar" style="border-color:#fecaca;background:#fef2f2;">
    <i class="fa fa-circle-exclamation" style="color:#b91c1c"></i>
    <div>
        <div style="font-weight:600;font-size:13px;color:#7f1d1d">Rejection Reason</div>
        <div style="font-size:13px;color:#7f1d1d">{{ $directPurchase->approval_note }}</div>
    </div>
</div>
@endif

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Direct Purchase Information</span>
    </div>
    <div class="card-body-custom">
        <table class="info-table" style="width:100%">
            <tr>
                <td>Supplier</td>
                <td>
                    {{ $directPurchase->supplier_name }}
                    @if($directPurchase->supplier_phone)<br><span style="font-size:12px;color:var(--text-muted)">Phone: {{ $directPurchase->supplier_phone }}</span>@endif
                </td>
            </tr>
            <tr>
                <td>No. Document</td>
                <td>{{ $directPurchase->dp_number ?: '—' }}</td>
            </tr>
            <tr>
                <td>Date</td>
                <td>{{ $directPurchase->date?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            @if($directPurchase->payment_term_days !== null)
            <tr>
                <td>Payment Terms</td>
                <td>{{ \App\Models\Supplier::termLabel($directPurchase->payment_term_days) }}</td>
            </tr>
            @endif
            <tr>
                <td>Goods Purpose</td>
                <td>
                    <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:11px">{{ $directPurchase->purpose_label }}</span>
                    @if($directPurchase->purpose === 'project')
                        {{ $directPurchase->project ? $directPurchase->project->project_number.' — '.$directPurchase->project->project_name : '—' }}
                    @elseif($directPurchase->purpose === 'stock')
                        <span style="font-size:12px;color:var(--text-muted)">into warehouse as free stock</span>
                    @else
                        <span style="font-size:12px;color:var(--text-muted)">confirm receipt, not into stock</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Created By</td>
                <td>{{ $directPurchase->creator?->display_name ?? '—' }} &middot; {{ $directPurchase->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
            @if($directPurchase->final_checked_by)
            <tr>
                <td>{{ $directPurchase->status === 'approved' ? 'Approved By' : 'Rejected By' }}</td>
                <td>{{ $directPurchase->finalChecker?->display_name ?? '—' }} &middot; {{ ($directPurchase->approved_at ?? $directPurchase->rejected_at)?->format('d/m/Y H:i') }}</td>
            </tr>
            @endif
            <tr>
                <td>Notes</td>
                <td>{{ $directPurchase->notes ?: '—' }}</td>
            </tr>
        </table>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Item List</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Part Number</th>
                        <th>Description / Item Name</th>
                        <th class="text-center">Qty</th>
                        <th>Unit</th>
                        <th class="text-center">Received</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Subtotal ({{ $directPurchase->currencyCode() }})</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($directPurchase->items as $i => $item)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td>{{ $item->part_number ?: '—' }}</td>
                            <td>{!! $item->description ?: '—' !!}</td>
                            <td class="text-center">{{ $item->qty ?? '—' }}</td>
                            <td>{{ $item->unit ?: '—' }}</td>
                            <td class="text-center">{{ $item->receivedQty() }} / {{ (int) $item->qty }}</td>
                            <td class="text-end">
                                @if($item->price !== null)
                                    {{ $item->currency ?: 'IDR' }} {{ \App\Support\Number::money($item->price) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">{{ \App\Support\Number::money($item->amount()) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center" style="color:var(--text-muted)">No items yet.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    @php($dpTotals = $directPurchase->totalsFrom($directPurchase->grandTotal()))
                    <tr>
                        <td colspan="7" class="text-end">Subtotal</td>
                        <td class="text-end fw-semibold">{{ \App\Support\Number::money($dpTotals['subtotal']) }}</td>
                    </tr>
                    @if($dpTotals['ppn'] > 0)
                    <tr>
                        <td colspan="7" class="text-end">VAT {{ \App\Support\Number::percent($directPurchase->ppn_percent) }}%</td>
                        <td class="text-end fw-semibold">{{ \App\Support\Number::money($dpTotals['ppn']) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="7" class="text-end fw-bold">Grand Total ({{ $directPurchase->currencyCode() }})</td>
                        <td class="text-end fw-bold">{{ \App\Support\Number::money($dpTotals['total']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@if($directPurchase->status === 'approved')
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-truck-ramp-box me-2" style="color:var(--accent)"></i>Goods Receiving</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>No. Receipt</th>
                        <th>Date</th>
                        <th>Warehouse</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($directPurchase->goodsReceipts as $receipt)
                        <tr>
                            <td><a href="{{ route('goods-receipt.show', $receipt->id) }}">{{ $receipt->receipt_number }}</a></td>
                            <td>{{ $receipt->received_date?->format('d/m/Y') }}</td>
                            <td>{{ $receipt->warehouse?->name ?? 'Use directly' }}</td>
                            <td>{!! $receipt->statusBadgeHtml() !!}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center" style="color:var(--text-muted)">No receiving yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@if($directPurchase->supplierBills->isNotEmpty())
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom"><span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Bills &amp; Payments</span></div>
    <div class="card-body-custom p-2">
        <table class="table table-custom align-middle mb-0">
            <tbody>
                @foreach($directPurchase->supplierBills as $bill)
                    <tr @if($bill->isVoid()) style="opacity:.55" @endif>
                        <td><a href="{{ route('supplier-bill.show', $bill->id) }}" class="fw-semibold">{{ $bill->bill_number }}</a></td>
                        <td>Due {{ $bill->due_date?->format('d/m/Y') }}</td>
                        <td class="text-end" style="white-space:nowrap">{{ $bill->currency }} {{ \App\Models\SupplierBill::formatMoney($bill->total) }}<div style="font-size:12px;color:var(--text-muted)">Balance {{ \App\Models\SupplierBill::formatMoney($bill->outstanding()) }}</div></td>
                        <td class="text-end">{!! $bill->statusBadgeHtml() !!}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@include('partials.purchase-documents', [
    'documents' => $documents,
    'types' => $directPurchase->documentTypes(),
    'uploadUrl' => route('direct-purchase.documents.store', $directPurchase->id),
    'canUpload' => $canCreate,
    'hint' => 'Optional — supplier quote / note. Once decided, documents can only be added.',
])
@endsection

@push('modals')
<div class="modal fade" id="dpRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Reject Direct Purchase</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea id="dp-reject-note" class="form-control" rows="3" placeholder="Rejection reason required"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="dp-btn-reject">
                    <i class="fa fa-xmark me-1"></i> Reject
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let dpRejectModalInstance = null;
const dpId = {{ $directPurchase->id }};
const dpSubmitUrl = '{{ route("direct-purchase.submit", $directPurchase->id) }}';
const dpApproveUrl = '{{ route("direct-purchase.approve", $directPurchase->id) }}';
const dpRejectUrl = '{{ route("direct-purchase.reject", $directPurchase->id) }}';

function dpSubmit() {
    Swal.fire({
        title: 'Submit Approval?',
        text: 'Direct purchase will be submitted for approval.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Submit',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(dpSubmitUrl, { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Submitted for approval.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to submit.');
            });
    });
}

function dpApprove() {
    Swal.fire({
        title: 'Approve Direct Purchase?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Approve',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(dpApproveUrl, { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Approved.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to approve.');
            });
    });
}

function dpOpenReject() {
    $('#dp-reject-note').val('');
    if (!dpRejectModalInstance) {
        dpRejectModalInstance = new bootstrap.Modal(document.getElementById('dpRejectModal'));
    }
    dpRejectModalInstance.show();
}

$(document).on('click', '#dp-btn-reject', function() {
    var note = $('#dp-reject-note').val().trim();
    if (!note) {
        toastr.error('Rejection reason is required.');
        return;
    }
    $('#dp-btn-reject').prop('disabled', true);
    $.ajax({
        url: dpRejectUrl,
        method: 'POST',
        data: { _token: '{{ csrf_token() }}', approval_note: note }
    }).done(function(res) {
        toastr.success(res.message || 'Rejected.');
        dpRejectModalInstance.hide();
        setTimeout(function() { window.location.reload(); }, 800);
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to reject.');
    }).always(function() {
        $('#dp-btn-reject').prop('disabled', false);
    });
});
</script>
@endsection
