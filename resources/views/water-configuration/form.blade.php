@extends('layouts.app')

@section('title', $quotation ? 'Edit Quote Configuration #'.$quotation->id : 'Buat Quote Configuration')
@section('page-title', $quotation ? 'Edit Quote Configuration' : 'Buat Quote Configuration')

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
                                {{ ($quotation?->task_id ?? $preselectedTaskId) == $task->id ? 'selected' : '' }}>
                                {{ $task->opportunity?->opportunity_name ?? $task->title }}
                                {{ $task->opportunity?->accountCompany?->account_name ? ' (' . $task->opportunity->accountCompany->account_name . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="task_id" id="wc-task-id" value="{{ $quotation?->task_id ?? $preselectedTaskId }}">
                    <input type="hidden" name="opportunity_id" id="wc-opportunity-id" value="{{ $quotation?->opportunity_id }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal</label>
                    <input type="date" id="wc-date" name="date" class="form-control" value="{{ $quotation?->date?->format('Y-m-d') }}" readonly>
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
                    <option value="">— Pilih Template —</option>
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
            @include('configuration.partials._item-editor', [
                'items' => $items,
                'searchUrl' => route('water-configuration.search-products'),
            ])
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

@section('scripts')
<script>
const wcStoreUrl = '{{ route("water-configuration.store") }}';
const wcUpdateUrl = '{{ route("water-configuration.update", "__ID__") }}';
const wcIndexUrl = '{{ route("water-configuration.index") }}';
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

    // Muat item dari template yang dipilih.
    $('#wc-template').on('change', function() {
        var templateId = $(this).val();
        if (!templateId) {
            return;
        }

        $('#wc-items-body').empty();

        $.get(wcFetchTemplateUrl.replace('__ID__', templateId), function(res) {
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

    @if ($preselectedTaskId)
        $('#wc-task').trigger('change');
    @endif
});
</script>
@endsection
