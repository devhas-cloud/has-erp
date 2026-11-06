@extends('layouts.app')

@section('title', $project ? 'Edit Project' : 'Create Project')
@section('page-title', $project ? 'Edit Project' : 'Create Project')

@section('styles')
<style>
    .pj-readonly { background: var(--accent-subtle, #f8fafc) !important; }
    .pj-ach-table td, .pj-ach-table th { vertical-align: middle; }

    .pj-hint { font-size: 12px; color: var(--text-muted); }
    .pj-sticky { position: sticky; top: 84px; }

    .pj-source {
        display: flex; gap: 12px; align-items: flex-start;
        background: var(--accent-subtle); border: 1px dashed var(--card-border);
        border-radius: var(--radius-sm); padding: 10px 12px; font-size: 13px;
    }
    .pj-source i { color: var(--accent); margin-top: 3px; }
    .pj-source strong { color: var(--text-primary); }

    .pj-segment { display: flex; gap: 6px; }
    .pj-segment input { display: none; }
    .pj-segment label {
        flex: 1; text-align: center; cursor: pointer; margin: 0;
        padding: 7px 10px; font-size: 13px; font-weight: 600;
        border: 1px solid var(--card-border); border-radius: var(--radius-sm);
        color: var(--text-muted); transition: all .15s var(--ease, ease);
    }
    .pj-segment input:checked + label { background: var(--accent-soft); border-color: var(--accent); color: var(--accent); }

    .pj-amount-box { padding: 12px 14px; border-radius: var(--radius-sm); background: var(--accent-soft); }
    .pj-amount-box .lbl { font-size: 12px; color: var(--text-muted); }
    .pj-amount-box input {
        border: 0; background: transparent !important; padding: 0; box-shadow: none !important;
        font-size: 20px; font-weight: 700; color: var(--accent); text-align: left !important;
    }
    .pj-amount-box input:not([readonly]) { border-bottom: 1px dashed var(--accent); }
    .pj-amount-box .prefix { font-size: 20px; font-weight: 700; color: var(--accent); margin-right: 4px; }
    .pj-amount-box.secondary { background: transparent; border: 1px solid var(--card-border); }
    .pj-amount-box.secondary input, .pj-amount-box.secondary .prefix { font-size: 16px; color: var(--text-primary); }

    /* Rincian nilai project dari Estimasi PL */
    .pj-breakdown { width: 100%; font-size: 13px; border-collapse: collapse; }
    .pj-breakdown td { padding: 5px 2px; vertical-align: middle; }
    .pj-breakdown td.k { color: var(--text-muted); }
    .pj-breakdown td.v { text-align: right; font-weight: 600; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .pj-breakdown td.op { width: 16px; color: var(--text-muted); font-weight: 700; text-align: center; }
    .pj-breakdown tr.minus td.v { color: var(--danger); font-weight: 500; }
    .pj-breakdown tr.sub td { border-top: 1px dashed var(--card-border); }
    .pj-breakdown tr.sub td.k { color: var(--text-primary); font-weight: 600; }
    .pj-breakdown tr.start td.k { color: var(--text-primary); font-weight: 600; }
    .pj-breakdown td .tag {
        display: block; font-size: 10.5px; font-weight: 500; color: var(--text-muted);
    }
    .pj-breakdown tr.final td { background: var(--accent-soft); color: var(--accent); font-weight: 700; padding-top: 9px; padding-bottom: 9px; }
    .pj-breakdown tr.final td:first-child { border-radius: var(--radius-sm) 0 0 var(--radius-sm); padding-left: 8px; }
    .pj-breakdown tr.final td:last-child { border-radius: 0 var(--radius-sm) var(--radius-sm) 0; padding-right: 8px; font-size: 15px; }
    .pj-breakdown-source { font-size: 11.5px; color: var(--text-muted); margin-top: 8px; display: flex; justify-content: space-between; gap: 8px; }

    .pj-split-item { display: flex; align-items: center; gap: 10px; padding: 7px 0; }
    .pj-split-item + .pj-split-item { border-top: 1px dashed var(--card-border); }
    .pj-split-avatar {
        width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: var(--accent-soft); color: var(--accent); font-size: 11px; font-weight: 700;
    }
    .pj-split-name { flex: 1; font-size: 13px; font-weight: 600; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pj-split-pct { font-size: 11px; color: var(--text-muted); font-weight: 400; }
    .pj-split-amount { font-size: 13px; font-weight: 700; font-variant-numeric: tabular-nums; }
    .pj-empty { font-size: 13px; color: var(--text-muted); text-align: center; padding: 10px 0; }

    .pj-file-current {
        display: flex; align-items: center; gap: 8px; margin-top: 8px;
        font-size: 13px; padding: 6px 10px; border: 1px solid var(--card-border); border-radius: var(--radius-sm);
    }
    .pj-file-current i { color: var(--danger); }

    /* Kontrak / perjanjian */
    .pj-doc-list { display: flex; flex-direction: column; gap: 6px; }
    .pj-doc-row {
        display: flex; align-items: center; gap: 10px; padding: 7px 10px;
        border: 1px solid var(--card-border); border-radius: var(--radius-sm); font-size: 13px;
    }
    .pj-doc-row > i { color: var(--danger); font-size: 16px; }
    .pj-doc-row .meta { font-size: 11.5px; color: var(--text-muted); }
    .pj-doc-row .name { font-weight: 600; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pj-doc-row.pending { border-style: dashed; background: var(--accent-subtle); }

    /* Timeline penagihan */
    .pj-term-progress { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    .pj-term-progress .pj-bar-track { flex: 1; height: 8px; border-radius: 999px; background: var(--card-border); overflow: hidden; }
    .pj-term-progress .pj-bar-fill { height: 100%; border-radius: 999px; background: #f59e0b; transition: width .2s; }
    .pj-term-progress.full .pj-bar-fill { background: #16a34a; }
    .pj-term-progress.over .pj-bar-fill { background: var(--danger); }
    .pj-term-total { font-size: 12.5px; font-weight: 600; white-space: nowrap; }
    #pj-term-table td { vertical-align: top; }
    #pj-term-table .pj-term-num { padding-top: 14px; color: var(--text-muted); font-weight: 600; }
    #pj-term-table .pj-term-amount { padding-top: 14px; font-size: 12.5px; color: var(--text-muted); white-space: nowrap; font-variant-numeric: tabular-nums; }
    #pj-term-table tr.locked { background: var(--accent-subtle); }
    .pj-term-lock { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
    .pj-template-chip {
        display: inline-flex; align-items: center; gap: 4px; cursor: pointer;
        padding: 3px 10px; border-radius: 999px; font-size: 12px;
        border: 1px dashed var(--card-border); color: var(--text-muted); background: transparent;
    }
    .pj-template-chip:hover { border-color: var(--accent); color: var(--accent); }

    /* Nilai kontrak project manual */
    .pj-mv-field .form-label { margin-bottom: 4px; }
    .pj-mv-field .input-group > .btn { min-width: 42px; font-weight: 600; font-size: 13px; }
    .pj-mv-field .input-group > .btn-check:checked + .btn { background: var(--accent-soft); border-color: var(--accent); color: var(--accent); }
    .pj-mv-calc { font-size: 12px; color: var(--text-muted); margin-top: 4px; min-height: 18px; font-variant-numeric: tabular-nums; }
    .pj-mv-calc strong { color: var(--text-primary); font-weight: 600; }
    .pj-mv-calc.minus strong { color: var(--danger); }
    .pj-mv-subtotal .input-group-text, .pj-mv-subtotal input { font-size: 17px; font-weight: 700; }
    .pj-mv-locked { display: flex; gap: 8px; align-items: center; font-size: 12.5px; padding: 8px 12px; border-radius: var(--radius-sm); background: #fef3c7; color: #92400e; margin-bottom: 12px; }
</style>
@endsection

@section('content')
@php
    $initialQuotationId = old('quotation_id', $project?->quotation_id ?? $preselected?->id);
    $initialCompanyId = old('company_id', $project?->company_id ?? $preselected?->opportunity?->account_companies_id);
    $initialCompanyName = $project?->company?->account_name ?? $preselected?->opportunity?->accountCompany?->account_name;
    $isRetention = (bool) $project?->is_retention;
    $type = $project?->type_of_achievement ?? 'project';
@endphp
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $project ? 'Edit Project' : 'Create Project' }}</h1>
        <p class="page-header-sub">
            @if($project)
                {{ $project->project_number }} &middot; {{ $project->project_name }}
            @else
                Select a quotation, complete customer PO data, then assign sales.
            @endif
        </p>
    </div>
    <div class="page-header-actions">
        @if($project && ($canUpdate ?? false))
        <a href="{{ route('project.achievements', $project->id) }}" class="btn btn-soft btn-sm">
            <i class="fa fa-list-check me-1"></i> Device Details
        </a>
        @endif
        <a href="{{ $project ? route('project.show', $project->id) : route('project.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        {{-- 1. Sumber quotation & informasi project --}}
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Project Information</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Quotation</label>
                        <select id="pj-quotation-select" class="form-select" style="width:100%" @if($project) disabled @endif>
                            <option value="">— Without Quotation (legacy / manual data) —</option>
                            @foreach($quotations as $q)
                                <option value="{{ $q->id }}" @selected((int) $initialQuotationId === (int) $q->id)>
                                    {{ $q->quotation_number ?? '#'.$q->id }} — {{ $q->to_name }} ({{ $q->status }})
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" id="pj-quotation-id" value="{{ $initialQuotationId }}">
                        <div class="pj-hint mt-1">
                            @if($project)
                                <i class="fa fa-lock me-1"></i> Quotation cannot be changed after the project is created.
                            @else
                                Only Approved/Finished quotations that already have a Profit Estimate. Leave empty for legacy/manual data.
                            @endif
                        </div>
                        <div id="pj-quotation-info" class="pj-source mt-2" style="display:none"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Project Name <span class="text-danger">*</span></label>
                        <input type="text" id="pj-project-name" class="form-control" value="{{ $project?->project_name }}" maxlength="255" placeholder="Auto-filled from opportunity name when selecting a quotation">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Project Description</label>
                        <textarea id="pj-project-description" class="form-control" rows="3" placeholder="Scope, location, important notes...">{{ $project?->project_description }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. PO Customer --}}
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Customer PO</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Company <span class="text-danger">*</span></label>
                        <select id="pj-company" class="form-select" style="width:100%"></select>
                        <input type="hidden" id="pj-company-id" value="{{ $initialCompanyId }}">
                        <div class="pj-hint mt-1" id="pj-company-hint">Auto-filled from quotation opportunity; manual only when there is no quotation.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Customer PO No. <span class="text-danger">*</span></label>
                        <input type="text" id="pj-po-number" class="form-control" value="{{ $project?->po_number_company }}" maxlength="100" placeholder="Example: 016/PK/DSDA/2025">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">PO Date <span class="text-danger">*</span></label>
                        <input type="date" id="pj-po-date" class="form-control" value="{{ $project?->po_date?->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">PO Received Date <span class="text-danger">*</span></label>
                        <input type="date" id="pj-po-date-received" class="form-control" value="{{ $project?->po_date_received?->format('Y-m-d') ?? now()->toDateString() }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">PO Due Date <span class="text-danger">*</span></label>
                        <input type="date" id="pj-po-due-date" class="form-control" value="{{ $project?->po_due_date?->format('Y-m-d') }}">
                        <div class="pj-hint mt-1" id="pj-due-hint"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">PO Document <span class="text-danger" id="pj-po-file-required" @if($project) style="display:none" @endif>*</span></label>
                        <input type="file" id="pj-po-file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="pj-hint mt-1" id="pj-po-file-hint">
                            PDF, JPG, or PNG, max 10MB.
                            @if($project?->hasPoDocument()) Choose a new file only to replace the current one. @endif
                        </div>
                        @if($project?->hasPoDocument())
                        <a href="{{ route('project.po', $project->id) }}" target="_blank" class="pj-file-current text-decoration-none">
                            <i class="fa fa-file-pdf"></i>
                            <span>{{ $project->po_document_name }}</span>
                            <span class="ms-auto pj-hint">View <i class="fa fa-arrow-up-right-from-square ms-1" style="color:inherit"></i></span>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>



        {{-- 3. Kontrak / perjanjian --}}
        <div class="card-custom fade-in mt-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-file-signature me-2" style="color:var(--accent)"></i>Supporting Documents <span class="pj-hint ms-1">optional, can be more than one</span></span>
            </div>
            <div class="card-body-custom">
                <div class="pj-doc-list mb-2" id="pj-agreement-list">
                    @foreach($agreements as $doc)
                        <div class="pj-doc-row" data-doc-id="{{ $doc['id'] }}">
                            <i class="fa {{ $doc['icon'] }}"></i>
                            <div style="min-width:0;flex:1">
                                <div class="name">{{ $doc['file_name'] }}</div>
                                <div class="meta">{{ $doc['notes'] ? $doc['notes'].' · ' : '' }}{{ $doc['size'] }} · {{ $doc['uploaded_by'] ?? '—' }}, {{ $doc['uploaded_at'] }}</div>
                            </div>
                            <a href="{{ $doc['view_url'] }}" target="_blank" class="btn-icon" title="View"><i class="fa fa-eye"></i></a>
                            @if(($canDelete ?? false) && $doc['delete_url'])
                                <button type="button" class="btn-icon text-danger" title="Delete" onclick="pjDeleteAgreement(this, '{{ $doc['delete_url'] }}')"><i class="fa fa-trash"></i></button>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="row g-2">
                    <div class="col-md-7">
                        <input type="file" id="pj-agreement-files" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple>
                        <div class="pj-hint mt-1">PDF, JPG, or PNG, max 10MB per file. You can select multiple files at once.</div>
                    </div>
                    <div class="col-md-5">
                        <input type="text" id="pj-agreement-notes" class="form-control" maxlength="255" placeholder="Notes, e.g. Master contract / Addendum 1">
                    </div>
                </div>
                <div class="pj-doc-list mt-2" id="pj-agreement-pending"></div>
            </div>
        </div>

        {{-- Nilai kontrak project manual (tanpa quotation): skema sama dengan quotation --}}
        @php
            $mv = $manualValues ?? null;
            $mvFmt = fn ($v) => $v === null ? '' : \App\Models\Project::formatNumber($v);
            $discMode = $mv && ! $mv['discount_percent'] && $mv['discount_amount'] > 0 ? 'amt' : 'pct';
            $discVal = $discMode === 'amt' ? $mvFmt($mv['discount_amount']) : ($mv && $mv['discount_percent'] ? $mvFmt($mv['discount_percent']) : '');
            $ppnMode = $mv && ! $mv['ppn_percent'] && $mv['ppn_amount'] > 0 ? 'amt' : 'pct';
            $ppnVal = $ppnMode === 'amt' ? $mvFmt($mv['ppn_amount']) : ($mv ? ($mv['ppn_percent'] ? $mvFmt($mv['ppn_percent']) : '') : '11');
        @endphp
        <div class="card-custom fade-in mt-3" id="pj-manual-card" @if($initialQuotationId) style="display:none" @endif>
            <div class="card-header-custom">
                <span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Contract Value <span class="pj-hint ms-1">project without quotation</span></span>
            </div>
            <div class="card-body-custom">
                @if($netLocked ?? false)
                    <div class="pj-mv-locked"><i class="fa fa-lock"></i> Value locked because a term invoice already exists. Cancel that invoice first if the project value changes.</div>
                @endif
                <div class="row g-3">
                    <div class="col-12 pj-mv-field pj-mv-subtotal">
                        <label class="form-label" for="pj-m-subtotal">Subtotal <span class="text-danger">*</span> <span class="pj-hint">PO value before discount &amp; VAT</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" inputmode="decimal" autocomplete="off" id="pj-m-subtotal" class="form-control pj-number pj-manual-input text-end" value="{{ $mv ? $mvFmt($mv['subtotal']) : '' }}" placeholder="0">
                        </div>
                    </div>

                    <div class="col-md-4 pj-mv-field">
                        <label class="form-label" for="pj-m-disc-val">Discount <span class="pj-hint">optional</span></label>
                        <div class="input-group">
                            <input type="radio" class="btn-check pj-manual-input" name="pj-m-disc-mode" id="pj-m-disc-mode-pct" value="pct" @checked($discMode === 'pct')>
                            <label class="btn btn-outline-secondary" for="pj-m-disc-mode-pct" title="Discount in percent">%</label>
                            <input type="radio" class="btn-check pj-manual-input" name="pj-m-disc-mode" id="pj-m-disc-mode-amt" value="amt" @checked($discMode === 'amt')>
                            <label class="btn btn-outline-secondary" for="pj-m-disc-mode-amt" title="Discount in Rupiah">Rp</label>
                            <input type="text" inputmode="decimal" autocomplete="off" id="pj-m-disc-val" class="form-control pj-number pj-manual-input text-end" value="{{ $discVal }}" placeholder="0">
                        </div>
                        <div class="pj-mv-calc minus" id="pj-m-disc-calc"></div>
                    </div>

                    <div class="col-md-4 pj-mv-field">
                        <label class="form-label" for="pj-m-ppn-val">VAT</label>
                        <div class="input-group">
                            <input type="radio" class="btn-check pj-manual-input" name="pj-m-ppn-mode" id="pj-m-ppn-mode-pct" value="pct" @checked($ppnMode === 'pct')>
                            <label class="btn btn-outline-secondary" for="pj-m-ppn-mode-pct" title="VAT in percent">%</label>
                            <input type="radio" class="btn-check pj-manual-input" name="pj-m-ppn-mode" id="pj-m-ppn-mode-amt" value="amt" @checked($ppnMode === 'amt')>
                            <label class="btn btn-outline-secondary" for="pj-m-ppn-mode-amt" title="VAT in Rupiah">Rp</label>
                            <input type="text" inputmode="decimal" autocomplete="off" id="pj-m-ppn-val" class="form-control pj-number pj-manual-input text-end" value="{{ $ppnVal }}" placeholder="0">
                        </div>
                        <div class="pj-mv-calc" id="pj-m-ppn-calc"></div>
                    </div>

                    <div class="col-md-4 pj-mv-field">
                        <label class="form-label" for="pj-m-ref-pct">Referral <span class="pj-hint">optional</span></label>
                        <div class="input-group">
                            <input type="text" inputmode="decimal" autocomplete="off" id="pj-m-ref-pct" class="form-control pj-number pj-manual-input text-end" value="{{ $mv && $mv['referral_percent'] ? $mvFmt($mv['referral_percent']) : '' }}" placeholder="0">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="pj-mv-calc minus" id="pj-m-ref-calc"></div>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="pj-hint">
                            <i class="fa fa-circle-info me-1"></i>Value after discount becomes the billing timeline basis;
                            Net Sale (after referral) becomes the sales achievement basis. See the summary on the right.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. Timeline penagihan --}}
        <div class="card-custom fade-in mt-3">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-timeline me-2" style="color:var(--accent)"></i>Billing Timeline <span class="pj-hint ms-1">optional, can be completed later</span></span>
                <span class="pj-term-total" id="pj-term-total-label">0% scheduled</span>
            </div>
            <div class="card-body-custom">
                <div class="pj-hint mb-2">
                    Split the value after discount, before VAT (<strong id="pj-billing-base-label">Rp 0</strong>) into several payment stages.
                    <span id="pj-billing-base-source"></span>
                    Invoices are later created directly from each row, and the percentage follows that row.
                </div>
                <div class="pj-term-progress" id="pj-term-progress">
                    <div class="pj-bar-track"><div class="pj-bar-fill" id="pj-term-bar" style="width:0%"></div></div>
                    <span class="pj-term-total" id="pj-term-remaining"></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom mb-0" id="pj-term-table">
                        <thead>
                            <tr>
                                <th style="width:36px" class="text-center">#</th>
                                <th style="width:24%">Payment Name <span class="text-danger">*</span></th>
                                <th style="width:96px">% <span class="text-danger">*</span></th>
                                <th style="width:15%">≈ Amount</th>
                                <th>Notes</th>
                                <th style="width:150px">Billing Target</th>
                                <th style="width:40px"></th>
                            </tr>
                        </thead>
                        <tbody id="pj-term-body"></tbody>
                    </table>
                </div>
                <div class="pj-empty" id="pj-term-empty">
                    <i class="fa-solid fa-timeline me-1"></i> No billing timeline yet. Use the examples below or add rows yourself.
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <button type="button" class="btn btn-soft btn-sm" onclick="pjAddTerm()"><i class="fa fa-plus me-1"></i> Add Payment</button>
                    <span class="pj-hint ms-1">Quick examples:</span>
                    <button type="button" class="pj-template-chip" onclick="pjApplyTermTemplate('dp2')">DP · Term 1 · Term 2</button>
                    <button type="button" class="pj-template-chip" onclick="pjApplyTermTemplate('dp1')">DP · Settlement</button>
                    <button type="button" class="pj-template-chip" onclick="pjApplyTermTemplate('full')">Full Payment</button>
                </div>
            </div>
        </div>

        {{-- 5. Masa garansi --}}
        <div class="card-custom fade-in mt-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-shield-halved me-2" style="color:var(--accent)"></i>Warranty Period <span class="pj-hint ms-1">optional, fill after implementation / BAST</span></span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3 align-items-start">
                    <div class="col-md-4">
                        <label class="form-label" for="pj-warranty-start">Warranty Start</label>
                        <input type="date" id="pj-warranty-start" class="form-control" value="{{ $project?->warranty?->start_date?->format('Y-m-d') }}">
                        <div class="pj-hint mt-1">E.g. installation completion / BAST date.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="pj-warranty-end">Warranty End</label>
                        <input type="date" id="pj-warranty-end" class="form-control" value="{{ $project?->warranty?->end_date?->format('Y-m-d') }}">
                        <div class="pj-hint mt-1" id="pj-warranty-hint"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Quick duration</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="pj-template-chip" onclick="pjWarrantyMonths(6)">6 months</button>
                            <button type="button" class="pj-template-chip" onclick="pjWarrantyMonths(12)">1 year</button>
                            <button type="button" class="pj-template-chip" onclick="pjWarrantyMonths(24)">2 years</button>
                            <button type="button" class="pj-template-chip" onclick="pjClearWarranty()" title="Clear warranty period"><i class="fa fa-xmark"></i> Clear</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Ringkasan: nilai, klasifikasi, sales --}}
    <div class="col-lg-4">
        <div class="pj-sticky">
            <div class="card-custom fade-in mb-3">
                <div class="card-header-custom">
                    <span><i class="fa-solid fa-coins me-2" style="color:var(--accent)"></i>Value &amp; Classification</span>
                </div>
                <div class="card-body-custom">
                    {{-- Project dari quotation: rincian langkah demi langkah dari Estimasi PL --}}
                    <div id="pj-value-breakdown" class="mb-3" style="display:none">
                        <table class="pj-breakdown">
                            <tbody id="pj-breakdown-body"></tbody>
                        </table>
                        <div class="pj-breakdown-source">
                            <span><i class="fa fa-lock me-1"></i><span id="pj-breakdown-note">Read-only from PL Estimate.</span></span>
                            <a href="#" target="_blank" id="pj-breakdown-link" class="text-decoration-none">View PL Estimate <i class="fa fa-arrow-up-right-from-square ms-1" style="font-size:10px"></i></a>
                        </div>
                    </div>

                    {{-- Project tanpa quotation: ringkasan live dari kartu "Nilai Kontrak" (diisi di kolom kiri). --}}
                    <div id="pj-value-manual" class="mb-3">
                        <table class="pj-breakdown">
                            <tbody id="pj-manual-summary"></tbody>
                        </table>
                        <div class="pj-breakdown-source">
                            <span id="pj-manual-summary-note"><i class="fa fa-pen me-1"></i>Fill in the Contract Value card.</span>
                            <a href="#pj-manual-card" class="text-decoration-none" onclick="pjFocusManual(); return false;">Edit <i class="fa fa-arrow-left ms-1" style="font-size:10px"></i></a>
                        </div>
                        <input type="hidden" id="pj-total-sale" value="{{ $project ? \App\Models\Project::formatNumber($project->total_sale_amount) : '' }}">
                        <input type="hidden" id="pj-net-sale" value="{{ $project ? \App\Models\Project::formatNumber($project->net_sale_amount) : '' }}">
                    </div>

                    <label class="form-label">Retention <span class="text-danger">*</span></label>
                    <div class="pj-segment mb-3">
                        <input type="radio" name="pj-retention-opt" id="pj-retention-0" value="0" @checked(! $isRetention)>
                        <label for="pj-retention-0">No</label>
                        <input type="radio" name="pj-retention-opt" id="pj-retention-1" value="1" @checked($isRetention)>
                        <label for="pj-retention-1">Yes</label>
                    </div>
                    <input type="hidden" id="pj-is-retention" value="{{ $isRetention ? 1 : 0 }}">

                    <label class="form-label">Achievement Type <span class="text-danger">*</span></label>
                    <div class="pj-segment">
                        <input type="radio" name="pj-type-opt" id="pj-type-project" value="project" @checked($type === 'project')>
                        <label for="pj-type-project"><i class="fa-solid fa-diagram-project me-1"></i> Project</label>
                        <input type="radio" name="pj-type-opt" id="pj-type-retail" value="retail" @checked($type === 'retail')>
                        <label for="pj-type-retail"><i class="fa-solid fa-store me-1"></i> Retail</label>
                    </div>
                    <input type="hidden" id="pj-type" value="{{ $type }}">
                </div>
            </div>

            <div class="card-custom fade-in mb-3">
                <div class="card-header-custom">
                    <span><i class="fa-solid fa-user-tie me-2" style="color:var(--accent)"></i>Sales &amp; Achievement</span>
                </div>
                <div class="card-body-custom">
                    <label class="form-label">Sales <span class="text-danger">*</span></label>
                    <select id="pj-sales" class="form-select" style="width:100%" multiple></select>
                    <div class="pj-hint mt-1 mb-2">Net sale is split evenly; rounding remainder goes to the last sales.</div>
                    <div id="pj-sales-preview"><div class="pj-empty">No sales selected.</div></div>
                </div>
            </div>

            <button type="button" class="btn btn-primary w-100" id="pj-save-btn" onclick="pjSave()">
                <i class="fa fa-save me-1"></i> Save {{ $project ? 'Changes' : 'Project' }}
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
@include('project.partials._number-format')
const pjIsEdit = {{ $project ? 'true' : 'false' }};
const pjProjectId = {{ $project?->id ?? 'null' }};
const pjHasPoDocument = {{ $project?->hasPoDocument() ? 'true' : 'false' }};
const pjStoreUrl = '{{ route("project.store") }}';
const pjUpdateUrl = '{{ $project ? route("project.update", $project->id) : "" }}';
const pjShowUrl = '{{ route("project.show", "__ID__") }}';
const pjIndexUrl = '{{ route("project.index") }}';
const pjFetchQuotationUrl = '{{ route("project.fetch-quotation") }}';
const pjSearchCompanyUrl = '{{ route("project.search-companies") }}';
const pjSearchSalesUrl = '{{ route("project.search-sales") }}';
const pjMaxFileSize = 10 * 1024 * 1024;

const pjInitialSales = @json($salesOptions);
const pjInitialCompanyId = @json($initialCompanyId);
const pjInitialCompanyName = @json($initialCompanyName);
const pjInitialTerms = @json($billingTerms);
const pjNetLocked = {{ ($netLocked ?? false) ? 'true' : 'false' }};
let pjBillingBase = {{ (float) $billingBase }};
let pjTermSeq = 0;

// ── Timeline penagihan ──

const pjTermTemplates = {
    dp2: [
        { name: 'DP', percent: 30, description: 'Billed for processing' },
        { name: 'Term 1', percent: 50, description: 'Billed when goods are about to be shipped' },
        { name: 'Term 2', percent: 20, description: 'Billed during installation' }
    ],
    dp1: [
        { name: 'DP', percent: 30, description: 'Billed for processing' },
        { name: 'Settlement', percent: 70, description: 'Billed after work is completed' }
    ],
    full: [
        { name: 'Full Payment', percent: 100, description: 'Billed after work is completed' }
    ]
};

function pjTermRowHtml(term) {
    term = term || {};
    // Termin yang sudah ditagih: persen terkunci & tidak bisa dihapus.
    var locked = !!term.billed;
    var key = 'term-' + (++pjTermSeq);
    var html = '<tr data-key="' + key + '" data-id="' + (term.id || '') + '"' + (locked ? ' class="locked" data-locked="1"' : '') + '>';
    html += '<td class="text-center pj-term-num"></td>';
    html += '<td><input type="text" class="form-control form-control-sm pj-term-name" maxlength="100" placeholder="e.g. DP, Term 1" value="' + pjEsc(term.name || '') + '">';
    if (locked) {
        html += '<div class="pj-term-lock"><i class="fa fa-lock me-1"></i>Already billed: ' + pjEsc(term.invoice_number || '') + '</div>';
    }
    html += '</td>';
    html += '<td><div class="input-group input-group-sm"><input type="number" class="form-control pj-term-percent text-end" min="0" max="100" step="any" placeholder="0" value="' + (term.percent != null ? term.percent : '') + '"' + (locked ? ' readonly title="Locked because an invoice already exists"' : '') + '></div></td>';
    html += '<td class="pj-term-amount">—</td>';
    html += '<td><input type="text" class="form-control form-control-sm pj-term-desc" maxlength="255" placeholder="e.g. Billed when goods are shipped" value="' + pjEsc(term.description || '') + '"></td>';
    html += '<td><input type="date" class="form-control form-control-sm pj-term-date" value="' + pjEsc(term.target_date || '') + '"></td>';
    html += '<td class="text-center">' + (locked
        ? '<span class="btn-icon" style="opacity:.35;cursor:not-allowed" title="Cannot be deleted because an invoice already exists"><i class="fa fa-lock"></i></span>'
        : '<button type="button" class="btn-icon text-danger" title="Delete row" onclick="pjRemoveTerm(this)"><i class="fa fa-trash"></i></button>') + '</td>';
    html += '</tr>';
    return html;
}

function pjAddTerm(term) {
    var $row = $(pjTermRowHtml(term));
    $('#pj-term-body').append($row);
    pjRecalcTerms();
    if (!term) $row.find('.pj-term-name').trigger('focus');
}

function pjRemoveTerm(btn) {
    $(btn).closest('tr').remove();
    pjRecalcTerms();
}

function pjApplyTermTemplate(name) {
    if ($('#pj-term-body tr[data-locked]').length) {
        toastr.warning('Timeline already has a billed term, quick examples cannot be used. Add rows manually.');
        return;
    }
    var apply = function() {
        $('#pj-term-body').empty();
        pjTermTemplates[name].forEach(function(t) { pjAddTerm($.extend({}, t)); });
        toastr.info('Example applied. Adjust names, percentages, and notes as needed.');
    };
    if (!$('#pj-term-body tr').length) { apply(); return; }
    Swal.fire({
        title: 'Replace timeline?',
        text: 'Filled timeline rows will be replaced with the example.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Replace',
        cancelButtonText: 'Cancel'
    }).then(function(r) { if (r.isConfirmed) apply(); });
}

function pjCurrentBillingBase() {
    // Tanpa quotation: dasar penagihan = subtotal − diskon (sebelum PPN) dari rincian manual.
    return $('#pj-quotation-id').val() ? pjBillingBase : pjManualValues().base;
}

// ── Rincian nilai project manual (sama dengan Project::computeManualValues di server) ──
function pjNum(selector) {
    return parseFloat(pjToRaw($(selector).val())) || 0;
}

function pjMode(name) {
    return $('input[name="' + name + '"]:checked').val() || 'pct';
}

function pjManualValues() {
    var subtotal = pjCents($('#pj-m-subtotal').val());
    var discMode = pjMode('pj-m-disc-mode');
    var discPct = discMode === 'pct' ? pjNum('#pj-m-disc-val') : 0;
    var discount = discMode === 'pct' ? Math.round(subtotal * discPct / 100) : pjCents($('#pj-m-disc-val').val());
    var base = subtotal - discount;
    var ppnMode = pjMode('pj-m-ppn-mode');
    var ppnPct = ppnMode === 'pct' ? pjNum('#pj-m-ppn-val') : 0;
    var ppn = ppnMode === 'pct' ? Math.round(base * ppnPct / 100) : pjCents($('#pj-m-ppn-val').val());
    var refPct = pjNum('#pj-m-ref-pct');
    var referral = refPct > 0 ? Math.round(base * refPct / 100) : 0;

    return {
        subtotal: subtotal / 100, discMode: discMode, discountPct: discPct, discount: discount / 100, base: base / 100,
        ppnMode: ppnMode, ppnPct: ppnPct, ppn: ppn / 100, total: (base + ppn) / 100,
        refPct: refPct, referral: referral / 100, net: (base - referral) / 100
    };
}

function pjRecalcManual() {
    if ($('#pj-quotation-id').val()) return;
    var m = pjManualValues();

    // Keterangan hasil hitung di bawah tiap input.
    $('#pj-m-disc-calc').html(m.discount > 0
        ? (m.discMode === 'pct' ? '= <strong>− ' + pjFormatMoney(m.discount) + '</strong>' : '≈ ' + pjFormatNumber(m.subtotal > 0 ? Math.round(m.discount / m.subtotal * 10000) / 100 : 0) + '% of subtotal')
        : 'No discount');
    $('#pj-m-ppn-calc').html(m.ppn > 0
        ? (m.ppnMode === 'pct' ? '= <strong>' + pjFormatMoney(m.ppn) + '</strong> of value after discount' : '≈ ' + pjFormatNumber(m.base > 0 ? Math.round(m.ppn / m.base * 10000) / 100 : 0) + '% of value after discount')
        : 'No VAT');
    $('#pj-m-ref-calc').html(m.referral > 0 ? '= <strong>− ' + pjFormatMoney(m.referral) + '</strong>' : 'No referral');
    $('#pj-m-disc-val').attr('placeholder', m.discMode === 'pct' ? '0 %' : 'Rp 0');
    $('#pj-m-ppn-val').attr('placeholder', m.ppnMode === 'pct' ? '0 %' : 'Rp 0');

    // Ringkasan sidebar: urutan sama dengan rincian Estimasi PL.
    var row = function(cls, op, label, value, tag) {
        return '<tr class="' + cls + '"><td class="op">' + op + '</td><td class="k">' + label
            + (tag ? '<span class="tag">' + tag + '</span>' : '') + '</td><td class="v">' + value + '</td></tr>';
    };
    var html = '';
    html += row('start', '', 'Subtotal', pjFormatMoney(m.subtotal), 'Before discount & VAT');
    if (m.discount > 0) html += row('minus', '−', 'Discount' + (m.discountPct > 0 ? ' ' + pjFormatNumber(m.discountPct) + '%' : ''), '− ' + pjFormatMoney(m.discount));
    html += row('sub', '=', 'Value After Discount', pjFormatMoney(m.base));
    html += row('', '+', 'VAT' + (m.ppnPct > 0 ? ' ' + pjFormatNumber(m.ppnPct) + '%' : ''), pjFormatMoney(m.ppn));
    html += row('sub', '=', 'Total Sale Amount', pjFormatMoney(m.total), 'Including VAT');
    if (m.referral > 0) html += row('minus', '−', 'Referral ' + pjFormatNumber(m.refPct) + '% <span class="pj-hint">of value after discount</span>', '− ' + pjFormatMoney(m.referral));
    html += row('final', '=', 'Net Sale Amount', pjFormatMoney(m.net), 'Basis for sales achievement');
    $('#pj-manual-summary').html(html);
    $('#pj-manual-summary-note').html(pjNetLocked
        ? '<i class="fa fa-lock me-1"></i>Locked: a term invoice already exists.'
        : '<i class="fa fa-pen me-1"></i>Fill in the Contract Value card.');

    $('#pj-total-sale').val(pjFormatStored(m.total));
    $('#pj-net-sale').val(pjFormatStored(m.net));
    pjRenderSalesSplit();
    pjRecalcTerms();
}

// Ganti mode % <-> Rp: isi nilai setara agar hasil tidak melompat.
let pjModeBefore = null;
function pjSwitchMode(prefix) {
    var before = pjModeBefore || pjManualValues();
    pjModeBefore = null;
    var mode = pjMode('pj-m-' + prefix + '-mode');
    var $val = $('#pj-m-' + prefix + '-val');
    if (prefix === 'disc') {
        $val.val(mode === 'pct'
            ? (before.subtotal > 0 && before.discount > 0 ? pjFormatStored(Math.round(before.discount / before.subtotal * 10000) / 100) : '')
            : (before.discount > 0 ? pjFormatStored(before.discount) : ''));
    } else {
        $val.val(mode === 'pct'
            ? (before.base > 0 && before.ppn > 0 ? pjFormatStored(Math.round(before.ppn / before.base * 10000) / 100) : '')
            : (before.ppn > 0 ? pjFormatStored(before.ppn) : ''));
    }
    pjRecalcManual();
    $val.trigger('focus');
}

function pjFocusManual() {
    var $card = $('#pj-manual-card');
    $('html, body').animate({ scrollTop: Math.max(($card.offset() || { top: 0 }).top - 100, 0) }, 250);
    $('#pj-m-subtotal').trigger('focus');
}

function pjRecalcTerms() {
    var base = pjCurrentBillingBase();
    var total = 0;
    var $rows = $('#pj-term-body tr');

    $rows.each(function(i) {
        var $row = $(this);
        var pct = parseFloat($row.find('.pj-term-percent').val()) || 0;
        total += pct;
        $row.find('.pj-term-num').text(i + 1);
        $row.find('.pj-term-amount').text(base > 0 && pct > 0 ? pjFormatMoney(Math.round(base * pct) / 100) : '—');
    });

    total = Math.round(total * 100) / 100;
    var remaining = Math.round((100 - total) * 100) / 100;
    var $progress = $('#pj-term-progress');

    $('#pj-term-empty').toggle($rows.length === 0);
    $('#pj-term-table').toggle($rows.length > 0);
    $progress.toggle($rows.length > 0).toggleClass('full', total === 100).toggleClass('over', total > 100);
    $('#pj-term-bar').css('width', Math.min(total, 100) + '%');
    $('#pj-billing-base-label').text(pjFormatMoney(base));
    $('#pj-billing-base-source').text($('#pj-quotation-id').val()
        ? '(quotation subtotal − discount; VAT does not affect)'
        : '(subtotal − discount; VAT does not affect)');
    $('#pj-term-total-label').text(pjFormatNumber(total) + '% scheduled');

    var text;
    if (total > 100) {
        text = '<span class="text-danger"><i class="fa fa-triangle-exclamation me-1"></i>Over by ' + pjFormatNumber(-remaining) + '%</span>';
    } else if (total === 100) {
        text = '<span style="color:#16a34a"><i class="fa fa-circle-check me-1"></i>Complete 100%</span>';
    } else {
        text = '<span style="color:#b45309">' + pjFormatNumber(remaining) + '% remaining not yet scheduled</span>';
    }
    $('#pj-term-remaining').html(text);
}

function pjCollectTerms() {
    return $('#pj-term-body tr').map(function() {
        var $row = $(this);
        return {
            id: $row.attr('data-id') || '',
            name: $.trim($row.find('.pj-term-name').val()),
            percent: $row.find('.pj-term-percent').val(),
            description: $.trim($row.find('.pj-term-desc').val()),
            target_date: $row.find('.pj-term-date').val()
        };
    }).get();
}

// ── Kontrak / perjanjian ──

function pjRenderPendingAgreements() {
    var files = document.getElementById('pj-agreement-files').files;
    var html = '';
    for (var i = 0; i < files.length; i++) {
        var kb = files[i].size / 1024;
        html += '<div class="pj-doc-row pending"><i class="fa fa-cloud-arrow-up" style="color:var(--accent)"></i>'
            + '<div style="min-width:0;flex:1"><div class="name">' + pjEsc(files[i].name) + '</div>'
            + '<div class="meta">' + (kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : Math.max(1, Math.round(kb)) + ' KB') + ' · will be uploaded on save</div></div></div>';
    }
    $('#pj-agreement-pending').html(html);
}

function pjDeleteAgreement(btn, url) {
    Swal.fire({
        title: 'Delete document?',
        text: 'This contract/agreement file will be permanently deleted.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $.ajax({ url: url, method: 'DELETE', data: { _token: '{{ csrf_token() }}' } })
            .done(function(res) {
                $(btn).closest('.pj-doc-row').remove();
                toastr.success(res.message || 'Document deleted.');
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to delete document.');
            });
    });
}

function pjEsc(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function pjInitials(name) {
    var parts = $.trim(String(name || '')).split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
}

function pjInitQuotation() {
    $('#pj-quotation-select').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Search quotation number or customer...'
    });
}

function pjInitCompany() {
    $('#pj-company').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Search company...',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: pjSearchCompanyUrl,
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term || '' }; },
            processResults: function(data) { return data; }
        }
    });

    if (pjInitialCompanyId) {
        $('#pj-company').append(new Option(pjInitialCompanyName || ('Company #' + pjInitialCompanyId), pjInitialCompanyId, true, true));
    }

    $('#pj-company').on('select2:select', function(e) {
        $('#pj-company-id').val(e.params.data.id);
        pjClearInvalid(this);
    }).on('select2:clear', function() {
        $('#pj-company-id').val('');
    });
}

