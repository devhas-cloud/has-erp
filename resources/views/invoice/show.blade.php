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
    .inv-term-box { padding: 12px 14px; border-radius: var(--radius-sm); background: var(--accent-soft); height: 100%; }
    .inv-doc-item {
        display: flex; align-items: center; gap: 10px; padding: 8px 10px;
        border: 1px solid var(--card-border); border-radius: var(--radius-sm); font-size: 13px;
    }
    .inv-doc-item + .inv-doc-item { margin-top: 6px; }
    .inv-doc-item > i { color: var(--danger); font-size: 17px; }
    .inv-doc-item .name { font-weight: 600; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
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
        \App\Models\Invoice::APPROVAL_APPROVED => 'Invoice sudah disetujui. PDF siap dikirim ke customer. Catat penerimaan di menu Penerimaan Customer setelah dana masuk.',
        default => null,
    };

    $isPaid = $invoice->status === \App\Models\Invoice::STATUS_PAID;
    $isCancelled = $invoice->status === \App\Models\Invoice::STATUS_CANCELLED;
    if ($isPaid) {
        $nextHint = 'Invoice sudah lunas.';
    } elseif ($isCancelled) {
        $nextHint = 'Invoice ini sudah dibatalkan dan tidak berlaku lagi.';
    }

    $canEditTaxInvoice = ($canUpdate ?? false) && ! $isCancelled;
    $activeReceipt = $invoice->activeReceipt();

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
                @if($activeReceipt && $activeReceipt->isDraft())
                    <a href="{{ route('customer-receipt.show', $activeReceipt->id) }}" class="btn btn-soft btn-sm">
                        <i class="fa fa-hand-holding-dollar me-1"></i> Penerimaan {{ $activeReceipt->receipt_number }} (Draft)
                    </a>
                @elseif(($canCreateReceipt ?? false) && $invoice->awaitsReceipt())
                    <a href="{{ route('customer-receipt.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-success btn-sm">
                        <i class="fa fa-hand-holding-dollar me-1"></i> Catat Penerimaan
                    </a>
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
                    @if($activeReceipt)
                        &middot; Penerimaan Customer <a href="{{ route('customer-receipt.show', $activeReceipt->id) }}" style="color:inherit">{{ $activeReceipt->receipt_number }}</a>
                    @endif
                    @if($invoice->payer)
                        <span style="font-weight:400">— diposting oleh {{ $invoice->payer->display_name }}</span>
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

        @if($invoice->isOutsideTimeline())
            <div class="mt-3 p-3" style="border:1px solid #fde68a;background:#fffbeb;border-radius:var(--radius-sm);">
                <div style="font-weight:600;font-size:13px;color:#92400e">
                    <i class="fa fa-triangle-exclamation me-1"></i> Tagihan di luar timeline project
                </div>
                <div style="font-size:13px;color:#92400e;margin-top:4px">{{ $invoice->non_term_reason ?: 'Invoice lama tanpa termin — alasan belum dicatat.' }}</div>
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
                <span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Pembayaran, Pajak &amp; Persetujuan</span>
            </div>
            <div class="card-body-custom">
                <dl class="inv-dl mb-0">
                    <dt>Penerimaan Customer</dt>
                    <dd>
                        @if($activeReceipt)
                            <a href="{{ route('customer-receipt.show', $activeReceipt->id) }}" class="text-decoration-none">{{ $activeReceipt->receipt_number }}</a> {!! $activeReceipt->statusBadgeHtml() !!}
                            <div class="inv-muted" style="font-weight:400">{{ $activeReceipt->receipt_date?->format('d M Y') }}{{ $activeReceipt->reference_number ? ' · Ref '.$activeReceipt->reference_number : '' }}</div>
                        @else
                            Belum ada
                        @endif
                    </dd>
                    <dt>No. Faktur Pajak</dt>
                    <dd>
                        <span id="inv-tax-number">{{ $invoice->tax_invoice_number ?: '—' }}</span>
                        @if($canEditTaxInvoice)
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1" onclick="invEditTaxInvoice()" title="Isi / ubah no. faktur pajak"><i class="fa fa-pen"></i></button>
                        @endif
                    </dd>
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

