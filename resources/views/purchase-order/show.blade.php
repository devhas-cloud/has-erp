@extends('layouts.app')

@section('title', 'Detail Purchase Order')
@section('page-title', 'Detail Purchase Order')

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
        <h1 class="page-header-title">Purchase Order #{{ $purchaseOrder->id }}</h1>
        <p class="page-header-sub">
            {{ $purchaseOrder->po_number ?: 'Belum ada No. PO' }} &middot; {{ $purchaseOrder->supplier_name }}
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('purchase-order.pdf', $purchaseOrder->id) }}" target="_blank" class="btn btn-secondary btn-sm">
            <i class="fa fa-print me-1"></i> Cetak PDF
        </a>
        <a href="{{ route('purchase-order.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="qt-action-bar">
    <span>{!! $purchaseOrder->statusBadgeHtml() !!}</span>
    <div class="spacer"></div>
    @if($purchaseOrder->status === 'draft' && (auth()->id() === $purchaseOrder->created_by || auth()->user()->role === 'Admin'))
        <a href="{{ route('purchase-order.edit', $purchaseOrder->id) }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-pen me-1"></i> Edit
        </a>
        <button type="button" class="btn btn-primary btn-sm" onclick="poSubmit()">
            <i class="fa fa-paper-plane me-1"></i> Submit Approval
        </button>
    @endif
    @if($purchaseOrder->status === 'waiting_approval' && $canApprove)
        <button type="button" class="btn btn-success btn-sm" onclick="poApprove()">
            <i class="fa fa-check me-1"></i> Approve
        </button>
        <button type="button" class="btn btn-danger btn-sm" onclick="poOpenReject()">
            <i class="fa fa-xmark me-1"></i> Reject
        </button>
    @endif
</div>

@if($purchaseOrder->status === 'rejected' && $purchaseOrder->approval_note)
<div class="qt-action-bar" style="border-color:#fecaca;background:#fef2f2;">
    <i class="fa fa-circle-exclamation" style="color:#b91c1c"></i>
    <div>
        <div style="font-weight:600;font-size:13px;color:#7f1d1d">Alasan Penolakan</div>
        <div style="font-size:13px;color:#7f1d1d">{{ $purchaseOrder->approval_note }}</div>
    </div>