function pjSetCompany(id, name, locked) {
    if (id) {
        var option = $('#pj-company option[value="' + id + '"]');
        if (!option.length) {
            $('#pj-company').append(new Option(name || ('Company #' + id), id, true, true));
        }
        $('#pj-company').val(String(id)).trigger('change.select2');
        $('#pj-company-id').val(id);
    }
    $('#pj-company').prop('disabled', !!locked).trigger('change.select2');
    $('#pj-company-hint').html(locked
        ? '<i class="fa fa-lock me-1"></i> Locked to the quotation opportunity.'
        : 'Select a company manually (without quotation).');
}

function pjLockAmounts(locked) {
    // Project dari quotation: kartu Nilai Kontrak disembunyikan (nilai dari Estimasi PL).
    // Project manual: input terkunci bila sudah ada invoice termin.
    $('#pj-manual-card').toggle(!locked);
    $('#pj-m-subtotal, #pj-m-disc-val, #pj-m-ppn-val, #pj-m-ref-pct').prop('readonly', pjNetLocked).toggleClass('pj-readonly', pjNetLocked);
    $('input[name="pj-m-disc-mode"], input[name="pj-m-ppn-mode"]').prop('disabled', pjNetLocked);
    if (!locked) pjRecalcManual();
}

// Rincian nilai project dari Estimasi PL (urutan sama dengan halaman Estimasi PL).
function pjRenderBreakdown(d) {
    var b = d && d.breakdown;
    if (!b) {
        $('#pj-value-breakdown').hide();
        $('#pj-value-manual').show();
        return;
    }

    var row = function(cls, op, label, value, tag) {
        return '<tr class="' + cls + '"><td class="op">' + op + '</td><td class="k">' + label
            + (tag ? '<span class="tag">' + tag + '</span>' : '') + '</td><td class="v">' + value + '</td></tr>';
    };
    var minus = function(v) { return '− ' + pjFormatMoney(v); };

    var html = '';
    html += row('start', '', 'Initial Value', pjFormatMoney(b.nilai_awal), 'Total Sale Amount (quotation value)');
    html += row('minus', '−', 'VAT', minus(b.ppn_amount));
    html += row('sub', '=', 'Sub Total I', pjFormatMoney(b.subtotal_1));
    html += row('minus', '−', 'Discount', minus(b.discount_amount));
    html += row('sub', '=', 'Sub Total II', pjFormatMoney(b.subtotal_2));
    html += row('minus', '−', 'Referral ' + pjFormatNumber(b.referral_percent) + '%', minus(b.referral_amount));
    html += row('final', '=', 'Real Project Value', pjFormatMoney(b.real_project_value), 'Net Sale Amount');

    $('#pj-breakdown-body').html(html);
    $('#pj-breakdown-link').attr('href', b.profit_estimate_url || '#').toggle(!!b.profit_estimate_url);
    $('#pj-breakdown-note').text('Read-only from PL Estimate ' + (d.quotation_number || '') + '.');
    $('#pj-value-manual').hide();
    $('#pj-value-breakdown').show();
}

