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
    $orderedCount = $items->filter(fn ($item) => $item->isFulfilled())->count();
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
            ? 'Semua item sudah terpenuhi (Purchase Order / Keluar Stok).'
            : 'Disetujui '.$required.' approver — item siap diproses Purchase Order atau dikeluarkan dari stok.',
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
        <h1 class="page-header-title">Permintaan Barang {{ $goodsRequest->number_label }}</h1>
        <p class="page-header-sub">
            {{ $goodsRequest->destinationLabel() }}
            @if($goodsRequest->isForProject() && $project?->company) &middot; {{ $project->company->account_name }} @endif
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
                @php $quotation = $goodsRequest->quotation(); @endphp
                <dl class="gr-dl">
                    <dt>Tujuan</dt>
                    <dd><span class="gr-chip {{ $goodsRequest->isForProject() ? 'config' : 'catalog' }}">{{ $goodsRequest->purpose_label }}</span></dd>
                    @if($goodsRequest->isForProject())
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
                    <dd>{{ $project?->company?->account_name ?? $quotation?->to_name ?? '—' }}</dd>
                    <dt>Opportunity</dt>
                    <dd>{{ $goodsRequest->opportunity()?->opportunity_name ?? '—' }}</dd>
                    <dt>Quotation</dt>
                    <dd>
                        @if($quotation)
                            <a href="{{ route('quotation.show', $quotation->id) }}">{{ $quotation->quotation_number ?? '#'.$quotation->id }}</a>
                        @else
                            <span class="gr-chip warn">Project tanpa quotation</span>
                        @endif
                    </dd>
                    @else
                    <dt>Keterangan</dt>
                    <dd>Kebutuhan stok / internal. Barang yang dibeli masuk gudang stok sebagai stok bebas.</dd>
                    @endif
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

@php
    $fulfillment = $showPoColumn ? $goodsRequest->fulfillmentStatus() : null;
    $hasRemaining = $showPoColumn && $items->contains(fn ($item) => $item->remainingQty() > 0);
    $unmappedCount = $items->whereNull('master_product_id')->count();
