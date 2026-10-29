@extends('layouts.app')

@section('title', 'Detail Invoice')
@section('page-title', 'Detail Invoice')

@section('styles')
<style>
    .inv-label { font-size: 12px; color: var(--text-muted); margin-bottom: 2px; }
    .inv-value { font-size: 14px; font-weight: 600; color: var(--text-primary); }
    .inv-muted { font-size: 12px; color: var(--text-muted); }

    .inv-steps { display: flex; align-items: center; gap: 0; flex-wrap: wrap; }
    .inv-step { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-muted); }
    .inv-step .dot {
        width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid var(--card-border); font-size: 11px; font-weight: 700; background: var(--card);
    }
    .inv-step.done .dot { background: var(--accent); border-color: var(--accent); color: #fff; }
    .inv-step.current .dot { border-color: var(--accent); color: var(--accent); }
    .inv-step.current, .inv-step.done { color: var(--text-primary); font-weight: 600; }
    .inv-step.failed .dot { background: var(--danger); border-color: var(--danger); color: #fff; }
    .inv-step.failed { color: var(--danger); font-weight: 600; }
    .inv-step-line { flex: 1; min-width: 24px; height: 2px; background: var(--card-border); margin: 0 10px; }
    .inv-step-line.done { background: var(--accent); }
    .inv-next { font-size: 13px; color: var(--text-muted); margin-top: 10px; }
    .inv-next i { color: var(--accent); }

    .inv-hero { display: flex; gap: 24px; flex-wrap: wrap; align-items: stretch; }
    .inv-hero-total {
        flex: 1 1 280px; padding: 16px 18px; border-radius: var(--radius-sm);
        background: var(--accent-soft);
    }
    .inv-hero-total .amount { font-size: 28px; font-weight: 800; color: var(--accent); line-height: 1.2; font-variant-numeric: tabular-nums; }
    .inv-hero-total .formula { font-size: 12.5px; color: var(--text-muted); margin-top: 4px; }
    .inv-hero-facts { flex: 2 1 380px; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px 18px; align-content: center; }

    .inv-pill { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; margin-top: 3px; }
    .inv-pill.ok { background: var(--accent-soft); color: var(--accent); }
    .inv-pill.warn { background: #fef3c7; color: #92400e; }
    .inv-pill.danger { background: var(--danger-soft); color: #7f1d1d; }

    .inv-chip { display: inline-block; font-size: 12px; padding: 2px 8px; border: 1px solid var(--card-border); border-radius: 6px; margin: 2px 4px 2px 0; }

    .inv-dl { display: grid; grid-template-columns: 140px 1fr; gap: 8px 12px; font-size: 13.5px; }
    .inv-dl dt { color: var(--text-muted); font-weight: 400; }
    .inv-dl dd { margin: 0; font-weight: 600; color: var(--text-primary); }

    .inv-items tfoot td { border: 0; padding-top: 6px; padding-bottom: 6px; }
    .inv-items tfoot tr.first td { border-top: 2px solid var(--card-border); padding-top: 12px; }
    .inv-items tfoot tr.grand td { font-size: 15px; font-weight: 800; color: var(--accent); }
</style>
@endsection

@section('content')
@php
    $project = $invoice->project;
    $company = $project?->company;
    $approval = $invoice->approval_status;
    $isRejected = $approval === \App\Models\Invoice::APPROVAL_REJECTED;
    $ppnAmount = $invoice->total_payment - $invoice->amount_payment;
    $hasDiscount = (float) $invoice->discount_amount > 0;
    $discountLabel = 'Diskon'.($invoice->discount_percent ? ' '.\App\Models\Invoice::formatNumber($invoice->discount_percent).'%' : '');
    $termLabel = ($invoice->noted ?: 'Pembayaran').' ('.\App\Models\Invoice::formatNumber($invoice->percent_payment).'%)';
    $doNumbers = collect(preg_split('/[\r\n,;]+/', (string) $invoice->do_number))->map(fn ($v) => trim($v))->filter();

    // Langkah approval: 1 Draft, 2 Menunggu Approval, 3 Approved.
    $step = match ($approval) {
        \App\Models\Invoice::APPROVAL_WAITING_APPROVAL, \App\Models\Invoice::APPROVAL_REJECTED => 2,
        \App\Models\Invoice::APPROVAL_APPROVED => 3,
        default => 1,
    };

    $nextHint = match ($approval) {
        \App\Models\Invoice::APPROVAL_DRAFT => 'Invoice masih draft. Periksa data, lalu klik Submit Approval agar bisa disetujui.',
        \App\Models\Invoice::APPROVAL_WAITING_APPROVAL => 'Menunggu persetujuan. Invoice tidak bisa diubah selama proses approval.',
        \App\Models\Invoice::APPROVAL_REJECTED => 'Invoice ditolak. Perbaiki sesuai alasan penolakan, lalu submit ulang.',
        \App\Models\Invoice::APPROVAL_APPROVED => 'Invoice sudah disetujui. PDF siap dikirim ke customer. Catat pembayaran setelah dana diterima.',
        default => null,
    };

    $isPaid = $invoice->status === \App\Models\Invoice::STATUS_PAID;
    $isCancelled = $invoice->status === \App\Models\Invoice::STATUS_CANCELLED;
    if ($isPaid) {
        $nextHint = 'Invoice sudah lunas.';
    } elseif ($isCancelled) {
        $nextHint = 'Invoice ini sudah dibatalkan dan tidak berlaku lagi.';
    }

    // Sisa hari menuju jatuh tempo (hanya relevan bila belum dibayar).
    $dueDays = $invoice->due_date && $invoice->status === \App\Models\Invoice::STATUS_UNPAID
        ? (int) now()->startOfDay()->diffInDays($invoice->due_date->copy()->startOfDay(), false)
        : null;
@endphp

<div class="page-header">
    <div>
        <h1 class="page-header-title">Invoice {{ $invoice->invoice_number }}</h1>
        <p class="page-header-sub">
            {{ $company?->account_name ?: '—' }} &middot; {{ $project?->project_name ?: '—' }}
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('invoice.pdf', $invoice->id) }}" target="_blank" class="btn btn-soft btn-sm">
            <i class="fa fa-file-pdf me-1"></i> Lihat PDF
        </a>
        <a href="{{ route('invoice.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

{{-- Progres approval + aksi --}}
<div class="card-custom fade-in mb-3">
    <div class="card-body-custom">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="inv-steps flex-grow-1">
                <div class="inv-step {{ $step > 1 ? 'done' : 'current' }}">
                    <span class="dot">@if($step > 1)<i class="fa fa-check"></i>@else 1 @endif</span> Draft
                </div>
                <div class="inv-step-line {{ $step > 1 ? 'done' : '' }}"></div>
                <div class="inv-step {{ $isRejected ? 'failed' : ($step > 2 ? 'done' : ($step === 2 ? 'current' : '')) }}">
                    <span class="dot">
                        @if($isRejected)<i class="fa fa-xmark"></i>@elseif($step > 2)<i class="fa fa-check"></i>@else 2 @endif
                    </span>
                    {{ $isRejected ? 'Ditolak' : 'Menunggu Approval' }}
                </div>
                <div class="inv-step-line {{ $step > 2 ? 'done' : '' }}"></div>
                <div class="inv-step {{ $step === 3 ? 'done' : '' }}">
                    <span class="dot">@if($step === 3)<i class="fa fa-check"></i>@else 3 @endif</span> Approved
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @if(($canUpdate ?? false) && $invoice->canBeModified())
                    <a href="{{ route('invoice.edit', $invoice->id) }}" class="btn btn-secondary btn-sm">
                        <i class="fa fa-pen me-1"></i> Edit
                    </a>
                @endif
                @if(($canDelete ?? false) && $invoice->canBeModified())
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="invDelete()">
                        <i class="fa fa-trash me-1"></i> Hapus
                    </button>
                @endif
                @if(($canCreate ?? false) && in_array($approval, [\App\Models\Invoice::APPROVAL_DRAFT, \App\Models\Invoice::APPROVAL_REJECTED], true))
                    <button type="button" class="btn btn-primary btn-sm" onclick="invSubmit()">
                        <i class="fa fa-paper-plane me-1"></i> {{ $isRejected ? 'Submit Ulang' : 'Submit Approval' }}
                    </button>
                @endif
                @if(($canApprove ?? false) && $approval === \App\Models\Invoice::APPROVAL_WAITING_APPROVAL)
                    <button type="button" class="btn btn-success btn-sm" onclick="invApprove()">
                        <i class="fa fa-check me-1"></i> Approve
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="invOpenReject()">
                        <i class="fa fa-xmark me-1"></i> Reject
                    </button>
                @endif
                @if(($canCreate ?? false) && $invoice->canBeCancelled())
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="invOpenModal('invCancelModal')">
                        <i class="fa fa-ban me-1"></i> Batalkan Invoice
                    </button>
                @endif
                @if(($canApprove ?? false) && $invoice->canRecordPayment())
                    <button type="button" class="btn btn-success btn-sm" onclick="invOpenModal('invPayModal')">
                        <i class="fa fa-money-bill-wave me-1"></i> Catat Pembayaran
                    </button>
                @endif
            </div>
        </div>

        @if($nextHint)
            <div class="inv-next"><i class="fa-solid fa-circle-info me-1"></i> {{ $nextHint }}</div>
        @endif

        @if($isPaid)
            <div class="mt-3 p-3" style="border:1px solid #bbf7d0;background:#f0fdf4;border-radius:var(--radius-sm);">
                <div style="font-weight:600;font-size:13px;color:#166534">
                    <i class="fa fa-circle-check me-1"></i> Lunas pada {{ $invoice->date_payment?->format('d M Y') ?? '—' }}
                    &middot; Ref {{ $invoice->reference_number ?: '—' }}
                    @if($invoice->payer)
                        <span style="font-weight:400">— dicatat oleh {{ $invoice->payer->display_name }}</span>
                    @endif
                </div>
            </div>
        @endif

        @if($isCancelled)
            <div class="mt-3 p-3" style="border:1px solid #fecaca;background:#fef2f2;border-radius:var(--radius-sm);">
                <div style="font-weight:600;font-size:13px;color:#7f1d1d">
                    <i class="fa fa-ban me-1"></i> Alasan Pembatalan
                    @if($invoice->canceller)
                        <span style="font-weight:400">— {{ $invoice->canceller->display_name }}, {{ $invoice->cancelled_at?->format('d/m/Y H:i') }}</span>
                    @endif
                </div>
                <div style="font-size:13px;color:#7f1d1d;margin-top:4px">{{ $invoice->cancel_reason ?: '—' }}</div>
            </div>
        @endif

        @if($isRejected && $invoice->approval_note)
            <div class="mt-3 p-3" style="border:1px solid #fecaca;background:#fef2f2;border-radius:var(--radius-sm);">
                <div style="font-weight:600;font-size:13px;color:#7f1d1d">
                    <i class="fa fa-circle-exclamation me-1"></i> Alasan Penolakan
                    @if($invoice->finalChecker)
                        <span style="font-weight:400">— {{ $invoice->finalChecker->display_name }}, {{ $invoice->rejected_at?->format('d/m/Y H:i') }}</span>
                    @endif
                </div>
                <div style="font-size:13px;color:#7f1d1d;margin-top:4px">{{ $invoice->approval_note }}</div>
            </div>
        @endif
    </div>
</div>

{{-- Ringkasan tagihan --}}
<div class="card-custom fade-in mb-3">
    <div class="card-body-custom">
        <div class="inv-hero">
            <div class="inv-hero-total">
                <div class="inv-label">Total Tagihan</div>
                <div class="amount">{{ \App\Models\Invoice::formatMoney($invoice->total_payment) }}</div>
                <div class="formula">
                    {{ $termLabel }} dari {{ \App\Models\Invoice::formatMoney($invoice->net_subtotal) }}
                    @if ($hasDiscount)<span class="inv-muted">(setelah {{ strtolower($discountLabel) }})</span>@endif
                    + PPN {{ \App\Models\Invoice::formatNumber($invoice->ppn) }}%
                </div>
            </div>
            <div class="inv-hero-facts">
                <div>
                    <div class="inv-label">Status Bayar</div>
                    <div>{!! $invoice->statusBadgeHtml() !!}</div>
                    @if($invoice->date_payment)
                        <div class="inv-muted mt-1">Dibayar {{ $invoice->date_payment->format('d M Y') }}</div>
                    @endif
                </div>
                <div>
                    <div class="inv-label">Tanggal Invoice</div>
                    <div class="inv-value">{{ $invoice->invoice_date?->format('d M Y') ?? '—' }}</div>
                </div>
                <div>
                    <div class="inv-label">Jatuh Tempo</div>
                    <div class="inv-value">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</div>
                    @if($dueDays !== null)
                        @if($dueDays < 0)
                            <span class="inv-pill danger">Lewat {{ abs($dueDays) }} hari</span>
                        @elseif($dueDays === 0)
                            <span class="inv-pill warn">Jatuh tempo hari ini</span>
                        @elseif($dueDays <= 7)
                            <span class="inv-pill warn">{{ $dueDays }} hari lagi</span>
                        @else
                            <span class="inv-pill ok">{{ $dueDays }} hari lagi</span>
                        @endif
                    @endif
                </div>
                <div>
                    <div class="inv-label">Terms</div>
                    <div class="inv-value">{{ $invoice->terms ? $invoice->terms.(is_numeric($invoice->terms) ? ' hari' : '') : '—' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-building me-2" style="color:var(--accent)"></i>Customer &amp; Pengiriman</span>
            </div>
            <div class="card-body-custom">
                <dl class="inv-dl mb-0">
                    <dt>Customer</dt>
                    <dd>{{ $company?->account_name ?: '—' }}</dd>
                    <dt>Project</dt>
                    <dd>
                        @if($project)
                            <a href="{{ route('project.show', $project->id) }}" class="text-decoration-none">{{ $project->project_number }}</a>
                            <div class="inv-muted" style="font-weight:400">{{ $project->project_name }}</div>
                        @else
                            —
                        @endif
                    </dd>
                    <dt>No. PO Customer</dt>
                    <dd>{{ $project?->po_number_company ?: '—' }}</dd>
                    <dt>Ship To</dt>
                    <dd style="white-space:pre-line">{{ $invoice->ship_to ?: '—' }}</dd>
                    <dt>Tanggal Kirim</dt>
                    <dd>{{ $invoice->date_shipped?->format('d M Y') ?? '—' }}</dd>
                    <dt>Dikirim Melalui</dt>
                    <dd>{{ $invoice->shipping_method ?: '—' }}</dd>
                    <dt>No. DO</dt>
                    <dd>
                        @forelse($doNumbers as $do)
                            <span class="inv-chip">{{ $do }}</span>
                        @empty
                            —
                        @endforelse
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Pembayaran &amp; Persetujuan</span>
            </div>
            <div class="card-body-custom">
                <dl class="inv-dl mb-0">
                    <dt>Tanggal Bayar</dt>
                    <dd>{{ $invoice->date_payment?->format('d M Y') ?? '—' }}</dd>
                    <dt>Reference No.</dt>
                    <dd>{{ $invoice->reference_number ?: '—' }}</dd>
                    @if($invoice->payer)
                        <dt>Dicatat Oleh</dt>
                        <dd>{{ $invoice->payer->display_name }}</dd>
                    @endif
                    <dt>Approval</dt>
                    <dd>{!! $invoice->approvalBadgeHtml() !!}</dd>
                    @if($invoice->final_checked_by && ! $isRejected)
                        <dt>Disetujui Oleh</dt>
                        <dd>
                            {{ $invoice->finalChecker?->display_name ?? '—' }}
                            <div class="inv-muted" style="font-weight:400">{{ $invoice->approved_at?->format('d/m/Y H:i') }}</div>
                        </dd>
                    @endif
                    <dt>Penanda Tangan</dt>
                    <dd>
                        {{ $invoice->signer_name ?: \App\Models\Invoice::DEFAULT_SIGNER_NAME }}
                        <div class="inv-muted" style="font-weight:400">{{ $invoice->signer_position ?: \App\Models\Invoice::DEFAULT_SIGNER_POSITION }}</div>
                    </dd>
                    <dt>Dibuat Oleh</dt>
                    <dd>
                        {{ $invoice->creator?->display_name ?? '—' }}
                        <div class="inv-muted" style="font-weight:400">{{ $invoice->created_at?->format('d/m/Y H:i') }}</div>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in mb-4">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Item Invoice <span class="inv-muted">({{ $invoice->items->count() }} item)</span></span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0 inv-items">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Deskripsi</th>
                        <th class="text-center" style="width:12%">Qty</th>
                        <th class="text-end" style="width:18%">Harga Satuan</th>
                        <th class="text-end" style="width:18%">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoice->items as $i => $item)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>
                            @if($item->description)
                                <div style="font-size:13px;line-height:1.45">{!! \App\Models\Quotation::renderDescription($item->description) !!}</div>
                            @else
                                <div style="font-weight:600">{{ $item->product_name }}</div>
                            @endif
                        </td>
                        <td class="text-center">{{ trim(\App\Models\Invoice::formatNumber($item->qty).' '.$item->unit) }}</td>
                        <td class="text-end">{{ \App\Models\Invoice::formatMoney($item->unit_price) }}</td>
                        <td class="text-end fw-semibold">{{ \App\Models\Invoice::formatMoney($item->total_price) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center" style="color:var(--text-muted)">Belum ada item.</td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="first">
                        <td colspan="4" class="text-end inv-muted" style="font-size:13.5px">Sub Total</td>
                        <td class="text-end fw-semibold">{{ \App\Models\Invoice::formatMoney($invoice->subtotal) }}</td>
                    </tr>
                    @if ($hasDiscount)
                    <tr>
                        <td colspan="4" class="text-end inv-muted" style="font-size:13.5px">{{ $discountLabel }}</td>
                        <td class="text-end fw-semibold text-danger">- {{ \App\Models\Invoice::formatMoney($invoice->discount_amount) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end inv-muted" style="font-size:13.5px">Nilai Sebelum PPN</td>
                        <td class="text-end fw-semibold">{{ \App\Models\Invoice::formatMoney($invoice->net_subtotal) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="4" class="text-end inv-muted" style="font-size:13.5px">{{ $termLabel }}</td>
                        <td class="text-end fw-semibold">{{ \App\Models\Invoice::formatMoney($invoice->amount_payment) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end inv-muted" style="font-size:13.5px">PPN {{ \App\Models\Invoice::formatNumber($invoice->ppn) }}%</td>
                        <td class="text-end fw-semibold">{{ \App\Models\Invoice::formatMoney($ppnAmount) }}</td>
                    </tr>
                    <tr class="grand">
                        <td colspan="4" class="text-end">Total Payment</td>
                        <td class="text-end">{{ \App\Models\Invoice::formatMoney($invoice->total_payment) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="invRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Tolak Invoice</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea id="inv-reject-note" class="form-control" rows="3" placeholder="Wajib diisi alasan penolakan"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="inv-btn-reject">
                    <i class="fa fa-xmark me-1"></i> Tolak
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="invPayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa fa-money-bill-wave me-1"></i> Catat Pembayaran</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="p-2 mb-3" style="background:var(--accent-soft);border-radius:var(--radius-sm);font-size:13px">
                    Total tagihan: <strong>{{ \App\Models\Invoice::formatMoney($invoice->total_payment) }}</strong>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tanggal Bayar <span class="text-danger">*</span></label>
                    <input type="date" id="inv-pay-date" class="form-control" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
                </div>
                <div class="mb-1">
                    <label class="form-label">Reference No <span class="text-danger">*</span></label>
                    <input type="text" id="inv-pay-reference" class="form-control" maxlength="100" placeholder="No. bukti transfer / referensi bank">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success btn-sm" id="inv-btn-pay">
                    <i class="fa fa-check me-1"></i> Simpan Pembayaran
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="invCancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa fa-ban me-1"></i> Batalkan Invoice</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="p-2 mb-3" style="background:#fef2f2;color:#7f1d1d;border-radius:var(--radius-sm);font-size:13px">
                    Invoice yang dibatalkan tidak bisa diaktifkan kembali.
                </div>
                <label class="form-label">Alasan Pembatalan <span class="text-danger">*</span></label>
                <textarea id="inv-cancel-reason" class="form-control" rows="3" maxlength="1000" placeholder="Wajib diisi alasan pembatalan"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Kembali</button>
                <button type="button" class="btn btn-danger btn-sm" id="inv-btn-cancel">
                    <i class="fa fa-ban me-1"></i> Batalkan Invoice
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let invRejectModalInstance = null;
const invSubmitUrl = '{{ route("invoice.submit", $invoice->id) }}';
const invApproveUrl = '{{ route("invoice.approve", $invoice->id) }}';
const invRejectUrl = '{{ route("invoice.reject", $invoice->id) }}';
const invPayUrl = '{{ route("invoice.pay", $invoice->id) }}';
const invCancelUrl = '{{ route("invoice.cancel", $invoice->id) }}';

function invOpenModal(id) {
    bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show();
}

function invPostAction(url, data, $btn, fallback) {
    $btn.prop('disabled', true);
    $.post(url, $.extend({ _token: '{{ csrf_token() }}' }, data))
        .done(function(res) {
            toastr.success(res.message);
            setTimeout(function() { window.location.reload(); }, 800);
        }).fail(function(xhr) {
            $btn.prop('disabled', false);
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : fallback);
        });
}

$(document).on('click', '#inv-btn-pay', function() {
    var date = $('#inv-pay-date').val();
    var reference = $.trim($('#inv-pay-reference').val());
    if (!date) { toastr.error('Tanggal bayar wajib diisi.'); $('#inv-pay-date').focus(); return; }
    if (!reference) { toastr.error('Reference No wajib diisi.'); $('#inv-pay-reference').focus(); return; }

    invPostAction(invPayUrl, { date_payment: date, reference_number: reference }, $(this), 'Gagal mencatat pembayaran.');
});

$(document).on('click', '#inv-btn-cancel', function() {
    var reason = $.trim($('#inv-cancel-reason').val());
    if (!reason) { toastr.error('Alasan pembatalan wajib diisi.'); $('#inv-cancel-reason').focus(); return; }

    invPostAction(invCancelUrl, { cancel_reason: reason }, $(this), 'Gagal membatalkan invoice.');
});

function invSubmit() {
    Swal.fire({
        title: 'Submit Approval?',
        text: 'Invoice akan dikirim untuk approval.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Submit',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        $.post(invSubmitUrl, { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Invoice dikirim untuk approval.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal submit.');
            });
    });
}

function invApprove() {
    Swal.fire({
        title: 'Approve Invoice?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        $.post(invApproveUrl, { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Invoice disetujui.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal approve.');
            });
    });
}

function invOpenReject() {
    $('#inv-reject-note').val('');
    if (!invRejectModalInstance) {
        invRejectModalInstance = new bootstrap.Modal(document.getElementById('invRejectModal'));
    }
    invRejectModalInstance.show();
}

$(document).on('click', '#inv-btn-reject', function() {
    var note = $('#inv-reject-note').val().trim();
    if (!note) {
        toastr.error('Alasan penolakan wajib diisi.');
        return;
    }

    $.post(invRejectUrl, { _token: '{{ csrf_token() }}', approval_note: note })
        .done(function(res) {
            toastr.success(res.message || 'Invoice ditolak.');
            invRejectModalInstance.hide();
            setTimeout(function() { window.location.reload(); }, 800);
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menolak invoice.');
        });
});

function invDelete() {
    Swal.fire({
        title: 'Hapus Invoice?',
        text: 'Data invoice dan item akan dihapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: '{{ route("invoice.destroy", $invoice->id) }}',
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Invoice dihapus.');
            window.location.href = '{{ route("invoice.index") }}';
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus invoice.');
        });
    });
}
</script>
@endsection
