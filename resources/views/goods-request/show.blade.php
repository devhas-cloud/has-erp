@extends('layouts.app')

@section('title', 'Detail Permintaan Barang')
@section('page-title', 'Detail Permintaan Barang')

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
        <h1 class="page-header-title">Permintaan Barang #{{ $goodsRequest->id }}</h1>
        <p class="page-header-sub">
            Quotation {{ $goodsRequest->quotation?->quotation_number ?? '#'.$goodsRequest->quotation_id }} &middot; {{ $goodsRequest->quotation?->to_name ?? '—' }}
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('goods-request.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="qt-action-bar">
    <span>{!! $goodsRequest->statusBadgeHtml() !!}</span>
    <div class="spacer"></div>
    @if($goodsRequest->status === 'draft' && (auth()->id() === $goodsRequest->created_by || auth()->user()->role === 'Admin'))
        <a href="{{ route('goods-request.edit', $goodsRequest->id) }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-pen me-1"></i> Edit
        </a>
        <button type="button" class="btn btn-primary btn-sm" onclick="grSubmit()">
            <i class="fa fa-paper-plane me-1"></i> Submit Approval
        </button>
    @endif
    @if($goodsRequest->status === 'waiting_approval' && $canApprove)
        <button type="button" class="btn btn-success btn-sm" onclick="grApprove()">
            <i class="fa fa-check me-1"></i> Approve
        </button>
        <button type="button" class="btn btn-danger btn-sm" onclick="grOpenReject()">
            <i class="fa fa-xmark me-1"></i> Reject
        </button>
    @endif
</div>

@if($goodsRequest->status === 'rejected' && $goodsRequest->approval_note)
<div class="qt-action-bar" style="border-color:#fecaca;background:#fef2f2;">
    <i class="fa fa-circle-exclamation" style="color:#b91c1c"></i>
    <div>
        <div style="font-weight:600;font-size:13px;color:#7f1d1d">Alasan Penolakan</div>
        <div style="font-size:13px;color:#7f1d1d">{{ $goodsRequest->approval_note }}</div>
    </div>
</div>
@endif

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi Permintaan</span>
    </div>
    <div class="card-body-custom">
        <table class="info-table" style="width:100%">
            <tr>
                <td>Opportunity</td>
                <td>{{ $goodsRequest->opportunity?->opportunity_name ?? '—' }}</td>
            </tr>
            <tr>
                <td>Quotation</td>
                <td>
                    @if($goodsRequest->quotation)
                        <a href="{{ route('quotation.show', $goodsRequest->quotation_id) }}">{{ $goodsRequest->quotation->quotation_number ?? '#'.$goodsRequest->quotation_id }}</a>
                    @else
                        —
                    @endif
                </td>
            </tr>
            <tr>
                <td>To (Company)</td>
                <td>{{ $goodsRequest->quotation?->to_name ?? '—' }}</td>
            </tr>
            <tr>
                <td>Divisi Pemohon</td>
                <td>{{ $goodsRequest->division?->division_name ?? '—' }}</td>
            </tr>
            <tr>
                <td>Dibuat Oleh</td>
                <td>{{ $goodsRequest->creator?->username ?? '—' }} &middot; {{ $goodsRequest->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
            @if($goodsRequest->final_checked_by)
            <tr>
                <td>{{ $goodsRequest->status === 'approved' ? 'Disetujui Oleh' : 'Ditolak Oleh' }}</td>
                <td>{{ $goodsRequest->finalChecker?->username ?? '—' }} &middot; {{ ($goodsRequest->approved_at ?? $goodsRequest->rejected_at)?->format('d/m/Y H:i') }}</td>
            </tr>
            @endif
            <tr>
                <td>Catatan</td>
                <td>{{ $goodsRequest->notes ?: '—' }}</td>
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
                    </tr>
                </thead>
                <tbody>
                    @forelse($goodsRequest->items as $i => $item)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td>{{ $item->part_number ?: '—' }}</td>
                            <td>{!! $item->description ?: '—' !!}</td>
                            <td class="text-center">{{ $item->qty ?? '—' }}</td>
                            <td>{{ $item->unit ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center" style="color:var(--text-muted)">Belum ada item.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="grRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Tolak Permintaan Barang</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea id="gr-reject-note" class="form-control" rows="3" placeholder="Wajib diisi alasan penolakan"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="gr-btn-reject">
                    <i class="fa fa-xmark me-1"></i> Tolak
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let grRejectModalInstance = null;
const grId = {{ $goodsRequest->id }};
const grSubmitUrl = '{{ route("goods-request.submit", $goodsRequest->id) }}';
const grApproveUrl = '{{ route("goods-request.approve", $goodsRequest->id) }}';
const grRejectUrl = '{{ route("goods-request.reject", $goodsRequest->id) }}';

function grSubmit() {
    Swal.fire({
        title: 'Submit Approval?',
        text: 'Permintaan barang akan dikirim untuk approval.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Submit',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(grSubmitUrl, { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Dikirim untuk approval.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal submit.');
            });
    });
}

function grApprove() {
    Swal.fire({
        title: 'Approve Permintaan Barang?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(grApproveUrl, { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Disetujui.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal approve.');
            });
    });
}

function grOpenReject() {
    $('#gr-reject-note').val('');
    if (!grRejectModalInstance) {
        grRejectModalInstance = new bootstrap.Modal(document.getElementById('grRejectModal'));
    }
    grRejectModalInstance.show();
}

$(document).on('click', '#gr-btn-reject', function() {
    var note = $('#gr-reject-note').val().trim();
    if (!note) {
        toastr.error('Alasan penolakan wajib diisi.');
        return;
    }
    $('#gr-btn-reject').prop('disabled', true);
    $.ajax({
        url: grRejectUrl,
        method: 'POST',
        data: { _token: '{{ csrf_token() }}', approval_note: note }
    }).done(function(res) {
        toastr.success(res.message || 'Ditolak.');
        grRejectModalInstance.hide();
        setTimeout(function() { window.location.reload(); }, 800);
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menolak.');
    }).always(function() {
        $('#gr-btn-reject').prop('disabled', false);
    });
});
</script>
@endsection
