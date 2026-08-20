@extends('layouts.app')

@section('title', $quotation ? 'Edit Quote Configuration #'.$quotation->id : 'Buat Quote Configuration')
@section('page-title', $quotation ? 'Edit Quote Configuration' : 'Buat Quote Configuration')

@section('styles')
<style>
    #pp-table tbody tr {
        cursor: pointer;
        transition: background-color .15s ease;
    }
    #pp-table tbody tr:hover {
        background-color: #d1fae5 !important;
    }
    #pp-table tbody tr:hover td {
        color: #065f46;
    }
    #pp-table tbody tr:hover code {
        color: #065f46;
    }
    .wc-desc[contenteditable="true"] {
        border: 1px solid var(--card-border, #ced4da);
        border-radius: .25rem;
        padding: .25rem .5rem;
        min-height: 34px;
        background: #fff;
        font-size: .85rem;
        line-height: 1.45;
        white-space: pre-wrap;
    }
    .wc-desc[contenteditable="true"]:focus {
        outline: none;
        border-color: var(--accent);
    }
    .wc-desc[contenteditable="true"]:empty::before {
        content: attr(data-placeholder);
        color: #999;
    }
    .wc-desc-wrap { position: relative; }
    .wc-desc-toolbar {
        position: absolute;
        top: 2px;
        right: 4px;
        display: flex;
        gap: 2px;
        opacity: .55;
    }
    .wc-desc-toolbar button {
        border: 1px solid var(--card-border, #ced4da);
        background: #fff;
        border-radius: 3px;
        font-size: 11px;
        line-height: 1;
        padding: 2px 5px;
        cursor: pointer;
        color: var(--text-primary);
    }
    .wc-desc-toolbar button:hover { background: var(--bg); opacity: 1; }
    .wc-desc-wrap:hover .wc-desc-toolbar { opacity: 1; }
    .wc-cat[contenteditable="true"] {
        border: 1px solid var(--card-border, #ced4da);
        border-radius: .25rem;
        padding: .25rem .5rem;
        min-height: 34px;
        background: #fff;
        font-size: .85rem;
        line-height: 1.45;
        white-space: pre-wrap;
    }
    .wc-cat[contenteditable="true"]:focus {
        outline: none;
        border-color: var(--accent);
    }
    .wc-cat[contenteditable="true"]:empty::before {
        content: attr(data-placeholder);
        color: #999;
    }
    #wc-items-table { table-layout: fixed; }
    #wc-items-table .wc-part-display { word-break: break-word; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $quotation ? 'Edit Quote Configuration #'.$quotation->id : 'Buat Quote Configuration' }}</h1>
        <p class="page-header-sub">Pilih task quote, data customer otomatis terambil. Item part disusun hierarki — baris parent bisa punya child melalui tombol ＋, lalu simpan sebagai draft.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('water-configuration.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<form id="wc-form" autocomplete="off">
    <input type="hidden" id="wc-edit-id" value="{{ $quotation?->id }}">

    <div class="card-custom fade-in mb-3">
        <div class="card-header-custom">
            <span><i class="fa-solid fa-tasks me-2" style="color:var(--accent)"></i>Task Quote</span>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Pilih Task Quote <span class="text-danger">*</span></label>
                    <select id="wc-task" class="form-select" style="width:100%">
                        <option value="">— Pilih Task —</option>
                        @foreach($tasks as $task)
                            <option value="{{ $task->id }}"
                                {{ $quotation?->task_id == $task->id ? 'selected' : '' }}>
                                {{ $task->opportunity?->opportunity_name ?? $task->title }}
                                {{ $task->opportunity?->accountCompany?->account_name ? ' (' . $task->opportunity->accountCompany->account_name . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="task_id" id="wc-task-id" value="{{ $quotation?->task_id }}">
                    <input type="hidden" name="opportunity_id" id="wc-opportunity-id" value="{{ $quotation?->opportunity_id }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal</label>
                    <input type="date" id="wc-date" name="date" class="form-control" value="{{ $quotation?->date?->format('Y-m-d') }}">
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
                    <input type="text" class="form-control" id="wc-to" value="{{ $quotation?->location }}" readonly>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" rows="2" id="wc-address" readonly>{{ $quotation?->address }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">PIC Name</label>
                    <input type="text" class="form-control" id="wc-pic-name" value="{{ $quotation?->pic_name }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">PIC Phone</label>
                    <input type="text" class="form-control" id="wc-pic-phone" value="{{ $quotation?->pic_phone }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">PIC Email</label>
                    <input type="text" class="form-control" id="wc-pic-email" value="{{ $quotation?->pic_email }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sales (Pemberi Task)</label>
                    <input type="text" class="form-control" id="wc-sales" value="{{ $quotation?->sales_name }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Parameter</label>
                    <input type="text" id="wc-parameter" name="parameter_note" class="form-control" placeholder="cth: pH, Ammonia, COD, TSS dan Debit" value="{{ $quotation?->parameter_note }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card-custom fade-in mb-3">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>List Part Instrument</span>
            <div class="d-flex gap-2 align-items-center">
                @if(! $quotation)
                <select id="wc-template" class="form-select form-select-sm" style="width:auto">
                    <option value="">— Pilih Template (Configuration) —</option>
                    @foreach($templates as $tpl)
                        <option value="{{ $tpl['id'] }}">{{ $tpl['label'] }}</option>
                    @endforeach
                </select>
                @endif
                <button type="button" class="btn btn-primary btn-sm" onclick="openProductPickerAsParent()">
                    <i class="fa fa-plus me-1"></i> Tambah Item
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addItemRow({}, null)">
                    <i class="fa fa-plus me-1"></i> Tambah Baris Manual
                </button>
            </div>
        </div>
        <div class="card-body-custom p-2">
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0" id="wc-items-table">
                    <thead>
                        <tr>
                            <th style="width:50px">No</th>
                            <th style="width:120px">Produk (Part Number)</th>
                            <th style="width:120px">Kategori</th>
                            <th style="width:300px">Deskripsi</th>
                            <th style="width:55px">Qty</th>
                            <th class="text-center" style="width:150px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="wc-items-body">
                        @php
                            $rendered = [];
                            $wcKeySeq = 0;
                            $wcByParent = $items ? collect($items)->groupBy(fn ($it) => $it->parent_id ?? 'root') : collect();
                            $wcRenderItem = function ($item, $depth, $wcByParent, &$rendered) use (&$wcRenderItem, &$wcKeySeq) {
                                if ($item->id && in_array($item->id, $rendered)) {
                                    return;
                                }
                                if ($item->id) {
                                    $rendered[] = $item->id;
                                }
                                $key = $item->id ? 'db-'.$item->id : 'new-'.(++$wcKeySeq);
                                $parentKey = $item->parent_id ? 'db-'.$item->parent_id : '';
                                $isParent = $depth === 0;
                                echo '<tr data-key="'.$key.'" data-parent="'.$parentKey.'" data-depth="'.$depth.'" class="wc-item-row">';
                                echo '<td><input type="text" class="form-control form-control-sm wc-item-no text-center" value="'.e($item->item_no ?? '').'" placeholder="1 / 1.1"></td>';
                                echo '<td><input type="hidden" class="wc-product-id" value="'.$item->product_id.'">';
                                echo '<input type="hidden" class="wc-part" value="'.e($item->part_number).'">';
                                echo '<input type="hidden" class="wc-category-data" value="'.e($item->category).'">';
                                echo '<code class="wc-part-display" style="color:var(--accent)">'.e($item->part_number ?: ($item->product?->code ?: '—')).'</code></td>';
                                if ($isParent) {
                                    echo '<td><div class="form-control form-control-sm wc-cat" contenteditable="true" data-placeholder="Kategori">'.e($item->category).'</div></td>';
                                } else {
                                    echo '<td></td>';
                                }
                                echo '<td><div class="wc-desc-wrap" style="margin-left:'.($depth * 18).'px">';
                                echo '<div class="form-control form-control-sm wc-desc" contenteditable="true" data-placeholder="Deskripsi item (wajib)">'.\App\Models\Quotation::renderDescription($item->description).'</div>';
                                echo '<div class="wc-desc-toolbar">';
                                echo '<button type="button" data-cmd="bold" title="Bold"><b>B</b></button>';
                                echo '<button type="button" data-cmd="italic" title="Italic"><i>I</i></button>';
                                echo '<button type="button" data-cmd="underline" title="Underline"><u>U</u></button>';
                                echo '</div></div></td>';
                                echo '<td class="text-center"><input type="number" class="form-control form-control-sm wc-qty text-center" value="'.$item->qty.'"></td>';
                                echo '<td class="text-center">';
                                echo '<div class="d-flex justify-content-center gap-1">';
                                echo '<button type="button" class="btn btn-sm btn-soft wc-move-up" title="Naik"><i class="fa fa-chevron-up"></i></button>';
                                echo '<button type="button" class="btn btn-sm btn-soft wc-move-down" title="Turun"><i class="fa fa-chevron-down"></i></button>';
                                echo '<button type="button" class="btn btn-sm btn-soft" onclick="openProductPicker(this)" title="Tambah Item di bawah baris ini"><i class="fa fa-plus" style="color:var(--accent)"></i></button>';
                                echo '<button type="button" class="btn btn-sm btn-soft" onclick="removeItemRow(this)" title="Hapus"><i class="fa fa-trash" style="color:var(--danger)"></i></button>';
                                echo '</div></td></tr>';
                                foreach (($wcByParent[$item->id] ?? []) as $child) {
                                    $wcRenderItem($child, $depth + 1, $wcByParent, $rendered);
                                }
                            };
                        @endphp
                        @foreach($wcByParent['root'] ?? [] as $item)
                            @php($wcRenderItem($item, 0, $wcByParent, $rendered))
                        @endforeach
                        @foreach($items ?? [] as $item)
                            @if(! in_array($item->id, $rendered))
                                @php($wcRenderItem($item, 0, $wcByParent, $rendered))
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card-custom fade-in mb-3">
        <div class="card-header-custom">
            <span><i class="fa-solid fa-note-sticky me-2" style="color:var(--accent)"></i>Catatan</span>
        </div>
        <div class="card-body-custom">
            <textarea id="wc-notes" name="notes" class="form-control" rows="3" placeholder="Catatan (opsional)">{{ $quotation?->notes }}</textarea>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('water-configuration.index') }}" class="btn btn-secondary">Batal</a>
        <button type="button" class="btn-accent" id="btn-save-wc">
            <i class="fa fa-save me-1"></i> Simpan Draft
        </button>
    </div>
</form>

@endsection

@push('modals')
<div class="modal fade" id="productPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-box-open me-2" style="color:var(--accent)"></i>Pilih Part Instrument
                    <small id="pp-target-label" style="font-size:11px;color:var(--text-muted);font-weight:400"></small></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <input type="text" id="pp-search" class="form-control" placeholder="Cari part number / brand / kategori...">
                </div>
                <div class="table-responsive">
                    <table id="pp-table" class="table table-custom align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:40px"></th>
                                <th>Part Number</th>
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
                <button type="button" class="btn btn-primary btn-sm" id="btn-pp-add">
                    <i class="fa fa-plus me-1"></i> Tambah
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let wcRowIndex = {{ count($items) }};
let ppTable = null;
let ppModalInstance = null;
let existingProductIds = [];
let ppSelected = {};
let ppTargetParentKey = null;

const wcStoreUrl = '{{ route("water-configuration.store") }}';
const wcUpdateUrl = '{{ route("water-configuration.update", "__ID__") }}';
const wcIndexUrl = '{{ route("water-configuration.index") }}';
const wcSearchUrl = '{{ route("water-configuration.search-products") }}';
const wcFetchTaskUrl = '{{ route("water-configuration.fetch-task") }}';
const wcFetchTemplateUrl = '{{ route("water-configuration.fetch-template", "__ID__") }}';

function applyTaskData(data) {
    $('#wc-task-id').val(data.task_id || '');
    $('#wc-opportunity-id').val(data.opportunity_id || '');
    $('#wc-to').val(data.location || '');
    $('#wc-address').val(data.address || '');
    $('#wc-pic-name').val(data.pic_name || '');
    $('#wc-pic-phone').val(data.pic_phone || '');
    $('#wc-pic-email').val(data.pic_email || '');
    $('#wc-sales').val(data.sales_name || '');
    if (data.date && !$('#wc-date').val()) {
        $('#wc-date').val(data.date);
    }
}

$(document).on('change', '#wc-task', function() {
    var taskId = $(this).val();
    if (!taskId) {
        applyTaskData({
            task_id: '', opportunity_id: '', to_name: '',
            address: '', pic_name: '', pic_phone: '', pic_email: '', sales_name: '', date: ''
        });
        return;
    }

    $.ajax({
        url: wcFetchTaskUrl,
        data: { task_id: taskId },
        dataType: 'json',
        success: function(res) {
            applyTaskData(res.data || {});
        },
        error: function(xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat data task.';
            toastr.error(msg);
        }
    });
});

function openProductPicker(btn) {
    ppTargetParentKey = null;
    $('#pp-target-label').text('');
    if (btn) {
        var $row = $(btn).closest('tr.wc-item-row');
        ppTargetParentKey = $row.attr('data-key');
        var part = $row.find('.wc-part').val() || $row.find('.wc-part-display').text().trim();
        $('#pp-target-label').text('→ sebagai item di bawah ' + (part || 'baris ini'));
    }

    ppSelected = {};
    existingProductIds = [];
    $('#wc-items-body .wc-product-id').each(function() {
        var v = $(this).val();
        if (v) existingProductIds.push(v);
    });
    if (ppTable) {
        ppTable.ajax.reload();
    }
    if (!ppModalInstance) {
        ppModalInstance = new bootstrap.Modal(document.getElementById('productPickerModal'));
    }
    ppModalInstance.show();
}

function openProductPickerAsParent() {
    ppTargetParentKey = null;
    $('#pp-target-label').text('→ sebagai parent (baris utama)');

    ppSelected = {};
    existingProductIds = [];
    $('#wc-items-body .wc-product-id').each(function() {
        var v = $(this).val();
        if (v) existingProductIds.push(v);
    });
    if (ppTable) {
        ppTable.ajax.reload();
    }
    if (!ppModalInstance) {
        ppModalInstance = new bootstrap.Modal(document.getElementById('productPickerModal'));
    }
    ppModalInstance.show();
}

function addItemRow(item, parentKey) {
    item = item || {};
    var key;
    if (item._key) {
        key = item._key;
    } else {
        wcRowIndex++;
        key = 'new-' + wcRowIndex;
    }
    var depth = 0;
    if (parentKey) {
        var parentRow = $('tr[data-key="' + parentKey + '"]');
        depth = (parseInt(parentRow.attr('data-depth')) || 0) + 1;
    }
    var isParent = !parentKey;

    var html = '<tr data-key="' + key + '" data-parent="' + (parentKey || '') + '" data-depth="' + depth + '" class="wc-item-row">';
    html += '<td><input type="text" class="form-control form-control-sm wc-item-no text-center" value="' + (item.item_no || '') + '" placeholder="1 / 1.1"></td>';
    html += '<td><input type="hidden" class="wc-product-id" value="' + (item.id || item.product_id || '') + '">';
    html += '<input type="hidden" class="wc-part" value="' + (item.code || item.part_number || '') + '">';
    html += '<input type="hidden" class="wc-category-data" value="' + (item.category || '') + '">';
    html += '<code class="wc-part-display" style="color:var(--accent)">' + (item.code || item.part_number || '—') + '</code></td>';
    if (isParent) {
        html += '<td><div class="form-control form-control-sm wc-cat" contenteditable="true" data-placeholder="Kategori">' + (item.category || '') + '</div></td>';
    } else {
        html += '<td></td>';
    }
    html += '<td><div class="wc-desc-wrap" style="margin-left:' + (depth * 18) + 'px">';
    html += '<div class="form-control form-control-sm wc-desc" contenteditable="true" data-placeholder="Deskripsi item (wajib)">' + (item.description || item.name || '') + '</div>';
    html += '<div class="wc-desc-toolbar">';
    html += '<button type="button" data-cmd="bold" title="Bold"><b>B</b></button>';
    html += '<button type="button" data-cmd="italic" title="Italic"><i>I</i></button>';
    html += '<button type="button" data-cmd="underline" title="Underline"><u>U</u></button>';
    html += '</div></div></td>';
    html += '<td class="text-center"><input type="number" class="form-control form-control-sm wc-qty text-center" value="' + (item.qty != null && item.qty !== '' ? item.qty : 1) + '"></td>';
    html += '<td class="text-center"><div class="d-flex justify-content-center gap-1">';
    html += '<button type="button" class="btn btn-sm btn-soft wc-move-up" title="Naik"><i class="fa fa-chevron-up"></i></button>';
    html += '<button type="button" class="btn btn-sm btn-soft wc-move-down" title="Turun"><i class="fa fa-chevron-down"></i></button>';
    html += '<button type="button" class="btn btn-sm btn-soft" onclick="openProductPicker(this)" title="Tambah Item di bawah baris ini"><i class="fa fa-plus" style="color:var(--accent)"></i></button>';
    html += '<button type="button" class="btn btn-sm btn-soft" onclick="removeItemRow(this)" title="Hapus"><i class="fa fa-trash" style="color:var(--danger)"></i></button>';
    html += '</div></td>';
    html += '</tr>';

    // Sisipkan DFS setelah keturunan terakhir dari parent (menjaga urutan).
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
        $('#wc-items-body').append(html);
    }
    refreshItems();
    return key;
}

function removeItemRow(btn) {
    var row = $(btn).closest('tr.wc-item-row');
    var key = row.attr('data-key');
    // Hapus juga semua turunannya.
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
    refreshItems();
}

function moveItemRow($row, dir) {
    var parent = $row.attr('data-parent') || '';
    var selector = 'tr.wc-item-row[data-parent="' + parent + '"]';
    if (dir === 'up') {
        var $prev = $row.prevAll(selector).first();
        if ($prev.length) {
            $row.insertBefore($prev);
        }
    } else {
        var $next = $row.nextAll(selector).first();
        if ($next.length) {
            $row.insertAfter($next);
        }
    }
}

function setEmptyState() {
    var $empty = $('#wc-items-body tr.wc-empty-row');
    if ($('#wc-items-body tr.wc-item-row').length === 0) {
        if ($empty.length === 0) {
            $('#wc-items-body').html(
                '<tr class="wc-empty-row"><td colspan="6" class="text-center" style="padding:24px;color:var(--text-muted);font-size:13px">Belum ada item. Klik <strong>Tambah Baris Manual</strong> atau gunakan tombol <strong>＋</strong> pada baris.</td></tr>'
            );
        }
    } else if ($empty.length) {
        $empty.remove();
    }
}

function refreshItems() {
    setEmptyState();
}

$(document).on('click', '.wc-move-up', function(e) {
    e.stopPropagation();
    moveItemRow($(this).closest('tr.wc-item-row'), 'up');
});

$(document).on('click', '.wc-move-down', function(e) {
    e.stopPropagation();
    moveItemRow($(this).closest('tr.wc-item-row'), 'down');
});

$(document).on('mousedown', '.wc-desc-toolbar button', function(e) {
    e.preventDefault();
});

$(document).on('click', '.wc-desc-toolbar button', function() {
    document.execCommand($(this).data('cmd'), false, null);
});

function collectItems() {
    var items = [];
    var valid = true;

    $('#wc-items-body tr.wc-item-row').each(function() {
        var $row = $(this);
        var $desc = $row.find('.wc-desc');
        var descHtml = $desc.html() || '';
        var descText = $('<div>').html(descHtml).text().trim();

        var qtyVal = $row.find('.wc-qty').val();
        var qty = (qtyVal === '' || qtyVal == null) ? 0 : parseInt(qtyVal, 10);
        qty = isNaN(qty) ? 0 : qty;

        var catEl = $row.find('.wc-cat');
        var category = catEl.length
            ? catEl.text().trim()
            : $row.find('.wc-category-data').val().trim();

        $row.find('.is-invalid').removeClass('is-invalid');

        if (!descText) {
            $desc.addClass('is-invalid');
            valid = false;
        }

        items.push({
            _key: $row.attr('data-key'),
            parent_key: $row.attr('data-parent') || null,
            item_no: $row.find('.wc-item-no').val(),
            product_id: $row.find('.wc-product-id').val() || null,
            category: category,
            part_number: $row.find('.wc-part').val().trim(),
            description: descHtml,
            qty: qty
        });
    });

    return valid ? items : null;
}

$('#btn-save-wc').on('click', function() {
    var taskId = $('#wc-task-id').val();
    if (!taskId) {
        toastr.error('Pilih Task Quote terlebih dahulu.');
        return;
    }

    var items = collectItems();
    if (!items) {
        toastr.error('Deskripsi item wajib diisi.');
        return;
    }

    var payload = {
        task_id: taskId,
        date: $('#wc-date').val(),
        parameter_note: $('#wc-parameter').val(),
        notes: $('#wc-notes').val(),
        items: items
    };

    var id = $('#wc-edit-id').val();
    var isEdit = !!id;
    var url = isEdit ? wcUpdateUrl.replace('__ID__', id) : wcStoreUrl;
    var method = isEdit ? 'PUT' : 'POST';

    $('#btn-save-wc').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...');

    $.ajax({
        url: url,
        method: method,
        data: JSON.stringify(payload),
        contentType: 'application/json',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(function(res) {
        toastr.success(res.message || 'Berhasil disimpan.');
        setTimeout(function() { window.location.href = wcIndexUrl; }, 600);
    }).fail(function(xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Terjadi kesalahan saat menyimpan.';
        toastr.error(msg);
    }).always(function() {
        $('#btn-save-wc').prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan Draft');
    });
});

$(document).ready(function() {
    $('#wc-task').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: '— Pilih Task —',
        allowClear: true
    });

    // Muat parent dari template configuration yang dipilih.
    $('#wc-template').on('change', function() {
        var configId = $(this).val();
        if (!configId) {
            return;
        }

        $('#wc-items-body').empty();

        $.get(wcFetchTemplateUrl.replace('__ID__', configId), function(res) {
            var items = res.items || [];
            if (items.length === 0) {
                toastr.info('Template tidak memiliki item.');
            }
            // Load DFS: item parent_key null = root, children menyusul dengan relasi.
            items.forEach(function(p) {
                addItemRow(p, p.parent_key || null);
            });
            toastr.success(items.length + ' item (parent & children) dimuat dari template.');
            $('#wc-template').val('');
        }).fail(function(xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat template.';
            toastr.error(msg);
            refreshItems();
        });
    });

    refreshItems();
});

// ── Product Picker Modal ──
$(document).ready(function() {
    ppTable = $('#pp-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wcSearchUrl,
            data: function(d) {
                d.search = d.search || {};
                d.search.value = $('#pp-search').val() || '';
                d.search.regex = false;
            }
        },
        pageLength: 100,
        lengthChange: false,
        searching: false,
        paging: true,
        info: true,
        order: [[1, 'asc']],
        columns: [
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) {
                    var checked = (ppSelected[data] || existingProductIds.indexOf(data) !== -1) ? ' checked' : '';
                    return '<input type="checkbox" class="pp-check" value="' + data + '"' + checked + '>';
                }
            },
            { data: 'code', orderable: true, searchable: true,
                render: function(data) { return '<code style="color:var(--accent)">' + data + '</code>'; }
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

    var ppSearchTimer = null;
    $('#pp-search').on('input', function() {
        clearTimeout(ppSearchTimer);
        ppSearchTimer = setTimeout(function() {
            ppTable.ajax.reload();
        }, 350);
    });

    // Klik baris => toggle checkbox
    $(document).on('click', '#pp-table tbody tr', function(e) {
        if ($(e.target).is('input.pp-check') || $(e.target).closest('input.pp-check').length) {
            return;
        }
        var $cb = $(this).find('.pp-check');
        if ($cb.length) {
            $cb.prop('checked', !$cb.prop('checked')).trigger('change');
        }
    });

    // Simpan state pilihan lintas paging
    $(document).on('change', '.pp-check', function() {
        var id = $(this).val();
        var rowData = ppTable.row($(this).closest('tr')).data();
        if ($(this).is(':checked')) {
            if (rowData) {
                ppSelected[id] = {
                    id: rowData.id,
                    code: rowData.code,
                    name: rowData.name,
                    category: rowData.category,
                    description: rowData.description
                };
            }
        } else {
            delete ppSelected[id];
        }
    });

    $(document).on('click', '#btn-pp-add', function() {
        var selected = Object.values(ppSelected);

        if (selected.length === 0) {
            toastr.error('Pilih minimal 1 part terlebih dahulu.');
            return;
        }

        selected.forEach(function(p) {
            addItemRow(p, ppTargetParentKey);
        });

        refreshItems();
        toastr.success(selected.length + ' part ditambahkan' + (ppTargetParentKey ? ' sebagai item child.' : ' sebagai parent.'));
        if (ppModalInstance) {
            ppModalInstance.hide();
        }
    });

    // Reset pilihan setelah modal ditutup (Batal)
    $(document).on('hidden.bs.modal', '#productPickerModal', function() {
        ppSelected = {};
        ppTargetParentKey = null;
        existingProductIds = [];
        $('#pp-search').val('');
        $('#pp-target-label').text('');
        if (ppTable) {
            ppTable.ajax.reload();
        }
    });
});
</script>
@endsection