function pjRenderQuotationInfo(d) {
    if (!d) { $('#pj-quotation-info').hide().empty(); return; }

    var html = '<i class="fa-solid fa-link"></i><div>';
    html += '<strong>' + pjEsc(d.quotation_number || '—') + '</strong>';
    html += '<div>' + pjEsc(d.company_name || '—') + '</div>';
    html += '<div>Total sale ' + pjFormatMoney(d.total_sale_amount) + ' &middot; Net sale ' + pjFormatMoney(d.net_sale_amount) + '</div>';
    html += '</div>';
    $('#pj-quotation-info').html(html).show();
}

function pjApplyQuotation(id) {
    if (!id) {
        pjLockAmounts(false);
        pjRenderBreakdown(null);
        //pjRenderQuotationInfo(null);
        $('#pj-company').prop('disabled', false).trigger('change.select2');
        $('#pj-company-hint').text('Select a company manually (without quotation).');
        $('#pj-sync-note').text('Calculated automatically.');
        $('#pj-po-file-required').toggle(false);
        pjRenderSalesSplit();
        return;
    }

    $('#pj-po-file-required').toggle(!pjIsEdit);

    $.get(pjFetchQuotationUrl, { quotation_id: id })
        .done(function(res) {
            var d = res.data;
            $('#pj-total-sale').val(pjFormatStored(d.total_sale_amount));
            $('#pj-net-sale').val(pjFormatStored(d.net_sale_amount));
            if (!pjIsEdit && !$.trim($('#pj-project-name').val())) {
                $('#pj-project-name').val(d.project_name);
            }
            pjSetCompany(d.company_id, d.company_name, true);
            pjLockAmounts(true);
            pjBillingBase = parseFloat(d.billing_base_amount) || 0;
            pjRenderBreakdown(d);
            pjRecalcTerms();
            //pjRenderQuotationInfo(d);
            $('#pj-sync-note').html('<i class="fa fa-lock me-1"></i> Read-only from quotation ' + pjEsc(d.quotation_number || ('#' + id)) + '.');
            pjRenderSalesSplit();
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to load quotation.');
            if (!pjIsEdit) {
                $('#pj-quotation-select').val('').trigger('change');
            }
        });
}