@endphp
<div class="card-custom fade-in mb-4">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Daftar Item <span class="gr-chip ms-1">{{ $items->count() }} item</span>
            @if($fulfillment)
                <span class="gr-chip ms-1 {{ $fulfillment === 'done' ? 'catalog' : ($fulfillment === 'partial' ? 'warn' : 'manual') }}">Pemenuhan: {{ \App\Models\GoodsRequest::FULFILLMENT_LABELS[$fulfillment] }}</span>
            @endif
        </span>
        <div class="d-flex gap-2">
            @if($hasRemaining && $canCloseRemaining)
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="grOpenClose(null, 'semua sisa kebutuhan')"><i class="fa fa-ban me-1"></i> Tutup Semua Sisa</button>
            @endif
            @if($canModify)
                <a href="{{ route('goods-request.edit', $goodsRequest->id) }}" class="btn btn-soft btn-sm"><i class="fa fa-pen me-1"></i> Ubah Item</a>
            @endif
        </div>
    </div>
    @if($unmappedCount)
        <div class="gr-banner danger mx-3 mt-3 mb-0">
            <i class="fa fa-triangle-exclamation mt-1"></i>
            <div>
                <div class="title">{{ $unmappedCount }} item belum terhubung master produk</div>
                <div>Item tanpa master produk tidak bisa dipenuhi dari stok, di-PO, atau diterima ke gudang. Klik <strong>Petakan</strong> pada item terkait — sistem menyarankan produk berdasarkan part number.</div>
            </div>
        </div>
    @endif
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom mb-0 gr-items">
                <thead>
                    <tr>
                        <th style="width:40px" class="text-center">#</th>
                        <th style="width:20%">Produk</th>
                        <th>Nama Barang / Deskripsi</th>
                        <th class="text-center" style="width:80px">Diminta</th>
                        <th style="width:80px">Mata Uang</th>
                        @if($showPoColumn)<th style="width:250px">Pemenuhan</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $i => $item)
                        @php
                            $source = $sourceOf($item);
                            $stockQty = $showPoColumn ? $item->stockQty() : 0;
                            $poLines = $showPoColumn ? $item->purchaseOrderItems->filter(fn ($l) => $l->committedQty() > 0) : collect();
                            $remainingQty = $showPoColumn ? $item->remainingQty() : 0;
                            $suggestion = $productSuggestions[$item->id] ?? null;
                        @endphp
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td style="font-size:13px">
                                <div class="fw-semibold">{{ $item->part_number ?: '—' }}</div>
                                @if($item->masterProduct)
                                    <div class="muted">{{ $item->masterProduct->name }}</div>
                                @else
                                    <span class="gr-chip warn mt-1"><i class="fa fa-triangle-exclamation"></i> Belum ada produk</span>
                                    <div class="mt-1">
                                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                            onclick="grOpenMap({{ $item->id }}, @js($item->part_number), @js($suggestion ? ['id' => $suggestion->id, 'text' => $suggestion->code.' — '.$suggestion->name] : null))">
                                            <i class="fa fa-link me-1"></i> Petakan
                                        </button>
                                    </div>
                                @endif
                            </td>
                            <td>
                                {{-- Deskripsi disimpan lewat Quotation::sanitizeDescription() --}}
                                <div class="gr-desc">{!! $item->description ?: '—' !!}</div>
                                <span class="gr-chip {{ $source }} mt-1">{{ $sourceLabels[$source] }}</span>
                            </td>
                            <td class="text-center fw-semibold">{{ $item->qty ?? '—' }} <div class="muted">{{ $item->unit }}</div></td>
                            <td><span class="gr-chip">{{ $item->currency ?: '—' }}</span></td>
                            @if($showPoColumn)
                            <td style="font-size:12.5px">
                                @if($stockQty > 0)
                                    <div><span class="gr-chip catalog">Dari stok {{ $stockQty }}</span></div>
                                @endif
                                @foreach($poLines as $line)
                                    <div class="mt-1">
                                        <a href="{{ route('purchase-order.show', $line->purchase_order_id) }}" class="fw-semibold">{{ $line->purchaseOrder?->po_number ?: 'PO #'.$line->purchase_order_id }}</a>
                                        · {{ $line->committedQty() }} {{ $item->unit }}
                                        <span class="muted">({{ $line->purchaseOrder?->status_label }})</span>
                                    </div>
                                @endforeach
                                @if($item->closed_qty > 0)
                                    <div class="mt-1"><span class="gr-chip manual" title="{{ $item->closed_reason }}">Ditutup {{ $item->closed_qty }}</span> <span class="muted">{{ $item->closed_reason }}</span></div>
                                @endif
                                @if($remainingQty > 0)
                                    <div class="mt-1 d-flex align-items-center gap-2">
                                        <span class="gr-chip warn">Sisa {{ $remainingQty }}</span>
                                        @if($canCloseRemaining)
                                            <a href="javascript:void(0)" class="muted" onclick="grOpenClose({{ $item->id }}, @js($item->part_number ?: 'item ini'))"><i class="fa fa-ban me-1"></i>Tutup sisa</a>
                                        @endif
                                    </div>
                                @elseif($stockQty <= 0 && $poLines->isEmpty() && $item->closed_qty <= 0)
                                    <span class="gr-chip warn">Belum diproses</span>
                                @else
                                    <div class="mt-1"><span class="gr-chip catalog"><i class="fa fa-check"></i> Terpenuhi</span></div>
                                @endif
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showPoColumn ? 6 : 5 }}" class="text-center py-4" style="color:var(--text-muted)">
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
                        <td colspan="{{ $showPoColumn ? 2 : 1 }}"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
        @if($showPoColumn)
            <div class="px-2 pt-2 muted">
                <i class="fa fa-circle-info me-1"></i>Pemenuhan adalah pilihan: ambil dari stok (menu Pemenuhan dari Stok) dan/atau beli lewat Purchase Order. Sisa yang tidak diperlukan lagi bisa ditutup.
            </div>
        @endif
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="grCloseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Tutup Sisa Kebutuhan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="muted mb-2">Sisa kebutuhan <strong id="gr-close-label"></strong> tidak bisa dipenuhi dari stok atau di-PO lagi.</p>
                <label class="form-label">Alasan <span class="text-danger">*</span></label>
                <textarea id="gr-close-reason" class="form-control" rows="2" maxlength="255" placeholder="Contoh: customer mengurangi qty"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="gr-btn-close-remaining"><i class="fa fa-ban me-1"></i> Tutup Sisa</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="grMapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Petakan ke Master Produk</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="muted mb-2">Part number: <strong id="gr-map-pn"></strong></p>
                <label class="form-label">Master Produk <span class="text-danger">*</span></label>
                <select id="gr-map-product" style="width:100%"></select>
                <div class="muted mt-1" id="gr-map-hint"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="gr-btn-map"><i class="fa fa-link me-1"></i> Petakan</button>
            </div>
        </div>
    </div>
