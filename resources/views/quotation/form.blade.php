@extends('layouts.app')

@section('title', $quotation ? 'Edit Quotation #'.$quotation->id : 'Buat Quotation')
@section('page-title', $quotation ? 'Edit Quotation' : 'Buat Quotation')

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
                                        echo '<td><textarea class="form-control form-control-sm qt-desc" rows="3" style="margin-left:'.($depth * 18).'px" required>'.e($item['description'] ?? '').'</textarea></td>';
                                        echo '<td><input type="number" min="0" class="form-control form-control-sm qt-qty" value="'.($item['qty'] ?? '').'"></td>';
                                        echo '<td><input type="text" class="form-control form-control-sm qt-unit" value="'.e($item['unit'] ?? '').'"></td>';
                                        echo '<td><input type="number" min="0" step="any" class="form-control form-control-sm qt-price text-end" value="'.($item['price'] ?? '').'"></td>';
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
                            <textarea class="form-control" rows="9" id="qt-terms" name="terms" style="font-family:monospace;font-size:12px">{{ $terms }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <tbody>
                                <tr>
                                    <td style="width:50%">Subtotal</td>
                                    <td class="text-end fw-bold" id="qt-subtotal">0</td>
                                </tr>
                                <tr>
                                    <td>DPP Pajak</td>
                                    <td class="text-end" id="qt-dpp">0</td>
                                </tr>
                                <tr>
                                    <td>PPN</td>
                                    <td class="text-end" id="qt-ppn">0</td>
                                </tr>
                                <tr style="background:var(--accent-soft)">
                                    <td class="fw-bold">Full Amount</td>
                                    <td class="text-end fw-bold" style="color:var(--accent);font-size:16px" id="qt-total">0</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <small style="color:var(--text-muted)">Subtotal dihitung dari Qty × Unit Price setiap baris yang diisi keduanya (induk ikut dihitung bila diisi). PPN 11%; DPP = PPN / 12%; Full Amount = Subtotal + PPN.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn-accent" id="qt-save-btn">
            <i class="fa fa-save me-1"></i> <span>{{ $quotation ? 'Simpan Perubahan' : 'Simpan Quotation' }}</span>
        </button>
        <a href="{{ route('quotation.index') }}" class="btn btn-secondary">Batal</a>
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

function qtFmt(n) {
    var v = Number(n || 0);
    return v.toLocaleString('en-US', { maximumFractionDigits: 2, minimumFractionDigits: 0 });
}

function qtRecalc() {
    var subtotal = 0;
    $('#qt-items-body tr').each(function() {
        var qty = parseFloat($(this).find('.qt-qty').val()) || 0;
        var price = parseFloat($(this).find('.qt-price').val()) || 0;
        var amount = (qty > 0 && price > 0) ? qty * price : 0;
        $(this).find('.qt-amount').text(amount ? qtFmt(amount) : '');
        subtotal += amount;
    });
    var ppn = Math.round(subtotal * 0.11 * 100) / 100;
    var dpp = Math.round((ppn / 0.12) * 100) / 100;
    var total = subtotal + ppn;

    $('#qt-subtotal').text(qtFmt(subtotal));
    $('#qt-dpp').text(qtFmt(dpp));
    $('#qt-ppn').text(qtFmt(ppn));
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
    html += '<td><textarea class="form-control form-control-sm qt-desc" rows="3" style="margin-left:' + (depth * 18) + 'px" required>' + (item.description || '') + '</textarea></td>';
    html += '<td><input type="number" min="0" class="form-control form-control-sm qt-qty" value="' + (item.qty != null ? item.qty : '') + '"></td>';
    html += '<td><input type="text" class="form-control form-control-sm qt-unit" value="' + (item.unit || '') + '"></td>';
    html += '<td><input type="number" min="0" step="any" class="form-control form-control-sm qt-price text-end" value="' + (item.price != null ? item.price : '') + '"></td>';
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
            description: $(this).find('.qt-desc').val(),
            qty: $(this).find('.qt-qty').val(),
            price: $(this).find('.qt-price').val(),
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
            html += '<td><textarea class="form-control form-control-sm qc-desc" rows="2">' + (it.description || '') + '</textarea></td>';
            html += '<td><input type="number" min="0" class="form-control form-control-sm qc-qty" value="' + (it.qty != null ? it.qty : '') + '"></td>';
            html += '<td><input type="number" min="0" step="any" class="form-control form-control-sm qc-price text-end" value="' + (it.price != null ? it.price : '') + '"></td>';
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
        var qty = parseFloat($(this).find('.qc-qty').val()) || 0;
        var price = parseFloat($(this).find('.qc-price').val()) || 0;
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
}

function addQtConfigRow(btn) {
    var block = $(btn).closest('.qt-config-block');
    var html = '<tr class="qc-item" data-cat="">';
    html += '<td class="qc-no text-center"></td>';
    html += '<td><input type="text" class="form-control form-control-sm qc-pn"></td>';
    html += '<td><textarea class="form-control form-control-sm qc-desc" rows="2"></textarea></td>';
    html += '<td><input type="number" min="0" class="form-control form-control-sm qc-qty"></td>';
    html += '<td><input type="number" min="0" step="any" class="form-control form-control-sm qc-price text-end"></td>';
    html += '<td class="qc-amount text-end"></td>';
    html += '</tr>';
    $(html).insertBefore($(block).find('.qc-total'));
    qcRecalcBlock(block);
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
    html += '<td><textarea class="form-control form-control-sm qc-desc" rows="2"></textarea></td>';
    html += '<td><input type="number" min="0" class="form-control form-control-sm qc-qty"></td>';
    html += '<td><input type="number" min="0" step="any" class="form-control form-control-sm qc-price text-end"></td>';
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
                description: $(this).find('.qc-desc').val(),
                qty: $(this).find('.qc-qty').val(),
                price: $(this).find('.qc-price').val(),
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

    $(document).on('input', '#qt-items-body input, #qt-items-body textarea', qtRecalc);
    $(document).on('input', '.qt-config-block input, .qt-config-block textarea', function() {
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
            if (!items[i].description || !items[i].description.trim()) {
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

    qtRecalc();
});
</script>
@endsection
