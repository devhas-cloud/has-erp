@extends('layouts.app')

@section('title', 'Detail Direct Purchase')
@section('page-title', 'Detail Direct Purchase')

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
            {{ $directPurchase->dp_number ?: 'Belum ada No. Dokumen' }} &middot; {{ $directPurchase->supplier_name }}
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('direct-purchase.pdf', $directPurchase->id) }}" target="_blank" class="btn btn-secondary btn-sm">
            <i class="fa fa-print me-1"></i> Cetak PDF
        </a>
        <a href="{{ route('direct-purchase.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
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
            <i class="fa fa-truck-ramp-box me-1"></i> Terima Barang
        </a>
    @endif
</div>

@if($directPurchase->status === 'rejected' && $directPurchase->approval_note)
<div class="qt-action-bar" style="border-color:#fecaca;background:#fef2f2;">
    <i class="fa fa-circle-exclamation" style="color:#b91c1c"></i>
    <div>
        <div style="font-weight:600;font-size:13px;color:#7f1d1d">Alasan Penolakan</div>
        <div style="font-size:13px;color:#7f1d1d">{{ $directPurchase->approval_note }}</div>
    </div>
</div>
@endif

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi Direct Purchase</span>
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
                <td>No. Dokumen</td>
                <td>{{ $directPurchase->dp_number ?: '—' }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>{{ $directPurchase->date?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            @if($directPurchase->payment_term_days !== null)
            <tr>
                <td>Termin Bayar</td>
                <td>{{ \App\Models\Supplier::termLabel($directPurchase->payment_term_days) }}</td>
            </tr>
            @endif
            <tr>
                <td>Tujuan Barang</td>
                <td>
                    <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:11px">{{ $directPurchase->purpose_label }}</span>
                    @if($directPurchase->purpose === 'project')
                        {{ $directPurchase->project ? $directPurchase->project->project_number.' — '.$directPurchase->project->project_name : '—' }}
                    @elseif($directPurchase->purpose === 'stock')
                        <span style="font-size:12px;color:var(--text-muted)">masuk gudang sebagai stok bebas</span>
                    @else
                        <span style="font-size:12px;color:var(--text-muted)">konfirmasi terima, tidak masuk stok</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Dibuat Oleh</td>
                <td>{{ $directPurchase->creator?->display_name ?? '—' }} &middot; {{ $directPurchase->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
            @if($directPurchase->final_checked_by)
            <tr>
                <td>{{ $directPurchase->status === 'approved' ? 'Disetujui Oleh' : 'Ditolak Oleh' }}</td>
                <td>{{ $directPurchase->finalChecker?->display_name ?? '—' }} &middot; {{ ($directPurchase->approved_at ?? $directPurchase->rejected_at)?->format('d/m/Y H:i') }}</td>
            </tr>
            @endif
            <tr>
                <td>Catatan</td>
                <td>{{ $directPurchase->notes ?: '—' }}</td>
            </tr>
        </table>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Daftar Item</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Part Number</th>
                        <th>Deskripsi / Nama Barang</th>
                        <th class="text-center">Qty</th>
                        <th>Unit</th>
                        <th class="text-center">Diterima</th>
                        <th class="text-end">Harga Satuan</th>
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
                                    {{ $item->currency ?: 'IDR' }} {{ number_format($item->price, 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($item->amount(), 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center" style="color:var(--text-muted)">Belum ada item.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    @php($dpTotals = $directPurchase->totalsFrom($directPurchase->grandTotal()))
                    <tr>
                        <td colspan="7" class="text-end">Sub Total</td>
                        <td class="text-end fw-semibold">{{ number_format($dpTotals['subtotal'], 2, ',', '.') }}</td>
                    </tr>
                    @if($dpTotals['ppn'] > 0)
                    <tr>
                        <td colspan="7" class="text-end">PPN {{ rtrim(rtrim(number_format($directPurchase->ppn_percent, 2, ',', '.'), '0'), ',') }}%</td>
                        <td class="text-end fw-semibold">{{ number_format($dpTotals['ppn'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="7" class="text-end fw-bold">Grand Total ({{ $directPurchase->currencyCode() }})</td>
                        <td class="text-end fw-bold">{{ number_format($dpTotals['total'], 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@if($directPurchase->status === 'approved')
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-truck-ramp-box me-2" style="color:var(--accent)"></i>Penerimaan Barang</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>No. Penerimaan</th>
                        <th>Tanggal</th>
                        <th>Gudang</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($directPurchase->goodsReceipts as $receipt)
                        <tr>
                            <td><a href="{{ route('goods-receipt.show', $receipt->id) }}">{{ $receipt->receipt_number }}</a></td>
                            <td>{{ $receipt->received_date?->format('d/m/Y') }}</td>
                            <td>{{ $receipt->warehouse?->name ?? 'Langsung pakai' }}</td>
                            <td>{!! $receipt->statusBadgeHtml() !!}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center" style="color:var(--text-muted)">Belum ada penerimaan.</td>
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
    <div class="card-header-custom"><span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Tagihan &amp; Pembayaran</span></div>
    <div class="card-body-custom p-2">
        <table class="table table-custom align-middle mb-0">
            <tbody>
                @foreach($directPurchase->supplierBills as $bill)
                    <tr @if($bill->isVoid()) style="opacity:.55" @endif>
                        <td><a href="{{ route('supplier-bill.show', $bill->id) }}" class="fw-semibold">{{ $bill->bill_number }}</a></td>
                        <td>Jatuh tempo {{ $bill->due_date?->format('d/m/Y') }}</td>
                        <td class="text-end" style="white-space:nowrap">{{ $bill->currency }} {{ \App\Models\SupplierBill::formatMoney($bill->total) }}<div style="font-size:12px;color:var(--text-muted)">Sisa {{ \App\Models\SupplierBill::formatMoney($bill->outstanding()) }}</div></td>
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
    'hint' => 'Opsional — penawaran / nota supplier. Setelah diputuskan, dokumen hanya bisa ditambah.',
])
@endsection

@push('modals')
<div class="modal fade" id="dpRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Tolak Direct Purchase</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea id="dp-reject-note" class="form-control" rows="3" placeholder="Wajib diisi alasan penolakan"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="dp-btn-reject">
                    <i class="fa fa-xmark me-1"></i> Tolak
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
        text: 'Direct purchase akan dikirim untuk approval.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Submit',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(dpSubmitUrl, { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Dikirim untuk approval.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal submit.');
            });
    });
}

function dpApprove() {
    Swal.fire({
        title: 'Approve Direct Purchase?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(dpApproveUrl, { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Disetujui.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal approve.');
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
        toastr.error('Alasan penolakan wajib diisi.');
        return;
    }
    $('#dp-btn-reject').prop('disabled', true);
    $.ajax({
        url: dpRejectUrl,
        method: 'POST',
        data: { _token: '{{ csrf_token() }}', approval_note: note }
    }).done(function(res) {
        toastr.success(res.message || 'Ditolak.');
        dpRejectModalInstance.hide();
        setTimeout(function() { window.location.reload(); }, 800);
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menolak.');
    }).always(function() {
        $('#dp-btn-reject').prop('disabled', false);
    });
});
</script>
@endsection