function pjInitSales() {
    $('#pj-sales').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Search sales...',
        minimumInputLength: 0,
        ajax: {
            url: pjSearchSalesUrl,
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term || '' }; },
            processResults: function(data) { return data; }
        }
    });

    (pjInitialSales || []).forEach(function(s) {
        $('#pj-sales').append(new Option(s.text, s.id, true, true));
    });

    $('#pj-sales').on('change', function() {
        pjClearInvalid(this);
        pjRenderSalesSplit();
    });
}

function pjRenderSalesSplit() {
    var ids = $('#pj-sales').val() || [];
    var netCents = pjCents($('#pj-net-sale').val());

    if (ids.length === 0) {
        $('#pj-sales-preview').html('<div class="pj-empty"><i class="fa-solid fa-user-plus me-1"></i> No sales selected.</div>');
        return;
    }

    var base = Math.floor(netCents / ids.length);
    var html = '';
    ids.forEach(function(id, i) {
        var cents = i === ids.length - 1 ? netCents - (base * (ids.length - 1)) : base;
        var label = $('#pj-sales option[value="' + id + '"]').text() || ('User #' + id);
        var pct = netCents > 0 ? (cents / netCents * 100) : (100 / ids.length);
        html += '<div class="pj-split-item">';
        html += '<span class="pj-split-avatar">' + pjEsc(pjInitials(label)) + '</span>';
        html += '<span class="pj-split-name">' + pjEsc(label) + '<div class="pj-split-pct">' + pjFormatNumber(Math.round(pct * 100) / 100) + '%</div></span>';
        html += '<span class="pj-split-amount">' + pjFormatMoney(cents / 100) + '</span>';
        html += '</div>';
    });
    $('#pj-sales-preview').html(html);
}

