@extends('layouts.app')

@section('title', $quotation ? 'Edit Quote Configuration #'.$quotation->id : 'Buat Quote Configuration')
@section('page-title', $quotation ? 'Edit Quote Configuration' : 'Buat Quote Configuration')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $quotation ? 'Edit Quote Configuration #'.$quotation->id : 'Buat Quote Configuration' }}</h1>
        <p class="page-header-sub">Pilih task quote, data customer otomatis terambil. Tambahkan item part, lalu simpan sebagai draft.</p>
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
                    <label class="form-label">Location / Company</label>
                    <input type="text" class="form-control" id="wc-location" value="{{ $quotation?->location }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">To (Contact)</label>
                    <input type="text" class="form-control" id="wc-to" value="{{ $quotation?->to_name }}" readonly>
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
            <button type="button" class="btn btn-primary btn-sm" onclick="addItemRow()">
                <i class="fa fa-plus me-1"></i> Tambah Item
            </button>
        </div>
        <div class="card-body-custom p-2">
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0" id="wc-items-table">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th style="min-width:260px">Produk (Part Number)</th>
                            <th style="width:150px">Kategori</th>
                            <th>Deskripsi</th>
                            <th style="width:180px">Qty</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="wc-items-body">
                        @forelse($items as $item)
                        <tr data-row="{{ $loop->iteration }}">
                            <td class="text-center wc-row-num">{{ $loop->iteration }}</td>
                            <td>
                                <input type="hidden" class="wc-product-id" value="{{ $item->product_id }}">
                                <input type="hidden" class="wc-part" value="{{ $item->part_number }}">
                                <select class="form-select form-select-sm wc-product">
                                    @if($item->product)
                                        <option value="{{ $item->product_id }}" selected>{{ $item->part_number ?: $item->product->name }}</option>
                                    @endif
                                </select>
                            </td>
                            <td><input type="text" class="form-control form-control-sm wc-category" list="wc-category-list" placeholder="Kategori bebas" value="{{ $item->category }}"></td>
                            <td><input type="text" class="form-control form-control-sm wc-desc" placeholder="Deskripsi item (wajib)" value="{{ $item->description }}"></td>
                            <td><input type="number" class="form-control form-control-sm wc-qty" value="{{ $item->qty }}" min="1"></td>
                            <td class="text-center"><button type="button" class="btn btn-sm btn-soft" onclick="removeItemRow(this)"><i class="fa fa-trash" style="color:var(--danger)"></i></button></td>
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
        <a href="{{ route('water-configuration.index') }}" class="btn btn-secondary">Batal</a>
        <button type="button" class="btn-accent" id="btn-save-wc">
            <i class="fa fa-save me-1"></i> Simpan Draft
        </button>
    </div>
</form>

<datalist id="wc-category-list">
    @foreach($categories as $category)
        <option value="{{ $category }}"></option>
    @endforeach
</datalist>
@endsection

@section('scripts')
<script>
let wcRowIndex = {{ count($items) }};

const wcStoreUrl = '{{ route("water-configuration.store") }}';
const wcUpdateUrl = '{{ route("water-configuration.update", "__ID__") }}';
const wcIndexUrl = '{{ route("water-configuration.index") }}';
const wcSearchUrl = '{{ route("water-configuration.search-products") }}';
const wcFetchTaskUrl = '{{ route("water-configuration.fetch-task") }}';

