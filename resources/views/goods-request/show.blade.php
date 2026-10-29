@extends('layouts.app')

@section('title', 'Detail Permintaan Barang')
@section('page-title', 'Detail Permintaan Barang')

@php
    use App\Models\GoodsRequest;

    $project = $goodsRequest->project;
    $status = $goodsRequest->status;
    $isRejected = $status === GoodsRequest::STATUS_REJECTED;
    $isOwner = auth()->id() === $goodsRequest->created_by || auth()->user()->role === 'Admin';
    $canModify = $status === GoodsRequest::STATUS_DRAFT && $isOwner;
    $items = $goodsRequest->items;
    $totalQty = (int) $items->sum('qty');
    $orderedCount = $items->filter(fn ($item) => $item->purchaseOrderItem)->count();
    $showPoColumn = $status === GoodsRequest::STATUS_APPROVED;

    $approvals = $goodsRequest->approvals;
    $required = GoodsRequest::REQUIRED_APPROVALS;
    $remaining = max(0, $required - $approvals->count());
    $myApproval = $approvals->firstWhere('user_id', auth()->id());

    // Langkah: 1 Draft, 2..(1+required) Approval ke-n, terakhir Approved.
    // Saat menunggu/ditolak, langkah aktif = slot approval berikutnya.
    $lastStep = $required + 2;
    $step = match ($status) {
        GoodsRequest::STATUS_DRAFT => 1,
        GoodsRequest::STATUS_APPROVED => $lastStep,
        default => min(2 + $approvals->count(), $lastStep - 1),
    };

    $nextHint = match ($status) {
        GoodsRequest::STATUS_DRAFT => $items->isEmpty()
            ? 'Tambahkan item terlebih dahulu, lalu ajukan approval.'
            : 'Periksa kembali item, lalu klik Submit Approval.',
        GoodsRequest::STATUS_WAITING_APPROVAL => $myApproval
            ? 'Anda sudah approve. Menunggu '.$remaining.' approver lain.'
            : 'Menunggu '.$remaining.' approval lagi dari user yang punya hak approve.',
        GoodsRequest::STATUS_APPROVED => $orderedCount === $items->count() && $items->isNotEmpty()
            ? 'Semua item sudah masuk Purchase Order.'
            : 'Disetujui '.$required.' approver — item siap diproses menjadi Purchase Order oleh purchasing.',
        GoodsRequest::STATUS_REJECTED => 'Permintaan ditolak. Buat permintaan baru bila masih dibutuhkan.',
        default => '',
    };

    $stepClass = function (int $n) use ($step, $isRejected, $lastStep) {
        if ($isRejected && $n === $step) return 'failed';
        if ($n < $step || $step === $lastStep) return 'done';
        return $n === $step ? 'current' : '';
    };

    $stepDefs = [1 => ['Draft', null]];
    for ($i = 0; $i < $required; $i++) {
        $approval = $approvals->get($i);
        $stepDefs[$i + 2] = ['Approval '.($i + 1), $approval ? $approval->user?->display_name : null];
    }
    $stepDefs[$lastStep] = ['Approved', null];
    if ($isRejected) $stepDefs[$step][1] = 'Ditolak — '.($goodsRequest->finalChecker?->display_name ?? '—');
    $sourceOf = fn ($item) => $item->quote_configuration_item_id ? 'config' : ($item->master_product_id ? 'catalog' : 'manual');
    $sourceLabels = ['config' => 'Configuration', 'catalog' => 'Katalog', 'manual' => 'Manual'];
@endphp