function pjUpdateDueHint() {
    var po = $('#pj-po-date').val();
    var due = $('#pj-po-due-date').val();
    var $hint = $('#pj-due-hint');

    if (!po || !due) { $hint.text(''); return; }

    var days = Math.round((new Date(due + 'T00:00:00') - new Date(po + 'T00:00:00')) / 86400000);
    if (days < 0) {
        $hint.html('<span class="text-danger"><i class="fa fa-triangle-exclamation me-1"></i>Before PO date.</span>');
    } else {
        $hint.text(days + ' days from PO date.');
    }
}

// ── Masa garansi ──
function pjWarrantyMonths(months) {
    var start = $('#pj-warranty-start').val();
    if (!start) {
        toastr.info('Fill warranty start date first.');
        $('#pj-warranty-start').trigger('focus');
        return;
    }
    var d = new Date(start + 'T00:00:00');
    var day = d.getDate();
    d.setMonth(d.getMonth() + months);
    if (d.getDate() !== day) d.setDate(0); // mis. 31 Jan + 1 bulan -> akhir Februari
    var pad = function(n) { return (n < 10 ? '0' : '') + n; };
    $('#pj-warranty-end').val(d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()));
    pjUpdateWarrantyHint();
}

function pjClearWarranty() {
    $('#pj-warranty-start, #pj-warranty-end').val('');
    pjUpdateWarrantyHint();
}

