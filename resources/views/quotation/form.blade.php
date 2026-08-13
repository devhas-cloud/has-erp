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
        min-height: 58px;
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
                <div class="col-12" id="qt-configs-wrap" style="{{ $preselected ? '' : 'display:none' }}">
                    <label class="form-label">Configuration (gabungan IMS / WATER) <span class="text-danger">*</span></label>
                    <div id="qt-configs" class="d-flex flex-wrap gap-2"></div>
                    <small style="color:var(--text-muted)">Centang config yang ingin digabung sebagai sumber item quotation.</small>
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
                                        echo '<tr data-key="'.$key.'"'
                                            .' data-parent="'.$parentKey.'"'
                                            .' data-depth="'.$depth.'">';
                                        echo '<td><input type="text" class="form-control form-control-sm qt-no" value="'.e($item['item_no'] ?? '').'" placeholder="1 / 1.1"></td>';
                                        echo '<td><div class="qt-desc-wrap" style="margin-left:'.($depth * 18).'px">';
                                        echo '<div class="qt-desc" contenteditable="true" data-placeholder="Deskripsi item...">'.\App\Models\Quotation::renderDescription($item['description'] ?? '').'</div>';
                                        echo '<div class="qt-desc-toolbar">';
                                        echo '<button type="button" data-cmd="bold" title="Bold"><b>B</b></button>';
                                        echo '<button type="button" data-cmd="italic" title="Italic"><i>I</i></button>';
                                        echo '<button type="button" data-cmd="underline" title="Underline"><u>U</u></button>';
                                        echo '</div></div></td>';
                                        echo '<td><input type="text" inputmode="decimal" min="0" class="form-control form-control-sm qt-qty" value="'.($item['qty'] ?? '').'"></td>';
                                        echo '<td><input type="text" class="form-control form-control-sm qt-unit" value="'.e($item['unit'] ?? '').'"></td>';
                                        echo '<td><input type="text" inputmode="decimal" min="0" step="any" class="form-control form-control-sm qt-price text-end" value="'.($item['price'] ?? '').'"></td>';
                                        echo '<td class="qt-amount text-end"></td>';
                                        echo '<td class="text-center">';
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
            </div>
        </div>
    </div>

    <div class="card-custom fade-in mb-3">
        <div class="card-header-custom">
            <span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Ringkasan Harga</span>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="row g-2">
                        <div class="col-6" style="display:none"><label class="form-label">Notes / Catatan</label></div>
                        <div class="col-12" style="display:none">
                            <textarea class="form-control" rows="4" id="qt-notes" name="notes" placeholder="Catatan tambahan untuk customer (opsional)">{{ $quotation?->notes }}</textarea>
                        </div>
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
                                            <input type="text" inputmode="decimal" id="qt-ppn-amt" class="form-control form-control-sm text-end" min="0" step="any" placeholder="Nominal" style="width:150px" value="{{ $quotation?->ppn_amount }}">
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
    $('.qt-qty, .qt-price, .qc-qty, .qc-price, #qt-disc-amt, #qt-ppn-amt').each(function() {
        $(this).val(qtFormatInput($(this).val()));
    });
}

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
    var discount = discPct > 0 ? Math.round(subtotal * discPct / 100 * 100) / 100 : discAmt;
    var netto = Math.round((subtotal - discount) * 100) / 100;

    var ppnPct = parseFloat(qtToRaw($('#qt-ppn-pct').val())) || 0;
    var ppnAmt = parseFloat(qtToRaw($('#qt-ppn-amt').val())) || 0;
    var ppn = ppnPct > 0 ? Math.round(netto * ppnPct / 100 * 100) / 100 : ppnAmt;
    var total = Math.round((netto + ppn) * 100) / 100;

    if (discPct > 0) {
        $('#qt-disc-amt').val(qtFormatInput(discount.toFixed(0)));
    }
    if (ppnPct > 0) {
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
    html += '<td><input type="text" class="form-control form-control-sm qt-no" value="' + (item.item_no || '') + '" placeholder="1 / 1.1"></td>';
    html += '<td><div class="qt-desc-wrap" style="margin-left:' + (depth * 18) + 'px">';
    html += '<div class="qt-desc" contenteditable="true" data-placeholder="Deskripsi item...">' + (item.description || '') + '</div>';
    html += '<div class="qt-desc-toolbar">';
    html += '<button type="button" data-cmd="bold" title="Bold"><b>B</b></button>';
    html += '<button type="button" data-cmd="italic" title="Italic"><i>I</i></button>';
    html += '<button type="button" data-cmd="underline" title="Underline"><u>U</u></button>';
    html += '</div></div></td>';
    html += '<td><input type="text" inputmode="decimal" min="0" class="form-control form-control-sm qt-qty" value="' + (item.qty != null ? item.qty : '') + '"></td>';
    html += '<td><input type="text" class="form-control form-control-sm qt-unit" value="' + (item.unit || '') + '"></td>';
    html += '<td><input type="text" inputmode="decimal" min="0" step="any" class="form-control form-control-sm qt-price text-end" value="' + (item.price != null ? item.price : '') + '"></td>';
    html += '<td class="qt-amount text-end"></td>';
    html += '<td class="text-center">';
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
        items.push({
            _key: $(this).attr('data-key'),
            parent_key: $(this).attr('data-parent'),
            item_no: $(this).find('.qt-no').val(),
            quote_configuration_id: $(this).attr('data-config'),
            description: $(this).find('.qt-desc').html(),
            qty: qtToRaw($(this).find('.qt-qty').val()),
            price: qtToRaw($(this).find('.qt-price').val()),
            unit: $(this).find('.qt-unit').val()
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

    // Render checkbox config
    var wrap = $('#qt-configs');
    wrap.empty();
    (data.configs || []).forEach(function(c) {
        var isChecked = qtSelectedConfigs.indexOf(c.id) !== -1;
        var chk = $('<label class="form-check form-check-inline" style="font-size:13px">');
        chk.append('<input type="checkbox" class="form-check-input qt-config-chk" value="' + c.id + '" ' + (isChecked ? 'checked' : '') + '>');
        chk.append('<span class="form-check-label">' + c.label + '</span>');
        wrap.append(chk);
    });
    $('#qt-configs-wrap').show();
}

// ── List Configuration (Tab 2) ──

function buildConfigBlockHtml(configId, label, items) {
    var groups = {};
    var order = [];
    items.forEach(function(it) {
        var cat = it.category || '';
        if (!(cat in groups)) {
            groups[cat] = [];
            order.push(cat);
        }
        groups[cat].push(it);
    });

    var html = '<div class="qt-config-block mb-3 border rounded p-2" data-config="' + configId + '">';
    html += '<div class="d-flex justify-content-between align-items-center mb-2">';
    html += '<strong style="font-size:13px">' + label + '</strong>';
    html += '<button type="button" class="btn btn-secondary btn-sm" onclick="addQtConfigRow(this)"><i class="fa fa-plus me-1"></i> Tambah Item</button>';
    html += '</div>';
    html += '<div class="table-responsive"><table class="table table-custom align-middle mb-0"><thead><tr>';
    html += '<th style="width:30px">No</th><th style="width:200px">Part Number</th><th>Deskripsi</th><th style="width:100px">Qty</th><th style="width:200px">Unit Price</th><th class="text-end" style="width:130px">Amount</th>';
    html += '</tr></thead><tbody>';

    order.forEach(function(cat) {
        if (cat !== '') {
            html += '<tr class="qc-cat" data-cat="' + cat + '">';
            html += '<td colspan="6"><strong style="font-size:13px;color:var(--accent)">Category : ' + cat + '</strong>';
            html += '<button type="button" class="btn-icon ms-2" title="Tambah Item di Kategori Ini" onclick="addQtConfigRowToCat(this, \'' + qcEscapeAttr(cat) + '\')"><i class="fa fa-plus"></i></button>';
            html += '</td></tr>';
        }
        groups[cat].forEach(function(it) {
            html += '<tr class="qc-item" data-cat="' + cat + '">';
            html += '<td class="qc-no text-center"></td>';
            html += '<td><input type="text" class="form-control form-control-sm qc-pn" value="' + (it.part_number || '') + '"></td>';
            html += '<td><div class="qt-desc-wrap"><div class="qc-desc" contenteditable="true" data-placeholder="Deskripsi item...">' + (it.description || '') + '</div>';
            html += '<div class="qt-desc-toolbar"><button type="button" data-cmd="bold" title="Bold"><b>B</b></button><button type="button" data-cmd="italic" title="Italic"><i>I</i></button><button type="button" data-cmd="underline" title="Underline"><u>U</u></button></div></div></td>';
            html += '<td><input type="text" inputmode="decimal" min="0" class="form-control form-control-sm qc-qty" value="' + (it.qty != null ? it.qty : '') + '"></td>';
            html += '<td><input type="text" inputmode="decimal" min="0" step="any" class="form-control form-control-sm qc-price text-end" value="' + (it.price != null ? it.price : '') + '"></td>';
            html += '<td class="qc-amount text-end"></td>';
            html += '</tr>';
        });
        html += '<tr class="qc-sub" data-cat="' + cat + '"><td colspan="5" class="text-end"><strong>' + (cat ? 'Sub Total ' + cat : 'Sub Total') + '</strong></td><td class="qc-sub-val text-end"></td></tr>';
    });

    html += '<tr class="qc-total"><td colspan="5" class="text-end fw-bold">Total</td><td class="qc-total-val text-end fw-bold"></td></tr>';
    html += '</tbody></table></div></div>';

    return html;
}

function qcRecalcBlock(block) {
    var total = 0;
    var cats = {};
    $(block).find('tr.qc-item').each(function() {
        var qty = parseFloat(qtToRaw($(this).find('.qc-qty').val())) || 0;
        var price = parseFloat(qtToRaw($(this).find('.qc-price').val())) || 0;
        var amount = (qty > 0 && price > 0) ? qty * price : 0;
        $(this).find('.qc-amount').text(amount ? qtFmt(amount) : '');
        total += amount;
        var cat = $(this).attr('data-cat');
        cats[cat] = (cats[cat] || 0) + amount;
    });

    var no = 0;
    var curCat = null;
    $(block).find('tr.qc-item').each(function() {
        var cat = $(this).attr('data-cat');
        if (cat !== curCat) {
            curCat = cat;
            no = 0;
        }
        no++;
        $(this).find('.qc-no').text(no);
    });

    $(block).find('tr.qc-sub').each(function() {
        var cat = $(this).attr('data-cat');
        $(this).find('.qc-sub-val').text(qtFmt(cats[cat] || 0));
    });

    $(block).find('.qc-total-val').text(qtFmt(total));
}

function renderQtConfigLists(data) {
    var container = $('#qt-config-lists');
    container.empty();
    (data.configs || []).forEach(function(c) {
        var items = (data.items || []).filter(function(it) { return it.quote_configuration_id == c.id; });
        container.append(buildConfigBlockHtml(c.id, c.label, items));
    });
    $('#qt-configs-empty').hide();
    $('.qt-config-block').each(function() { qcRecalcBlock(this); });
    qtFormatAllNumeric();
}

function addQtConfigRow(btn) {
    var block = $(btn).closest('.qt-config-block');
    var html = '<tr class="qc-item" data-cat="">';
    html += '<td class="qc-no text-center"></td>';
    html += '<td><input type="text" class="form-control form-control-sm qc-pn"></td>';
    html += '<td><div class="qt-desc-wrap"><div class="qc-desc" contenteditable="true" data-placeholder="Deskripsi item..."></div>';
    html += '<div class="qt-desc-toolbar"><button type="button" data-cmd="bold" title="Bold"><b>B</b></button><button type="button" data-cmd="italic" title="Italic"><i>I</i></button><button type="button" data-cmd="underline" title="Underline"><u>U</u></button></div></div></td>';
    html += '<td><input type="text" inputmode="decimal" min="0" class="form-control form-control-sm qc-qty"></td>';
    html += '<td><input type="text" inputmode="decimal" min="0" step="any" class="form-control form-control-sm qc-price text-end"></td>';
    html += '<td class="qc-amount text-end"></td>';
    html += '</tr>';
    $(html).insertBefore($(block).find('.qc-total'));
    qcRecalcBlock(block);
    qtFormatAllNumeric();
}

// Escape nilai untuk aman diletakkan dalam atribut HTML/onclick.
function qcEscapeAttr(s) {
    return String(s || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// Tambah baris kosong ke dalam kategori tertentu (baris qc-cat) pada blok config.
function addQtConfigRowToCat(btn, cat) {
    var block = $(btn).closest('.qt-config-block');
    var catAttr = qcEscapeAttr(cat);
    var html = '<tr class="qc-item" data-cat="' + catAttr + '">';
    html += '<td class="qc-no text-center"></td>';
    html += '<td><input type="text" class="form-control form-control-sm qc-pn"></td>';
    html += '<td><div class="qt-desc-wrap"><div class="qc-desc" contenteditable="true" data-placeholder="Deskripsi item..."></div>';
    html += '<div class="qt-desc-toolbar"><button type="button" data-cmd="bold" title="Bold"><b>B</b></button><button type="button" data-cmd="italic" title="Italic"><i>I</i></button><button type="button" data-cmd="underline" title="Underline"><u>U</u></button></div></div></td>';
    html += '<td><input type="text" inputmode="decimal" min="0" class="form-control form-control-sm qc-qty"></td>';
    html += '<td><input type="text" inputmode="decimal" min="0" step="any" class="form-control form-control-sm qc-price text-end"></td>';
    html += '<td class="qc-amount text-end"></td>';
    html += '</tr>';

    var catRow = $(block).find('tr.qc-cat[data-cat="' + catAttr + '"]');
    var subRow = $(block).find('tr.qc-sub[data-cat="' + catAttr + '"]');
    if (subRow.length) {
        // Sisipkan sebelum baris Sub Total kategori tsb (di dalam kategori yang sama).
        $(html).insertBefore(subRow.first());
    } else if (catRow.length) {
        $(html).insertAfter(catRow.first());
    } else {
        $(html).insertBefore($(block).find('.qc-total'));
    }
    qcRecalcBlock(block);
    qtFormatAllNumeric();
}

function showConfigListBlock(configId) {
    if (!qtTaskData) return;
    var c = (qtTaskData.configs || []).find(function(x) { return x.id == configId; });
    if (!c) return;
    if ($('.qt-config-block[data-config="' + configId + '"]').length) return;
    var items = (qtTaskData.items || []).filter(function(it) { return it.quote_configuration_id == configId; });
    $('#qt-config-lists').append(buildConfigBlockHtml(configId, c.label, items));
    $('#qt-configs-empty').hide();
    qcRecalcBlock($('.qt-config-block[data-config="' + configId + '"]'));
    qtFormatAllNumeric();
}

function hideConfigListBlock(configId) {
    $('.qt-config-block[data-config="' + configId + '"]').remove();
}

function qtCollectConfigItems() {
    var items = [];
    $('.qt-config-block').each(function() {
        var cfgId = $(this).attr('data-config');
        $(this).find('tr.qc-item').each(function() {
            items.push({
                quote_configuration_id: cfgId,
                category: $(this).attr('data-cat'),
                part_number: $(this).find('.qc-pn').val(),
                description: $(this).find('.qc-desc').html(),
                qty: qtToRaw($(this).find('.qc-qty').val()),
                price: qtToRaw($(this).find('.qc-price').val()),
                unit: ''
            });
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
            $('#qt-configs').empty();
            $('#qt-configs-wrap').hide();
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

    $(document).on('change', '.qt-config-chk', function() {
        var configId = $(this).val();
        if ($(this).is(':checked')) {
            qtSelectedConfigs.push(configId);
            showConfigListBlock(configId);
        } else {
            qtSelectedConfigs = qtSelectedConfigs.filter(function(id) { return String(id) !== String(configId); });
            hideConfigListBlock(configId);
        }
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

    $(document).on('input', '#qt-items-body input, #qt-items-body textarea, #qt-items-body [contenteditable]', qtRecalc);

    $(document).on('input', '.qt-qty, .qt-price, .qc-qty, .qc-price, #qt-disc-amt, #qt-ppn-amt', function() {
        qtFormatNum(this);
        var block = $(this).closest('.qt-config-block');
        if (block.length) {
            qcRecalcBlock(block);
        } else {
            qtRecalc();
        }
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
        } else {
            $('#qt-items-body').trigger('input');
        }
    });
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

        var configIds = [];
        $('.qt-config-chk:checked').each(function() { configIds.push($(this).val()); });
        if (configIds.length === 0) {
            toastr.error('Pilih minimal 1 configuration.');
            return;
        }

        var editId = $('#qt-edit-id').val();
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
                items: items,
                config_items: qtCollectConfigItems()
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
});
</script>
@endsection