</div>
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
const grCloseUrl = '{{ route("goods-request.close-remaining", $goodsRequest->id) }}';
const grMapUrl = '{{ route("goods-request.items.product", [$goodsRequest->id, "__ITEM__"]) }}';
const grSearchProductUrl = '{{ route("goods-request.search-products") }}';
let grCloseItemId = null;
let grMapItemId = null;
let grCloseModal = null;
let grMapModal = null;

function grOpenClose(itemId, label) {
    grCloseItemId = itemId;
    $('#gr-close-label').text(label);
    $('#gr-close-reason').val('');
    if (!grCloseModal) grCloseModal = new bootstrap.Modal(document.getElementById('grCloseModal'));
    grCloseModal.show();
}

$(document).on('click', '#gr-btn-close-remaining', function() {
    var reason = $.trim($('#gr-close-reason').val());
    if (!reason) { toastr.error('Alasan wajib diisi.'); return; }
    var $btn = $(this).prop('disabled', true);
    $.ajax({ url: grCloseUrl, method: 'POST', data: { _token: '{{ csrf_token() }}', _method: 'PATCH', item_id: grCloseItemId || '', reason: reason } })
        .done(function(res) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 600); })
        .fail(function(xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menutup sisa.'); })
        .always(function() { $btn.prop('disabled', false); });
});

// Petakan item lama ke master produk; saran dari part number = kode produk.
function grOpenMap(itemId, partNumber, suggestion) {
    grMapItemId = itemId;
    $('#gr-map-pn').text(partNumber || '—');
    if (!grMapModal) {
        grMapModal = new bootstrap.Modal(document.getElementById('grMapModal'));
        $('#gr-map-product').select2({
            theme: 'bootstrap-5', width: '100%', dropdownParent: $('#grMapModal'),
            placeholder: 'Cari kode / nama produk...', minimumInputLength: 0,
            ajax: {
                url: grSearchProductUrl, dataType: 'json', delay: 250,
                data: function(params) { return { q: params.term || '', per_page: 20 }; },
                processResults: function(res) {
                    return { results: (res.data || []).map(function(p) { return { id: p.id, text: (p.code ? p.code + ' — ' : '') + (p.name || '') }; }) };
                }
            }
        });
    }
    $('#gr-map-product').empty();
    if (suggestion) {
        $('#gr-map-product').append(new Option(suggestion.text, suggestion.id, true, true)).trigger('change');
        $('#gr-map-hint').html('<i class="fa fa-wand-magic-sparkles me-1"></i>Disarankan: part number cocok dengan kode produk. Periksa lalu klik Petakan.');
    } else {
        $('#gr-map-hint').text('Tidak ada produk dengan kode yang sama. Cari produk secara manual.');
    }
    grMapModal.show();
}

$(document).on('click', '#gr-btn-map', function() {
    var productId = $('#gr-map-product').val();
    if (!productId) { toastr.error('Pilih master produk.'); return; }
    var $btn = $(this).prop('disabled', true);
    $.ajax({ url: grMapUrl.replace('__ITEM__', grMapItemId), method: 'POST', data: { _token: '{{ csrf_token() }}', _method: 'PATCH', master_product_id: productId } })
        .done(function(res) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 600); })
        .fail(function(xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memetakan produk.'); })
        .always(function() { $btn.prop('disabled', false); });
});

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
