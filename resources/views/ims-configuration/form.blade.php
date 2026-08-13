@extends('layouts.app')

@section('title', $quotation ? 'Edit IMS Configuration '.$quotation->opportunity->opportunity_name : 'Buat IMS Configuration')
@section('page-title', $quotation ? 'Edit IMS Configuration' : 'Buat IMS Configuration')

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
        min-height: 58px;
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
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $quotation ? 'Edit IMS Configuration '.$quotation->opportunity->opportunity_name : 'Buat IMS Configuration' }}</h1>
        <p class="page-header-sub">Pilih task quote, data customer otomatis terambil. Tambahkan item part, lalu simpan sebagai draft.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('ims-configuration.index') }}" class="btn btn-secondary btn-sm">
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
            </div>
        </div>
    </div>

    <div class="card-custom fade-in mb-3">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>List Part Instrument</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="openProductPicker()">
                <i class="fa fa-plus me-1"></i> Tambah Item
            </button>
        </div>
        <div class="card-body-custom p-2">
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0" id="wc-items-table">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th style="min-width:140px">Produk</th>
                            <th>Deskripsi</th>
                            <th style="width:90px">Qty</th>
                            <th style="width:120px">Unit</th>
                            <th style="width:160px">Harga</th>
                            <th class="text-center" style="width:130px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="wc-items-body">
                        @forelse($items as $item)
                        <tr data-row="{{ $loop->iteration }}" class="wc-item-row">
                            <td class="text-center wc-row-num">{{ $loop->iteration }}</td>
                            <td>
                                <input type="hidden" class="wc-product-id" value="{{ $item->product_id }}">
                                <input type="hidden" class="wc-part" value="{{ $item->part_number }}">
                                <strong style="font-size:13px">{{ $item->product?->name ?? ($item->part_number ?: '—') }}</strong>
                                @if ($item->part_number)
                                    <div style="font-size:11px;color:var(--text-muted)">{{ $item->part_number }}</div>
                                @endif
                            </td>
                            <td><div class="form-control form-control-sm wc-desc" contenteditable="true" data-placeholder="Deskripsi item (wajib)">{!! \App\Models\Quotation::renderDescription($item->description) !!}</div></td>
                            <td><input type="number" class="form-control form-control-sm wc-qty" value="{{ $item->qty }}" min="1"></td>
                            <td><input type="text" class="form-control form-control-sm wc-unit" placeholder="cth: pcs, lot" value="{{ $item->unit }}"></td>
                            <td>
                                <input type="hidden" class="wc-price" value="{{ $item->price ?? $item->product?->price }}">
                                <input type="text" class="form-control form-control-sm wc-price-display" value="{{ ($item->price ?? $item->product?->price) ? number_format($item->price ?? $item->product?->price, 0, ',', '.') : '—' }}" readonly>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button" class="btn btn-sm btn-soft wc-move-up" title="Naik"><i class="fa fa-chevron-up"></i></button>
                                    <button type="button" class="btn btn-sm btn-soft wc-move-down" title="Turun"><i class="fa fa-chevron-down"></i></button>
                                    <button type="button" class="btn btn-sm btn-soft" onclick="removeItemRow(this)" title="Hapus"><i class="fa fa-trash" style="color:var(--danger)"></i></button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        @endforelse
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
        <a href="{{ route('ims-configuration.index') }}" class="btn btn-secondary">Batal</a>
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
                <h6 class="modal-title"><i class="fa-solid fa-box-open me-2" style="color:var(--accent)"></i>Pilih Part Instrument</h6>
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

const wcStoreUrl = '{{ route("ims-configuration.store") }}';
const wcUpdateUrl = '{{ route("ims-configuration.update", "__ID__") }}';
const wcIndexUrl = '{{ route("ims-configuration.index") }}';
const wcSearchUrl = '{{ route("ims-configuration.search-products") }}';
const wcFetchTaskUrl = '{{ route("ims-configuration.fetch-task") }}';

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

function openProductPicker() {
    if (ppTable) {
        existingProductIds = [];
        $('#wc-items-body .wc-product-id').each(function() {
            var v = $(this).val();
            if (v) existingProductIds.push(v);
        });
        ppTable.ajax.reload();
    }
    if (!ppModalInstance) {
        ppModalInstance = new bootstrap.Modal(document.getElementById('productPickerModal'));
    }
    ppModalInstance.show();
}