function pjUpdateWarrantyHint() {
    var start = $('#pj-warranty-start').val();
    var end = $('#pj-warranty-end').val();
    var $hint = $('#pj-warranty-hint');
    if (!start || !end) { $hint.text(start || end ? 'Fill both dates.' : ''); return; }

    var s = new Date(start + 'T00:00:00');
    var e = new Date(end + 'T00:00:00');
    if (e < s) {
        $hint.html('<span class="text-danger"><i class="fa fa-triangle-exclamation me-1"></i>Before start date.</span>');
        return;
    }
    var months = (e.getFullYear() - s.getFullYear()) * 12 + (e.getMonth() - s.getMonth()) - (e.getDate() < s.getDate() ? 1 : 0);
    var today = new Date(); today.setHours(0, 0, 0, 0);
    var left = Math.round((e - today) / 86400000);
    var status = today < s ? 'not started' : (left < 0 ? 'already ended' : left + ' days left');
    $hint.text((months > 0 ? '± ' + months + ' months' : Math.round((e - s) / 86400000) + ' days') + ' · ' + status + '.');
}

function pjFieldTarget(selector) {
    var $el = $(selector);
    return $el.hasClass('select2-hidden-accessible') ? $el.next('.select2-container').find('.select2-selection') : $el;
}

