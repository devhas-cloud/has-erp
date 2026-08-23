@extends('layouts.app')

@section('title', $quotation ? 'Edit Quotation '.$quotation->opportunity->opportunity_name : 'Buat Quotation')
@section('page-title', $quotation ? 'Edit Quotation' : 'Buat Quotation')

@section('styles')
<style>
    .qt-desc[contenteditable="true"],
    .qc-desc[contenteditable="true"] {
        border: 1px solid var(--card-border, #ced4da);
        border-radius: .25rem;
        padding: .25rem .5rem;
        min-height: 30px;
        background: #fff;
        font-size: .85rem;
        line-height: 1.45;
        white-space: pre-wrap;
    }
    .qt-desc[contenteditable="true"]:focus,
    .qc-desc[contenteditable="true"]:focus {
        outline: none;
        border-color: var(--accent);
    }
    .qt-desc[contenteditable="true"]:empty::before,
    .qc-desc[contenteditable="true"]:empty::before {
        content: attr(data-placeholder);
        color: #999;
    }
    .qt-desc-wrap { position: relative; }
    .qt-desc-toolbar {
        position: absolute;
        top: 2px;
        right: 4px;
        display: flex;
        gap: 2px;
        opacity: .55;
    }
    .qt-desc-toolbar button {
        border: 1px solid var(--card-border, #ced4da);
        background: #fff;
        border-radius: 3px;
        font-size: 11px;
        line-height: 1;
        padding: 2px 5px;
        cursor: pointer;
        color: var(--text-primary);
    }
    .qt-desc-toolbar button:hover { background: var(--bg); opacity: 1; }
    .qt-desc-wrap:hover .qt-desc-toolbar { opacity: 1; }

    .qt-fx-wrap { position: relative; }
    .qt-fx-btn {
        position: absolute;
        top: 1px;
        right: 2px;
        border: 1px solid var(--card-border, #ced4da);
        background: #fff;
        border-radius: 3px;
        font-size: 10px;
        font-weight: 700;
        line-height: 1;
        padding: 2px 4px;
        cursor: pointer;
        color: var(--text-muted);
        opacity: .6;
        z-index: 2;
    }
    .qt-fx-btn:hover { opacity: 1; color: var(--accent); border-color: var(--accent); }
    .qt-fx-wrap.has-fx .qt-fx-btn::after {
        content: '●';
        position: absolute;
        top: -4px;
        right: -4px;
        font-size: 8px;
        color: var(--accent);
    }
    .qt-fx-wrap.has-fx input { border-color: var(--accent); }
    .qt-fx-wrap input.fx-editing { border-color: #f59e0b; background: #fffbeb; }
    .qt-fx-wrap input.fx-editing { font-family: monospace; }

    .qt-row-col {
        width: 26px;
        min-width: 26px;
        max-width: 26px;
        padding: 14px 4px !important;
        text-align: center;
        color: var(--text-muted);
        font-size: 11px;
        white-space: nowrap;
    }
    .qc-cat td {
        background: #f1f5f9;
        font-weight: 700;
        font-size: 13px;
        color: var(--accent);
        padding: 6px 8px;
    }
    .qc-cat .qc-cat-btn {
        float: right;
    }
    .qc-subtotal td {
        background: #f8fafc;
        font-style: italic;
        font-weight: 700;
        font-size: 12px;
        color: var(--text-muted);
        border-top: 1px dashed var(--card-border, #ced4da) !important;
    }
    tr.qc-subtotal td:first-child {
        border-top: none !important;
    }
    .qt-desc[contenteditable="false"] {
        background: #f5f5f5;
        cursor: not-allowed;
        color: #555;
    }
    .qt-desc-wrap.qt-locked .qt-desc-toolbar {
        display: none;
    }
    #qt-item-picker-body td {
        white-space: pre-wrap;
        word-break: break-word;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $quotation ? 'Edit Quotation #'.$quotation->id : 'Buat Quotation' }}</h1>
        <p class="page-header-sub">Pilih task yang memiliki quote configuration Approved. Item quotation disusun hierarki (penomoran ditentukan manual).</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('quotation.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<form id="qt-form" autocomplete="off">
    <input type="hidden" id="qt-edit-id" value="{{ $quotation?->id }}">
    <input type="hidden" id="qt-task-id" name="task_id" value="{{ $quotation?->task_id ?? $preselected?->id }}">

    <div class="card-custom fade-in mb-3">
        <div class="card-header-custom">
            <span><i class="fa-solid fa-database me-2" style="color:var(--accent)"></i>Sumber Task &amp; Configuration</span>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Task (Quote) <span class="text-danger">*</span></label>
                    <select id="qt-task" class="form-select" style="width:100%">
                        <option value="">— Pilih Task —</option>
                        @foreach($tasks as $task)
                            <option value="{{ $task->id }}"
                                {{ $preselected && $preselected->id === $task->id ? 'selected' : '' }}>
                                #{{ $task->id }} — {{ $task->title }}
                                ({{ $task->opportunity?->opportunity_name ?? $task->lead?->accountCompany?->account_name ?? 'N/A' }})
                            </option>
                        @endforeach
                    </select>
                    <small style="color:var(--text-muted)">Hanya task in_progress dengan quote configuration Approved versi terakhir.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal Quotation</label>
                    <input type="date" id="qt-date" name="date" class="form-control"
                        value="{{ $quotation?->date?->format('Y-m-d') ?? now()->format('Y-m-d') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card-custom fade-in mb-3">
        <div class="card-header-custom">
            <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Informasi Dokumen</span>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Our Ref (Nomor Quotation)</label>
                    <input type="text" class="form-control" id="qt-number" value="{{ $quotation?->quotation_number }}"
                        placeholder="Otomatis (cth: 087/HAS/QT-ZM/II/2026)" readonly>
                    <small style="color:var(--text-muted)">Nomor dibuat otomatis saat disimpan.</small>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Currency</label>
                    <input type="text" class="form-control" id="qt-currency" name="currency"
                        value="{{ $quotation?->currency ?? 'Rupiah' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Your Ref</label>
                    <input type="text" class="form-control" id="qt-your-ref" name="your_ref" value="{{ $quotation?->your_ref }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">No of Pages</label>
                    <input type="number" min="1" class="form-control" id="qt-pages" name="no_of_pages"
                        value="{{ $quotation?->no_of_pages ?? 1 }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card-custom fade-in mb-3">
        <div class="card-header-custom">
            <span><i class="fa-solid fa-building me-2" style="color:var(--accent)"></i>Informasi Customer <small style="color:var(--text-muted);font-weight:400">(otomatis dari task)</small></span>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">To <small style="color:var(--text-muted);font-weight:400">(company)</small></label>
                    <input type="text" class="form-control" id="qt-to" name="to_name" value="{{ $quotation?->to_name }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">From (Sales)</label>
                    <input type="text" class="form-control" id="qt-from" name="from_name" value="{{ $quotation?->from_name }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" rows="2" id="qt-address" name="address">{{ $quotation?->address }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Attn (PIC)</label>
                    <input type="text" class="form-control" id="qt-attn" name="attn_name" value="{{ $quotation?->attn_name }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Telp</label>
                    <input type="text" class="form-control" id="qt-phone" name="attn_phone" value="{{ $quotation?->attn_phone }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="text" class="form-control" id="qt-email" name="attn_email" value="{{ $quotation?->attn_email }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Contact Person Phone</label>
                    <input type="text" class="form-control" id="qt-contact-phone" name="contact_phone" value="{{ $quotation?->contact_phone }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Parameter</label>
                    <input type="text" class="form-control" id="qt-parameter" name="parameter_note" value="{{ $quotation?->parameter_note }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card-custom fade-in mb-3">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>List Item Quotation</span>
        </div>
        <div class="card-body-custom p-2">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#qt-tab-items" type="button" role="tab">
                        List Item Quotation
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#qt-tab-configs" type="button" role="tab">
                        List Configuration
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#qt-tab-costs" type="button" role="tab">
                        Biaya
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#qt-tab-notes" type="button" role="tab">
                        Catatan Internal
                    </button>
                </li>
            </ul>
            <div class="tab-content pt-3">
                <div class="tab-pane fade show active" id="qt-tab-items" role="tabpanel">
                    <div class="d-flex justify-content-end gap-2 mb-2">
                        <select id="qt-template" class="form-select form-select-sm" style="width:auto">
                            <option value="">— Pilih Template (Quotation) —</option>
                            @foreach($templates as $tpl)
                                <option value="{{ $tpl->id }}">
                                    {{ $tpl->quotation_number }} — {{ $tpl->to_name }}
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addQtChild(null)">
                            <i class="fa fa-plus me-1"></i> Tambah Baris Manual
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0" id="qt-items-table">
                            <thead>
                                <tr>
                                    <th class="text-center qt-row-col">#</th>
                                    <th style="width:6%">No</th>
                                    <th style="width:42%">Deskripsi</th>
                                    <th style="width:7%">Qty</th>
                                    <th style="width:7%">Unit</th>
                                    <th style="width:15%">Unit Price</th>
                                    <th class="text-end" style="width:8%">Amount</th>
                                    <th class="text-center" style="width:10%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="qt-items-body">
                                @php
                                    $rendered = [];
                                    $keySeq = 0;
                                    $byParent = $items ? collect($items)->groupBy(fn ($it) => $it['parent_id'] ?? 'root') : collect();
                                    $renderItem = function ($item, $depth, $byParent, &$rendered) use (&$renderItem, &$keySeq) {
                                        if ($item['id'] && in_array($item['id'], $rendered)) {
                                            return;
                                        }
                                        if ($item['id']) {
                                            $rendered[] = $item['id'];
                                        }
                                        $key = $item['id'] ? 'db-'.$item['id'] : 'new-'.($item['_key'] ?? (++$keySeq));
                                        $parentKey = ($item['parent_id'] ?? null)
                                            ? 'db-'.$item['parent_id']
                                            : ($item['_parent'] ?? '');
                                        $fxQty = isset($item['formula']['qty']) ? ' data-fx="'.e($item['formula']['qty']).'"' : '';
                                        $fxPrice = isset($item['formula']['price']) ? ' data-fx="'.e($item['formula']['price']).'"' : '';
                                        echo '<tr data-key="'.$key.'"'
                                            .' data-parent="'.$parentKey.'"'
                                            .' data-depth="'.$depth.'">';
                                        echo '<td class="text-center qt-row-col qt-row-num"></td>';
                                        echo '<td><input type="text" class="form-control form-control-sm qt-no" value="'.e($item['item_no'] ?? '').'" placeholder="1 / 1.1"></td>';
                                        echo '<td><div class="qt-desc-wrap" style="margin-left:'.($depth * 18).'px">';
                                        echo '<div class="qt-desc" contenteditable="true" data-placeholder="Deskripsi item...">'.\App\Models\Quotation::renderDescription($item['description'] ?? '').'</div>';
                                        echo '<div class="qt-desc-toolbar">';
                                        echo '<button type="button" data-cmd="bold" title="Bold"><b>B</b></button>';
                                        echo '<button type="button" data-cmd="italic" title="Italic"><i>I</i></button>';
                                        echo '<button type="button" data-cmd="underline" title="Underline"><u>U</u></button>';
                                        echo '</div></div></td>';
                                        echo '<td><input type="text" inputmode="decimal" min="0" class="form-control form-control-sm qt-qty" data-fx-table="items"'.$fxQty.' value="'.($item['qty'] ?? '').'"></td>';
                                        echo '<td><input type="text" class="form-control form-control-sm qt-unit" value="'.e($item['unit'] ?? '').'"></td>';
                                        echo '<td><input type="text" inputmode="decimal" min="0" step="any" class="form-control form-control-sm qt-price text-end" data-fx-table="items"'.$fxPrice.' value="'.($item['price'] ?? '').'"></td>';
                                        echo '<td class="qt-amount text-end"></td>';
                                        echo '<td class="text-center">';
                                        echo '<button type="button" class="btn-icon" title="Add Item dari Config" onclick="openQtItemPicker(this)"><i class="fa fa-cart-plus"></i></button>';
                                        echo '<button type="button" class="btn-icon" title="Tambah Anak" onclick="addQtChild(this)"><i class="fa fa-plus"></i></button>';
                                        echo '<button type="button" class="btn-icon text-danger" title="Hapus" onclick="removeQtItem(this)"><i class="fa fa-trash"></i></button>';
                                        echo '</td></tr>';
                                        foreach (($byParent[$item['id'] ?? null] ?? []) as $child) {
                                            $renderItem($child, $depth + 1, $byParent, $rendered);
                                        }
                                    };
                                    foreach ($byParent['root'] ?? [] as $item) {
                                        $renderItem($item, 0, $byParent, $rendered);
                                    }
                                    // Item yang bukan root & tidak dirender karena parent-nya hilang (anti-bug)
                                    foreach ($items as $item) {
                                        if (! in_array($item['id'] ?? null, $rendered)) {
                                            $renderItem($item, 0, $byParent, $rendered);
                                        }
                                    }
                                @endphp
                            </tbody>
                        </table>
                    </div>
                    @if(empty($items))
                        <div class="config-card-empty" id="qt-items-empty">
                            <i class="fa-solid fa-inbox"></i> Belum ada item. Tambahkan item secara manual menggunakan tombol "Tambah Baris Manual" atau "＋".
                        </div>
                    @endif
                </div>
                <div class="tab-pane fade" id="qt-tab-configs" role="tabpanel">
                    <div id="qt-config-lists"></div>
                    <div class="config-card-empty" id="qt-configs-empty">
                        <i class="fa-solid fa-inbox"></i> Belum ada list configuration. Pilih task di atas untuk menampilkan configuration yang terikat.
                    </div>
                </div>
                <div class="tab-pane fade" id="qt-tab-costs" role="tabpanel">
                    <div class="d-flex justify-content-end gap-2 mb-2">
                        @if($quotation)
                            <a href="{{ route('quotation.pdf-cost', $quotation->id) }}" target="_blank" class="btn btn-sm btn-soft">
                                <i class="fa fa-file-pdf me-1"></i> View PDF Biaya
                            </a>
                        @endif
                        <select id="qt-cost-template" class="form-select form-select-sm" style="width:auto">
                            <option value="">— Pilih Template Biaya —</option>
                            @foreach($costTemplates as $tpl)
                                <option value="{{ $tpl->id }}">
                                    {{ $tpl->quotation_number }} — {{ $tpl->to_name }}
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addQtCostTitle(null)">
                            <i class="fa fa-tag me-1"></i> Tambah Judul
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addQtCostItem(null)">
                            <i class="fa fa-plus me-1"></i> Tambah Baris Manual
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0" id="qt-costs-table">
                            <thead>
                                <tr>
                                    <th class="text-center qt-row-col">#</th>
                                    <th style="width:6%">No</th>
                                    <th style="width:45%">Judul / Deskripsi</th>
                                    <th style="width:7%">Qty</th>
                                    <th style="width:7%">Unit</th>
                                    <th style="width:15%">Harga</th>
                                    <th class="text-end" style="width:8%">Amount</th>
                                    <th class="text-center" style="width:10%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="qt-costs-body">
                                @php
                                    $cRendered = [];
                                    $cKeySeq = 0;
                                    $cByParent = $costItems ? collect($costItems)->groupBy(fn ($it) => $it['parent_id'] ?? 'root') : collect();
                                    $renderCost = function ($item, $depth, $cByParent, &$cRendered) use (&$renderCost, &$cKeySeq) {
                                        if ($item['id'] && in_array($item['id'], $cRendered)) {
                                            return;
                                        }
                                        if ($item['id']) {
                                            $cRendered[] = $item['id'];
                                        }
                                        $key = $item['id'] ? 'cost-db-'.$item['id'] : 'cost-new-'.($item['_key'] ?? (++$cKeySeq));
                                        $parentKey = ($item['parent_id'] ?? null)
                                            ? 'cost-db-'.$item['parent_id']
                                            : ($item['_parent'] ?? '');
                                        $fxCQty = isset($item['formula']['qty']) ? ' data-fx="'.e($item['formula']['qty']).'"' : '';
                                        $fxCPrice = isset($item['formula']['price']) ? ' data-fx="'.e($item['formula']['price']).'"' : '';
                                        echo '<tr data-key="'.$key.'"'
                                            .' data-parent="'.$parentKey.'"'
                                            .' data-depth="'.$depth.'"'
                                            .' data-type="'.(($item['title'] ?? null) ? 'title' : 'item').'">';
                                        echo '<td class="text-center qt-row-col qt-cost-row-num"></td>';
                                        echo '<td><input type="text" class="form-control form-control-sm qt-cost-no" value="'.e($item['item_no'] ?? '').'" placeholder="1 / 1.1"></td>';
                                        echo '<td><div class="qt-desc-wrap" style="margin-left:'.($depth * 18).'px">';
                                        echo '<div class="qt-desc" contenteditable="true" data-placeholder="Deskripsi / judul biaya...">'.\App\Models\Quotation::renderDescription($item['title'] ?? $item['description'] ?? '').'</div>';
                                        echo '<div class="qt-desc-toolbar">';
                                        echo '<button type="button" data-cmd="bold" title="Bold"><b>B</b></button>';
                                        echo '<button type="button" data-cmd="italic" title="Italic"><i>I</i></button>';
                                        echo '<button type="button" data-cmd="underline" title="Underline"><u>U</u></button>';
                                        echo '</div></div></td>';
                                        echo '<td><input type="text" inputmode="decimal" min="0" class="form-control form-control-sm qt-cost-qty" data-fx-table="costs"'.$fxCQty.' value="'.($item['qty'] ?? '').'"></td>';
                                        echo '<td><input type="text" class="form-control form-control-sm qt-cost-unit" value="'.e($item['unit'] ?? '').'"></td>';
                                        echo '<td><input type="text" inputmode="decimal" min="0" step="any" class="form-control form-control-sm qt-cost-price text-end" data-fx-table="costs"'.$fxCPrice.' value="'.($item['price'] ?? '').'"></td>';
                                        echo '<td class="qt-cost-amount text-end"></td>';
                                        echo '<td class="text-center">';
                                        echo '<button type="button" class="btn-icon" title="Tambah Judul" onclick="addQtCostTitle(this)"><i class="fa fa-tag"></i></button>';
                                        echo '<button type="button" class="btn-icon" title="Tambah Anak" onclick="addQtCostItem(this)"><i class="fa fa-plus"></i></button>';
                                        echo '<button type="button" class="btn-icon text-danger" title="Hapus" onclick="removeQtCostItem(this)"><i class="fa fa-trash"></i></button>';
                                        echo '</td></tr>';
                                        foreach (($cByParent[$item['id'] ?? null] ?? []) as $child) {
                                            $renderCost($child, $depth + 1, $cByParent, $cRendered);
                                        }
                                    };
                                    foreach ($cByParent['root'] ?? [] as $item) {
                                        $renderCost($item, 0, $cByParent, $cRendered);
                                    }
                                    foreach ($costItems as $item) {
                                        if (! in_array($item['id'] ?? null, $cRendered)) {
                                            $renderCost($item, 0, $cByParent, $cRendered);
                                        }
                                    }
                                @endphp
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end mt-2">
                        <table class="table table-custom align-middle mb-0" style="max-width:340px">
                            <tr>
                                <td class="text-end"><strong>Total Price Biaya</strong></td>
                                <td class="text-end fw-bold" id="qt-cost-total">0</td>
                            </tr>
                        </table>
                    </div>
                    <div class="mt-2">
                        <label class="form-label">Catatan Biaya</label>
                        <textarea class="form-control" rows="3" id="qt-cost-notes" name="cost_notes"
                            placeholder="Catatan untuk biaya (opsional)">{{ $quotation?->cost_notes }}</textarea>
                    </div>
                    @if(empty($costItems))
                        <div class="config-card-empty" id="qt-costs-empty">
                            <i class="fa-solid fa-inbox"></i> Belum ada biaya. Tambahkan judul biaya atau baris manual.
                        </div>
                    @endif
                </div>
                <div class="tab-pane fade" id="qt-tab-notes" role="tabpanel">
                    <div class="mb-2">
                        <label class="form-label">Catatan Pembuatan Quotation</label>
                        <textarea class="form-control" rows="10" id="qt-notes" name="notes"
                            placeholder="Tulis catatan selama pembuatan quotation agar lebih paham tentang apa yang sedang dibuat.">{{ $quotation?->notes }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card-custom fade-in mb-3" id="qt-price-summary">
        <div class="card-header-custom">
            <span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Ringkasan Harga</span>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="row g-2">
                        <div class="col-12 mt-2">
                            <label class="form-label">Term &amp; Conditions</label>
                            <textarea class="form-control" rows="16" id="qt-terms" name="terms" style="font-family:monospace;font-size:12px">{{ $terms }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <tbody>
                                <tr>
                                    <td style="width:40%">Subtotal</td>
                                    <td class="text-end fw-bold" id="qt-subtotal">0</td>
                                </tr>
                                <tr>
                                    <td>Diskon</td>
                                    <td>
                                        <div class="d-flex gap-1 justify-content-end">
                                            <input type="number" id="qt-disc-pct" class="form-control form-control-sm" min="0" max="100" step="any" placeholder="%" style="width:80px" value="{{ $quotation?->discount_percent }}">
                                            <input type="text" inputmode="decimal" id="qt-disc-amt" class="form-control form-control-sm text-end" min="0" step="any" placeholder="Nominal" style="width:150px" value="{{ $quotation?->discount_amount }}">
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Netto / DPP Pajak</td>
                                    <td class="text-end" id="qt-dpp">0</td>
                                </tr>
                                <tr>
                                    <td>PPN</td>
                                    <td>
                                        <div class="d-flex gap-1 justify-content-end">
                                            <input type="number" id="qt-ppn-pct" class="form-control form-control-sm" min="0" max="100" step="any" placeholder="%" style="width:80px" value="{{ $quotation?->ppn_percent ?? 11 }}">
                                            <input type="text" inputmode="decimal" id="qt-ppn-amt" class="form-control form-control-sm text-end" min="0" step="any" placeholder="Nominal" style="width:150px" value="{{ $quotation?->ppn_amount }}" data-fx-table="summary" @if(isset($formula['ppn_amount']) && $formula['ppn_amount']) data-fx="{{ $formula['ppn_amount'] }}" @endif>
                                        </div>
                                    </td>
                                </tr>
                                <tr style="background:var(--accent-soft)">
                                    <td class="fw-bold">Full Amount</td>
                                    <td class="text-end fw-bold" style="color:var(--accent);font-size:16px" id="qt-total">0</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <small style="color:var(--text-muted)">Diskon &amp; PPN: isi persen untuk menghitung nominal otomatis, atau isi nominal manual. PPN dihitung dari Netto (Subtotal − Diskon).</small>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4 justify-content-end">
        <a href="{{ route('quotation.index') }}" class="btn btn-secondary">Batal</a>
        <button type="submit" class="btn-accent" id="qt-save-btn">
            <i class="fa fa-save me-1"></i> <span>{{ $quotation ? 'Simpan Perubahan' : 'Simpan Quotation' }}</span>
        </button>

    </div>
</form>
@endsection

@section('scripts')
<script>
let qtKeySeq = 0;
let qtTaskData = null;

const qtFetchTaskUrl = '{{ route("quotation.fetch-task") }}';
const qtFetchTemplateUrl = '{{ route("quotation.fetch-template") }}';
const qtCostTemplateUrl = '{{ route("quotation.fetch-cost-template") }}';
const qtCfgSearchUrl = '{{ route("quotation.search-products") }}';

function qtNewKey() {
    return 'new-' + (++qtKeySeq);
}

function qtToRaw(str) {
    return String(str ?? '').replace(/,/g, '');
}

function qtFormatInput(str) {
    var s = qtToRaw(str).replace(/[^\d.]/g, '');
    if (s === '') return '';
    var neg = s.charAt(0) === '-' ? '-' : '';
    if (neg) s = s.slice(1);
    var parts = s.split('.');
    var int = parts[0] === '' ? '' : Number(parts[0]).toLocaleString('en-US');
    var dec = parts.length > 1 ? '.' + (parts[1] || '') : '';
    return neg + int + dec;
}

function qtFormatNum(el) {
    var $el = $(el);
    var start = el.selectionStart || 0;
    var before = qtToRaw($el.val()).slice(0, start);
    var digitsBefore = before.replace(/\D/g, '').length;
    var formatted = qtFormatInput($el.val());
    var caret = 0, seen = 0;
    for (var i = 0; i < formatted.length && seen < digitsBefore; i++) {
        caret = i + 1;
        if (/\d/.test(formatted[i])) seen++;
    }
    $el.val(formatted);
    try { el.setSelectionRange(caret, caret); } catch (e) {}
}

function qtFormatAllNumeric() {
    $('.qt-qty, .qt-price, .qc-qty, .qc-price, #qt-disc-amt, #qt-ppn-amt, .qt-cost-qty, .qt-cost-price').each(function() {
        $(this).val(qtFormatInput($(this).val()));
    });
    fxInitWraps();
    qtRenumberRows();
}

// ── Nomor baris (kolom #) ──
function qtRenumberRows() {
    $('#qt-items-body tr').each(function(i) { $(this).find('.qt-row-num').text(i + 1); });
    $('#qt-costs-body tr').each(function(i) { $(this).find('.qt-cost-row-num').text(i + 1); });
    $('.qt-config-block').each(function() {
        $(this).find('tbody tr.qc-item').each(function(i) { $(this).find('.qc-row-num').text(i + 1); });
    });
}

// ── Engine Rumus Excel (A1-style) ──

function fxInitWraps(scope) {
    var $scope = scope ? $(scope) : $(document);
    $scope.find('.qt-qty, .qt-price, .qc-qty, .qc-price, .qt-cost-qty, .qt-cost-price, #qt-ppn-amt').each(function() {
        var $input = $(this);
        if ($input.closest('.qt-fx-wrap').length) return;
        $input.wrap('<div class="qt-fx-wrap"></div>');
        var $wrap = $input.closest('.qt-fx-wrap');
        var btn = $('<button type="button" class="qt-fx-btn" title="Rumus (Excel)">ƒx</button>');
        $wrap.append(btn);
        var fx = $input.attr('data-fx');
        if (fx) {
            $input.data('fx-formula', fx);
            $wrap.addClass('has-fx');
        }
        fxSetReadonly($input);
    });
}

function fxSetReadonly($input) {
    $input.prop('readonly', !!$input.data('fx-formula'));
}

function fxTokenize(str) {
    var tokens = [];
    var s = String(str || '').replace(/^=/, '').trim();
    var i = 0;
    while (i < s.length) {
        var c = s[i];
        if (c === ' ' || c === '\t') { i++; continue; }
        if ('+-*/%(),:'.indexOf(c) !== -1) { tokens.push({ t: 'op', v: c }); i++; continue; }
        var m = s.slice(i).match(/^(\d+(\.\d+)?|\.\d+)/);
        if (m) { tokens.push({ t: 'num', v: parseFloat(m[0]) }); i += m[0].length; continue; }
        var r = s.slice(i).match(/^([A-Za-z]+)(\d+)/);
        if (r) { tokens.push({ t: 'ref', col: r[1].toUpperCase(), row: parseInt(r[2], 10) }); i += r[0].length; continue; }
        var id = s.slice(i).match(/^([A-Za-z]+)/);
        if (id) { tokens.push({ t: 'id', v: id[1].toUpperCase() }); i += id[0].length; continue; }
        throw new Error('Sintaks tidak dikenal');
    }
    return tokens;
}

function fxParse(tokens) {
    var pos = 0;
    function peek() { return tokens[pos]; }
    function next() { return tokens[pos++]; }
    function expectOp(v) { var t = next(); if (!t || t.t !== 'op' || t.v !== v) throw new Error('Sintaks'); }
    function parseExpression() {
        var left = parseTerm();
        while (peek() && peek().t === 'op' && (peek().v === '+' || peek().v === '-')) {
            var op = next().v;
            left = { t: 'bin', op: op, l: left, r: parseTerm() };
        }
        return left;
    }
    function parseTerm() {
        var left = parseFactor();
        while (peek() && peek().t === 'op' && (peek().v === '*' || peek().v === '/' || peek().v === '%')) {
            var op = next().v;
            left = { t: 'bin', op: op, l: left, r: parseFactor() };
        }
        return left;
    }
    function parseFactor() {
        var t = peek();
        if (!t) throw new Error('Unexpected end');
        if (t.t === 'num') { next(); return { t: 'num', v: t.v }; }
        if (t.t === 'op' && t.v === '(') { next(); var e = parseExpression(); expectOp(')'); return e; }
        if (t.t === 'op' && t.v === '-') { next(); return { t: 'neg', e: parseFactor() }; }
        if (t.t === 'ref') { next(); return { t: 'ref', col: t.col, row: t.row }; }
        if (t.t === 'id') {
            var name = t.v;
            next();
            expectOp('(');
            var args = [];
            if (!(peek() && peek().t === 'op' && peek().v === ')')) {
                args.push(parseArg());
                while (peek() && peek().t === 'op' && peek().v === ',') { next(); args.push(parseArg()); }
            }
            expectOp(')');
            return { t: 'fn', name: name, args: args };
        }
        throw new Error('Sintaks');
    }
    function parseArg() {
        if (peek() && peek().t === 'ref' && tokens[pos + 1] && tokens[pos + 1].t === 'op' && tokens[pos + 1].v === ':') {
            var a = next();
            next();
            var b = next();
            if (!b || b.t !== 'ref') throw new Error('Sintaks range');
            return { t: 'range', a: a, b: b };
        }
        return parseExpression();
    }
    var ast = parseExpression();
    if (pos < tokens.length) throw new Error('Sintaks sisa');
    return ast;
}

function fxGetRow(tableKey, row) {
    if (tableKey === 'items') return $('#qt-items-body tr').eq(row - 1);
    if (tableKey === 'costs') return $('#qt-costs-body tr').eq(row - 1);
    if (tableKey.indexOf('config:') === 0) {
        var cid = tableKey.slice(7);
        return $('.qt-config-block[data-config="' + cid + '"] tbody tr.qc-item').eq(row - 1);
    }
    return $();
}

function fxFieldValue(tr, field) {
    var el = tr.find(field === 'qty' ? '.qt-qty, .qc-qty, .qt-cost-qty' : '.qt-price, .qc-price, .qt-cost-price');
    return parseFloat(qtToRaw(el.val())) || 0;
}

function fxGetCellValue(tableKey, col, row, visited) {
    var key = tableKey + '|' + col + row;
    if (visited && visited[key]) throw new Error('#CIRC!');
    if (tableKey === 'summary') {
        switch (col + row) {
            case 'A1': return parseFloat(qtToRaw($('#qt-subtotal').text())) || 0;
            case 'A2': return parseFloat(qtToRaw($('#qt-disc-amt').val())) || 0;
            case 'A3': return parseFloat(qtToRaw($('#qt-dpp').text())) || 0;
            case 'A4': return parseFloat(qtToRaw($('#qt-ppn-amt').val())) || 0;
            case 'A5': return parseFloat(qtToRaw($('#qt-total').text())) || 0;
            default: return 0;
        }
    }
    var tr = fxGetRow(tableKey, row);
    if (!tr || !tr.length) return 0;
    if (col === 'C') return fxFieldValue(tr, 'qty') * fxFieldValue(tr, 'price');
    if (col === 'A') return fxFieldValue(tr, 'qty');
    if (col === 'B') return fxFieldValue(tr, 'price');
    return 0;
}

function fxRangeValues(tableKey, a, b, visited) {
    var c1 = Math.min(a.col.charCodeAt(0), b.col.charCodeAt(0));
    var c2 = Math.max(a.col.charCodeAt(0), b.col.charCodeAt(0));
    var r1 = Math.min(a.row, b.row);
    var r2 = Math.max(a.row, b.row);
    var vals = [];
    for (var c = c1; c <= c2; c++) {
        for (var r = r1; r <= r2; r++) {
            vals.push(fxGetCellValue(tableKey, String.fromCharCode(c), r, visited));
        }
    }
    return vals;
}

function fxEvalAst(tableKey, ast, visited) {
    switch (ast.t) {
        case 'num': return ast.v;
        case 'neg': return -fxEvalAst(tableKey, ast.e, visited);
        case 'bin': {
            var l = fxEvalAst(tableKey, ast.l, visited);
            var r = fxEvalAst(tableKey, ast.r, visited);
            switch (ast.op) {
                case '+': return l + r;
                case '-': return l - r;
                case '*': return l * r;
                case '/': return r === 0 ? 0 : l / r;
                case '%': return l % r;
            }
            throw new Error('Sintaks');
        }
        case 'ref': return fxGetCellValue(tableKey, ast.col, ast.row, visited);
        case 'range': return fxRangeValues(tableKey, ast.a, ast.b, visited);
        case 'fn': {
            var vals = [];
            for (var i = 0; i < ast.args.length; i++) {
                var v = fxEvalAst(tableKey, ast.args[i], visited);
                if (Array.isArray(v)) vals = vals.concat(v); else vals.push(v);
            }
            switch (ast.name) {
                case 'SUM': return vals.reduce(function (a, b) { return a + (isNaN(b) ? 0 : b); }, 0);
                case 'AVG': return vals.length ? vals.reduce(function (a, b) { return a + (isNaN(b) ? 0 : b); }, 0) / vals.length : 0;
                case 'MIN': return vals.length ? Math.min.apply(null, vals) : 0;
                case 'MAX': return vals.length ? Math.max.apply(null, vals) : 0;
                default: throw new Error('Fungsi tidak dikenal');
            }
        }
    }
    throw new Error('Sintaks');
}

function fxEvalCell(tableKey, ownRef, formula) {
    var visited = {};
    visited[tableKey + '|' + ownRef.col + ownRef.row] = true;
    var tokens = fxTokenize(formula);
    var ast = fxParse(tokens);
    return fxEvalAst(tableKey, ast, visited);
}

function fxTableFormulaCells(tableKey) {
    var result = [];
    var selector, rows;
    if (tableKey === 'items') { selector = '.qt-qty, .qt-price'; rows = $('#qt-items-body tr'); }
    else if (tableKey === 'costs') { selector = '.qt-cost-qty, .qt-cost-price'; rows = $('#qt-costs-body tr'); }
    else if (tableKey.indexOf('config:') === 0) {
        var cid = tableKey.slice(7);
        selector = '.qc-qty, .qc-price';
        rows = $('.qt-config-block[data-config="' + cid + '"] tbody tr.qc-item');
    }
    if (tableKey === 'summary') {
        var ppnEl = $('#qt-ppn-amt');
        if (ppnEl.data('fx-formula')) result.push({ el: ppnEl, ref: { col: 'A', row: 4 } });
        return result;
    }
    rows.each(function (idx) {
        $(this).find(selector).each(function () {
            if ($(this).data('fx-formula')) {
                var isQty = $(this).hasClass('qt-qty') || $(this).hasClass('qc-qty') || $(this).hasClass('qt-cost-qty');
                result.push({ el: $(this), ref: { col: isQty ? 'A' : 'B', row: idx + 1 } });
            }
        });
    });
    return result;
}

function fxRecalcTable(tableKey) {
    var cells = fxTableFormulaCells(tableKey);
    for (var pass = 0; pass < 10; pass++) {
        var changed = false;
        cells.forEach(function (item) {
            var formula = item.el.data('fx-formula');
            if (!formula) return;
            try {
                var val = fxEvalCell(tableKey, item.ref, formula);
                var newVal = qtFormatInput(String(Math.round(val * 100) / 100));
                if (item.el.val() !== newVal) {
                    item.el.val(newVal);
                    changed = true;
                }
                item.el.closest('.qt-fx-wrap').addClass('has-fx').removeClass('fx-error');
            } catch (e) {
                item.el.closest('.qt-fx-wrap').addClass('has-fx fx-error').attr('title', e.message || '#ERR!');
                item.el.val('');
            }
        });
        if (!changed) break;
    }
}

function fxRecalcAll() {
    fxRecalcTable('items');
    $('.qt-config-block').each(function () { fxRecalcTable('config:' + $(this).attr('data-config')); });
    fxRecalcTable('costs');
    qtRecalc();
    $('.qt-config-block').each(function () { qcRecalcBlock(this); });
    qtCostRecalc();
    // Ringkasan: rumus nominal PPN.
    if ($('#qt-ppn-amt').data('fx-formula')) $('#qt-ppn-pct').val('');
    fxRecalcTable('summary');
    qtRecalc();
}

function fxCommit($input) {
    var val = $input.val().trim();
    var $wrap = $input.closest('.qt-fx-wrap');
    if (val.indexOf('=') === 0) {
        $input.data('fx-formula', val);
        $wrap.addClass('has-fx').removeClass('fx-error');
    } else {
        $input.data('fx-formula', null);
        $wrap.removeClass('has-fx').removeAttr('title');
    }
    $input.removeClass('fx-editing');
    fxSetReadonly($input);
    fxRecalcAll();
}

$(document).on('click', '.qt-fx-btn', function (e) {
    e.preventDefault();
    var $wrap = $(this).closest('.qt-fx-wrap');
    var $input = $wrap.find('input');
    if ($input.hasClass('fx-editing')) {
        fxCommit($input);
        return;
    }
    $input.attr('data-orig', $input.val())
        .prop('readonly', false)
        .addClass('fx-editing')
        .val($input.data('fx-formula') || '=');
    $input.focus();
    try { $input[0].setSelectionRange($input.val().length, $input.val().length); } catch (err) {}
});

$(document).on('keydown', '.qt-fx-wrap input.fx-editing', function (e) {
    var $input = $(this);
    if (e.key === 'Enter') { e.preventDefault(); fxCommit($input); }
    if (e.key === 'Escape') {
        e.preventDefault();
        var $wrap = $input.closest('.qt-fx-wrap');
        $input.val($input.attr('data-orig') || '').removeClass('fx-editing');
        fxSetReadonly($input);
        fxRecalcAll();
    }
});

function qtFmt(n) {
    var v = Number(n || 0);
    return v.toLocaleString('en-US', { maximumFractionDigits: 2, minimumFractionDigits: 0 });
}

function qtRecalc() {
    var subtotal = 0;
    $('#qt-items-body tr').each(function() {
        var qty = parseFloat(qtToRaw($(this).find('.qt-qty').val())) || 0;
        var price = parseFloat(qtToRaw($(this).find('.qt-price').val())) || 0;
        var amount = (qty > 0 && price > 0) ? qty * price : 0;
        $(this).find('.qt-amount').text(amount ? qtFmt(amount) : '');
        subtotal += amount;
    });
    subtotal = Math.round(subtotal * 100) / 100;

    var discPct = parseFloat(qtToRaw($('#qt-disc-pct').val())) || 0;
    var discAmt = parseFloat(qtToRaw($('#qt-disc-amt').val())) || 0;
    var discount = (discPct > 0 && !$('#qt-disc-amt').data('fx-formula')) ? Math.round(subtotal * discPct / 100 * 100) / 100 : discAmt;
    var netto = Math.round((subtotal - discount) * 100) / 100;

    var ppnPct = parseFloat(qtToRaw($('#qt-ppn-pct').val())) || 0;
    var ppnAmt = parseFloat(qtToRaw($('#qt-ppn-amt').val())) || 0;
    var ppn = (ppnPct > 0 && !$('#qt-ppn-amt').data('fx-formula')) ? Math.round(netto * ppnPct / 100 * 100) / 100 : ppnAmt;
    var total = Math.round((netto + ppn) * 100) / 100;

    if (discPct > 0 && !$('#qt-disc-amt').data('fx-formula')) {
        $('#qt-disc-amt').val(qtFormatInput(discount.toFixed(0)));
    }
    if (ppnPct > 0 && !$('#qt-ppn-amt').data('fx-formula')) {
        $('#qt-ppn-amt').val(qtFormatInput(ppn.toFixed(0)));
    }

    $('#qt-subtotal').text(qtFmt(subtotal));
    $('#qt-dpp').text(qtFmt(netto));
    $('#qt-total').text(qtFmt(total));
}

function addQtRow(item, parentKey) {
    var key = item._key || qtNewKey();
    var depth = 0;
    if (parentKey) {
        var parentRow = $('tr[data-key="' + parentKey + '"]');
        depth = (parseInt(parentRow.attr('data-depth')) || 0) + 1;
    }

    var html = '<tr data-key="' + key + '" data-parent="' + (parentKey || '') + '" data-depth="' + depth + '">';
    html += '<td class="text-center qt-row-col qt-row-num"></td>';
    html += '<td><input type="text" class="form-control form-control-sm qt-no" value="' + (item.item_no || '') + '" placeholder="1 / 1.1"></td>';
    html += '<td><div class="qt-desc-wrap" style="margin-left:' + (depth * 18) + 'px">';
    html += '<div class="qt-desc" contenteditable="true" data-placeholder="Deskripsi item...">' + (item.description || '') + '</div>';
    html += '<div class="qt-desc-toolbar">';
    html += '<button type="button" data-cmd="bold" title="Bold"><b>B</b></button>';
    html += '<button type="button" data-cmd="italic" title="Italic"><i>I</i></button>';
    html += '<button type="button" data-cmd="underline" title="Underline"><u>U</u></button>';
    html += '</div></div></td>';
    html += '<td><input type="text" inputmode="decimal" min="0" class="form-control form-control-sm qt-qty" data-fx-table="items" value="' + (item.qty != null ? item.qty : '') + '"></td>';
    html += '<td><input type="text" class="form-control form-control-sm qt-unit" value="' + (item.unit || '') + '"></td>';
    html += '<td><input type="text" inputmode="decimal" min="0" step="any" class="form-control form-control-sm qt-price text-end" data-fx-table="items" value="' + (item.price != null ? item.price : '') + '"></td>';
    html += '<td class="qt-amount text-end"></td>';
    html += '<td class="text-center">';
    html += '<button type="button" class="btn-icon" title="Add Item dari Config" onclick="openQtItemPicker(this)"><i class="fa fa-cart-plus"></i></button>';
    html += '<button type="button" class="btn-icon" title="Tambah Anak" onclick="addQtChild(this)"><i class="fa fa-plus"></i></button>';
    html += '<button type="button" class="btn-icon text-danger" title="Hapus" onclick="removeQtItem(this)"><i class="fa fa-trash"></i></button>';
    html += '</td></tr>';

    // Sisipkan langsung setelah keturunan terakhir dari parent (menjaga urutan DFS)
    if (parentKey) {
        var last = $('tr[data-key="' + parentKey + '"]');
        var stack = [parentKey];
        while (stack.length) {
            var cur = stack.pop();
            $('tr[data-key="' + cur + '"]').nextAll('tr').each(function() {
                var p = $(this).attr('data-parent');
                if (p === cur) {
                    last = this;
                    stack.push($(this).attr('data-key'));
                }
            });
        }
        $(html).insertAfter(last);
    } else {
        $('#qt-items-body').append(html);
    }
    $('#qt-items-empty').hide();
    qtFormatAllNumeric();
    qtRecalc();
    return key;
}

function addQtChild(btn) {
    var parentKey = null;
    if (btn) {
        parentKey = $(btn).closest('tr').attr('data-key');
    }
    addQtRow({ description: '', qty: '', price: '', unit: '', part_number: '', item_no: '', quote_configuration_id: '' }, parentKey);
}

function removeQtItem(btn) {
    var row = $(btn).closest('tr');
    var key = row.attr('data-key');
    // Hapus juga semua turunannya
    var toRemove = [];
    var walk = function(k) {
        $('tr[data-parent="' + k + '"]').each(function() {
            toRemove.push(this);
            walk($(this).attr('data-key'));
        });
    };
    walk(key);
    toRemove.forEach(function(el) { $(el).remove(); });
    row.remove();
    qtRecalc();
    qtSyncEmpty();
    qtRenumberRows();
}

function qtSyncEmpty() {
    if ($('#qt-items-body tr').length === 0) {
        $('#qt-items-empty').show();
    } else {
        $('#qt-items-empty').hide();
    }
}

function collectKeys() {
    var keys = [];
    $('#qt-items-body tr').each(function() { keys.push($(this).attr('data-key')); });
    return keys;
}

function qtCollectItems() {
    var items = [];
    $('#qt-items-body tr').each(function() {
        var $qty = $(this).find('.qt-qty');
        var $price = $(this).find('.qt-price');
        var formula = {};
        if ($qty.data('fx-formula')) formula.qty = $qty.data('fx-formula');
        if ($price.data('fx-formula')) formula.price = $price.data('fx-formula');
        items.push({
            _key: $(this).attr('data-key'),
            parent_key: $(this).attr('data-parent'),
            item_no: $(this).find('.qt-no').val(),
            quote_configuration_id: $(this).attr('data-config'),
            description: $(this).find('.qt-desc').html(),
            qty: qtToRaw($qty.val()),
            price: qtToRaw($price.val()),
            unit: $(this).find('.qt-unit').val(),
            formula: formula
        });
    });
    return items;
}

function qtFillForm(data) {
    $('#qt-task-id').val(data.task_id || '');
    $('#qt-to').val(data.to_name || '');
    $('#qt-address').val(data.address || '');
    $('#qt-attn').val(data.attn_name || '');
    $('#qt-phone').val(data.attn_phone || '');
    $('#qt-email').val(data.attn_email || '');
    $('#qt-from').val(data.from_name || '');
    $('#qt-contact-phone').val(data.contact_phone || '');
    if (data.date) $('#qt-date').val(data.date);
}

// ── List Configuration (Tab 2) ──

function buildConfigBlockHtml(configId, label, items, divisionId) {
    // Bangun pohon: roots = items tanpa parent; children dikelompokkan per parent_id.
    var byParent = {};
    items.forEach(function(it) {
        var p = it.parent_id ? String(it.parent_id) : 'root';
        if (!byParent[p]) byParent[p] = [];
        byParent[p].push(it);
    });
    var roots = byParent['root'] || [];

    // Kelompokkan root per kategori (urut kemunculan); root tanpa kategori = tanpa header.
    var catOrder = [];
    var catMap = {};
    var noCatRoots = [];
    roots.forEach(function(root) {
        var cat = root.category ? String(root.category) : '';
        if (cat === '') {
            noCatRoots.push(root);
        } else {
            if (!catMap[cat]) { catMap[cat] = []; catOrder.push(cat); }
            catMap[cat].push(root);
        }
    });

    var html = '<div class="qt-config-block mb-3 border rounded p-2" data-config="' + configId + '"' + (divisionId ? ' data-division="' + divisionId + '"' : '') + '>';
    html += '<div class="d-flex justify-content-between align-items-center mb-2">';
    html += '<strong style="font-size:13px">' + label + '</strong>';
    html += '<div class="d-flex gap-2">';
    html += '<button type="button" class="btn btn-primary btn-sm" onclick="openQtConfigProductPicker(this)"><i class="fa fa-plus me-1"></i> Tambah Item</button>';
    html += '<button type="button" class="btn btn-secondary btn-sm" onclick="addQtConfigRow(this)"><i class="fa fa-plus me-1"></i> Tambah Baris Manual</button>';
    html += '</div></div>';
    html += '<div class="table-responsive"><table class="table table-custom align-middle mb-0"><thead><tr>';
    html += '<th class="text-center qt-row-col">#</th><th style="width:200px">Part Number</th><th>Deskripsi</th><th style="width:90px">Qty</th><th style="width:150px">Unit Price</th><th class="text-end" style="width:130px">Amount</th><th class="text-center" style="width:90px">Aksi</th>';
    html += '</tr></thead><tbody>';

// Render baris satu item.
    var escAttr = function(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    };
    var renderOne = function(it, depth, visited, cat) {
        var key = String(it.id || it._key || it.__key);
        if (visited[key]) return;
        visited[key] = true;
        var parentKey = it.parent_id ? String(it.parent_id) : '';
        var fxQty = (it.formula && it.formula.qty) ? ' data-fx="' + String(it.formula.qty).replace(/"/g, '&quot;') + '"' : '';
        var fxPrice = (it.formula && it.formula.price) ? ' data-fx="' + String(it.formula.price).replace(/"/g, '&quot;') + '"' : '';
        html += '<tr class="qc-item" data-key="' + key + '" data-parent="' + parentKey + '" data-depth="' + depth + '" data-cat="' + escAttr(cat) + '">';
        html += '<td class="text-center qt-row-col qc-row-num"></td>';
        html += '<td style="padding-left:' + (depth * 24) + 'px"><input type="text" class="form-control form-control-sm qc-pn" value="' + (it.part_number || '') + '"></td>';
        html += '<td><div class="qt-desc-wrap" style="margin-left:' + (depth * 24) + 'px"><div class="qc-desc" contenteditable="true" data-placeholder="Deskripsi item...">' + (it.description || '') + '</div>';
        html += '<div class="qt-desc-toolbar"><button type="button" data-cmd="bold" title="Bold"><b>B</b></button><button type="button" data-cmd="italic" title="Italic"><i>I</i></button><button type="button" data-cmd="underline" title="Underline"><u>U</u></button></div></div></td>';
        html += '<td><input type="text" inputmode="decimal" min="0" class="form-control form-control-sm qc-qty" data-fx-table="config:' + configId + '"' + fxQty + ' value="' + (it.qty != null ? it.qty : '') + '"></td>';
        html += '<td><input type="text" inputmode="decimal" min="0" step="any" class="form-control form-control-sm qc-price text-end" data-fx-table="config:' + configId + '"' + fxPrice + ' value="' + (it.price != null ? it.price : '') + '"></td>';
        html += '<td class="qc-amount text-end"></td>';
        html += '<td class="text-center">';
        html += '<button type="button" class="btn-icon" title="Tambah Item dari Produk (child)" onclick="openQtConfigProductPicker(this)"><i class="fa fa-plus"></i></button>';
        html += '<button type="button" class="btn-icon text-danger" title="Hapus" onclick="removeQtConfigRow(this)"><i class="fa fa-trash"></i></button>';
        html += '</td></tr>';
    };

    // DFS walk: parent selalu dirender sbg baris (depth 0), lalu anak depth 1+ (hirarki).
    var walkRoot = function(root, visited, cat) {
        renderOne(root, 0, visited, cat);
        var kids = byParent[String(root.id || root._key || root.__key)] || [];
        var stack = [];
        kids.forEach(function(k) { stack.push({ it: k, depth: 1, cat: cat }); });
        while (stack.length) {
            var cur = stack.pop();
            renderOne(cur.it, cur.depth, visited, cur.cat);
            var kk = byParent[String(cur.it.id || cur.it._key || cur.it.__key)] || [];
            // Push kebalik agar urutan DFS tetap parent -> children.
            for (var i = kk.length - 1; i >= 0; i--) {
                stack.push({ it: kk[i], depth: cur.depth + 1, cat: cur.cat });
            }
        }
    };

    catOrder.forEach(function(cat) {
        html += '<tr class="qc-cat" data-cat="' + escAttr(cat) + '">';
        html += '<td colspan="7"><span>Category : ' + escAttr(cat) + '</span>';
        html += '<button type="button" class="btn-icon ms-2 qc-cat-btn" title="Tambah Item Anak" onclick="addQtConfigChildToCat(this)"><i class="fa fa-plus"></i></button>';
        html += '</td></tr>';
        var visited = {};
        (catMap[cat] || []).forEach(function(root) { walkRoot(root, visited, cat); });
    });

    var visitedNoCat = {};
    noCatRoots.forEach(function(root) { walkRoot(root, visitedNoCat, ''); });

    html += '<tr class="qc-total"><td colspan="6" class="text-end fw-bold">Total</td><td class="qc-total-val text-end fw-bold"></td></tr>';
    html += '</tbody></table></div></div>';

    return html;
}

function qcRecalcBlock(block) {
    var $block = $(block);
    $block.find('tr.qc-subtotal').remove();

    // Kumpulkan baris item (urut DFS) + bangun pohon parent -> children.
    var rows = [];
    $block.find('tr.qc-item').each(function() {
        rows.push({
            el: this,
            key: $(this).attr('data-key'),
            parent: $(this).attr('data-parent'),
            depth: parseInt($(this).attr('data-depth'), 10) || 0,
            amount: 0,
            hasKids: false
        });
    });

    var kids = {};
    rows.forEach(function(r) {
        var p = r.parent || '_root';
        if (!kids[p]) kids[p] = [];
        kids[p].push(r);
    });

    rows.forEach(function(r) {
        var qty = parseFloat(qtToRaw($(r.el).find('.qc-qty').val())) || 0;
        var price = parseFloat(qtToRaw($(r.el).find('.qc-price').val())) || 0;
        r.amount = (qty > 0 && price > 0) ? qty * price : 0;
        r.hasKids = (kids[r.key] || []).length > 0;
    });

    var keyIndex = {};
    rows.forEach(function(r, i) { keyIndex[r.key] = i; });

    // Subtotal parent = jumlah leaf seluruh subtree (rekursif); hanya children.
    var leafTotal = function(key) {
        var c = kids[key] || [];
        if (!c.length) {
            var idx = keyIndex[key];
            return idx != null ? rows[idx].amount : 0;
        }
        var s = 0;
        c.forEach(function(ch) { s += leafTotal(ch.key); });
        return s;
    };

    var total = 0;
    rows.forEach(function(r) {
        if (r.hasKids) {
            // Parent (punya child): dianggap header -> amount kosong.
            $(r.el).find('.qc-amount').text('');
        } else {
            // Leaf: tampilkan amount per baris.
            $(r.el).find('.qc-amount').text(r.amount ? qtFmt(r.amount) : '');
            total += r.amount;
        }
    });

    // Cari last descendant tiap parent -> sisipkan baris subtotal setelah subtree-nya.
    var parents = [];
    rows.forEach(function(r) {
        if (!r.hasKids) return;
        var i = keyIndex[r.key];
        var j = i + 1;
        while (j < rows.length && rows[j].depth > r.depth) { j++; }
        parents.push({ key: r.key, depth: r.depth, end: j - 1 });
    });

    // Urut dari subtree terdalam/terakhir lebih dulu agar urutan baris subtotal benar.
    parents.sort(function(a, b) {
        return a.end !== b.end ? a.end - b.end : b.depth - a.depth;
    });

    var totalRow = $block.find('.qc-total')[0];

    // Hitung SEMUA baris subtotal terlebih dahulu terhadap DOM asli (sebelum disisipkan)
    // agar boundary tetap akurat walau ada header kategori (qc-cat) di antara baris.
    var insertions = parents.map(function(p) {
        var lastDesc = rows[p.end].el;
        var boundary = lastDesc.nextElementSibling || totalRow;
        var sum = leafTotal(p.key);
        var pad = (p.depth + 1) * 24;
        var html = '<tr class="qc-subtotal" data-st-parent="' + p.key + '">';
        html += '<td></td>';
        html += '<td colspan="4" class="text-end fw-bold" style="padding-left:' + pad + 'px">Subtotal</td>';
        html += '<td class="qc-subtotal-val text-end fw-bold">' + (sum ? qtFmt(sum) : '') + '</td>';
        html += '<td></td>';
        html += '</tr>';
        return { html: html, boundary: boundary };
    });

    insertions.forEach(function(ins) {
        $(ins.html).insertBefore(ins.boundary);
    });

    $block.find('.qc-total-val').text(qtFmt(total));
}

function renderQtConfigLists(data) {
    var container = $('#qt-config-lists');
    container.empty();
    (data.configs || []).forEach(function(c) {
        var items = (data.items || []).filter(function(it) { return it.quote_configuration_id == c.id; });
        container.append(buildConfigBlockHtml(c.id, c.label, items, c.division_id));
    });
    $('#qt-configs-empty').hide();
    $('.qt-config-block').each(function() { qcRecalcBlock(this); });
    qtFormatAllNumeric();
}

var qtConfigKeySeq = 0;
function qtConfigNewKey() {
    return 'config-new-' + (++qtConfigKeySeq);
}

function qcRowHtml(configId, item, parentKey, key, cat) {
    var depth = 0;
    if (parentKey) {
        var parentRow = $('tr[data-key="' + parentKey + '"]');
        depth = (parseInt(parentRow.attr('data-depth')) || 0) + 1;
        if (cat === undefined) cat = parentRow.attr('data-cat');
    }
    if (cat === undefined) cat = '';
    if (cat == null) cat = '';
    item = item || {};
    var fxQty = (item.formula && item.formula.qty) ? ' data-fx="' + String(item.formula.qty).replace(/"/g, '&quot;') + '"' : '';
    var fxPrice = (item.formula && item.formula.price) ? ' data-fx="' + String(item.formula.price).replace(/"/g, '&quot;') + '"' : '';
    var html = '<tr class="qc-item" data-key="' + key + '" data-parent="' + (parentKey || '') + '" data-depth="' + depth + '" data-cat="' + String(cat).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '">';
    html += '<td class="text-center qt-row-col qc-row-num"></td>';
    html += '<td style="padding-left:' + (depth * 24) + 'px"><input type="text" class="form-control form-control-sm qc-pn" value="' + (item.part_number || '') + '"></td>';
    html += '<td><div class="qt-desc-wrap" style="margin-left:' + (depth * 24) + 'px"><div class="qc-desc" contenteditable="true" data-placeholder="Deskripsi item...">' + (item.description || '') + '</div>';
    html += '<div class="qt-desc-toolbar"><button type="button" data-cmd="bold" title="Bold"><b>B</b></button><button type="button" data-cmd="italic" title="Italic"><i>I</i></button><button type="button" data-cmd="underline" title="Underline"><u>U</u></button></div></div></td>';
    html += '<td><input type="text" inputmode="decimal" min="0" class="form-control form-control-sm qc-qty" data-fx-table="config:' + configId + '"' + fxQty + ' value="' + (item.qty != null ? item.qty : '') + '"></td>';
    html += '<td><input type="text" inputmode="decimal" min="0" step="any" class="form-control form-control-sm qc-price text-end" data-fx-table="config:' + configId + '"' + fxPrice + ' value="' + (item.price != null ? item.price : '') + '"></td>';
    html += '<td class="qc-amount text-end"></td>';
    html += '<td class="text-center">';
    html += '<button type="button" class="btn-icon" title="Tambah Item dari Produk (child)" onclick="openQtConfigProductPicker(this)"><i class="fa fa-plus"></i></button>';
    html += '<button type="button" class="btn-icon text-danger" title="Hapus" onclick="removeQtConfigRow(this)"><i class="fa fa-trash"></i></button>';
    html += '</td></tr>';
    return html;
}

function insertQcRowAfter(lastKey, html) {
    var last = $('tr[data-key="' + lastKey + '"]');
    var stack = [lastKey];
    while (stack.length) {
        var cur = stack.pop();
        $('tr[data-key="' + cur + '"]').nextAll('tr').each(function() {
            var p = $(this).attr('data-parent');
            if (p === cur) {
                last = this;
                stack.push($(this).attr('data-key'));
            }
        });
    }
    $(html).insertAfter(last);
}

function addQtConfigRow(btn) {
    var block = $(btn).closest('.qt-config-block');
    var configId = block.attr('data-config');
    var key = qtConfigNewKey();
    var html = qcRowHtml(configId, {}, '', key);
    $(html).insertBefore($(block).find('.qc-total'));
    qcRecalcBlock(block);
    qtFormatAllNumeric();
    qtRenumberRows();
}

function addQtConfigChild(btn) {
    var row = $(btn).closest('tr.qc-item');
    var block = row.closest('.qt-config-block');
    var configId = block.attr('data-config');
    var parentKey = row.attr('data-key');
    var key = qtConfigNewKey();
    var html = qcRowHtml(configId, {}, parentKey, key);
    insertQcRowAfter(parentKey, html);
    qcRecalcBlock(block);
    qtFormatAllNumeric();
    qtRenumberRows();
}

function addQtConfigChildToCat(btn) {
    var catRow = $(btn).closest('tr.qc-cat');
    var block = catRow.closest('.qt-config-block');
    var configId = block.attr('data-config');
    var key = qtConfigNewKey();
    var cat = catRow.attr('data-cat') || '';
    var html = qcRowHtml(configId, {}, '', key, cat);
    // Sisipkan baris baru tepat di bawah baris kategori.
    $(html).insertAfter(catRow);
    qcRecalcBlock(block);
    qtFormatAllNumeric();
    qtRenumberRows();
}

function removeQtConfigRow(btn) {
    var block = $(btn).closest('.qt-config-block');
    var row = $(btn).closest('tr.qc-item');
    var key = row.attr('data-key');
    var toRemove = [];
    var walk = function(k) {
        $('tr[data-parent="' + k + '"]').each(function() {
            toRemove.push(this);
            walk($(this).attr('data-key'));
        });
    };
    walk(key);
    toRemove.forEach(function(el) { $(el).remove(); });
    row.remove();
    qcRecalcBlock(block);
    qtRenumberRows();
}

function qtCollectConfigItems() {
    var items = [];
    $('.qt-config-block').each(function() {
        var cfgId = $(this).attr('data-config');
        $(this).find('tr.qc-item').each(function() {
            var $qty = $(this).find('.qc-qty');
            var $price = $(this).find('.qc-price');
            var formula = {};
            if ($qty.data('fx-formula')) formula.qty = $qty.data('fx-formula');
            if ($price.data('fx-formula')) formula.price = $price.data('fx-formula');
            items.push({
                _key: $(this).attr('data-key'),
                parent_key: $(this).attr('data-parent') || null,
                quote_configuration_id: cfgId,
                category: $(this).attr('data-cat') || null,
                part_number: $(this).find('.qc-pn').val(),
                description: $(this).find('.qc-desc').html(),
                qty: qtToRaw($qty.val()),
                price: qtToRaw($price.val()),
                unit: '',
                formula: formula
            });
        });
    });
    return items;
}

// ── Product Picker (Tab List Configuration) ──
let qtcfgTable = null;
let qtcfgModal = null;
let qtcfgSelected = {};
let qtcfgTargetParentKey = null;
let qtcfgBlock = null;
let qtcfgDivisionId = '';

function openQtConfigProductPicker(btn) {
    qtcfgBlock = $(btn).closest('.qt-config-block');
    qtcfgTargetParentKey = null;
    $('#qtcfg-target-label').text('');

    if ($(btn).closest('tr.qc-item').length) {
        var $row = $(btn).closest('tr.qc-item');
        qtcfgTargetParentKey = $row.attr('data-key');
        var pn = $row.find('.qc-pn').val() || 'baris ini';
        $('#qtcfg-target-label').text('→ sebagai item di bawah ' + pn);
    } else {
        $('#qtcfg-target-label').text('→ sebagai item utama (root)');
    }

    qtcfgDivisionId = qtcfgBlock.attr('data-division') || '';
    qtcfgSelected = {};

    if (qtcfgTable) qtcfgTable.ajax.reload();
    if (!qtcfgModal) {
        qtcfgModal = new bootstrap.Modal(document.getElementById('qtcfgProductPickerModal'));
    }
    qtcfgModal.show();
}

$(document).ready(function() {
    qtcfgTable = $('#qtcfg-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: qtCfgSearchUrl,
            data: function(d) {
                d.search = d.search || {};
                d.search.value = $('#qtcfg-search').val() || '';
                d.search.regex = false;
                d.division_id = qtcfgDivisionId || '';
            }
        },
        pageLength: 100,
        lengthChange: false,
        searching: false,
        paging: true,
        info: true,
        order: [[2, 'asc']],
        columns: [
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) {
                    return '<input type="checkbox" class="qtcfg-check" value="' + data + '">';
                }
            },
            { data: 'code', orderable: true, searchable: true,
                render: function(data) { return '<code style="color:var(--accent)">' + data + '</code>'; }
            },
            { data: 'name', orderable: true, searchable: true,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'brand', orderable: false, searchable: true,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'category', orderable: false, searchable: true,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'description', orderable: false, searchable: true,
                render: function(data) {
                    if (!data) return '<span style="color:var(--text-muted)">—</span>';
                    var escaped = $('<div>').text(data).html();
                    return escaped.replace(/\n/g, '<br>');
                }
            }
        ]
    });

    var qtcfgSearchTimer = null;
    $('#qtcfg-search').on('input', function() {
        clearTimeout(qtcfgSearchTimer);
        qtcfgSearchTimer = setTimeout(function() { qtcfgTable.ajax.reload(); }, 350);
    });

    $(document).on('click', '#qtcfg-table tbody tr', function(e) {
        if ($(e.target).is('input.qtcfg-check') || $(e.target).closest('input.qtcfg-check').length) {
            return;
        }
        var $cb = $(this).find('.qtcfg-check');
        if ($cb.length) {
            $cb.prop('checked', !$cb.prop('checked')).trigger('change');
        }
    });

    $(document).on('change', '.qtcfg-check', function() {
        var id = $(this).val();
        var rowData = qtcfgTable.row($(this).closest('tr')).data();
        if ($(this).is(':checked')) {
            if (rowData) {
                qtcfgSelected[id] = {
                    id: rowData.id,
                    code: rowData.code,
                    name: rowData.name,
                    category: rowData.category,
                    description: rowData.description,
                    price: rowData.price
                };
            }
        } else {
            delete qtcfgSelected[id];
        }
    });

    $(document).on('click', '#qtcfg-btn-add', function() {
        var selected = Object.values(qtcfgSelected);
        if (selected.length === 0) {
            toastr.error('Pilih minimal 1 product terlebih dahulu.');
            return;
        }
        if (!qtcfgBlock || !qtcfgBlock.length) {
            toastr.error('Konfigurasi target tidak ditemukan.');
            return;
        }
        var beforeTotal = $(qtcfgBlock).find('.qc-total');
        selected.forEach(function(p) {
            var configId = qtcfgBlock.attr('data-config');
            var key = qtConfigNewKey();
            var item = {
                part_number: p.code || '',
                description: p.description || p.name || '',
                qty: 1,
                price: p.price
            };
            var html = qcRowHtml(configId, item, qtcfgTargetParentKey, key);
            if (qtcfgTargetParentKey) {
                insertQcRowAfter(qtcfgTargetParentKey, html);
            } else {
                beforeTotal = $(html).insertBefore(beforeTotal);
            }
        });
        qcRecalcBlock(qtcfgBlock);
        qtFormatAllNumeric();
        qtRenumberRows();
        toastr.success(selected.length + ' product ditambahkan' + (qtcfgTargetParentKey ? ' sebagai item child.' : ' sebagai item utama.'));
        if (qtcfgModal) qtcfgModal.hide();
    });

    $(document).on('hidden.bs.modal', '#qtcfgProductPickerModal', function() {
        qtcfgSelected = {};
        qtcfgTargetParentKey = null;
        qtcfgBlock = null;
        qtcfgDivisionId = '';
        $('#qtcfg-search').val('');
        $('#qtcfg-target-label').text('');
        if (qtcfgTable) qtcfgTable.ajax.reload();
    });
});

// ── Biaya (Tab 3) ──

let qtCostKeySeq = 0;

function qtCostNewKey() {
    return 'cost-new-' + (++qtCostKeySeq);
}

function qtCostRecalc() {
    var total = 0;
    $('#qt-costs-body tr').each(function() {
        var qty = parseFloat(qtToRaw($(this).find('.qt-cost-qty').val())) || 0;
        var price = parseFloat(qtToRaw($(this).find('.qt-cost-price').val())) || 0;
        var amount = (qty > 0 && price > 0) ? qty * price : 0;
        $(this).find('.qt-cost-amount').text(amount ? qtFmt(amount) : '');
        total += amount;
    });
    $('#qt-cost-total').text(qtFmt(total));
}

function qtCostRowHtml(item, parentKey, isTitle) {
    var key = item._key || qtCostNewKey();
    var depth = 0;
    if (parentKey) {
        var parentRow = $('tr[data-key="' + parentKey + '"]');
        depth = (parseInt(parentRow.attr('data-depth')) || 0) + 1;
    }
    var desc = isTitle ? (item.title || '') : (item.description || '');
    var html = '<tr data-key="' + key + '" data-parent="' + (parentKey || '') + '" data-depth="' + depth + '" data-type="' + (isTitle ? 'title' : 'item') + '">';
    html += '<td class="text-center qt-row-col qt-cost-row-num"></td>';
    html += '<td><input type="text" class="form-control form-control-sm qt-cost-no" value="' + (item.item_no || '') + '" placeholder="1 / 1.1"></td>';
    html += '<td><div class="qt-desc-wrap" style="margin-left:' + (depth * 18) + 'px">';
    html += '<div class="qt-desc" contenteditable="true" data-placeholder="' + (isTitle ? 'Judul biaya...' : 'Deskripsi biaya...') + '">' + (desc || '') + '</div>';
    html += '<div class="qt-desc-toolbar">';
    html += '<button type="button" data-cmd="bold" title="Bold"><b>B</b></button>';
    html += '<button type="button" data-cmd="italic" title="Italic"><i>I</i></button>';
    html += '<button type="button" data-cmd="underline" title="Underline"><u>U</u></button>';
    html += '</div></div></td>';
    html += '<td><input type="text" inputmode="decimal" min="0" class="form-control form-control-sm qt-cost-qty" data-fx-table="costs"' + (item.formula && item.formula.qty ? ' data-fx="' + String(item.formula.qty).replace(/"/g, '&quot;') + '"' : '') + ' value="' + (isTitle ? '' : (item.qty != null ? item.qty : '')) + '"></td>';
    html += '<td><input type="text" class="form-control form-control-sm qt-cost-unit" value="' + (item.unit || '') + '"></td>';
    html += '<td><input type="text" inputmode="decimal" min="0" step="any" class="form-control form-control-sm qt-cost-price text-end" data-fx-table="costs"' + (item.formula && item.formula.price ? ' data-fx="' + String(item.formula.price).replace(/"/g, '&quot;') + '"' : '') + ' value="' + (isTitle ? '' : (item.price != null ? item.price : '')) + '"></td>';
    html += '<td class="qt-cost-amount text-end"></td>';
    html += '<td class="text-center">';
    html += '<button type="button" class="btn-icon" title="Tambah Judul" onclick="addQtCostTitle(this)"><i class="fa fa-tag"></i></button>';
    html += '<button type="button" class="btn-icon" title="Tambah Anak" onclick="addQtCostItem(this)"><i class="fa fa-plus"></i></button>';
    html += '<button type="button" class="btn-icon text-danger" title="Hapus" onclick="removeQtCostItem(this)"><i class="fa fa-trash"></i></button>';
    html += '</td></tr>';

    if (parentKey) {
        var last = $('tr[data-key="' + parentKey + '"]');
        var stack = [parentKey];
        while (stack.length) {
            var cur = stack.pop();
            $('tr[data-key="' + cur + '"]').nextAll('tr').each(function() {
                var p = $(this).attr('data-parent');
                if (p === cur) {
                    last = this;
                    stack.push($(this).attr('data-key'));
                }
            });
        }
        $(html).insertAfter(last);
    } else {
        $('#qt-costs-body').append(html);
    }
    $('#qt-costs-empty').hide();
    qtFormatAllNumeric();
    qtCostRecalc();
}

function addQtCostTitle(btn) {
    var parentKey = btn ? $(btn).closest('tr').attr('data-key') : null;
    qtCostRowHtml({ title: '', item_no: '' }, parentKey, true);
}

function addQtCostItem(btn) {
    var parentKey = btn ? $(btn).closest('tr').attr('data-key') : null;
    qtCostRowHtml({ description: '', qty: '', price: '', unit: '', item_no: '' }, parentKey, false);
}

function removeQtCostItem(btn) {
    var row = $(btn).closest('tr');
    var key = row.attr('data-key');
    var toRemove = [];
    var walk = function(k) {
        $('tr[data-parent="' + k + '"]').each(function() {
            toRemove.push(this);
            walk($(this).attr('data-key'));
        });
    };
    walk(key);
    toRemove.forEach(function(el) { $(el).remove(); });
    row.remove();
    qtCostRecalc();
    qtRenumberRows();
}

function qtCollectCostItems() {
    var items = [];
    $('#qt-costs-body tr').each(function() {
        var isTitle = $(this).attr('data-type') === 'title';
        var desc = $(this).find('.qt-desc').html();
        var $qty = $(this).find('.qt-cost-qty');
        var $price = $(this).find('.qt-cost-price');
        var formula = {};
        if ($qty.data('fx-formula')) formula.qty = $qty.data('fx-formula');
        if ($price.data('fx-formula')) formula.price = $price.data('fx-formula');
        items.push({
            _key: $(this).attr('data-key'),
            parent_key: $(this).attr('data-parent'),
            item_no: $(this).find('.qt-cost-no').val(),
            title: isTitle ? desc : '',
            description: isTitle ? '' : desc,
            qty: qtToRaw($qty.val()),
            price: qtToRaw($price.val()),
            unit: $(this).find('.qt-cost-unit').val(),
            formula: formula
        });
    });
    return items;
}

@php
    $editConfigs = $quotation ? $quotation->configurations->pluck('id')->all() : [];
    echo 'let qtSelectedConfigs = ['.implode(',', array_map('intval', $editConfigs)).'];';
    echo 'let qtInitialConfigData = '.json_encode([
        'configs' => $quotation
            ? $quotation->configurations->map(fn ($c) => [
                'id' => $c->id,
                'division_id' => $c->division_id,
                'label' => '#'.$c->id.' v'.$c->version.' — '.($c->division?->division_name ?? ''),
            ])->values()->all()
            : [],
        'items' => $configItems,
    ]).';';
@endphp

function qtFillFormKeepItems(data) {
    // Edit mode: hanya isi field kosong + render config (item tetap dari snapshot)
    qtFillForm(data);
}

$(document).ready(function() {
    // Ringkasan Harga hanya tampil saat tab "List Item Quotation" aktif.
    $(document).on('shown.bs.tab', 'button[data-bs-toggle="tab"]', function(e) {
        $('#qt-price-summary').toggle($(e.target).attr('data-bs-target') === '#qt-tab-items');
    });

    // Cegah Enter mentriger submit (kecuali textarea/contenteditable/ƒx editing).
    $(document).on('keydown', '#qt-form input, #qt-form select', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            return false;
        }
    });

    $('#qt-task').select2({
        placeholder: '— Pilih Task —',
        allowClear: true,
        width: '100%'
    });

    // Edit mode: render list configuration dari snapshot tersimpan.
    if (qtInitialConfigData && (qtInitialConfigData.configs || []).length) {
        renderQtConfigLists(qtInitialConfigData);
    }

    $('#qt-task').on('change', function() {
        var id = $(this).val();
        if (!id) {
            $('#qt-task-id').val('');
            return;
        }
        $.get(qtFetchTaskUrl, { task_id: id })
            .done(function(res) {
                if (res.success) {
                    qtTaskData = res.data;
                    qtSelectedConfigs = (res.data.configs || []).map(function(c) { return c.id; });

                    if (!$('#qt-edit-id').val()) {
                        // Create mode: hanya isi customer + render List Configuration (Tab 2).
                        // List Item Quotation (Tab 1) diisi manual oleh user.
                        qtFillForm(res.data);
                        renderQtConfigLists(res.data);
                    } else {
                        // Edit mode: isi field kosong saja, item tetap snapshot
                        qtFillFormKeepItems(res.data);
                        renderQtConfigLists(res.data);
                    }
                    toastr.success('Data task dimuat.');
                } else {
                    toastr.error(res.message || 'Gagal memuat task.');
                }
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat task.');
            });
    });

    $('#qt-template').on('change', function() {
        var id = $(this).val();
        if (!id) return;

        Swal.fire({
            title: 'Terapkan Template?',
            text: 'Seluruh item yang sudah terisi di List Item Quotation akan diganti dengan item dari template.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Terapkan',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) {
                $('#qt-template').val('');
                return;
            }

            $.get(qtFetchTemplateUrl, { quotation_id: id })
                .done(function(res) {
                    if (res.success) {
                        $('#qt-items-body').empty();
                        (res.data.items || []).forEach(function(it) {
                            addQtRow(it, it.parent_key);
                        });
                        qtRecalc();
                        qtSyncEmpty();
                        toastr.success('Template "'.concat(res.data.quotation_number || '', '" diterapkan.'));
                    } else {
                        toastr.error(res.message || 'Gagal memuat template.');
                    }
                })
                .fail(function(xhr) {
                    toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat template.');
                })
                .always(function() {
                    $('#qt-template').val('');
                });
        });
    });

    $('#qt-cost-template').on('change', function() {
        var id = $(this).val();
        if (!id) return;

        Swal.fire({
            title: 'Terapkan Template Biaya?',
            text: 'Seluruh biaya yang sudah terisi di Tab Biaya akan diganti dengan struktur dari template (harga dikosongkan untuk diisi manual).',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Terapkan',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) {
                $('#qt-cost-template').val('');
                return;
            }

            $.get(qtCostTemplateUrl, { quotation_id: id })
                .done(function(res) {
                    if (res.success) {
                        $('#qt-costs-body').empty();
                        (res.data.items || []).forEach(function(it) {
                            qtCostRowHtml(it, it.parent_key, !!it.title);
                        });
                        qtCostRecalc();
                        qtRenumberRows();
                        $('#qt-costs-empty').hide();
                        toastr.success('Template biaya "'.concat(res.data.quotation_number || '', '" diterapkan.'));
                    } else {
                        toastr.error(res.message || 'Gagal memuat template biaya.');
                    }
                })
                .fail(function(xhr) {
                    toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat template biaya.');
                })
                .always(function() {
                    $('#qt-cost-template').val('');
                });
        });
    });

    $(document).on('input', '#qt-items-body input, #qt-items-body textarea, #qt-items-body [contenteditable]', qtRecalc);

    $(document).on('input', '.qt-qty, .qt-price, .qc-qty, .qc-price, #qt-disc-amt, #qt-ppn-amt, .qt-cost-qty, .qt-cost-price', function() {
        if ($(this).hasClass('fx-editing')) return; // sedang edit rumus
        qtFormatNum(this);
        fxRecalcAll();
    });

    $(document).on('input', '#qt-disc-pct', function() {
        // Saat persen diisi, kosongkan nominal manual.
        $('#qt-disc-amt').val('');
        qtRecalc();
    });
    $(document).on('input', '#qt-disc-amt', function() {
        // Saat nominal manual diisi, kosongkan persen.
        if ($(this).val() !== '') {
            $('#qt-disc-pct').val('');
        }
        qtRecalc();
    });
    $(document).on('input', '#qt-ppn-pct', function() {
        $('#qt-ppn-amt').val('');
        qtRecalc();
    });
    $(document).on('input', '#qt-ppn-amt', function() {
        if ($(this).val() !== '') {
            $('#qt-ppn-pct').val('');
        }
        qtRecalc();
    });

    $(document).on('mousedown', '.qt-desc-toolbar button', function(e) {
        e.preventDefault();
    });

    $(document).on('click', '.qt-desc-toolbar button', function() {
        document.execCommand($(this).data('cmd'), false, null);
        var block = $(this).closest('.qt-config-block');
        if (block.length) {
            qcRecalcBlock(block);
        } else if ($(this).closest('#qt-costs-body').length) {
            qtCostRecalc();
        } else {
            $('#qt-items-body').trigger('input');
        }
    });
    $(document).on('input', '#qt-costs-body [contenteditable]', qtCostRecalc);
    $(document).on('input', '.qt-config-block input, .qt-config-block textarea, .qt-config-block [contenteditable]', function() {
        qcRecalcBlock($(this).closest('.qt-config-block'));
    });

    $('#qt-form').on('submit', function(e) {
        e.preventDefault();

        var items = qtCollectItems();
        if (items.length === 0) {
            toastr.error('Minimal 1 item wajib diisi.');
            return;
        }
        for (var i = 0; i < items.length; i++) {
            var plain = $('<div>').html(items[i].description || '').text().trim();
            if (!plain) {
                toastr.error('Deskripsi item wajib diisi.');
                return;
            }
        }

        var configIds = (qtSelectedConfigs || []).slice();
        if (configIds.length === 0) {
            toastr.error('Pilih minimal 1 configuration.');
            return;
        }

        var editId = $('#qt-edit-id').val();

        Swal.fire({
            title: editId ? 'Simpan Perubahan?' : 'Simpan Quotation?',
            text: 'Pastikan data sudah benar sebelum disimpan.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Simpan',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            var url = editId
                ? '{{ route("quotation.update", "__ID__") }}'.replace('__ID__', editId)
                : '{{ route("quotation.store") }}';
            var method = editId ? 'PUT' : 'POST';

            $('#qt-save-btn').prop('disabled', true);

            $.ajax({
                url: url,
                method: method,
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                data: $.extend({
                    task_id: $('#qt-task-id').val(),
                    quote_configuration_ids: configIds,
                    date: $('#qt-date').val(),
                    currency: $('#qt-currency').val(),
                    your_ref: $('#qt-your-ref').val(),
                    no_of_pages: $('#qt-pages').val(),
                    to_name: $('#qt-to').val(),
                    address: $('#qt-address').val(),
                    attn_name: $('#qt-attn').val(),
                    attn_phone: $('#qt-phone').val(),
                    attn_email: $('#qt-email').val(),
                    from_name: $('#qt-from').val(),
                    contact_phone: $('#qt-contact-phone').val(),
                    parameter_note: $('#qt-parameter').val(),
                    notes: $('#qt-notes').val(),
                    terms: $('#qt-terms').val(),
                    discount_percent: $('#qt-disc-pct').val(),
                    discount_amount: qtToRaw($('#qt-disc-amt').val()),
                    ppn_percent: $('#qt-ppn-pct').val(),
                    ppn_amount: qtToRaw($('#qt-ppn-amt').val()),
                    formula: {
                        ppn_amount: $('#qt-ppn-amt').data('fx-formula') || null
                    },
                    items: items,
                    config_items: qtCollectConfigItems(),
                    cost_items: qtCollectCostItems(),
                    cost_notes: $('#qt-cost-notes').val()
                }, { _token: '{{ csrf_token() }}' })
            }).done(function(res) {
                toastr.success(res.message || 'Quotation disimpan.');
                setTimeout(function() {
                    window.location.href = '{{ route("quotation.show", "__ID__") }}'.replace('__ID__', res.id || editId);
                }, 700);
            }).fail(function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan quotation.';
                var errors = xhr.responseJSON && xhr.responseJSON.errors;
                if (errors) {
                    var keys = Object.keys(errors);
                    if (keys.length) {
                        msg = errors[keys[0]][0];
                    }
                }
                toastr.error(msg);
            }).always(function() {
                $('#qt-save-btn').prop('disabled', false);
            });
        });
    });

    // Prefill on load (edit: task sudah tersimpan; create via ?task_id=):
    // muat data task untuk render checkbox config + isi field customer kosong.
    @if($quotation?->task_id || $preselected)
        var taskId = $('#qt-task-id').val();
        if (taskId) {
            $.get(qtFetchTaskUrl, { task_id: taskId })
                .done(function(res) {
                    if (res.success) {
                        qtTaskData = res.data;
                        var d = res.data;
                        if (!$('#qt-to').val()) $('#qt-to').val(d.to_name || '');
                        if (!$('#qt-address').val()) $('#qt-address').val(d.address || '');
                        if (!$('#qt-attn').val()) $('#qt-attn').val(d.attn_name || '');
                        if (!$('#qt-phone').val()) $('#qt-phone').val(d.attn_phone || '');
                        if (!$('#qt-email').val()) $('#qt-email').val(d.attn_email || '');
                        if (!$('#qt-from').val()) $('#qt-from').val(d.from_name || '');
                        if (!$('#qt-contact-phone').val()) $('#qt-contact-phone').val(d.contact_phone || '');
                        qtFillForm(d);
                    }
                });
        }
    @endif

    // Format semua input angka (pemisah ribuan) saat load (edit mode / prefill).
    qtFormatAllNumeric();

    qtRecalc();
    qtCostRecalc();
    fxRecalcAll();
});

// ── Add Item dari Config (Tab List Item Quotation) ──
let qtItemTargetKey = null;
let qtItemPickerInstance = null;

// Render deskripsi apa adanya: pertahankan bold/italic/underline + baris baru.
function qtRenderDesc(str) {
    str = String(str || '');
    str = str.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '')
             .replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '');
    str = str.replace(/<(div|p|section)\b[^>]*>/gi, '<br>')
             .replace(/<\/(div|p|section)\s*>/gi, '')
             .replace(/<li\b[^>]*>/gi, '<br>')
             .replace(/<\/li\s*>/gi, '');
    str = str.replace(/(<br\s*\/?>\s*){2,}/gi, '<br>');
    str = str.replace(/\r\n|\r|\n/g, '<br>');
    // Hapus tag selain whitelist (b/strong/i/em/u/br).
    str = str.replace(/<(?!\/?(?:b|strong|i|em|u|br)\b)[^>]*>/gi, '');
    return str.trim();
}

function makeQtDescReadonly(row) {
    var $wrap = $(row).find('.qt-desc-wrap');
    var $desc = $wrap.find('.qt-desc');
    $desc.prop('contenteditable', false);
    $wrap.addClass('qt-locked');
}

function makeQtDescEditable(row) {
    var $wrap = $(row).find('.qt-desc-wrap');
    var $desc = $wrap.find('.qt-desc');
    $desc.prop('contenteditable', true);
    $wrap.removeClass('qt-locked');
}

// Format price en-US tanpa desimal .00: 1000000 -> 1,000,000
function qtFmtPrice(v) {
    if (v === '' || v == null) return '';
    var n = Number(v);
    if (isNaN(n)) return String(v);
    return n.toLocaleString('en-US', { maximumFractionDigits: 2, minimumFractionDigits: 0 });
}

function openQtItemPicker(btn) {
    qtItemTargetKey = $(btn).closest('tr').attr('data-key');
    var container = $('#qt-item-picker-body');
    container.empty();

    // Peta config_id -> division_name.
    var configDivision = {};
    var addDivision = function(list) {
        (list || []).forEach(function(c) {
            if (c && c.id) configDivision[c.id] = c.division_name || 'Lainnya';
        });
    };
    addDivision(qtInitialConfigData && qtInitialConfigData.configs);
    addDivision(qtTaskData && qtTaskData.configs);
    $('.qt-config-block').each(function() {
        var cid = $(this).attr('data-config');
        var label = $(this).find('.d-flex strong').first().text().trim();
        var divPart = label.split('—').pop().trim();
        configDivision[cid] = divPart || 'Lainnya';
    });

    // Kumpulkan item config dari data task / snapshot / block yang dirender.
    var pool = [];
    var seen = {};
    var add = function(it, division) {
        var pn = it.part_number || '';
        var desc = it.description || it.name || '';
        var key = pn + '|' + desc + '|' + (it.qty || '') + '|' + (it.price || '');
        if (seen[key]) return;
        seen[key] = true;
        pool.push({ division: division || 'Lainnya', part_number: pn, description: desc, qty: it.qty, price: it.price });
    };

    var cfgItems = [];
    if (qtTaskData && qtTaskData.items) cfgItems = cfgItems.concat(qtTaskData.items);
    if (qtInitialConfigData && qtInitialConfigData.items) cfgItems = cfgItems.concat(qtInitialConfigData.items);

    // Dari blok config yang dirender.
    $('.qt-config-block tbody tr.qc-item').each(function() {
        var cid = $(this).closest('.qt-config-block').attr('data-config');
        add({
            part_number: $(this).find('.qc-pn').val(),
            description: $(this).find('.qc-desc').html(),
            qty: $(this).find('.qc-qty').val(),
            price: $(this).find('.qc-price').val()
        }, configDivision[cid] || 'Lainnya');
    });

    cfgItems.forEach(function(it) {
        add(it, configDivision[it.quote_configuration_id] || 'Lainnya');
    });

    // Group by division (urutan kemunculan pertama).
    var byDiv = {};
    var divOrder = [];
    pool.forEach(function(it) {
        var d = it.division;
        if (!byDiv[d]) { byDiv[d] = []; divOrder.push(d); }
        byDiv[d].push(it);
    });

    var html = '';
    divOrder.forEach(function(d) {
        html += '<tr style="background:#f1f5f9;font-weight:700;font-size:12px;color:var(--accent);text-transform:uppercase;letter-spacing:.5px">' +
            '<td colspan="4"><i class="fa fa-tag me-1"></i>' + $('<div>').text(d).html() + '</td></tr>';
        byDiv[d].forEach(function(it) {
            var idx = pool.indexOf(it);
            html += '<tr class="qt-item-picker-row" style="cursor:pointer" data-idx="' + idx + '">';
            html += '<td>' + $('<div>').text(it.part_number).html() + '</td>';
            html += '<td>' + qtRenderDesc(it.description) + '</td>';
            html += '<td class="text-center">' + (it.qty || '') + '</td>';
            html += '<td class="text-end">' + qtFmtPrice(it.price) + '</td>';
            html += '</tr>';
        });
    });
    if (!html) {
        html = '<tr><td colspan="4" class="text-center" style="color:var(--text-muted);padding:16px">Tidak ada item config.</td></tr>';
    }
    container.html(html);

    container.off('click', '.qt-item-picker-row').on('click', '.qt-item-picker-row', function() {
        var item = pool[$(this).data('idx')];
        if (!item) return;
        var target = $('tr[data-key="' + qtItemTargetKey + '"]');
        if (!target.length) return;
        target.find('.qt-desc').html(qtRenderDesc(item.description));
        target.find('.qt-qty').val(item.qty || '');
        target.find('.qt-price').val(item.price || '');
        target.find('.qt-desc-wrap').removeClass('qt-locked');
        target.find('.qt-desc').prop('contenteditable', true);
        makeQtDescReadonly(target);
        qtFormatAllNumeric();
        qtRecalc();
        if (qtItemPickerInstance) qtItemPickerInstance.hide();
        toastr.success('Item config diterapkan ke baris.');
    });

    if (!qtItemPickerInstance) {
        qtItemPickerInstance = new bootstrap.Modal(document.getElementById('qtItemPickerModal'));
    }
    qtItemPickerInstance.show();
}

</script>
@endsection

@push('modals')
<div class="modal fade" id="qtItemPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-cart-plus me-2" style="color:var(--accent)"></i>Add Item dari Configuration</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Part Number</th>
                                <th>Deskripsi</th>
                                <th style="width:60px" class="text-center">Qty</th>
                                <th style="width:130px" class="text-end">Price</th>
                            </tr>
                        </thead>
                        <tbody id="qt-item-picker-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="qtcfgProductPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-box-open me-2" style="color:var(--accent)"></i>Pilih Product
                    <small id="qtcfg-target-label" style="font-size:11px;color:var(--text-muted);font-weight:400"></small></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <input type="text" id="qtcfg-search" class="form-control" placeholder="Cari part number / name / brand / kategori...">
                </div>
                <div class="table-responsive">
                    <table id="qtcfg-table" class="table table-custom align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:40px"></th>
                                <th>Part Number</th>
                                <th>Name Produk</th>
                                <th>Brand</th>
                                <th>Category</th>
                                <th>Deskripsi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="qtcfg-btn-add">
                    <i class="fa fa-plus me-1"></i> Tambah
                </button>
            </div>
        </div>
    </div>
</div>
@endpush