function addItemRow(item) {
    item = item || {};
    wcRowIndex++;

    var priceRaw = (item.price != null && item.price !== '') ? item.price : '';
    var priceDisplay = priceRaw !== '' ? numberFormat(Number(priceRaw)) : '—';

    var html = '<tr data-row="' + wcRowIndex + '" class="wc-item-row">';
    html += '<td class="text-center wc-row-num">' + wcRowIndex + '</td>';
    html += '<td><input type="hidden" class="wc-product-id" value="' + (item.id || item.product_id || '') + '">';
    html += '<input type="hidden" class="wc-part" value="' + (item.code || item.part_number || '') + '">';
    html += '<strong style="font-size:13px">' + (item.name || item.code || '—') + '</strong>';
    if (item.code) {
        html += '<div style="font-size:11px;color:var(--text-muted)">' + item.code + '</div>';
    }
    html += '</td>';
    html += '<td><div class="form-control form-control-sm wc-desc" contenteditable="true" data-placeholder="Deskripsi item (wajib)">' + (item.description || item.name || '') + '</div></td>';
    html += '<td><input type="number" class="form-control form-control-sm wc-qty" value="1" min="1"></td>';
    html += '<td><input type="text" class="form-control form-control-sm wc-unit" placeholder="cth: pcs, lot" value=""></td>';
    html += '<td><input type="hidden" class="wc-price" value="' + priceRaw + '">';
    html += '<input type="text" class="form-control form-control-sm wc-price-display" value="' + priceDisplay + '" readonly></td>';
    html += '<td class="text-center"><div class="d-flex justify-content-center gap-1">';
    html += '<button type="button" class="btn btn-sm btn-soft wc-move-up" title="Naik"><i class="fa fa-chevron-up"></i></button>';
    html += '<button type="button" class="btn btn-sm btn-soft wc-move-down" title="Turun"><i class="fa fa-chevron-down"></i></button>';
    html += '<button type="button" class="btn btn-sm btn-soft" onclick="removeItemRow(this)" title="Hapus"><i class="fa fa-trash" style="color:var(--danger)"></i></button>';
    html += '</div></td>';
    html += '</tr>';

    $('#wc-items-body').append(html);
}

function numberFormat(n) {
    return n.toLocaleString('id-ID');
}

function removeItemRow(btn) {
    $(btn).closest('tr.wc-item-row').remove();
    refreshItems();
}

function renumberItems() {
    var n = 0;
    $('#wc-items-body tr.wc-item-row').each(function() {
        n++;
        $(this).find('.wc-row-num').text(n);
    });
}

function moveItemRow($row, dir) {
    if (dir === 'up') {
        var $prev = $row.prevAll('tr').first();
        if ($prev.length && $prev.hasClass('wc-item-row')) {
            $row.insertBefore($prev);
        }
    } else {
        var $next = $row.nextAll('tr').first();
        if ($next.length && $next.hasClass('wc-item-row')) {
            $row.insertAfter($next);
        }
    }
    renumberItems();
}

function setEmptyState() {
    var $empty = $('#wc-items-body tr.wc-empty-row');
    if ($('#wc-items-body tr.wc-item-row').length === 0) {
        if ($empty.length === 0) {
            $('#wc-items-body').html(
                '<tr class="wc-empty-row"><td colspan="7" class="text-center" style="padding:24px;color:var(--text-muted);font-size:13px">Belum ada part. Klik <strong>Tambah Item</strong> untuk memilih.</td></tr>'
            );
        }
    } else if ($empty.length) {
        $empty.remove();
    }
}

function refreshItems() {
    renumberItems();
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

function collectItems() {
    var items = [];
    var valid = true;

    $('#wc-items-body tr.wc-item-row').each(function() {
        var $desc = $(this).find('.wc-desc');
        var descHtml = $desc.html() || '';
        var descText = $('<div>').html(descHtml).text().trim();
        var qty = parseInt($(this).find('.wc-qty').val(), 10);

        $(this).find('.is-invalid').removeClass('is-invalid');

        if (!descText) {
            $desc.addClass('is-invalid');
            valid = false;
        }

        items.push({
            product_id: $(this).find('.wc-product-id').val() || null,
            part_number: $(this).find('.wc-part').val().trim(),
            description: descHtml,
            qty: isNaN(qty) || qty < 1 ? 1 : qty,
            price: $(this).find('.wc-price').val().trim() || null,
            unit: $(this).find('.wc-unit').val().trim() || null
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
                    description: rowData.description,
                    price: rowData.price
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
            addItemRow(p);
        });

        refreshItems();
        toastr.success(selected.length + ' part ditambahkan.');
        if (ppModalInstance) {
            ppModalInstance.hide();
        }
    });

    // Reset pilihan setelah modal ditutup (Batal)
    $(document).on('hidden.bs.modal', '#productPickerModal', function() {
        ppSelected = {};
        existingProductIds = [];
        $('#pp-search').val('');
        if (ppTable) {
            ppTable.ajax.reload();
        }
    });
});
</script>
@endsection