function pjClearInvalid(el) {
    pjFieldTarget(el).removeClass('is-invalid');
}

function pjFail(selector, message) {
    var $target = pjFieldTarget(selector);
    $target.addClass('is-invalid');
    toastr.error(message);

    var top = $target.offset() ? $target.offset().top - 120 : 0;
    $('html, body').animate({ scrollTop: Math.max(top, 0) }, 250);
    if (!$(selector).hasClass('select2-hidden-accessible')) $(selector).trigger('focus');

    return false;
}

function pjValidate() {
    $('.is-invalid').removeClass('is-invalid');

    var quotationId = $('#pj-quotation-id').val();
    if (!$.trim($('#pj-project-name').val())) return pjFail('#pj-project-name', 'Project name is required.');
    if (!$('#pj-company-id').val()) return pjFail('#pj-company', 'Company is required.');
    if (!$.trim($('#pj-po-number').val())) return pjFail('#pj-po-number', 'Customer PO no. is required.');
    if (!$('#pj-po-date').val()) return pjFail('#pj-po-date', 'PO date is required.');
    if (!$('#pj-po-date-received').val()) return pjFail('#pj-po-date-received', 'PO received date is required.');
    if (!$('#pj-po-due-date').val()) return pjFail('#pj-po-due-date', 'PO due date is required.');

    var file = document.getElementById('pj-po-file').files[0];
    if (quotationId && !pjIsEdit && !file) {
        return pjFail('#pj-po-file', 'PO document is required for quotation-based projects.');
    }
    if (file && file.size > pjMaxFileSize) return pjFail('#pj-po-file', 'File size must not exceed 10MB.');

    if (!quotationId) {
        var m = pjManualValues();
        if (!(m.subtotal > 0)) return pjFail('#pj-m-subtotal', 'Project subtotal is required.');
        if (m.discountPct > 100) return pjFail('#pj-m-disc-val', 'Discount percent max 100.');
        if (m.discount > m.subtotal) return pjFail('#pj-m-disc-val', 'Discount must not exceed subtotal.');
        if (m.ppnPct > 100) return pjFail('#pj-m-ppn-val', 'VAT percent max 100.');
        if (m.refPct > 100) return pjFail('#pj-m-ref-pct', 'Referral percent max 100.');
    }

    if (($('#pj-sales').val() || []).length === 0) return pjFail('#pj-sales', 'Select at least one sales.');

    var agreementFiles = document.getElementById('pj-agreement-files').files;
    if (agreementFiles.length > 10) return pjFail('#pj-agreement-files', 'Max 10 contract files per upload.');
    for (var f = 0; f < agreementFiles.length; f++) {
        if (agreementFiles[f].size > pjMaxFileSize) return pjFail('#pj-agreement-files', 'File "' + agreementFiles[f].name + '" exceeds 10MB.');
    }

    var wStart = $('#pj-warranty-start').val();
    var wEnd = $('#pj-warranty-end').val();
    if (wStart && !wEnd) return pjFail('#pj-warranty-end', 'Warranty end date is required when a start date is filled.');
    if (wEnd && !wStart) return pjFail('#pj-warranty-start', 'Warranty start date is required when an end date is filled.');
    if (wStart && wEnd && wEnd < wStart) return pjFail('#pj-warranty-end', 'Warranty end date must not be before the start date.');

    var termTotal = 0;
    var $termRows = $('#pj-term-body tr');
    for (var t = 0; t < $termRows.length; t++) {
        var $row = $termRows.eq(t);
        if (!$.trim($row.find('.pj-term-name').val())) return pjFail($row.find('.pj-term-name'), 'Payment name on row ' + (t + 1) + ' is required.');
        var pct = parseFloat($row.find('.pj-term-percent').val());
        if (!(pct > 0)) return pjFail($row.find('.pj-term-percent'), 'Percentage on row ' + (t + 1) + ' must be greater than 0.');
        termTotal += pct;
    }
    if (Math.round(termTotal * 100) / 100 > 100) {
        return pjFail($termRows.last().find('.pj-term-percent'), 'Total billing timeline percentage exceeds 100%.');
    }

    return true;
}