@if($invoice->billingTerm)
@php $term = $invoice->billingTerm; @endphp
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="fa-solid fa-timeline me-2" style="color:var(--accent)"></i>Termin Penagihan &amp; Dokumen Pendukung
        </span>
        @if($invoice->project)
            <a href="{{ route('project.show', $invoice->project_id) }}#timeline-penagihan" class="btn btn-secondary btn-sm"><i class="fa fa-timeline me-1"></i> Lihat Timeline Project</a>
        @endif
    </div>
    <div class="card-body-custom">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="inv-term-box">
                    <div class="inv-muted">Termin</div>
                    <div style="font-size:16px;font-weight:700;color:var(--text-primary)">{{ $term->name }}</div>
                    <div style="font-weight:600;color:var(--accent)">{{ \App\Models\Invoice::formatNumber($term->percent) }}% dari nilai kontrak sebelum PPN</div>
                    @if($term->description)<div class="inv-muted mt-1">{{ $term->description }}</div>@endif
                    @if($term->target_date)<div class="inv-muted mt-1"><i class="fa fa-calendar me-1"></i>Target tagih {{ $term->target_date->format('d M Y') }}</div>@endif
                </div>
            </div>
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="inv-muted">Dokumen pendukung (<span id="inv-doc-count">{{ count($termDocuments) }}</span>)</span>
                </div>
                <div id="inv-doc-list"></div>
                @if($canCreate ?? false)
                <div class="mt-2 p-2" style="background:var(--accent-subtle);border-radius:var(--radius-sm)">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <input type="file" id="inv-doc-files" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" multiple>
                        </div>
                        <div class="col-md-4">
                            <input type="text" id="inv-doc-notes" class="form-control form-control-sm" maxlength="255" placeholder="Keterangan (opsional)">
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="button" class="btn btn-primary btn-sm" id="inv-doc-upload-btn" onclick="invUploadDocs()"><i class="fa fa-upload me-1"></i> Upload</button>
                        </div>
                    </div>
                    <div class="inv-muted mt-1">PDF, JPG, atau PNG, maksimal 10MB per file. Dokumen melekat pada termin dan ikut tampil di timeline project.</div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

@if($invoice->receipts->isNotEmpty())
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-hand-holding-dollar me-2" style="color:var(--accent)"></i>Riwayat Penerimaan Customer <span class="inv-muted">({{ $invoice->receipts->count() }})</span></span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead><tr><th>No. Penerimaan</th><th>Tanggal Terima</th><th>Metode / Referensi</th><th class="text-end">Nominal</th><th class="text-center">Status</th></tr></thead>
                <tbody>
                    @foreach($invoice->receipts as $receipt)
                    <tr @if($receipt->status === \App\Models\CustomerReceipt::STATUS_VOID) style="opacity:.6" @endif>
                        <td><a href="{{ route('customer-receipt.show', $receipt->id) }}" class="fw-semibold text-decoration-none">{{ $receipt->receipt_number }}</a></td>
                        <td>{{ $receipt->receipt_date?->format('d/m/Y') }}</td>
                        <td>{{ $receipt->method_label }}<div class="inv-muted">{{ $receipt->reference_number ?: '—' }}</div></td>
                        <td class="text-end">{{ \App\Models\Invoice::formatMoney($receipt->amount) }}</td>
                        <td class="text-center">{!! $receipt->statusBadgeHtml() !!}@if($receipt->void_reason)<div class="inv-muted">{{ $receipt->void_reason }}</div>@endif</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

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
const invCancelUrl = '{{ route("invoice.cancel", $invoice->id) }}';
const invTaxInvoiceUrl = '{{ route("invoice.tax-invoice", $invoice->id) }}';
let invTaxInvoiceNumber = @json($invoice->tax_invoice_number);
const invDocUploadUrl = '{{ route("invoice.documents.store", $invoice->id) }}';
const invCanDeleteDocs = {{ ($canDelete ?? false) ? 'true' : 'false' }};
let invDocs = @json($termDocuments);

function invEscHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function invRenderDocs() {
    $('#inv-doc-count').text(invDocs.length);
    if (!invDocs.length) {
        $('#inv-doc-list').html('<div class="inv-doc-item inv-muted"><i class="fa fa-paperclip" style="color:var(--text-muted)"></i> Belum ada dokumen pendukung untuk termin ini.</div>');
        return;
    }
    $('#inv-doc-list').html(invDocs.map(function(doc) {
        var html = '<div class="inv-doc-item"><i class="fa ' + invEscHtml(doc.icon) + '"></i>';
        html += '<div style="min-width:0;flex:1"><div class="name">' + invEscHtml(doc.file_name) + '</div>';
        html += '<div class="inv-muted">' + (doc.notes ? invEscHtml(doc.notes) + ' · ' : '') + invEscHtml(doc.size) + ' · ' + invEscHtml(doc.uploaded_by || '—') + ', ' + invEscHtml(doc.uploaded_at || '') + '</div></div>';
        html += '<a href="' + doc.view_url + '" target="_blank" class="btn-icon" title="Lihat"><i class="fa fa-eye"></i></a>';
        if (invCanDeleteDocs && doc.delete_url) {
            html += '<button type="button" class="btn-icon text-danger" title="Hapus" onclick="invDeleteDoc(\'' + doc.delete_url + '\')"><i class="fa fa-trash"></i></button>';
        } else if (!doc.delete_url) {
            html += '<span class="btn-icon" style="opacity:.4" title="Terkunci: invoice sudah Approved"><i class="fa fa-lock"></i></span>';
        }
        return html + '</div>';
    }).join(''));
}

function invUploadDocs() {
    var files = document.getElementById('inv-doc-files').files;
    if (!files.length) { toastr.error('Pilih minimal satu file.'); return; }
    for (var i = 0; i < files.length; i++) {
        if (files[i].size > 10 * 1024 * 1024) { toastr.error('File "' + files[i].name + '" lebih dari 10MB.'); return; }
    }
    var fd = new FormData();
    fd.append('_token', '{{ csrf_token() }}');
    fd.append('notes', $('#inv-doc-notes').val());
    for (var f = 0; f < files.length; f++) fd.append('files[' + f + ']', files[f]);

    var $btn = $('#inv-doc-upload-btn');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Mengupload...');
    $.ajax({ url: invDocUploadUrl, method: 'POST', data: fd, processData: false, contentType: false })
        .done(function(res) {
            invDocs = res.documents || [];
            $('#inv-doc-files').val('');
            $('#inv-doc-notes').val('');
            invRenderDocs();
            toastr.success(res.message || 'Dokumen diupload.');
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal mengupload dokumen.');
        })
        .always(function() { $btn.prop('disabled', false).html('<i class="fa fa-upload me-1"></i> Upload'); });
}

function invDeleteDoc(url) {
    Swal.fire({
        title: 'Hapus dokumen?',
        text: 'File akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $.ajax({ url: url, method: 'DELETE', data: { _token: '{{ csrf_token() }}' } })
            .done(function(res) {
                invDocs = invDocs.filter(function(d) { return d.delete_url !== url; });
                invRenderDocs();
                toastr.success(res.message || 'Dokumen dihapus.');
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus dokumen.');
            });
    });
}

$(function() { if ($('#inv-doc-list').length) invRenderDocs(); });

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

$(document).on('click', '#inv-btn-cancel', function() {
    var reason = $.trim($('#inv-cancel-reason').val());
    if (!reason) { toastr.error('Alasan pembatalan wajib diisi.'); $('#inv-cancel-reason').focus(); return; }

    invPostAction(invCancelUrl, { cancel_reason: reason }, $(this), 'Gagal membatalkan invoice.');
});

function invEditTaxInvoice() {
    Swal.fire({
        title: 'No. Faktur Pajak',
        input: 'text',
        inputValue: invTaxInvoiceNumber || '',
        inputAttributes: { maxlength: 50 },
        inputPlaceholder: 'Kosongkan untuk menghapus',
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        var number = $.trim(result.value || '');
        $.ajax({
            url: invTaxInvoiceUrl,
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', _method: 'PATCH', tax_invoice_number: number }
        }).done(function(res) {
            invTaxInvoiceNumber = number || null;
            $('#inv-tax-number').text(number || '—');
            toastr.success(res.message);
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan no. faktur pajak.');
        });
    });
}

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