</div>
@endif

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi Purchase Order</span>
    </div>
    <div class="card-body-custom">
        <table class="info-table" style="width:100%">
            <tr>
                <td>Supplier</td>
                <td>
                    {{ $purchaseOrder->supplier_name }}
                    @if($purchaseOrder->supplier_address)<br><span style="font-size:12px;color:var(--text-muted)">{!! nl2br(e($purchaseOrder->supplier_address)) !!}</span>@endif
                    @if($purchaseOrder->supplier_phone || $purchaseOrder->supplier_fax)
                        <br><span style="font-size:12px;color:var(--text-muted)">
                            @if($purchaseOrder->supplier_phone) Phone: {{ $purchaseOrder->supplier_phone }} @endif
                            @if($purchaseOrder->supplier_fax) &middot; Fax: {{ $purchaseOrder->supplier_fax }} @endif
                        </span>
                    @endif
                    @if($purchaseOrder->supplier_attn)<br><span style="font-size:12px;color:var(--text-muted)">Attn: {{ $purchaseOrder->supplier_attn }}</span>@endif
                </td>
            </tr>
            <tr>
                <td>No. PO</td>
                <td>{{ $purchaseOrder->po_number ?: '—' }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>{{ $purchaseOrder->date?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            <tr>
                <td>Terms</td>
                <td>{{ $purchaseOrder->terms ?: '—' }}</td>
            </tr>
            @php($divisionNames = $purchaseOrder->divisionNames())
            <tr>
                <td>Divisi Terkait</td>
                <td>
                    @if(count($divisionNames))
                        @foreach($divisionNames as $dn)
                            <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:11px;margin-right:4px">{{ $dn }}</span>
                        @endforeach
                    @else
                        —
                    @endif
                </td>
            </tr>
            <tr>
                <td>Dibuat Oleh</td>
                <td>{{ $purchaseOrder->creator?->username ?? '—' }} &middot; {{ $purchaseOrder->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
            @if($purchaseOrder->final_checked_by)
            <tr>
                <td>{{ $purchaseOrder->status === 'approved' ? 'Disetujui Oleh' : 'Ditolak Oleh' }}</td>
                <td>{{ $purchaseOrder->finalChecker?->username ?? '—' }} &middot; {{ ($purchaseOrder->approved_at ?? $purchaseOrder->rejected_at)?->format('d/m/Y H:i') }}</td>
            </tr>
            @endif
            <tr>
                <td>Catatan</td>
                <td>{{ $purchaseOrder->notes ?: '—' }}</td>
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
                        <th style="width:12%">Asal</th>
                        <th>Part Number</th>
                        <th>Deskripsi / Nama Barang</th>
                        <th class="text-center">Qty</th>
                        <th>Unit</th>
                        <th class="text-end">Harga Satuan</th>
                        <th class="text-end">Subtotal (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($poGroups as $group)
                        <tr style="background:#fff7d6;">
                            <td colspan="8" style="font-weight:600;color:#92400e">{{ $group['label'] }}</td>
                        </tr>
                        @foreach($group['items'] as $i => $item)
                            <tr>
                                <td class="text-center">{{ $i + 1 }}</td>
                                <td>
                                    @if($item->goodsRequest)
                                        <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:10px" title="Dari Permintaan Barang #{{ $item->goods_request_id }}">
                                            {{ $item->goodsRequest->quotation?->quotation_number ?? ('GR#'.$item->goods_request_id) }} / {{ $item->goodsRequest->division?->division_name ?? '-' }}
                                        </span>
                                    @else
                                        <span class="text-muted" style="font-size:11px">Manual</span>
                                    @endif
                                </td>
                                <td>{{ $item->part_number ?: '—' }}</td>
                                <td>{!! $item->description ?: '—' !!}</td>
                                <td class="text-center">{{ $item->qty ?? '—' }}</td>
                                <td>{{ $item->unit ?: '—' }}</td>
                                <td class="text-end">
                                    @if($item->price_currency !== null)
                                        {{ $item->currency ?: 'IDR' }} {{ number_format($item->price_currency, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($item->amount(), 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="8" class="text-center" style="color:var(--text-muted)">Belum ada item.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7" class="text-end fw-bold">Grand Total</td>
                        <td class="text-end fw-bold">{{ number_format($purchaseOrder->grandTotal(), 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="poRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Tolak Purchase Order</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea id="po-reject-note" class="form-control" rows="3" placeholder="Wajib diisi alasan penolakan"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="po-btn-reject">
                    <i class="fa fa-xmark me-1"></i> Tolak
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let poRejectModalInstance = null;
const poId = {{ $purchaseOrder->id }};
const poSubmitUrl = '{{ route("purchase-order.submit", $purchaseOrder->id) }}';
const poApproveUrl = '{{ route("purchase-order.approve", $purchaseOrder->id) }}';
const poRejectUrl = '{{ route("purchase-order.reject", $purchaseOrder->id) }}';

function poSubmit() {
    Swal.fire({
        title: 'Submit Approval?',
        text: 'Purchase order akan dikirim untuk approval.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Submit',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(poSubmitUrl, { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Dikirim untuk approval.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal submit.');
            });
    });
}

function poApprove() {
    Swal.fire({
        title: 'Approve Purchase Order?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(poApproveUrl, { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Disetujui.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal approve.');
            });
    });
}

function poOpenReject() {
    $('#po-reject-note').val('');
    if (!poRejectModalInstance) {
        poRejectModalInstance = new bootstrap.Modal(document.getElementById('poRejectModal'));
    }
    poRejectModalInstance.show();
}

$(document).on('click', '#po-btn-reject', function() {
    var note = $('#po-reject-note').val().trim();
    if (!note) {
        toastr.error('Alasan penolakan wajib diisi.');
        return;
    }
    $('#po-btn-reject').prop('disabled', true);
    $.ajax({
        url: poRejectUrl,
        method: 'POST',
        data: { _token: '{{ csrf_token() }}', approval_note: note }
    }).done(function(res) {
        toastr.success(res.message || 'Ditolak.');
        poRejectModalInstance.hide();
        setTimeout(function() { window.location.reload(); }, 800);
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menolak.');
    }).always(function() {
        $('#po-btn-reject').prop('disabled', false);
    });
});
</script>
@endsection