@section('styles')
<style>
    .gr-chip {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 1px 8px; border-radius: 999px;
        font-size: 11px; font-weight: 600; white-space: nowrap;
        background: var(--accent-soft); color: var(--accent);
    }
    .gr-chip.config { background: #dbeafe; color: #1e40af; }
    .gr-chip.catalog { background: #dcfce7; color: #166534; }
    .gr-chip.manual { background: #f3f4f6; color: #4b5563; }
    .gr-chip.warn { background: #fef3c7; color: #92400e; }

    .gr-stepper { display: flex; align-items: center; gap: 0; margin-bottom: 14px; }
    .gr-step { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-muted); white-space: nowrap; }
    .gr-step .dot {
        width: 26px; height: 26px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 700;
        background: var(--accent-subtle); border: 1px solid var(--card-border); color: var(--text-muted);
    }
    .gr-step.done .dot { background: var(--accent); border-color: var(--accent); color: #fff; }
    .gr-step.current { color: var(--text-primary); font-weight: 600; }
    .gr-step.current .dot { background: var(--accent-soft); border-color: var(--accent); color: var(--accent); }
    .gr-step.failed .dot { background: var(--danger); border-color: var(--danger); color: #fff; }
    .gr-step.failed { color: var(--danger); font-weight: 600; }
    .gr-step .sub { display: block; font-size: 11px; font-weight: 400; font-style: normal; color: var(--text-muted); }
    .gr-step-line { flex: 1; height: 2px; background: var(--card-border); margin: 0 10px; min-width: 24px; }
    .gr-step-line.done { background: var(--accent); }

    .gr-status-bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .gr-hint { font-size: 13px; color: var(--text-muted); }
    .gr-actions { display: flex; gap: 8px; flex-wrap: wrap; }

    .gr-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 16px; padding-top: 14px; border-top: 1px dashed var(--card-border); }
    .gr-fact .lbl { font-size: 12px; color: var(--text-muted); }
    .gr-fact .val { font-size: 18px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }
    .gr-fact .sub { font-size: 12px; color: var(--text-muted); }

    .gr-banner { display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: var(--radius); margin-bottom: 16px; font-size: 13px; }
    .gr-banner.danger { background: #fef2f2; border: 1px solid #fecaca; color: #7f1d1d; }
    .gr-banner .title { font-weight: 600; }

    .gr-dl { display: grid; grid-template-columns: 130px 1fr; gap: 9px 12px; margin: 0; font-size: 13px; }
    .gr-dl dt { color: var(--text-muted); font-weight: 500; font-size: 12.5px; }
    .gr-dl dd { margin: 0; color: var(--text-primary); }
    .gr-dl .muted { color: var(--text-muted); font-size: 12px; }

    .gr-items td { vertical-align: top; }
    .gr-items .gr-desc { font-size: 13px; line-height: 1.5; }
    .gr-items tfoot td { font-weight: 600; border-top: 2px solid var(--card-border); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Permintaan Barang #{{ $goodsRequest->id }}</h1>
        <p class="page-header-sub">
            {{ $project?->project_number ?? '—' }} &middot; {{ $project?->project_name ?? '—' }}
            @if($project?->company) &middot; {{ $project->company->account_name }} @endif
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('goods-request.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-body-custom">
        <div class="gr-stepper">
            @foreach($stepDefs as $n => [$label, $who])
                @php $cls = $stepClass($n); @endphp
                @if($n > 1)<div class="gr-step-line {{ $n <= $step ? 'done' : '' }}"></div>@endif
                <div class="gr-step {{ $cls }}">
                    <span class="dot">
                        @if($cls === 'done')<i class="fa fa-check"></i>
                        @elseif($cls === 'failed')<i class="fa fa-xmark"></i>
                        @else{{ $n }}@endif
                    </span>
                    <span>{{ $label }}@if($who)<span class="sub">{{ $who }}</span>@endif</span>
                </div>
            @endforeach
        </div>

        <div class="gr-status-bar">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                {!! $goodsRequest->statusBadgeHtml() !!}
                <span class="gr-hint">{{ $nextHint }}</span>
            </div>
            <div class="gr-actions">
                @if($canModify)
                    <a href="{{ route('goods-request.edit', $goodsRequest->id) }}" class="btn btn-secondary btn-sm">
                        <i class="fa fa-pen me-1"></i> Edit
                    </a>
                    @if($canDelete ?? false)
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="grDelete()">
                        <i class="fa fa-trash me-1"></i> Hapus
                    </button>
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" onclick="grSubmit()" @disabled($items->isEmpty())>
                        <i class="fa fa-paper-plane me-1"></i> Submit Approval
                    </button>
                @endif
                @if($canApproveThis)
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="grOpenReject()">
                        <i class="fa fa-xmark me-1"></i> Reject
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="grApprove()">
                        <i class="fa fa-check me-1"></i> Approve ({{ $approvals->count() + 1 }}/{{ $required }})
                    </button>
                @endif
            </div>
        </div>

        <div class="gr-facts">
            <div class="gr-fact">
                <div class="lbl">Jumlah Item</div>
                <div class="val">{{ number_format($items->count()) }}</div>
            </div>
            <div class="gr-fact">
                <div class="lbl">Total Qty</div>
                <div class="val">{{ number_format($totalQty) }}</div>
            </div>
            @if($showPoColumn)
            <div class="gr-fact">
                <div class="lbl">Sudah di-PO</div>
                <div class="val">{{ $orderedCount }} <span class="sub">/ {{ $items->count() }} item</span></div>
            </div>
            @endif
            <div class="gr-fact">
                <div class="lbl">Diajukan</div>
                <div class="val" style="font-size:14px">{{ $goodsRequest->created_at?->format('d M Y') ?? '—' }}</div>
                <div class="sub">{{ $goodsRequest->creator?->display_name ?? '—' }}</div>
            </div>
        </div>
    </div>
</div>

@if($isRejected && $goodsRequest->approval_note)
<div class="gr-banner danger">
    <i class="fa fa-circle-exclamation mt-1"></i>
    <div>
        <div class="title">Alasan Penolakan</div>
        <div>{{ $goodsRequest->approval_note }}</div>
    </div>
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-diagram-project me-2" style="color:var(--accent)"></i>Project &amp; Customer</span>
            </div>
            <div class="card-body-custom">
                <dl class="gr-dl">
                    <dt>Project</dt>
                    <dd>
                        @if($project)
                            <a href="{{ route('project.show', $goodsRequest->project_id) }}" class="fw-semibold">{{ $project->project_number }}</a>
                            <div>{{ $project->project_name }}</div>
                        @else
                            —
                        @endif
                    </dd>
                    <dt>Customer</dt>
                    <dd>{{ $project?->company?->account_name ?? $goodsRequest->quotation?->to_name ?? '—' }}</dd>
                    <dt>Opportunity</dt>
                    <dd>{{ $goodsRequest->opportunity?->opportunity_name ?? '—' }}</dd>
                    <dt>Quotation</dt>
                    <dd>
                        @if($goodsRequest->quotation)
                            <a href="{{ route('quotation.show', $goodsRequest->quotation_id) }}">{{ $goodsRequest->quotation->quotation_number ?? '#'.$goodsRequest->quotation_id }}</a>
                        @else
                            <span class="gr-chip warn">Project tanpa quotation</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-user-check me-2" style="color:var(--accent)"></i>Pengajuan &amp; Persetujuan</span>
            </div>
            <div class="card-body-custom">
                <dl class="gr-dl">
                    <dt>Divisi Pemohon</dt>
                    <dd>{{ $goodsRequest->division?->division_name ?? '—' }}</dd>
                    <dt>Dibuat Oleh</dt>
                    <dd>
                        {{ $goodsRequest->creator?->display_name ?? '—' }}
                        <div class="muted">{{ $goodsRequest->created_at?->format('d M Y H:i') }}</div>
                    </dd>
                    <dt>Approval</dt>
                    <dd>
                        @forelse($approvals as $i => $approval)
                            <div class="{{ $i ? 'mt-1' : '' }}">
                                <i class="fa fa-circle-check me-1" style="color:var(--accent)"></i>{{ $approval->user?->display_name ?? '—' }}
                                <span class="muted">· {{ $approval->approved_at?->format('d M Y H:i') }}</span>
                            </div>
                        @empty
                            <span class="muted">Belum ada</span>
                        @endforelse
                        @if($status === GoodsRequest::STATUS_WAITING_APPROVAL)
                            <div class="muted mt-1">{{ $approvals->count() }}/{{ $required }} approval terkumpul</div>
                        @endif
                    </dd>
                    @if($isRejected)
                    <dt>Ditolak Oleh</dt>
                    <dd>
                        {{ $goodsRequest->finalChecker?->display_name ?? '—' }}
                        <div class="muted">{{ $goodsRequest->rejected_at?->format('d M Y H:i') }}</div>
                    </dd>
                    @endif
                    <dt>Catatan</dt>
                    <dd style="white-space:pre-line">{{ $goodsRequest->notes ?: '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in mb-4">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Daftar Item <span class="gr-chip ms-1">{{ $items->count() }} item</span></span>
        @if($canModify)
            <a href="{{ route('goods-request.edit', $goodsRequest->id) }}" class="btn btn-soft btn-sm"><i class="fa fa-pen me-1"></i> Ubah Item</a>
        @endif
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom mb-0 gr-items">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th style="width:17%">Part Number</th>
                        <th>Nama Barang / Deskripsi</th>
                        <th class="text-center" style="width:80px">Qty</th>
                        <th style="width:90px">Unit</th>
                        <th style="width:90px">Mata Uang</th>
                        @if($showPoColumn)<th style="width:170px">Purchase Order</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $i => $item)
                        @php $source = $sourceOf($item); $po = $item->purchaseOrderItem?->purchaseOrder; @endphp
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td style="font-size:13px">{{ $item->part_number ?: '—' }}</td>
                            <td>
                                {{-- Deskripsi disimpan lewat Quotation::sanitizeDescription() --}}
                                <div class="gr-desc">{!! $item->description ?: '—' !!}</div>
                                <span class="gr-chip {{ $source }} mt-1">{{ $sourceLabels[$source] }}</span>
                            </td>
                            <td class="text-center fw-semibold">{{ $item->qty ?? '—' }}</td>
                            <td>{{ $item->unit ?: '—' }}</td>
                            <td><span class="gr-chip">{{ $item->currency ?: '—' }}</span></td>
                            @if($showPoColumn)
                            <td>
                                @if($po)
                                    <a href="{{ route('purchase-order.show', $po->id) }}" class="fw-semibold" style="font-size:13px">{{ $po->po_number ?: 'PO #'.$po->id }}</a>
                                @else
                                    <span class="gr-chip warn">Belum di-PO</span>
                                @endif
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showPoColumn ? 7 : 6 }}" class="text-center py-4" style="color:var(--text-muted)">
                                <i class="fa-solid fa-inbox me-1"></i> Belum ada item.
                                @if($canModify)<a href="{{ route('goods-request.edit', $goodsRequest->id) }}">Tambahkan sekarang</a>@endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($items->isNotEmpty())
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end">Total Qty</td>
                        <td class="text-center">{{ number_format($totalQty) }}</td>
                        <td colspan="{{ $showPoColumn ? 3 : 2 }}"></td>
                    </tr>
                </tfoot>
                @endif
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
const grIsLastApproval = {{ $remaining <= 1 ? 'true' : 'false' }};
const grDeleteUrl = '{{ route("goods-request.destroy", $goodsRequest->id) }}';

function grDelete() {
    Swal.fire({
        title: 'Hapus Permintaan Barang?',
        text: 'Permintaan barang #' + grId + ' akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: grDeleteUrl,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Dihapus.');
            setTimeout(function() { window.location.href = '{{ route("goods-request.index") }}'; }, 800);
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}

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
        text: grIsLastApproval
            ? 'Ini approval terakhir — permintaan barang menjadi Approved dan siap diproses PO.'
            : 'Approval Anda tercatat, masih menunggu approver berikutnya.',
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