function applyTaskData(data) {
    $('#wc-task-id').val(data.task_id || '');
    $('#wc-opportunity-id').val(data.opportunity_id || '');
    $('#wc-location').val(data.location || '');
    $('#wc-to').val(data.to_name || '');
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
            task_id: '', opportunity_id: '', location: '', to_name: '',
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

function initProductSelect($select) {
    $select.select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: '— Cari Produk —',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: wcSearchUrl,
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return { q: params.term || '' };
            },
            processResults: function(data) {
                return { results: data.results };
            },
            cache: true
        },
        templateResult: function(data) {
            if (!data.id) {
                return data.text;
            }
            var html = '<div class="d-flex align-items-center gap-2">';
            html += '<div><strong style="color:var(--accent)">' + (data.code || data.name) + '</strong>';
            if (data.name && data.code) {
                html += ' <span style="color:var(--text-secondary);font-size:12px;">' + data.name + '</span>';
            }
            if (data.brand) {
                html += ' <span style="color:var(--text-muted);font-size:11px;">' + data.brand + '</span>';
            }
            if (data.category) {
                html += ' <span style="font-size:10px;background:var(--accent-soft);color:var(--accent);padding:1px 6px;border-radius:999px;margin-left:4px;">' + data.category + '</span>';
            }
            if (data.description) {
                html += '<div style="font-size:11px;color:var(--text-muted);margin-top:2px;">' + data.description + '</div>';
            }
            html += '</div></div>';
            return $(html);
        },
        templateSelection: function(data) {
            return data.code || data.name || data.text;
        }
    });

    $select.on('select2:select', function(e) {
        var d = e.params.data;
        var row = $(this).closest('tr');

        row.find('.wc-product-id').val(d.id);
        row.find('.wc-part').val(d.code || '');
        row.find('.wc-desc').val(d.description || d.name || '');
        if (d.category) {
            row.find('.wc-category').val(d.category);
        }
    });

    $select.on('select2:clear', function() {
        $(this).closest('tr').find('.wc-product-id').val('');
        $(this).closest('tr').find('.wc-part').val('');
    });
}

function addItemRow(item) {
    item = item || {};
    wcRowIndex++;

    var html = '<tr data-row="' + wcRowIndex + '">';
    html += '<td class="text-center wc-row-num">' + wcRowIndex + '</td>';
    html += '<td><input type="hidden" class="wc-product-id" value="' + (item.product_id || '') + '">';
    html += '<input type="hidden" class="wc-part" value="' + (item.part_number || '') + '">';
    html += '<select class="form-select form-select-sm wc-product">';
    if (item.product_id && (item.part_number || item.product_name)) {
        html += '<option value="' + item.product_id + '" selected>' + (item.part_number || item.product_name) + '</option>';
    }
    html += '</select></td>';
    html += '<td><input type="text" class="form-control form-control-sm wc-category" list="wc-category-list" placeholder="Kategori bebas" value="' + (item.category || '') + '"></td>';
    html += '<td><input type="text" class="form-control form-control-sm wc-desc" placeholder="Deskripsi item (wajib)" value="' + (item.description || '') + '"></td>';
    html += '<td><input type="number" class="form-control form-control-sm wc-qty" value="' + (item.qty || 1) + '" min="1"></td>';
    html += '<td class="text-center"><button type="button" class="btn btn-sm btn-soft" onclick="removeItemRow(this)"><i class="fa fa-trash" style="color:var(--danger)"></i></button></td>';
    html += '</tr>';

    var $row = $(html);
    $('#wc-items-body').append($row);
    initProductSelect($row.find('.wc-product'));
}

function removeItemRow(btn) {
    $(btn).closest('tr').remove();
    renumberItems();
}

function renumberItems() {
    $('#wc-items-body tr').each(function(i) {
        $(this).find('.wc-row-num').text(i + 1);
    });
}

function collectItems() {
    var items = [];
    var valid = true;

    $('#wc-items-body tr').each(function() {
        var desc = $(this).find('.wc-desc').val().trim();
        var qty = parseInt($(this).find('.wc-qty').val(), 10);

        $(this).find('.is-invalid').removeClass('is-invalid');

        if (!desc) {
            $(this).find('.wc-desc').addClass('is-invalid');
            valid = false;
        }

        items.push({
            product_id: $(this).find('.wc-product-id').val() || null,
            category: $(this).find('.wc-category').val().trim(),
            part_number: $(this).find('.wc-part').val().trim(),
            description: desc,
            qty: isNaN(qty) || qty < 1 ? 1 : qty
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

    if ($('#wc-items-body tr').length === 0) {
        addItemRow();
    } else {
        $('#wc-items-body .wc-product').each(function() {
            initProductSelect($(this));
        });
    }
});
</script>
@endsection