function pjSetSaving(saving) {
    var $btn = $('#pj-save-btn');
    if (saving) {
        $btn.data('label', $btn.html());
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
    } else {
        $btn.prop('disabled', false).html($btn.data('label'));
    }
}

function pjSave() {
    if (!pjValidate()) return;

    var fd = new FormData();
    fd.append('_token', '{{ csrf_token() }}');
    if (pjIsEdit) fd.append('_method', 'PUT');

    fd.append('quotation_id', $('#pj-quotation-id').val() || '');
    fd.append('project_name', $('#pj-project-name').val());
    fd.append('project_description', $('#pj-project-description').val());
    fd.append('company_id', $('#pj-company-id').val());
    fd.append('po_number_company', $('#pj-po-number').val());
    fd.append('po_date', $('#pj-po-date').val());
    fd.append('po_date_received', $('#pj-po-date-received').val());
    fd.append('po_due_date', $('#pj-po-due-date').val());
    if (!$('#pj-quotation-id').val()) {
        // Server menghitung ulang total, net, dan dasar penagihan dari rincian ini.
        var mv = pjManualValues();
        fd.append('manual_subtotal', pjToRaw($('#pj-m-subtotal').val()));
        fd.append('manual_discount_percent', mv.discMode === 'pct' && mv.discountPct > 0 ? mv.discountPct : '');
        fd.append('manual_discount_amount', mv.discMode === 'amt' ? (pjToRaw($('#pj-m-disc-val').val()) || 0) : '');
        fd.append('manual_ppn_percent', mv.ppnMode === 'pct' && mv.ppnPct > 0 ? mv.ppnPct : '');
        fd.append('manual_ppn_amount', mv.ppnMode === 'amt' ? (pjToRaw($('#pj-m-ppn-val').val()) || 0) : '');
        fd.append('manual_referral_percent', pjNum('#pj-m-ref-pct') || '');
    }
    fd.append('is_retention', $('#pj-is-retention').val());
    fd.append('warranty_start_date', $('#pj-warranty-start').val());
    fd.append('warranty_end_date', $('#pj-warranty-end').val());
    fd.append('type_of_achievement', $('#pj-type').val());

    ($('#pj-sales').val() || []).forEach(function(id, i) {
        fd.append('sales[' + i + ']', id);
    });

    var file = document.getElementById('pj-po-file').files[0];
    if (file) fd.append('po_document', file);

    pjCollectTerms().forEach(function(term, i) {
        Object.keys(term).forEach(function(k) { fd.append('billing_terms[' + i + '][' + k + ']', term[k]); });
    });

    var agreementFiles = document.getElementById('pj-agreement-files').files;
    for (var a = 0; a < agreementFiles.length; a++) fd.append('agreement_files[' + a + ']', agreementFiles[a]);
    fd.append('agreement_notes', $('#pj-agreement-notes').val());

    pjSetSaving(true);

    $.ajax({
        url: pjIsEdit ? pjUpdateUrl : pjStoreUrl,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false
    }).done(function(res) {
        toastr.success(res.message || 'Project saved.');
        var id = pjIsEdit ? pjProjectId : res.id;
        setTimeout(function() {
            window.location.href = id ? pjShowUrl.replace('__ID__', id) : pjIndexUrl;
        }, 700);
    }).fail(function(xhr) {
        pjSetSaving(false);
        var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to save project.';
        toastr.error(message);
    });
}

$(document).ready(function() {
    pjInitQuotation();
    pjInitCompany();
    pjFormatAllStored();
    pjInitSales();
    pjSetCompany(pjInitialCompanyId, pjInitialCompanyName, !!$('#pj-quotation-id').val());

    $('#pj-quotation-select').on('change', function() {
        var id = $(this).val();
        $('#pj-quotation-id').val(id || '');
        pjApplyQuotation(id);
    });

    if ($('#pj-quotation-id').val()) {
        pjApplyQuotation($('#pj-quotation-id').val());
    } else {
        pjLockAmounts(false);
    }

    $('input[name="pj-retention-opt"]').on('change', function() { $('#pj-is-retention').val(this.value); });
    $('input[name="pj-type-opt"]').on('change', function() { $('#pj-type').val(this.value); });

    $(document).on('input', '.pj-number:not([readonly])', function() { pjFormatNum(this); });
    $(document).on('input', '.pj-manual-input', pjRecalcManual);
    // Simpan nilai sebelum mode diganti, lalu konversi ke mode baru.
    $(document).on('mousedown keydown', 'label[for^="pj-m-disc-mode"], label[for^="pj-m-ppn-mode"], input[name="pj-m-disc-mode"], input[name="pj-m-ppn-mode"]', function() { pjModeBefore = pjManualValues(); });
    $('input[name="pj-m-disc-mode"]').on('change', function() { pjSwitchMode('disc'); });
    $('input[name="pj-m-ppn-mode"]').on('change', function() { pjSwitchMode('ppn'); });
    $(document).on('input change', 'input, textarea', function() { pjClearInvalid(this); });
    $(document).on('change', '#pj-po-date, #pj-po-due-date', pjUpdateDueHint);

    pjUpdateDueHint();
    pjRenderSalesSplit();
    pjUpdateWarrantyHint();
    $(document).on('change', '#pj-warranty-start, #pj-warranty-end', pjUpdateWarrantyHint);

    (pjInitialTerms || []).forEach(function(t) { pjAddTerm(t); });
    pjRecalcTerms();
    $(document).on('input', '.pj-term-percent', pjRecalcTerms);

    $(document).on('input', '.pj-term-name, .pj-term-percent', function() { $(this).removeClass('is-invalid'); });
    $('#pj-agreement-files').on('change', pjRenderPendingAgreements);
});
</script>
@endsection
