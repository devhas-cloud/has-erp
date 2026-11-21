@extends('layouts.app')

@section('title', $purchaseOrder ? 'Edit Purchase Order' : 'Create Purchase Order')
@section('page-title', $purchaseOrder ? 'Edit Purchase Order' : 'Create Purchase Order')

@section('styles')
<style>
    .po-section-sub { font-size: 12px; color: var(--text-muted); font-weight: 400; margin-left: 6px; }
    .po-hint { font-size: 12px; color: var(--text-muted); }

    .po-desc[contenteditable="true"] {
        border: 1px solid var(--card-border, #ced4da);
        border-radius: .25rem;
        padding: .25rem .5rem;
        min-height: 34px;
        background: #fff;
        font-size: .85rem;
        line-height: 1.45;
        white-space: pre-wrap;
    }
    .po-desc[contenteditable="true"]:focus { outline: none; border-color: var(--accent); }
    .po-desc[contenteditable="true"]:empty::before { content: attr(data-placeholder); color: #999; }
    .po-desc.is-invalid { border-color: var(--danger); }

    /* Pilihan jenis PO sebagai kartu */
    .po-type-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
    @media (max-width: 575.98px) { .po-type-cards { grid-template-columns: 1fr; } }
    .po-type-card {
        display: flex; gap: 10px; align-items: flex-start;
        padding: 10px 12px; margin: 0;
        border: 1px solid var(--card-border); border-radius: var(--radius-sm);
        cursor: pointer; transition: all .15s; background: var(--card);
    }
    .po-type-card:hover { border-color: var(--accent); }
    .po-type-card .name { font-weight: 600; font-size: 13.5px; color: var(--text-primary); }
    .po-type-card .name .sfx { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-left: 4px; }
    .po-type-card .desc { font-size: 11.5px; color: var(--text-muted); line-height: 1.35; }
    .btn-check:checked + .po-type-card { border-color: var(--accent); background: var(--accent-subtle); box-shadow: 0 0 0 1px var(--accent) inset; }

    /* Supplier: kontak ringkas */
    .po-contact { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-top: 14px; padding: 10px 12px; border-radius: var(--radius-sm); background: var(--accent-subtle); border: 1px solid var(--card-border); }
    .po-contact-view { display: flex; flex-wrap: wrap; gap: 4px 22px; font-size: 12.5px; min-width: 0; }
    .po-contact-line { display: flex; gap: 6px; align-items: baseline; min-width: 0; }
    .po-contact-line b { color: var(--text-muted); font-weight: 500; font-size: 11.5px; }
    .po-contact-line span { white-space: pre-line; }
    .po-contact-line.is-empty span { color: var(--text-muted); font-style: italic; }
    .po-contact-edit { font-size: 12px; white-space: nowrap; }

    /* Item list */
    .po-chip { font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 999px; background: #f3f4f6; color: #374151; white-space: nowrap; }
    .po-chip-accent { background: var(--accent-soft); color: var(--accent); }
    .po-chip-warn { background: #fef3c7; color: #92400e; }
    #po-items-table th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    #po-items-body td { vertical-align: top; }
    #po-items-body .po-row-num { padding-top: 14px; color: var(--text-muted); font-weight: 600; }
    #po-items-body tr.po-group td { background: var(--accent-subtle); font-size: 12.5px; padding-top: 7px; padding-bottom: 7px; }
    #po-items-body tr.po-group .po-group-total { font-weight: 600; font-variant-numeric: tabular-nums; }
    #po-items-body .po-amount { padding-top: 14px; font-weight: 600; font-variant-numeric: tabular-nums; }
    #po-items-body .po-source { font-size: 10.5px; font-weight: 600; padding: 1px 7px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); display: inline-block; margin-top: 3px; white-space: normal; }
    #po-items-body .po-qty-unit { font-size: 11px; color: var(--text-muted); text-align: center; margin-top: 2px; white-space: nowrap; }
    #po-items-body .po-price.po-price-missing { border-color: #f59e0b; background: #fffbeb; }
    #po-items-table tfoot td { border-top: 2px solid var(--card-border); font-size: 14px; }
    .po-items-empty { text-align: center; padding: 34px 12px; color: var(--text-muted); font-size: 13px; }
    .po-items-empty .po-empty-icon { font-size: 28px; color: var(--accent); opacity: .6; display: block; margin-bottom: 8px; }

    /* Signatories */
    [data-bs-target="#po-sign-body"][aria-expanded="true"] .po-sign-caret { transform: rotate(180deg); }
    .po-sign-caret { transition: transform .15s ease; }

    .po-summary-sticky { position: sticky; top: 84px; }
    .po-summary-number {
        text-align: center; padding: 12px; margin-bottom: 10px;
        background: var(--accent-subtle); border-radius: var(--radius-sm);
    }
    .po-summary-number .lbl { font-size: 11.5px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
    .po-summary-number .val { font-size: 20px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }
    .po-summary-number .val.pending { font-size: 13px; font-weight: 500; color: var(--text-muted); }
    .po-summary-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 7px 0; font-size: 13.5px; }
    .po-summary-row + .po-summary-row { border-top: 1px dashed var(--card-border); }
    .po-summary-row .lbl { color: var(--text-muted); white-space: nowrap; }
    .po-summary-row .val { font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; overflow: hidden; text-overflow: ellipsis; }
    .po-summary-total {
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 8px; padding: 12px 14px;
        background: var(--accent-soft); border-radius: var(--radius-sm);
        font-weight: 700;
    }
    .po-summary-total .val { font-size: 17px; color: var(--accent); font-variant-numeric: tabular-nums; }
    .po-summary-note { margin-top: 10px; font-size: 12px; line-height: 1.5; color: var(--text-muted); }
    .po-sum-block { padding: 4px 0; border-bottom: 1px dashed var(--card-border); margin-bottom: 4px; }
    .po-sum-block .po-summary-row + .po-summary-row { border-top: 0; }
    .po-checklist { list-style: none; padding: 0; margin: 12px 0 0; font-size: 12.5px; }
    .po-checklist li { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; padding: 3px 0; color: var(--text-muted); }
    .po-check-state { font-size: 11px; font-weight: 600; white-space: nowrap; color: #94a3b8; }
    .po-checklist li.is-ok { color: var(--text-primary); }
    .po-checklist li.is-ok .po-check-state { color: #065f46; }
    .po-checklist li.is-warn .po-check-state { color: #b45309; }
    .po-dirty { color: #b45309; display: none; }

    .po-picker-toolbar { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 8px; }
    @media (max-width: 767.98px) { .po-picker-toolbar { grid-template-columns: 1fr; } }

    /* Picker: seluruh baris bisa diklik untuk memilih */
    .po-picker-group { border: 1px solid var(--card-border); border-radius: var(--radius); overflow: hidden; }
    .po-picker-group-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 8px 12px; background: var(--bg); border-bottom: 1px solid var(--card-border); cursor: pointer; user-select: none; }
    .po-picker-group-head:hover { background: #ecfdf5; }
    /* Skin baris = sama dengan picker IH Configuration & Goods Request */
    .po-picker-item-row { cursor: pointer; user-select: none; transition: background-color .15s ease; }
    .po-picker-item-row.is-selected, .po-picker-item-row.is-selected td { background-color: #ecfdf5 !important; }
    .po-picker-item-row:not(.is-disabled):hover, .po-picker-item-row:not(.is-disabled):hover td { background-color: #d1fae5 !important; color: #065f46; }
    .po-picker-item-row:not(.is-disabled):hover .po-hint { color: #065f46; }
    .po-picker-item-row.is-disabled { cursor: not-allowed; }
    .po-picker-item-row.is-disabled td { opacity: .5; }
    .po-picker-check, .po-picker-check-group, #po-picker-check-all { width: 16px; height: 16px; cursor: pointer; }
    .po-picker-selbar { display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; padding: 8px 12px; border: 1px solid var(--card-border); border-radius: var(--radius-sm); background: var(--bg); font-size: 12.5px; }
    .po-picker-selbar .po-picker-selcount { font-weight: 600; }
    .po-picker-selbar .po-picker-selcount.has { display: inline-flex; align-items: center; font-size: 11.5px; padding: .15rem .6rem; border-radius: 999px; background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
</style>
@endsection

@section('content')
@php
    $poType = $purchaseOrder?->po_type ?? \App\Models\PurchaseOrder::TYPE_MANUFACTURE;
    $typeMeta = [
        'manufacture' => ['fa-industry', 'Purchase from manufacturer / principal'],
        'local' => ['fa-store', 'Purchase from local supplier'],
        'calibration' => ['fa-scale-balanced', 'Equipment calibration service'],
    ];
@endphp
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $purchaseOrder ? 'Edit Purchase Order' : 'Create Purchase Order' }}</h1>
        <p class="page-header-sub">Select a supplier and PO type, then take items from Approved Goods Requests — one PO can combine multiple projects.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $purchaseOrder ? route('purchase-order.show', $purchaseOrder->id) : route('purchase-order.index') }}" class="btn btn-secondary btn-sm">
            Back
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span>Type &amp; PO Details</span>
            </div>
            <div class="card-body-custom">
                <label class="form-label">PO Type <span class="text-danger">*</span></label>
                <div class="po-type-cards mb-1" role="radiogroup">
                    @foreach(\App\Models\PurchaseOrder::TYPE_LABELS as $typeKey => $typeLabel)
                        <input type="radio" class="btn-check" name="po-type" id="po-type-{{ $typeKey }}" value="{{ $typeKey }}" autocomplete="off" @checked($poType === $typeKey)>
                        <label class="po-type-card" for="po-type-{{ $typeKey }}">
                            <span>
                                <span class="name">{{ $typeLabel }}@if(\App\Models\PurchaseOrder::TYPE_SUFFIXES[$typeKey])<span class="sfx">{{ \App\Models\PurchaseOrder::TYPE_SUFFIXES[$typeKey] }}</span>@endif</span>
                                <span class="desc d-block">{{ $typeMeta[$typeKey][1] ?? '' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="po-hint mb-3" id="po-number-preview"></div>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">PO Date</label>
                        <input type="date" class="form-control" id="po-date" value="{{ $purchaseOrder ? $purchaseOrder->date?->format('Y-m-d') : now()->toDateString() }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Terms</label>
                        <input type="text" class="form-control" id="po-terms" list="po-terms-options" placeholder="Example: TT" value="{{ $purchaseOrder?->terms }}">
                        <datalist id="po-terms-options">
                            <option value="TT"><option value="TT in Advance"><option value="COD">
                            <option value="Net 14 Days"><option value="Net 30 Days"><option value="DP 50%">
                        </datalist>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Payment Terms</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="po-payment-term-days" min="0" max="365" step="1" placeholder="Follow supplier" value="{{ $purchaseOrder?->payment_term_days }}">
                            <span class="input-group-text">days</span>
                        </div>
                        <small class="po-hint">0 = cash / advance payment.</small>
                    </div>

                    <div class="col-md-3" id="po-rate-wrap">
                        <label class="form-label" for="po-exchange-rate">Exchange Rate <span class="po-section-sub">1 <span class="po-rate-cur">—</span> = <span class="po-rate-base">IDR</span></span> <span class="text-danger po-rate-required">*</span></label>
                        <input type="text" class="form-control text-end" id="po-exchange-rate" data-number="rate" value="{{ $purchaseOrder?->exchange_rate ? \App\Support\Number::rate($purchaseOrder->exchange_rate) : '' }}">
                        <small class="po-hint" id="po-rate-hint">Used to value the goods when they are received.</small>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Delivery <span class="po-section-sub">optional</span></label>
                        <input type="text" class="form-control" id="po-delivery" maxlength="150" placeholder="Example: Franco Jakarta" value="{{ $purchaseOrder?->delivery }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Your Ref <span class="po-section-sub">supplier quote no.</span></label>
                        <input type="text" class="form-control" id="po-your-ref" maxlength="100" placeholder="Example: 1055/TSC/10/2026" value="{{ $purchaseOrder?->your_ref }}">
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label">Discount</label>
                        <div class="input-group">
                            <input type="number" class="form-control po-percent-input" id="po-discount-percent" min="0" max="100" step="any" placeholder="0" value="{{ $purchaseOrder?->discount_percent }}">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label">VAT</label>
                        <div class="input-group">
                            <input type="number" class="form-control po-percent-input" id="po-ppn-percent" min="0" max="100" step="any" placeholder="0" value="{{ $purchaseOrder ? $purchaseOrder->ppn_percent : \App\Models\PurchaseOrder::DEFAULT_PPN_PERCENT }}">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes <span class="po-section-sub">optional, printed below items</span></label>
                        <textarea id="po-notes" class="form-control" rows="2" placeholder="Example: Freight to Jakarta using FedEx account ...">{{ $purchaseOrder?->notes }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Supplier</span>
                <span class="po-hint">Contact details are printed on the PO</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <label class="form-label" for="po-supplier-select">Supplier <span class="text-danger">*</span></label>
                        <select id="po-supplier-select" class="form-select" style="width:100%">
                            @if($purchaseOrder && $purchaseOrder->supplier_id)
                                <option value="{{ $purchaseOrder->supplier_id }}" selected>{{ $purchaseOrder->supplier_name }}</option>
                            @endif
                        </select>
                        <input type="hidden" id="po-supplier-id" value="{{ $purchaseOrder?->supplier_id }}">
                        <input type="hidden" id="po-supplier-name" value="{{ $purchaseOrder?->supplier_name }}">
                        @if($purchaseOrder && ! $purchaseOrder->supplier_id)
                            <small class="po-hint" style="color:#b45309">Created before the Supplier module ("{{ $purchaseOrder->supplier_name }}") — re-select from master to link.</small>
                        @else
                            <small class="po-hint">Not listed? Add it via the Supplier menu.</small>
                        @endif
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="po-supplier-attn">Attn</label>
                        <input type="text" class="form-control" id="po-supplier-attn" placeholder="Contact person" value="{{ $purchaseOrder?->supplier_attn }}">
                    </div>
                </div>

                {{-- Kontak yang dicetak: ringkas, ubah bila perlu --}}
                <div class="po-contact" id="po-contact">
                    <div class="po-contact-view">
                        <div class="po-contact-line"><b>Address</b><span id="po-contact-address">—</span></div>
                        <div class="po-contact-line"><b>Phone</b><span id="po-contact-phone">—</span></div>
                        <div class="po-contact-line"><b>Fax</b><span id="po-contact-fax">—</span></div>
                    </div>
                    <button type="button" class="btn btn-link btn-sm p-0 po-contact-edit" data-bs-toggle="collapse" data-bs-target="#po-contact-form" aria-expanded="false">
                        Edit contact
                    </button>
                </div>
                <div class="collapse" id="po-contact-form">
                    <div class="row g-3 pt-3">
                        <div class="col-lg-8">
                            <label class="form-label" for="po-supplier-address">Address</label>
                            <textarea id="po-supplier-address" class="form-control" rows="3" placeholder="Filled when a supplier is selected">{{ $purchaseOrder?->supplier_address }}</textarea>
                        </div>
                        <div class="col-lg-4">
                            <div class="row g-3">
                                <div class="col-lg-12 col-sm-6">
                                    <label class="form-label" for="po-supplier-phone">Phone</label>
                                    <input type="text" class="form-control" id="po-supplier-phone" value="{{ $purchaseOrder?->supplier_phone }}">
                                </div>
                                <div class="col-lg-12 col-sm-6">
                                    <label class="form-label" for="po-supplier-fax">Fax</label>
                                    <input type="text" class="form-control" id="po-supplier-fax" value="{{ $purchaseOrder?->supplier_fax }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="d-flex align-items-center flex-wrap gap-2">
                    <span>Item List</span>
                    <span class="po-chip po-chip-accent" id="po-items-count">0 items</span>
                    <span class="po-chip" id="po-items-currency" style="display:none"></span>
                    <span class="po-chip po-chip-warn" id="po-items-noprice" style="display:none"></span>
                </span>
                <button type="button" class="btn btn-soft btn-sm" onclick="poLoadPicker()">
                    Take from Goods Request
                </button>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom mb-0" id="po-items-table">
                        <thead>
                            <tr>
                                <th style="width:38px" class="text-center">#</th>
                                <th style="width:22%">Item</th>
                                <th>Description <span class="po-section-sub">printed for supplier</span></th>
                                <th class="text-center" style="width:120px">Qty</th>
                                <th class="text-end" style="width:170px">Unit Price</th>
                                <th class="text-end" style="width:130px">Subtotal</th>
                                <th style="width:40px"></th>
                            </tr>
                        </thead>
                        <tbody id="po-items-body"></tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-end fw-bold">Subtotal <span class="po-currency-code"></span></td>
                                <td class="text-end fw-bold" id="po-grand-total">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="po-items-empty" id="po-items-empty">
                    <div class="fw-semibold" style="color:var(--text-primary)">No items yet</div>
                    <div class="po-hint mb-2">Take items from Approved Goods Requests — one PO can combine several projects.</div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="poLoadPicker()">
                        Take from Goods Request
                    </button>
                </div>
                <div class="px-2 pt-2 po-hint">
                    Qty may be lower than the remaining need (split PO). Enter in qty / price moves to the next field. For purchases without a request, use Direct Purchase.
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2" role="button" data-bs-toggle="collapse" data-bs-target="#po-sign-body" aria-expanded="false" style="cursor:pointer">
                <span>Signatories <span class="po-section-sub">printed on PDF · optional</span></span>
                <span class="d-flex align-items-center gap-2">
                    <span class="po-hint" id="po-sign-summary">Not filled</span>
                    <i class="fa fa-chevron-down po-sign-caret" style="font-size:11px;color:var(--text-muted)"></i>
                </span>
            </div>
            <div class="collapse" id="po-sign-body">
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="po-request-by">Request By</label>
                            <input type="text" class="form-control po-sign" id="po-request-by" placeholder="Requester name" value="{{ $purchaseOrder?->request_by_name }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="po-finance-name">Finance Dept</label>
                            <input type="text" class="form-control po-sign" id="po-finance-name" placeholder="Finance person in charge" value="{{ $purchaseOrder?->finance_name }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="po-accounting-name">Accounting Dept</label>
                            <input type="text" class="form-control po-sign" id="po-accounting-name" placeholder="Accounting person in charge" value="{{ $purchaseOrder?->accounting_name }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in po-summary-sticky">
            <div class="card-header-custom">
                <span>Summary</span>
            </div>
            <div class="card-body-custom">
                <div class="po-summary-number">
                    <div class="lbl">PO No.</div>
                    <div class="val" id="po-sum-number">—</div>
                    <div class="po-hint" id="po-sum-type">—</div>
                </div>

                <div class="po-sum-block">
                    <div class="po-summary-row"><span class="lbl">Supplier</span><span class="val" id="po-sum-supplier" title="">—</span></div>
                    <div class="po-summary-row"><span class="lbl">Items</span><span class="val"><span id="po-sum-items">0</span> · <span id="po-sum-projects">0</span> <span class="fw-normal po-hint">group(s)</span></span></div>
                    <div class="po-summary-row"><span class="lbl">Currency</span><span class="val" id="po-sum-currency">—</span></div>
                </div>

                <div class="po-sum-block">
                    <div class="po-summary-row"><span class="lbl">Subtotal</span><span class="val" id="po-sum-subtotal">0</span></div>
                    <div class="po-summary-row" id="po-sum-discount-row"><span class="lbl" id="po-sum-discount-label">Discount</span><span class="val text-danger" id="po-sum-discount">0</span></div>
                    <div class="po-summary-row" id="po-sum-ppn-row"><span class="lbl" id="po-sum-ppn-label">VAT</span><span class="val" id="po-sum-ppn">0</span></div>
                </div>
                <div class="po-summary-total"><span>Total</span><span class="val" id="po-sum-total">0</span></div>
                <div class="po-summary-row" id="po-sum-base-row" style="display:none"><span class="lbl">≈ <span class="po-rate-base">IDR</span> <span class="po-hint" id="po-sum-rate"></span></span><span class="val" id="po-sum-base">0</span></div>

                <ul class="po-checklist" id="po-checklist">
                    <li data-check="supplier"><span class="po-check-text">Supplier selected</span><span class="po-check-state">Pending</span></li>
                    <li data-check="items"><span class="po-check-text">At least one item</span><span class="po-check-state">Pending</span></li>
                    <li data-check="prices"><span class="po-check-text">All prices filled <span class="po-hint">(needed to submit)</span></span><span class="po-check-state">Pending</span></li>
                </ul>

                <button type="button" class="btn btn-primary w-100 mt-2" id="po-save-btn" onclick="poSave()" title="Ctrl+S">
                    Save {{ $purchaseOrder ? 'Changes' : 'Draft' }}
                </button>
                <div class="po-summary-note">
                    <span class="po-dirty" id="po-dirty">Unsaved changes · </span>
                    Saved as <strong>Draft</strong>. Submit for approval from the detail page once prices are complete.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="poPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Take Items from Goods Request</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="po-picker-toolbar mb-2">
                    <select id="po-picker-project" style="width:100%">
                        <option value="">All destinations</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" id="po-picker-search" placeholder="Search part number / item name..." autocomplete="off">
                </div>
                <div class="po-picker-selbar">
                    <label class="d-flex align-items-center gap-2 mb-0" style="cursor:pointer">
                        <input type="checkbox" id="po-picker-check-all"> <span>Select all shown</span>
                    </label>
                    <span class="po-picker-selcount" id="po-picker-selcount">No items selected</span>
                    <button type="button" class="btn btn-link btn-sm p-0" id="po-picker-clear" style="display:none">Clear selection</button>
                </div>
                <small class="d-block mb-2" style="color:var(--text-muted)">Click a row to select it, Shift+click to select a range. Only Approved items with remaining need; free stock is informational only — still purchasable.</small>
                <div id="po-picker-body">
                    <div class="config-card-empty"><span class="config-spinner"></span>Loading...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="po-picker-apply" onclick="poApplyPicked()" disabled>
                    <span id="po-picker-apply-label">Add Selected</span>
                </button>
            </div>
        </div>
    </div>
</div>

@endpush

@section('scripts')
<script>
let poKeySeq = 0;
let poPickerInstance = null;
let poPickerPool = [];

const poFetchAvailableItemsUrl = '{{ route("purchase-order.fetch-available-items") }}';
const poStoreUrl = '{{ route("purchase-order.store") }}';
const poUpdateUrl = '{{ $purchaseOrder ? route("purchase-order.update", $purchaseOrder->id) : "" }}';
const poShowUrl = '{{ $purchaseOrder ? route("purchase-order.show", $purchaseOrder->id) : "" }}';
const poCurrencies = @json($currencies ?? []);
const poInitialItems = @json($items ?? []);
const poCurrentNumber = @json($purchaseOrder?->po_number);
const poTypeSuffixes = @json(\App\Models\PurchaseOrder::TYPE_SUFFIXES);
const poTypeLabels = @json(\App\Models\PurchaseOrder::TYPE_LABELS);
const poSaveLabel = 'Save {{ $purchaseOrder ? 'Changes' : 'Draft' }}';
let poPickerProjectsLoaded = false;


function poNewKey() {
    return 'row-' + (++poKeySeq);
}

function poEsc(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Format angka satu standar (gaya US) — lihat partials/number-input.
function poToRaw(str) {
    return String(str == null ? '' : str).replace(/,/g, '');
}

function poFmt(v) { return Num.format(v, 2); }

function poBaseCurrency() {
    return poCurrencies.length ? poCurrencies[0].name : 'IDR';
}

// ── Item PO: hanya dari item Permintaan Barang (dikelompokkan per tujuan) ──

// Satu PO hanya boleh satu mata uang: mata uang item pertama (selain $exceptRow).
function poActiveCurrency($exceptRow) {
    var active = null;
    $('#po-items-body tr.po-line').each(function() {
        if ($exceptRow && this === $exceptRow[0]) return;
        active = $(this).attr('data-currency') || null;
        if (active) return false;
    });
    return active;
}

function poItemCurrencies() {
    var set = {};
    $('#po-items-body tr.po-line').each(function() {
        var c = $(this).attr('data-currency');
        if (c) set[c] = true;
    });
    return Object.keys(set);
}

function poSyncEmpty() {
    $('#po-items-empty').toggle($('#po-items-body tr.po-line').length === 0);
    $('#po-items-table').toggle($('#po-items-body tr.po-line').length > 0);
}

function poGroupKey(label) {
    return 'grp-' + String(label || '').toLowerCase().replace(/[^a-z0-9]+/g, '-');
}

function poEnsureGroup(label) {
    var key = poGroupKey(label);
    if (!$('#po-items-body tr.po-group[data-group="' + key + '"]').length) {
        $('#po-items-body').append('<tr class="po-group" data-group="' + key + '"><td colspan="5"><strong>' + poEsc(label) + '</strong>'
            + ' <span class="po-hint ms-1 po-group-count"></span></td><td class="text-end po-group-total"></td><td></td></tr>');
    }
    return key;
}

function poRowHtml(item, groupKey) {
    var max = parseInt(item.remaining_qty != null ? item.remaining_qty : item.qty, 10) || 0;
    var html = '<tr class="po-line" data-group="' + groupKey + '" data-goods-request-item-id="' + item.goods_request_item_id + '"';
    html += ' data-currency="' + poEsc(item.currency || poBaseCurrency()) + '" data-max="' + max + '">';
    html += '<td class="text-center po-row-num"></td>';
    html += '<td style="font-size:12.5px"><div class="fw-semibold" style="color:var(--text-primary)">' + poEsc(item.part_number || '—') + '</div>';
    if (item.product_name) html += '<div class="po-hint">' + poEsc(item.product_name) + '</div>';
    html += '<span class="po-source">' + poEsc(item.source_label || ('Request ' + (item.request_number || ''))) + '</span></td>';
    html += '<td><div class="form-control form-control-sm po-desc" contenteditable="true" data-placeholder="Description for supplier">' + (item.description || '') + '</div></td>';
    html += '<td><input type="text" inputmode="numeric" class="form-control form-control-sm po-qty text-center" value="' + (item.qty != null ? item.qty : max) + '" aria-label="Qty">';
    html += '<div class="po-qty-unit">' + poEsc(item.unit || '') + ' · max ' + max + '</div></td>';
    html += '<td><div class="input-group input-group-sm"><span class="input-group-text" style="font-size:11px" title="Follows the Goods Request currency">' + poEsc(item.currency || poBaseCurrency()) + '</span>'
        + '<input type="text" inputmode="decimal" class="form-control text-end po-price" placeholder="0" aria-label="Unit price" value="' + (item.price != null && item.price !== '' ? poFmtInput(item.price) : '') + '"></div></td>';
    html += '<td class="text-end po-amount">0</td>';
    html += '<td class="text-center" style="padding-top:12px"><button type="button" class="btn-icon text-danger" title="Remove" aria-label="Remove item" onclick="poRemoveRow(this)"><i class="fa fa-xmark"></i></button></td>';
    html += '</tr>';
    return html;
}

function poFmtInput(v) {
    var n = Number(v);
    return isFinite(n) ? Num.format(n, 2) : '';
}

function poAddRow(item) {
    var groupKey = poEnsureGroup(item.project_name || 'Stock / Internal');
    var $last = $('#po-items-body tr[data-group="' + groupKey + '"]').last();
    $(poRowHtml(item, groupKey)).insertAfter($last);
    poRenumber();
    poSyncEmpty();
    poRecalc();
}

function poRemoveRow(btn) {
    poMarkDirty();
    var $row = $(btn).closest('tr');
    var groupKey = $row.attr('data-group');
    $row.remove();
    if (!$('#po-items-body tr.po-line[data-group="' + groupKey + '"]').length) {
        $('#po-items-body tr.po-group[data-group="' + groupKey + '"]').remove();
    }
    poRenumber();
    poSyncEmpty();
    poRecalc();
}

function poRenumber() {
    $('#po-items-body tr.po-line').each(function(i) { $(this).find('.po-row-num').text(i + 1); });
}

function poRecalc() {
    var grand = 0, groups = {}, counts = {}, noPrice = 0;
    $('#po-items-body tr.po-line').each(function() {
        var $row = $(this);
        var qty = parseFloat(poToRaw($row.find('.po-qty').val())) || 0;
        var rawPrice = poToRaw($row.find('.po-price').val()).trim();
        var price = parseFloat(rawPrice) || 0;
        var amount = qty * price;
        var g = $row.attr('data-group');
        grand += amount;
        groups[g] = (groups[g] || 0) + amount;
        counts[g] = (counts[g] || 0) + 1;
        if (rawPrice === '') noPrice++;
        $row.find('.po-price').toggleClass('po-price-missing', rawPrice === '');
        $row.find('.po-amount').text(rawPrice === '' ? '—' : poFmt(amount));
    });
    $('#po-items-body tr.po-group').each(function() {
        var g = $(this).attr('data-group');
        $(this).find('.po-group-count').text((counts[g] || 0) + (counts[g] === 1 ? ' item' : ' items'));
        $(this).find('.po-group-total').text(poFmt(groups[g] || 0));
    });
    $('#po-grand-total').text(poFmt(grand));
    $('#po-items-noprice').toggle(noPrice > 0).text(noPrice + ' without price');
    poUpdateSummary(grand, noPrice);
}

function poUpdateSummary(grand, noPrice) {
    var $items = $('#po-items-body tr.po-line');
    var groups = $('#po-items-body tr.po-group').length;

    $('#po-items-count').text($items.length + ($items.length === 1 ? ' item' : ' items'));
    $('#po-sum-items').text($items.length);
    $('#po-sum-projects').text(groups);
    var currencies = poItemCurrencies();
    $('#po-sum-currency').text(currencies.length ? currencies.join(', ') : '—').toggleClass('text-danger', currencies.length > 1);
    $('#po-items-currency').toggle(currencies.length > 0).text('Currency ' + currencies.join(', '));

    // Sama dengan PurchaseOrder::totalsFrom(): Diskon -> Sub Total 1 -> PPN -> Total.
    var discPct = parseFloat($('#po-discount-percent').val()) || 0;
    var ppnPct = parseFloat($('#po-ppn-percent').val()) || 0;
    var discount = Math.round(grand * discPct) / 100;
    var ppn = Math.round((grand - discount) * ppnPct) / 100;
    var code = currencies.length === 1 ? currencies[0] : (currencies.length ? '' : poBaseCurrency());
    $('.po-currency-code').text(code ? '(' + code + ')' : '');
    $('#po-sum-subtotal').text(code + ' ' + poFmt(grand));
    $('#po-sum-discount-row').toggle(discPct > 0);
    $('#po-sum-discount-label').text('Discount ' + poFmt(discPct) + '%');
    $('#po-sum-discount').text('- ' + code + ' ' + poFmt(discount));
    $('#po-sum-ppn-row').toggle(ppnPct > 0);
    $('#po-sum-ppn-label').text('VAT ' + poFmt(ppnPct) + '%');
    $('#po-sum-ppn').text(code + ' ' + poFmt(ppn));
    $('#po-sum-total').text(code + ' ' + poFmt(grand - discount + ppn));
    poSyncRate(currencies.length === 1 ? currencies[0] : null, grand - discount + ppn);
    poChecklist({ items: $items.length > 0, prices: $items.length > 0 && !noPrice, noPrice: noPrice || 0 });
}

// Checklist kesiapan di ringkasan (harga lengkap baru wajib saat diajukan).
function poChecklist(state) {
    state = state || {};
    var supplier = !!$('#po-supplier-name').val();
    var set = function(key, ok, warn, text) {
        var $li = $('#po-checklist li[data-check="' + key + '"]');
        $li.toggleClass('is-ok', ok).toggleClass('is-warn', !ok && !!warn);
        $li.find('.po-check-state').text(ok ? 'Done' : (warn ? 'Incomplete' : 'Pending'));
        if (text) $li.find('.po-check-text').contents().filter(function() { return this.nodeType === 3; }).first().replaceWith(text);
    };
    set('supplier', supplier);
    if (state.items !== undefined) {
        set('items', state.items);
        set('prices', state.prices, state.noPrice > 0, state.noPrice > 0 ? state.noPrice + ' item(s) without price ' : 'All prices filled ');
    }
}

// Kurs PO selalu tampil. Valas: bisa diisi (default kurs master); mata uang dasar / belum ada item: terkunci 1.
let poRateForeign = $('#po-exchange-rate').val() || '';
function poSyncRate(code, total) {
    var base = poBaseCurrency();
    var foreign = !!code && code !== base;
    var master = (poCurrencies.filter(function(c) { return c.name === code; })[0] || {}).rate;
    var $input = $('#po-exchange-rate');
    $('.po-rate-cur').text(code || base);
    $('.po-rate-base').text(base);
    $('.po-rate-required').toggle(foreign);
    if (foreign) {
        if ($input.prop('readonly')) $input.prop('readonly', false).css('background', '').val(poRateForeign);
        if (!$input.val() && master) $input.val(Num.format(master, 2));
        poRateForeign = $input.val();
    } else {
        $input.prop('readonly', true).css('background', 'var(--bg-soft, #f3f4f6)').val('1').removeClass('is-invalid');
    }
    $('#po-rate-hint').text(foreign
        ? 'Used to value the goods when they are received.'
        : (code ? base + ' PO — no conversion needed.' : 'Follows the item currency; add items first.'));
    var rate = Num.val($('#po-exchange-rate'));
    $('#po-sum-base-row').toggle(foreign);
    $('#po-sum-rate').text(foreign ? '@ ' + Num.format(rate, 2) : '');
    if (foreign) $('#po-sum-base').text(base + ' ' + poFmt(Math.round(total * rate * 100) / 100));
}

function poUpdateSupplierSummary() {
    var name = $('#po-supplier-name').val();
    $('#po-sum-supplier').text(name || '—').attr('title', name || '');
    poRenderContact();
    poChecklist();
}

// Ringkasan kontak yang dicetak di PO (diubah lewat "Edit contact").
function poRenderContact() {
    [['address', 'No address'], ['phone', 'No phone'], ['fax', 'No fax']].forEach(function(f) {
        var v = $.trim($('#po-supplier-' + f[0]).val());
        $('#po-contact-' + f[0]).text(v || f[1]).parent().toggleClass('is-empty', !v);
    });
}

function poSignSummary() {
    var names = $('.po-sign').map(function() { return $.trim(this.value); }).get().filter(Boolean);
    $('#po-sign-summary').text(names.length ? names.length + '/3 filled · ' + names.join(', ') : 'Not filled');
}

let poDirty = false;
let poSaving = false;
function poMarkDirty() { poDirty = true; $('#po-dirty').show(); }

function poCollectItems() {
    return $('#po-items-body tr.po-line').map(function() {
        var $row = $(this);
        var price = poToRaw($row.find('.po-price').val()).trim();
        return {
            goods_request_item_id: $row.attr('data-goods-request-item-id'),
            description: $row.find('.po-desc').html(),
            qty: poToRaw($row.find('.po-qty').val()),
            price: price
        };
    }).get();
}

// ── Picker: Take from Goods Request (stok tersedia hanya informasi) ──

function poBuildPickerGroupsHtml(groups) {
    var html = '';
    var poActive = poActiveCurrency();

    groups.forEach(function(gr, gIdx) {
        var group = 'g' + gIdx;
        var title = (gr.project_number ? gr.project_number + ' — ' : '') + gr.project_name + ' · ' + (gr.division_name || 'No Division') + ' · Request ' + gr.request_number;
        var sourceLabel = 'Request ' + gr.request_number + ' / ' + (gr.division_name || '-');

        html += '<div class="po-picker-group mb-3" data-group="' + group + '">';
        html += '<div class="po-picker-group-head" data-group="' + group + '" title="Click to select / deselect all items in this group">';
        html += '<span class="d-flex align-items-center gap-2">';
        html += '<input type="checkbox" class="po-picker-check-group" data-group="' + group + '" aria-label="Select group">';
        html += '<strong style="font-size:13px">' + poEsc(title) + '</strong>';
        html += '</span>';
        html += '<span class="badge po-picker-group-count" style="background:var(--accent-soft);color:var(--accent);font-size:11px">' + gr.items.length + ' item</span>';
        html += '</div>';
        html += '<div class="table-responsive"><table class="table table-custom align-middle mb-0"><thead><tr>';
        html += '<th style="width:36px"></th><th style="width:18%">Product</th><th>Description</th>';
        html += '<th class="text-center" style="width:90px">Remaining Need</th><th style="width:80px">Currency</th><th style="width:150px">Free Stock</th>';
        html += '</tr></thead><tbody>';

        gr.items.forEach(function(it) {
            var poolIdx = poPickerPool.length;
            poPickerPool.push($.extend({}, it, { source_label: sourceLabel }));
            var otherCurrency = poActive && it.currency && it.currency !== poActive;
            var desc = $('<div>').html(it.description || '').text();

            html += '<tr class="po-picker-item-row" data-group="' + group + '" data-currency="' + poEsc(it.currency || '') + '" data-search="' + poEsc(((it.part_number || '') + ' ' + (it.product_name || '') + ' ' + desc).toLowerCase()) + '">';
            html += '<td><input type="checkbox" class="po-picker-check" data-idx="' + poolIdx + '" data-currency="' + poEsc(it.currency || '') + '"'
                + (otherCurrency ? ' data-locked="1" disabled title="Currency differs from this PO"' : '') + ' aria-label="Select item"></td>';
            html += '<td style="font-size:12.5px"><div class="fw-semibold">' + poEsc(it.part_number || '—') + '</div><div class="po-hint">' + poEsc(it.product_name || '') + '</div></td>';
            html += '<td style="font-size:12.5px">' + poEsc(desc.length > 120 ? desc.slice(0, 120) + '…' : desc) + '</td>';
            html += '<td class="text-center fw-semibold">' + it.remaining_qty + ' <span class="po-hint">/ ' + it.requested_qty + ' ' + poEsc(it.unit || '') + '</span></td>';
            html += '<td>' + poEsc(it.currency || '') + (otherCurrency ? '<div class="po-hint">Separate PO</div>' : '') + '</td>';
            html += '<td style="font-size:12px">' + (it.free_stock > 0
                ? '<span class="badge" style="background:#dcfce7;color:#166534">Available ' + it.free_stock + '</span><div class="po-hint">Can be fulfilled from stock — still purchasable</div>'
                : '<span class="po-hint">No free stock</span>') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div></div>';
    });

    return html;
}

// ── Picker: pemilihan per baris ──
let poPickerLastIdx = null; // untuk Shift+klik (rentang)

// Mata uang yang "mengunci" pilihan: mata uang PO (item yang sudah ada) atau item pertama yang dicentang.
function poPickerLockCurrency() {
    return poActiveCurrency() || ($('.po-picker-check:checked').first().data('currency') || null);
}

// Baris yang bisa dipilih: tampil (lolos pencarian) & tidak terkunci mata uang.
function poPickerSelectable($scope) {
    return ($scope || $('#po-picker-body')).find('.po-picker-item-row:visible').filter(function() {
        return !$(this).find('.po-picker-check').prop('disabled');
    }).find('.po-picker-check');
}

function poPickerSync() {
    var lock = poPickerLockCurrency();

    $('.po-picker-item-row').each(function() {
        var $row = $(this);
        var $cb = $row.find('.po-picker-check');
        var currency = $row.data('currency') || '';
        var locked = !!$cb.data('locked');
        var mismatch = !locked && lock && currency && currency !== lock && !$cb.prop('checked');
        if (!locked) {
            $cb.prop('disabled', !!mismatch);
            $row.attr('title', mismatch ? 'Different currency (' + currency + ') from selected items (' + lock + ') — use a separate PO' : '');
        }
        $row.toggleClass('is-disabled', $cb.prop('disabled'));
        $row.toggleClass('is-selected', $cb.prop('checked'));
    });

    // Checkbox grup: centang / sebagian (indeterminate) / kosong + jumlah terpilih
    $('.po-picker-group').each(function() {
        var $group = $(this);
        var $all = $group.find('.po-picker-check').not(':disabled');
        var selected = $group.find('.po-picker-check:checked').length;
        var total = $group.find('.po-picker-check').length;
        var $gcb = $group.find('.po-picker-check-group');
        $gcb.prop('disabled', $all.length === 0)
            .prop('checked', $all.length > 0 && $all.filter(':checked').length === $all.length)
            .prop('indeterminate', selected > 0 && $all.filter(':checked').length < $all.length);
        $group.find('.po-picker-group-count').text(selected ? selected + ' / ' + total + ' selected' : total + ' item');
    });

    var $selectable = poPickerSelectable();
    var visibleChecked = $selectable.filter(':checked').length;
    $('#po-picker-check-all')
        .prop('disabled', $selectable.length === 0)
        .prop('checked', $selectable.length > 0 && visibleChecked === $selectable.length)
        .prop('indeterminate', visibleChecked > 0 && visibleChecked < $selectable.length);

    var count = $('.po-picker-check:checked').length;
    var hidden = $('.po-picker-item-row:hidden .po-picker-check:checked').length;
    $('#po-picker-selcount').toggleClass('has', count > 0).text(count
        ? count + ' item selected' + (lock ? ' · ' + lock : '') + (hidden ? ' (' + hidden + ' hidden by search)' : '')
        : 'No items selected');
    $('#po-picker-clear').toggle(count > 0);
    $('#po-picker-apply').prop('disabled', count === 0);
    $('#po-picker-apply-label').text(count ? 'Add ' + count + ' Item' + (count > 1 ? 's' : '') : 'Add Selected');
}

// Klik di mana saja pada baris = toggle; Shift+klik = pilih rentang.
$(document).on('click', '.po-picker-item-row', function(e) {
    var $cb = $(this).find('.po-picker-check');
    if ($cb.prop('disabled')) return;
    var onCheckbox = $(e.target).is('.po-picker-check');
    var checked = onCheckbox ? $cb.prop('checked') : !$cb.prop('checked');

    var $rows = $('.po-picker-item-row:visible');
    var idx = $rows.index(this);
    if (e.shiftKey && poPickerLastIdx !== null && poPickerLastIdx !== idx) {
        var from = Math.min(poPickerLastIdx, idx), to = Math.max(poPickerLastIdx, idx);
        $rows.slice(from, to + 1).find('.po-picker-check').not(':disabled').prop('checked', checked);
        window.getSelection && window.getSelection().removeAllRanges();
    } else {
        $cb.prop('checked', checked);
    }
    poPickerLastIdx = idx;
    poPickerSync();
});

// Header grup (atau checkbox-nya) = pilih / batal semua item tampil di grup.
$(document).on('click', '.po-picker-group-head', function(e) {
    var $gcb = $(this).find('.po-picker-check-group');
    if ($gcb.prop('disabled')) return;
    var checked = $(e.target).is('.po-picker-check-group') ? $gcb.prop('checked') : !$gcb.prop('checked');
    var $group = $(this).closest('.po-picker-group');
    if (checked) {
        // Pilih satu per satu agar kunci mata uang ikut berlaku
        poPickerSelectable($group).each(function() {
            if (!$(this).prop('disabled')) { $(this).prop('checked', true); poPickerSync(); }
        });
    } else {
        $group.find('.po-picker-item-row:visible .po-picker-check').prop('checked', false);
    }
    poPickerSync();
});

function poCurrentGoodsRequestItemIds() {
    return $('#po-items-body tr.po-line').map(function() { return $(this).attr('data-goods-request-item-id'); }).get();
}

function poLoadPicker() {
    if (!poPickerInstance) {
        poPickerInstance = new bootstrap.Modal(document.getElementById('poPickerModal'));
        $('#po-picker-project').select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $('#poPickerModal'),
            placeholder: 'All destinations',
            allowClear: true
        }).on('change', poFetchPickerItems);
    }
    poPickerProjectsLoaded = false;
    $('#po-picker-project').val('').trigger('change.select2');
    poPickerInstance.show();
    poFetchPickerItems();
}

function poFetchPickerItems() {
    $('#po-picker-body').html('<div class="config-card-empty"><span class="config-spinner"></span>Loading...</div>');
    $('#po-picker-search').val('');
    poPickerPool = [];
    poPickerLastIdx = null;
    poPickerSync();
    $.get(poFetchAvailableItemsUrl, {
        exclude_goods_request_item_ids: poCurrentGoodsRequestItemIds(),
        project_id: $('#po-picker-project').val() || ''
    })
        .done(function(res) {
            if (!poPickerProjectsLoaded) {
                poPickerProjectsLoaded = true;
                var opts = '<option value="">All destinations</option><option value="internal">Stock / Internal</option>';
                (res.projects || []).forEach(function(p) {
                    opts += '<option value="' + p.id + '">' + poEsc(p.text) + '</option>';
                });
                $('#po-picker-project').html(opts).trigger('change.select2');
            }
            var groups = res.data || [];
            if (groups.length === 0) {
                $('#po-picker-body').html('<div class="config-card-empty">No Approved Goods Request items with remaining need.</div>');
                poPickerSync();
                return;
            }
            $('#po-picker-body').html(poBuildPickerGroupsHtml(groups)
                + '<div class="config-card-empty" id="po-picker-noresult" style="display:none">No items match the search.</div>');
            poPickerSync();
        })
        .fail(function() {
            $('#po-picker-body').html('<div class="config-card-empty">Failed to load items.</div>');
        });
}

function poApplyPicked() {
    var chosen = {};
    $('.po-picker-check:checked').each(function() {
        var c = $(this).data('currency');
        if (c) chosen[c] = true;
    });
    var chosenCurrencies = Object.keys(chosen);
    var active = poActiveCurrency();
    if (chosenCurrencies.length > 1 || (active && chosenCurrencies.length && chosenCurrencies[0] !== active)) {
        toastr.error('A PO may only use one currency. Selected items: ' + chosenCurrencies.join(', ')
            + (active ? ' (this PO ' + active + ')' : '') + '. Select items with the same currency.');
        return;
    }

    var picked = 0;
    $('.po-picker-check:checked').each(function() {
        var it = poPickerPool[$(this).data('idx')];
        if (!it) return;
        poAddRow(it);
        picked++;
    });
    if (picked === 0) {
        toastr.error('Select at least one item.');
        return;
    }
    poMarkDirty();
    toastr.success(picked + ' items added. Qty filled from remaining need — adjust if PO is split.');
    if (poPickerInstance) poPickerInstance.hide();
}

function poSelectedType() {
    return $('input[name="po-type"]:checked').val() || 'manufacture';
}

function poUpdateNumberPreview() {
    var suffix = poTypeSuffixes[poSelectedType()] || '';
    if (poCurrentNumber) {
        var number = String(poCurrentNumber).replace(/-[A-Z]+$/, '') + suffix;
        $('#po-number-preview').html('No. PO: <strong>' + poEsc(number) + '</strong>'
            + (number !== poCurrentNumber ? ' <span>(previously ' + poEsc(poCurrentNumber) + ')</span>' : ''));
    } else {
        $('#po-number-preview').html('PO no. is auto-generated on save' + (suffix ? ', ending with <strong>' + suffix + '</strong>' : '') + '.');
    }

    $('#po-sum-type').text(poTypeLabels[poSelectedType()] || '—');
    if (poCurrentNumber) {
        $('#po-sum-number').removeClass('pending').text(String(poCurrentNumber).replace(/-[A-Z]+$/, '') + suffix);
    } else {
        $('#po-sum-number').addClass('pending').text('Auto on save');
    }
}

function poFail(message, $el) {
    toastr.error(message);
    if ($el && $el.length) {
        $el.addClass('is-invalid');
        $el[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function() { $el.trigger('focus'); }, 300);
    }
    return false;
}

function poValidate() {
    $('#po-items-body .is-invalid').removeClass('is-invalid');

    if (!$('#po-supplier-name').val()) {
        toastr.error('Supplier is required.');
        $('#po-supplier-select').select2('open');
        return false;
    }

    var discPct = parseFloat($('#po-discount-percent').val()) || 0;
    var ppnPct = parseFloat($('#po-ppn-percent').val()) || 0;
    if (discPct < 0 || discPct > 100) return poFail('Discount must be 0 to 100%.', $('#po-discount-percent'));
    if (ppnPct < 0 || ppnPct > 100) return poFail('VAT must be 0 to 100%.', $('#po-ppn-percent'));

    var currencies = poItemCurrencies();
    if (currencies.length > 1) {
        return poFail('All items in a PO must use the same currency (found ' + currencies.join(', ') + ').');
    }
    if (!$('#po-exchange-rate').prop('readonly') && !(Num.val($('#po-exchange-rate')) > 0)) {
        return poFail('Enter the exchange rate for ' + currencies[0] + '.', $('#po-exchange-rate'));
    }

    var ok = true;
    var n = 0;
    $('#po-items-body tr.po-line').each(function() {
        var $row = $(this);
        n++;
        var qty = parseInt(poToRaw($row.find('.po-qty').val()), 10) || 0;
        var max = parseInt($row.attr('data-max'), 10) || 0;
        if (qty < 1) {
            ok = poFail('Qty on row ' + n + ' must be at least 1.', $row.find('.po-qty'));
            return false;
        }
        if (qty > max) {
            ok = poFail('Qty on row ' + n + ' exceeds remaining need (' + max + ').', $row.find('.po-qty'));
            return false;
        }
    });

    return ok;
}

function poSetSaving(saving) {
    $('#po-save-btn').prop('disabled', saving).html(saving
        ? '<span class="spinner-border spinner-border-sm me-1"></span> Saving...'
        : poSaveLabel);
}

function poSave() {
    if (poSaving || !poValidate()) return;
    var supplierName = $('#po-supplier-name').val();

    var payload = {
        supplier_name: supplierName,
        supplier_id: $('#po-supplier-id').val() || null,
        supplier_address: $('#po-supplier-address').val(),
        supplier_phone: $('#po-supplier-phone').val(),
        supplier_fax: $('#po-supplier-fax').val(),
        supplier_attn: $('#po-supplier-attn').val(),
        po_type: poSelectedType(),
        date: $('#po-date').val(),
        terms: $('#po-terms').val(),
        payment_term_days: $('#po-payment-term-days').val(),
        delivery: $('#po-delivery').val(),
        your_ref: $('#po-your-ref').val(),
        discount_percent: $('#po-discount-percent').val(),
        ppn_percent: $('#po-ppn-percent').val(),
        exchange_rate: $('#po-exchange-rate').prop('readonly') ? '' : Num.val($('#po-exchange-rate')),
        notes: $('#po-notes').val(),
        request_by_name: $('#po-request-by').val(),
        finance_name: $('#po-finance-name').val(),
        accounting_name: $('#po-accounting-name').val(),
        items: poCollectItems(),
        _token: '{{ csrf_token() }}'
    };

    var url = poUpdateUrl || poStoreUrl;
    var method = poUpdateUrl ? 'PUT' : 'POST';

    poSaving = true;
    poSetSaving(true);
    $.ajax({ url: url, method: method, data: payload })
        .done(function(res) {
            poDirty = false;
            toastr.success(res.message || 'Saved.');
            setTimeout(function() {
                window.location.href = poUpdateUrl ? poShowUrl : '{{ route("purchase-order.index") }}';
            }, 800);
        })
        .fail(function(xhr) {
            poSaving = false;
            poSetSaving(false);
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to save.');
        });
}

$(document).ready(function() {
    (poInitialItems || []).forEach(function(it) { poAddRow(it); });
    poSyncEmpty();
    poRecalc();
    poRenderContact();
    poSignSummary();
    if ($('.po-sign').filter(function() { return $.trim(this.value) !== ''; }).length) {
        $('#po-sign-body').addClass('show');
        $('[data-bs-target="#po-sign-body"]').attr('aria-expanded', 'true');
    }

    // Kontak & penandatangan: ringkasan ikut berubah.
    $(document).on('input', '#po-supplier-address, #po-supplier-phone, #po-supplier-fax', poRenderContact);
    $(document).on('input', '.po-sign', poSignSummary);
    // Perubahan belum disimpan.
    $(document).on('input change', '.card-body-custom input, .card-body-custom textarea, .po-desc, input[name="po-type"]', poMarkDirty);
    // Enter di qty / harga: pindah ke field berikutnya (qty -> harga -> qty baris berikutnya).
    $(document).on('keydown', '#po-items-body .po-qty, #po-items-body .po-price', function(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var $fields = $('#po-items-body .po-qty, #po-items-body .po-price');
        var $next = $fields.eq($fields.index(this) + 1);
        if ($next.length) $next.focus().select();
    });
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 's') { e.preventDefault(); poSave(); }
    });
    window.addEventListener('beforeunload', function(e) {
        if (poDirty && !poSaving) { e.preventDefault(); e.returnValue = ''; }
    });

    $('#po-picker-check-all').on('change', function() {
        if ($(this).is(':checked')) {
            poPickerSelectable().each(function() {
                if (!$(this).prop('disabled')) { $(this).prop('checked', true); poPickerSync(); }
            });
        } else {
            $('.po-picker-item-row:visible .po-picker-check').prop('checked', false);
        }
        poPickerSync();
    });

    $('#po-picker-clear').on('click', function() {
        $('.po-picker-check').prop('checked', false);
        poPickerLastIdx = null;
        poPickerSync();
    });

    $(document).on('input change', '.po-qty, .po-price', poRecalc);
    $(document).on('input', '.po-qty', function() {
        var clean = $(this).val().replace(/\D/g, '');
        if (clean !== $(this).val()) $(this).val(clean);
    });

    $(document).on('input', '.po-percent-input', function() { poRecalc(); });
    $(document).on('input', '#po-exchange-rate', function() { $(this).removeClass('is-invalid'); poRecalc(); });
    $(document).on('input', '.po-desc, .po-qty', function() { $(this).removeClass('is-invalid'); });
    poUpdateSupplierSummary();
    $(document).on('change', 'input[name="po-type"]', poUpdateNumberPreview);
    poUpdateNumberPreview();

    $('#po-picker-search').on('input', function() {
        var q = $.trim($(this).val()).toLowerCase();
        $('.po-picker-item-row').each(function() {
            $(this).toggle(!q || String($(this).data('search')).indexOf(q) !== -1);
        });
        $('.po-picker-group').each(function() {
            $(this).toggle($(this).find('.po-picker-item-row:visible').length > 0 || !q);
        });
        $('#po-picker-noresult').toggle(!!q && $('.po-picker-item-row:visible').length === 0);
        poPickerLastIdx = null;
        poPickerSync();
    });

    $('#po-supplier-select').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search supplier...',
        allowClear: true,
        width: '100%',
        minimumInputLength: 0,
        ajax: {
            url: '{{ route("supplier.search") }}',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term || '' }; },
            processResults: function(res) { return { results: res.results }; }
        }
    }).on('select2:select', function(e) {
        var s = e.params.data;
        $('#po-supplier-id').val(s.id);
        $('#po-supplier-name').val(s.text);
        $('#po-supplier-address').val(s.address || '');
        $('#po-supplier-phone').val(s.phone || '');
        $('#po-supplier-fax').val(s.fax || '');
        $('#po-supplier-attn').val(s.attn_name || '');
        if (s.payment_term_days != null) $('#po-payment-term-days').val(s.payment_term_days);
        poMarkDirty();
        poUpdateSupplierSummary();
    }).on('select2:clear', function() {
        $('#po-supplier-id').val('');
        $('#po-supplier-name').val('');
        poMarkDirty();
        poUpdateSupplierSummary();
    });
});
</script>
@endsection
