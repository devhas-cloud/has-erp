@extends('layouts.app')

@section('title', 'Detail Purchase Order')
@section('page-title', 'Detail Purchase Order')

@section('styles')
<style>
    .po-muted { color: var(--text-muted); font-size: 12px; }
    .po-hero { background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); padding: 16px 18px; margin-bottom: 14px; }
    .po-hero-top { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .po-number { font-size: 20px; font-weight: 700; color: var(--text-primary); margin: 4px 0 2px; }
    .po-total { text-align: right; }
    .po-total .val { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
    @media (max-width: 575.98px) { .po-total { text-align: left; } }

    /* Tahapan status */
    .po-steps { display: flex; margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--card-border); }
    .po-step { flex: 1; text-align: center; position: relative; font-size: 12px; color: var(--text-muted); }
    .po-step::before { content: ''; position: absolute; top: 11px; left: -50%; width: 100%; height: 2px; background: var(--card-border); }
    .po-step:first-child::before { display: none; }
    .po-step .dot {
        position: relative; width: 24px; height: 24px; border-radius: 50%; margin-bottom: 3px;
        display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700;
        background: #fff; border: 2px solid var(--card-border);
    }
    .po-step.done::before, .po-step.current::before { background: var(--accent); }
    .po-step.done .dot { background: var(--accent); border-color: var(--accent); color: #fff; }
    .po-step.current { color: var(--text-primary); font-weight: 600; }
    .po-step.current .dot { border-color: #f59e0b; color: #b45309; }
    .po-step-note { font-size: 11px; color: var(--text-muted); font-weight: 400; }
    .po-step.stopped { color: #b91c1c; font-weight: 600; }
    .po-step.stopped .dot { border-color: #dc2626; color: #dc2626; }

    /* Langkah berikutnya */
    .po-next { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; border: 1px solid; border-radius: var(--radius); padding: 12px 16px; margin-bottom: 14px; }
    .po-next .txt { flex: 1; min-width: 220px; font-size: 13px; }
    .po-next .txt strong { display: block; font-size: 14px; }
    .po-next .acts { display: flex; gap: 8px; flex-wrap: wrap; }
    .po-next.info { background: #eff6ff; border-color: #bfdbfe; color: #1e3a8a; }
    .po-next.warn { background: #fffbeb; border-color: #fde68a; color: #78350f; }
    .po-next.ok { background: #f0fdf4; border-color: #bbf7d0; color: #14532d; }
    .po-next.danger { background: #fef2f2; border-color: #fecaca; color: #7f1d1d; }
    .po-next.muted { background: #f9fafb; border-color: var(--card-border); color: #374151; }

    .po-group-row td { background: #fffbeb !important; color: #92400e; font-weight: 600; font-size: 12.5px; }
    .po-items td { vertical-align: top; }
    .po-desc { font-size: 12.5px; color: var(--text-muted); }
    .po-desc p { margin: 0; }
    .po-bar { height: 4px; border-radius: 99px; background: #eef2f7; overflow: hidden; margin-top: 4px; }
    .po-bar > span { display: block; height: 100%; background: var(--accent); }

    .po-dl { display: grid; grid-template-columns: 95px 1fr; gap: 7px 10px; margin: 0; font-size: 13px; }
    .po-dl dt { color: var(--text-muted); font-weight: 500; font-size: 12px; }
    .po-dl dd { margin: 0; word-break: break-word; }
    .po-sum { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; }
    .po-sum .v { font-weight: 600; font-variant-numeric: tabular-nums; }
    .po-sum.grand { border-top: 1px solid var(--card-border); margin-top: 4px; padding-top: 10px; font-size: 15px; font-weight: 700; }
</style>
@endsection

@php
    $po = $purchaseOrder;
    $money = fn ($v) => \App\Support\Number::money((float) $v);
    $percent = fn ($v) => \App\Support\Number::percent((float) $v);
    $currency = $po->currencyCode();
    $poTotals = $po->totalsFrom($po->grandTotal());
    $isOwner = auth()->id() === $po->created_by || auth()->user()->role === 'Admin';
    $cancelled = $po->status === 'cancelled';
    $receivedQty = (int) $po->items->sum(fn ($item) => $item->receivedQty());
    $remainingQty = $cancelled ? 0 : (int) $po->items->sum(fn ($item) => $item->remainingReceiptQty());

    // Satu approval saja (1 user can_approve) -> satu titik "Approval".
    $steps = ['Draft', 'Approval', 'Penerimaan', 'Selesai'];
    $stepIndex = match ($po->status) {
        'waiting_approval', 'rejected' => 1,
        'approved', 'partially_received', 'cancelled' => 2,
        'received' => 3,
        default => 0,
    };
    $stopped = in_array($po->status, ['rejected', 'cancelled'], true);
    $stepNotes = [
        1 => match (true) {
            (bool) $po->approved_at => 'Disetujui '.($po->finalChecker?->display_name ?? '').' · '.$po->approved_at->format('d/m/Y'),
            (bool) $po->rejected_at => 'Ditolak '.($po->finalChecker?->display_name ?? '').' · '.$po->rejected_at->format('d/m/Y'),
            $po->status === 'waiting_approval' => 'Menunggu 1 approver',
            default => null,
        },
        2 => in_array($po->status, ['partially_received', 'cancelled'], true) && $receivedQty > 0 ? $receivedQty.' / '.(int) $po->items->sum('qty').' qty' : null,
    ];
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Detail Purchase Order</h1>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('purchase-order.pdf', $po->id) }}" target="_blank" class="btn btn-secondary btn-sm"><i class="fa fa-print me-1"></i> Cetak PDF</a>
        <a href="{{ route('purchase-order.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

{{-- Ringkas: nomor, supplier, total, tahapan --}}
<div class="po-hero fade-in">
    <div class="po-hero-top">
        <div>
            {!! $po->statusBadgeHtml() !!}
            <div class="po-number">{{ $po->po_number ?: 'PO #'.$po->id }}</div>
            <div class="po-muted">{{ $po->type_label }} &middot; {{ $po->supplier_name }} &middot; {{ $po->date?->format('d/m/Y') ?? 'Tanpa tanggal' }}</div>
            @if($po->revisionOf)
                <div class="po-muted mt-1"><i class="fa fa-code-branch me-1"></i>Revisi dari <a href="{{ route('purchase-order.show', $po->revision_of_id) }}">{{ $po->revisionOf->po_number }}</a> (Rejected)</div>
            @endif
        </div>
        <div class="po-total">
            <div class="po-muted">Grand Total</div>
            <div class="val">{{ $currency }} {{ $money($poTotals['total']) }}</div>
        </div>
    </div>
    <div class="po-steps">
        @foreach($steps as $i => $label)
            @php
                $state = $i < $stepIndex || ($i === $stepIndex && $po->status === 'received') ? 'done'
                    : ($i === $stepIndex ? ($stopped ? 'stopped' : 'current') : '');
            @endphp
            <div class="po-step {{ $state }}">
                <div class="dot">
                    @if($state === 'done')<i class="fa fa-check"></i>@elseif($state === 'stopped')<i class="fa fa-xmark"></i>@else{{ $i + 1 }}@endif
                </div>
                <div>{{ $state === 'stopped' ? ($po->status === 'rejected' ? 'Ditolak' : 'Dibatalkan') : ($i === 1 && $po->approved_at ? 'Disetujui' : $label) }}</div>
                @if(! empty($stepNotes[$i]))<div class="po-step-note">{{ $stepNotes[$i] }}</div>@endif
            </div>
        @endforeach
    </div>
</div>

{{-- Langkah berikutnya + semua tombol aksi --}}
@switch($po->status)
    @case('draft')
        <div class="po-next info">
            <div class="txt"><strong>{{ $isOwner ? 'Periksa item, lalu ajukan approval' : 'Masih draft' }}</strong>Barang baru bisa diterima setelah PO disetujui.</div>
            @if($isOwner)
            <div class="acts">
                <a href="{{ route('purchase-order.edit', $po->id) }}" class="btn btn-secondary btn-sm"><i class="fa fa-pen me-1"></i> Edit</a>
                <button type="button" class="btn btn-primary btn-sm" onclick="poSubmit()"><i class="fa fa-paper-plane me-1"></i> Submit Approval</button>
            </div>
            @endif
        </div>
        @break
    @case('waiting_approval')
        <div class="po-next warn">
            <div class="txt"><strong>{{ $canApprove ? 'Menunggu persetujuan Anda' : 'Menunggu persetujuan approver' }}</strong>Cukup 1 approver (user dengan hak Approve). Periksa supplier, item, dan total sebelum memutuskan.</div>
            @if($canApprove)
            <div class="acts">
                <button type="button" class="btn btn-danger btn-sm" onclick="poOpenReject()"><i class="fa fa-xmark me-1"></i> Reject</button>
                <button type="button" class="btn btn-success btn-sm" onclick="poApprove()"><i class="fa fa-check me-1"></i> Approve</button>
            </div>
            @endif
        </div>
        @break
    @case('approved')
    @case('partially_received')
        <div class="po-next info">
            <div class="txt"><strong>{{ $receivedQty > 0 ? 'Sisa '.$remainingQty.' qty belum diterima' : 'Siap menerima barang' }}</strong>Catat barang yang datang lewat Terima Barang, boleh bertahap.</div>
            <div class="acts">
                @if($po->canBeCancelled() && $canApprove)
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="poOpenCancel()"><i class="fa fa-ban me-1"></i> Batalkan PO</button>
                @endif
                @if($po->isReceivable())
                    <a href="{{ route('goods-receipt.create', ['source_type' => 'po', 'source_id' => $po->id]) }}" class="btn btn-primary btn-sm"><i class="fa fa-truck-ramp-box me-1"></i> Terima Barang</a>
                @endif
            </div>
        </div>
        @break
    @case('received')
        <div class="po-next ok"><div class="txt"><strong><i class="fa fa-circle-check me-1"></i>Semua barang sudah diterima</strong>PO selesai.</div></div>
        @break
    @case('rejected')
        @php $revision = $po->revisions->first(); @endphp
        <div class="po-next danger">
            <div class="txt">
                <strong>Ditolak{{ $po->finalChecker ? ' oleh '.$po->finalChecker->display_name : '' }}</strong>{{ $po->approval_note ?: 'Tanpa alasan.' }}
                @if($revision)
                    <div class="mt-1"><i class="fa fa-code-branch me-1"></i>Sudah direvisi menjadi <a href="{{ route('purchase-order.show', $revision->id) }}" class="fw-semibold">{{ $revision->po_number }}</a> ({{ $revision->status_label }}).</div>
                @else
                    <div class="mt-1">Perbaiki lewat <strong>Buat Revisi</strong>: PO disalin menjadi draft baru bernomor sama + REV.</div>
                @endif
            </div>
            @if(! $revision && $canUpdate)
            <div class="acts">
                <button type="button" class="btn btn-primary btn-sm" id="po-revise-btn" onclick="poRevise()"><i class="fa fa-code-branch me-1"></i> Buat Revisi</button>
            </div>
            @endif
        </div>
        @break
    @case('cancelled')
        <div class="po-next muted">
            <div class="txt"><strong>Dibatalkan{{ $po->canceller ? ' oleh '.$po->canceller->display_name : '' }}{{ $po->cancelled_at ? ' · '.$po->cancelled_at->format('d/m/Y H:i') : '' }}</strong>{{ $po->cancel_reason }} — qty yang belum diterima kembali ke Permintaan Barang.</div>
        </div>
        @break
@endswitch

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Item <span class="po-muted">({{ $po->items->count() }})</span></span>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom po-items mb-0">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th class="text-center" style="width:80px">Qty</th>
                                <th class="text-end" style="width:130px">Harga</th>
                                <th class="text-end" style="width:140px">Subtotal</th>
                                <th style="width:120px">Diterima</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($poGroups as $group)
                                <tr class="po-group-row"><td colspan="5"><i class="fa-solid fa-diagram-project me-1"></i>{{ $group['label'] }}</td></tr>
                                @foreach($group['items'] as $item)
                                    @php
                                        $gr = $item->goodsRequest();
                                        $received = $item->receivedQty();
                                        $pct = $item->qty > 0 ? min(100, round($received / $item->qty * 100)) : 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold" style="font-size:13px">{{ $item->part_number ?: '—' }}</div>
                                            <div class="po-desc">{!! $item->description ?: '—' !!}</div>
                                            @if($gr)
                                                <a href="{{ route('goods-request.show', $gr->id) }}" class="po-muted text-decoration-none" title="Permintaan Barang asal">
                                                    <i class="fa fa-link me-1"></i>Permintaan {{ $gr->number_label }} · {{ $gr->division?->division_name ?? '-' }}
                                                </a>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $item->qty ?? '—' }} <span class="po-muted">{{ $item->unit }}</span></td>
                                        <td class="text-end">{{ $item->price !== null ? $money($item->price) : '—' }}</td>
                                        <td class="text-end fw-semibold">{{ $money($item->amount()) }}</td>
                                        <td style="font-size:12px">
                                            @if($received >= $item->qty && $item->qty > 0)
                                                <span style="color:#15803d;font-weight:600"><i class="fa fa-check me-1"></i>Lengkap</span>
                                            @else
                                                {{ $received }} / {{ $item->qty }}@if($cancelled) <span class="po-muted">· dilepas</span>@endif
                                            @endif
                                            <div class="po-bar"><span style="width:{{ $pct }}%"></span></div>
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr><td colspan="5" class="text-center po-muted py-3">Belum ada item.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($po->goodsReceipts->isNotEmpty())
        @php $returnable = $po->items->sum(fn ($item) => max(0, $item->receivedQty() - $item->billedQty())); @endphp
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-truck-ramp-box me-2" style="color:var(--accent)"></i>Penerimaan Barang <span class="po-muted">({{ $po->goodsReceipts->count() }})</span></span>
                @if($canCreateReturn && $returnable > 0 && $po->supplier_id)
                    <a href="{{ route('supplier-return.create', ['purchase_order_id' => $po->id]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-truck-arrow-right me-1"></i> Retur ke Supplier</a>
                @endif
            </div>
            <div class="card-body-custom p-2">
                <table class="table table-custom align-middle mb-0">
                    <tbody>
                        @foreach($po->goodsReceipts as $receipt)
                            <tr>
                                <td><a href="{{ route('goods-receipt.show', $receipt->id) }}" class="fw-semibold">{{ $receipt->receipt_number }}</a></td>
                                <td>{{ $receipt->received_date?->format('d/m/Y') }}</td>
                                <td>{{ $receipt->warehouse?->name ?? 'Langsung pakai' }}</td>
                                <td class="text-end">{{ (int) $receipt->items_sum_qty }} qty</td>
                                <td class="text-end">{!! $receipt->statusBadgeHtml() !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Tagihan supplier (Finance): 3-way match terhadap barang yang sudah diterima --}}
        @php
            $billing = $po->billingStatus();
            $billingColors = ['none' => '#f3f4f6;color:#4b5563', 'partial' => '#ede9fe;color:#5b21b6', 'full' => '#dcfce7;color:#166534'];
            $billableNow = $po->items->sum(fn ($item) => $item->billableQty());
        @endphp
        @if(in_array($po->status, ['approved', 'partially_received', 'received', 'cancelled'], true))
        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Tagihan Supplier
                    <span class="status-badge ms-1" style="background:{{ $billingColors[$billing] }}">{{ \App\Models\PurchaseOrder::BILLING_LABELS[$billing] }}</span>
                    @php $payState = $po->paymentStatus(); @endphp
                    @if($payState !== 'unbilled')
                        <span class="status-badge ms-1" style="background:{{ ['unpaid' => '#fef3c7;color:#92400e', 'partial' => '#ede9fe;color:#5b21b6', 'paid' => '#dcfce7;color:#166534', 'advance' => '#e0f2fe;color:#075985', 'refund' => '#fee2e2;color:#991b1b'][$payState] }}">{{ \App\Models\PurchaseOrder::PAYMENT_LABELS[$payState] }}</span>
                    @endif
                </span>
                <div class="d-flex gap-2 flex-wrap">
                    @if($canCreateBill && $po->isReceivable() && ($po->supplier?->max_advance_percent ?? 0) > 0 && $po->advanceLimit() - $po->advanceBilled() > 0.005)
                        <a href="{{ route('supplier-bill.create', ['purchase_order_id' => $po->id, 'type' => 'advance']) }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-hand-holding-dollar me-1"></i> Uang Muka</a>
                    @endif
                    @if($canCreateBill && $billableNow > 0)
                        <a href="{{ route('supplier-bill.create', ['purchase_order_id' => $po->id]) }}" class="btn btn-soft btn-sm"><i class="fa fa-plus me-1"></i> Catat Tagihan</a>
                    @endif
                </div>
            </div>
            <div class="card-body-custom p-2">
                @php $refundDue = $po->refundDue(); @endphp
                @if($po->advancePaid() > 0 || $refundDue > 0)
                    <div class="d-flex flex-wrap gap-3 align-items-center px-2 pb-2 mb-2" style="border-bottom:1px dashed var(--card-border);font-size:12.5px">
                        <span>Uang muka dibayar <strong>{{ $po->currencyCode() }} {{ \App\Models\SupplierBill::formatMoney($po->advancePaid()) }}</strong></span>
                        <span>Dipotongkan <strong>{{ \App\Models\SupplierBill::formatMoney($po->advanceApplied()) }}</strong></span>
                        <span>Saldo <strong>{{ \App\Models\SupplierBill::formatMoney($po->advanceBalance()) }}</strong></span>
                        @if($refundDue > 0)
                            <span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Menunggu refund {{ \App\Models\SupplierBill::formatMoney($refundDue) }}</span>
                            @if($canCreateRefund)<a href="{{ route('supplier-refund.create', ['purchase_order_id' => $po->id]) }}" class="btn btn-outline-danger btn-sm py-0">Catat Refund</a>@endif
                        @endif
                    </div>
                @endif
                @if($po->supplierBills->isEmpty())
                    <div class="text-center py-3 po-muted">
                        Belum ada tagihan.
                        @if($billableNow > 0) {{ $billableNow }} qty sudah diterima dan bisa ditagih. @else Tagihan bisa dicatat setelah barang diterima. @endif
                    </div>
                @else
                <table class="table table-custom align-middle mb-0">
                    <tbody>
                        @foreach($po->supplierBills as $bill)
                            <tr @if($bill->isVoid()) style="opacity:.55" @endif>
                                <td><a href="{{ route('supplier-bill.show', $bill->id) }}" class="fw-semibold">{{ $bill->bill_number }}</a>@if($bill->isAdvance()) <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px">Uang Muka</span>@endif<div class="po-muted">Inv. {{ $bill->supplier_invoice_number }}</div></td>
                                <td>{{ $bill->bill_date?->format('d/m/Y') }}<div class="po-muted">Jatuh tempo {{ $bill->due_date?->format('d/m/Y') }}</div></td>
                                <td class="text-end" style="white-space:nowrap">{{ $bill->currency }} {{ \App\Models\SupplierBill::formatMoney($bill->total) }}</td>
                                <td class="text-end">{!! $bill->statusBadgeHtml() !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
        @endif
    </div>

    <div class="col-xl-4">
        @include('partials.purchase-documents', [
            'documents' => $documents,
            'types' => $po->documentTypes(),
            'uploadUrl' => route('purchase-order.documents.store', $po->id),
            'canUpload' => $canCreate,
            'hint' => 'Opsional — penawaran supplier, PO bertanda tangan, dll. Setelah PO diputuskan, dokumen hanya bisa ditambah.',
        ])
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Total ({{ $currency }})</span></div>
            <div class="card-body-custom">
                <div class="po-sum"><span>Sub Total</span><span class="v">{{ $money($poTotals['subtotal']) }}</span></div>
                @if($poTotals['discount'] > 0)
                    <div class="po-sum"><span>Diskon {{ $percent($po->discount_percent) }}%</span><span class="v text-danger">- {{ $money($poTotals['discount']) }}</span></div>
                    <div class="po-sum"><span>Sub Total 1</span><span class="v">{{ $money($poTotals['after_discount']) }}</span></div>
                @endif
                @if($poTotals['ppn'] > 0)
                    <div class="po-sum"><span>PPN {{ $percent($po->ppn_percent) }}%</span><span class="v">{{ $money($poTotals['ppn']) }}</span></div>
                @endif
                <div class="po-sum grand"><span>Grand Total</span><span>{{ $money($poTotals['total']) }}</span></div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom"><span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span></div>
            <div class="card-body-custom">
                <dl class="po-dl">
                    <dt>Supplier</dt>
                    <dd>
                        <div class="fw-semibold">{{ $po->supplier_name }}</div>
                        @if($po->supplier_address)<div class="po-muted">{!! nl2br(e($po->supplier_address)) !!}</div>@endif
                        @if($po->supplier_phone)<div class="po-muted">Telp {{ $po->supplier_phone }}@if($po->supplier_fax) · Fax {{ $po->supplier_fax }}@endif</div>@endif
                        @if($po->supplier_attn)<div class="po-muted">Attn {{ $po->supplier_attn }}</div>@endif
                    </dd>
                    <dt>Jenis</dt><dd>{{ $po->type_label }}</dd>
                    @if($po->payment_term_days !== null)<dt>Termin Bayar</dt><dd>{{ \App\Models\Supplier::termLabel($po->payment_term_days) }}</dd>@endif
                    @if($po->terms)<dt>Terms</dt><dd>{{ $po->terms }}</dd>@endif
                    @if($po->delivery)<dt>Delivery</dt><dd>{{ $po->delivery }}</dd>@endif
                    @if($po->your_ref)<dt>Your Ref</dt><dd>{{ $po->your_ref }}</dd>@endif
                    @if(count($po->divisionNames()))<dt>Divisi</dt><dd>{{ implode(', ', $po->divisionNames()) }}</dd>@endif
                    <dt>Dibuat</dt><dd>{{ $po->creator?->display_name ?? '—' }} <div class="po-muted">{{ $po->created_at?->format('d/m/Y H:i') }}</div></dd>
                    @if($po->final_checked_by)
                        <dt>{{ $po->rejected_at ? 'Ditolak' : 'Disetujui' }}</dt>
                        <dd>{{ $po->finalChecker?->display_name ?? '—' }} <div class="po-muted">{{ ($po->approved_at ?? $po->rejected_at)?->format('d/m/Y H:i') }}</div></dd>
                    @endif
                    @if($po->notes)<dt>Catatan</dt><dd>{{ $po->notes }}</dd>@endif
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="poCancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Batalkan Purchase Order</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p style="font-size:13px;color:var(--text-muted)">Barang yang sudah diterima tetap tercatat. Qty yang belum diterima dilepas dan kembali menjadi sisa kebutuhan Permintaan Barang.</p>
                <label class="form-label">Alasan Pembatalan <span class="text-danger">*</span></label>
                <textarea id="po-cancel-reason" class="form-control" rows="3" placeholder="Wajib diisi"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-danger btn-sm" id="po-btn-cancel">
                    <i class="fa fa-ban me-1"></i> Batalkan PO
                </button>
            </div>
        </div>
    </div>
</div>

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
const poCancelUrl = '{{ route("purchase-order.void", $purchaseOrder->id) }}';
const poReviseUrl = '{{ route("purchase-order.revise", $purchaseOrder->id) }}';

function poRevise() {
    Swal.fire({
        title: 'Buat Revisi PO?',
        text: 'PO ini disalin menjadi draft baru dengan nomor yang sama + REV. Qty mengikuti sisa kebutuhan Permintaan Barang saat ini.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Buat Revisi',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $('#po-revise-btn').prop('disabled', true);
        $.post(poReviseUrl, { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Revisi dibuat.');
                setTimeout(function() { window.location.href = res.redirect; }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal membuat revisi.');
                $('#po-revise-btn').prop('disabled', false);
            });
    });
}
let poCancelModalInstance = null;

function poOpenCancel() {
    $('#po-cancel-reason').val('');
    if (!poCancelModalInstance) {
        poCancelModalInstance = new bootstrap.Modal(document.getElementById('poCancelModal'));
    }
    poCancelModalInstance.show();
}

$(document).on('click', '#po-btn-cancel', function() {
    var reason = $('#po-cancel-reason').val().trim();
    if (!reason) {
        toastr.error('Alasan pembatalan wajib diisi.');
        return;
    }
    $('#po-btn-cancel').prop('disabled', true);
    $.post(poCancelUrl, { _token: '{{ csrf_token() }}', cancel_reason: reason })
        .done(function(res) {
            toastr.success(res.message || 'PO dibatalkan.');
            poCancelModalInstance.hide();
            setTimeout(function() { window.location.reload(); }, 800);
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal membatalkan.');
        }).always(function() {
            $('#po-btn-cancel').prop('disabled', false);
        });
});

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
    // Approver melihat dokumen terlampir sebelum menyetujui (opsional, tidak wajib).
    var docs = (typeof pdocDocuments !== 'undefined' ? pdocDocuments : []);
    var docHtml = docs.length
        ? '<div style="text-align:left;font-size:13px"><div class="mb-1">Dokumen terlampir (' + docs.length + '):</div><ul style="padding-left:18px;margin:0">'
            + docs.map(function(d) { return '<li><a href="' + d.view_url + '" target="_blank">' + pdocEsc(d.file_name) + '</a>' + (d.type_label ? ' <small style="color:#6b7280">· ' + pdocEsc(d.type_label) + '</small>' : '') + '</li>'; }).join('')
            + '</ul></div>'
        : '<div style="font-size:13px;color:#6b7280">Belum ada dokumen pembelian terlampir.</div>';
    Swal.fire({
        title: 'Approve Purchase Order?',
        html: docHtml,
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
