@extends('layouts.app')

@section('title', $project ? 'Edit Project' : 'Buat Project')
@section('page-title', $project ? 'Edit Project' : 'Buat Project')

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
        <h1 class="page-header-title">{{ $project ? 'Edit Project' : 'Buat Project' }}</h1>
        <p class="page-header-sub">
            @if($project)
                {{ $project->project_number }} &middot; {{ $project->project_name }}
            @else
                Pilih quotation, lengkapi data PO customer, lalu tentukan sales.
            @endif
        </p>
    </div>
    <div class="page-header-actions">
        @if($project && ($canUpdate ?? false))
        <a href="{{ route('project.achievements', $project->id) }}" class="btn btn-soft btn-sm">
            <i class="fa fa-list-check me-1"></i> Rincian Device
        </a>
        @endif
        <a href="{{ $project ? route('project.show', $project->id) : route('project.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        {{-- 1. Sumber quotation & informasi project --}}
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi Project</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Quotation</label>
                        <select id="pj-quotation-select" class="form-select" style="width:100%" @if($project) disabled @endif>
                            <option value="">— Tanpa Quotation (data lama / manual) —</option>
                            @foreach($quotations as $q)
                                <option value="{{ $q->id }}" @selected((int) $initialQuotationId === (int) $q->id)>
                                    {{ $q->quotation_number ?? '#'.$q->id }} — {{ $q->to_name }} ({{ $q->status }})
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" id="pj-quotation-id" value="{{ $initialQuotationId }}">
                        <div class="pj-hint mt-1">
                            @if($project)
                                <i class="fa fa-lock me-1"></i> Quotation tidak bisa diganti setelah project dibuat.
                            @else
                                Hanya quotation Approved/Finish yang sudah punya Profit Estimate. Kosongkan untuk input data lama/manual.
                            @endif
                        </div>
                        <div id="pj-quotation-info" class="pj-source mt-2" style="display:none"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Nama Project <span class="text-danger">*</span></label>
                        <input type="text" id="pj-project-name" class="form-control" value="{{ $project?->project_name }}" maxlength="255" placeholder="Otomatis dari nama opportunity bila memilih quotation">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Deskripsi Project</label>
                        <textarea id="pj-project-description" class="form-control" rows="3" placeholder="Ruang lingkup, lokasi, catatan penting...">{{ $project?->project_description }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. PO Customer --}}
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>PO Customer</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Company <span class="text-danger">*</span></label>
                        <select id="pj-company" class="form-select" style="width:100%"></select>
                        <input type="hidden" id="pj-company-id" value="{{ $initialCompanyId }}">
                        <div class="pj-hint mt-1" id="pj-company-hint">Otomatis dari opportunity quotation; manual hanya bila tanpa quotation.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">No PO Customer <span class="text-danger">*</span></label>
                        <input type="text" id="pj-po-number" class="form-control" value="{{ $project?->po_number_company }}" maxlength="100" placeholder="Contoh: 016/PK/DSDA/2025">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tanggal PO <span class="text-danger">*</span></label>
                        <input type="date" id="pj-po-date" class="form-control" value="{{ $project?->po_date?->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tanggal PO Diterima <span class="text-danger">*</span></label>
                        <input type="date" id="pj-po-date-received" class="form-control" value="{{ $project?->po_date_received?->format('Y-m-d') ?? now()->toDateString() }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Jatuh Tempo PO <span class="text-danger">*</span></label>
                        <input type="date" id="pj-po-due-date" class="form-control" value="{{ $project?->po_due_date?->format('Y-m-d') }}">
                        <div class="pj-hint mt-1" id="pj-due-hint"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Dokumen PO <span class="text-danger" id="pj-po-file-required" @if($project) style="display:none" @endif>*</span></label>
                        <input type="file" id="pj-po-file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="pj-hint mt-1" id="pj-po-file-hint">
                            PDF, JPG, atau PNG, maksimal 10MB.
                            @if($project?->hasPoDocument()) Pilih file baru hanya bila ingin mengganti. @endif
                        </div>
                        @if($project?->hasPoDocument())
                        <a href="{{ route('project.po', $project->id) }}" target="_blank" class="pj-file-current text-decoration-none">
                            <i class="fa fa-file-pdf"></i>
                            <span>{{ $project->po_document_name }}</span>
                            <span class="ms-auto pj-hint">Lihat <i class="fa fa-arrow-up-right-from-square ms-1" style="color:inherit"></i></span>
                        </a>
                        @endif
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
                    <span><i class="fa-solid fa-coins me-2" style="color:var(--accent)"></i>Nilai &amp; Klasifikasi</span>
                </div>
                <div class="card-body-custom">
                    <div class="pj-amount-box mb-2">
                        <div class="lbl">Net Sale Amount <span class="text-danger">*</span></div>
                        <div class="d-flex align-items-center">
                            <span class="prefix">Rp</span>
                            <input type="text" inputmode="decimal" autocomplete="off" id="pj-net-sale" class="form-control pj-number pj-readonly text-end" value="{{ $project ? \App\Models\Project::formatNumber($project->net_sale_amount) : '' }}" placeholder="0" readonly>
                        </div>
                        <div class="pj-hint">Dari Profit Estimate (real project value).</div>
                    </div>
                    <div class="pj-amount-box secondary mb-3">
                        <div class="lbl">Total Sale Amount <span class="text-danger">*</span></div>
                        <div class="d-flex align-items-center">
                            <span class="prefix">Rp</span>
                            <input type="text" inputmode="decimal" autocomplete="off" id="pj-total-sale" class="form-control pj-number pj-readonly text-end" value="{{ $project ? \App\Models\Project::formatNumber($project->total_sale_amount) : '' }}" placeholder="0" readonly>
                        </div>
                        <div class="pj-hint" id="pj-sync-note">Diisi manual bila tanpa quotation.</div>
                    </div>

                    <label class="form-label">Retensi <span class="text-danger">*</span></label>
                    <div class="pj-segment mb-3">
                        <input type="radio" name="pj-retention-opt" id="pj-retention-0" value="0" @checked(! $isRetention)>
                        <label for="pj-retention-0">Tidak</label>
                        <input type="radio" name="pj-retention-opt" id="pj-retention-1" value="1" @checked($isRetention)>
                        <label for="pj-retention-1">Ya</label>
                    </div>
                    <input type="hidden" id="pj-is-retention" value="{{ $isRetention ? 1 : 0 }}">

                    <label class="form-label">Tipe Achievement <span class="text-danger">*</span></label>
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
                    <div class="pj-hint mt-1 mb-2">Net sale dibagi rata; sisa pembulatan ke sales terakhir.</div>
                    <div id="pj-sales-preview"><div class="pj-empty">Belum ada sales dipilih.</div></div>
                </div>
            </div>

            <button type="button" class="btn btn-primary w-100" id="pj-save-btn" onclick="pjSave()">
                <i class="fa fa-save me-1"></i> Simpan {{ $project ? 'Perubahan' : 'Project' }}
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
        placeholder: 'Cari nomor quotation atau customer...'
    });
}

function pjInitCompany() {
    $('#pj-company').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Cari company...',
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
        ? '<i class="fa fa-lock me-1"></i> Terkunci mengikuti opportunity quotation.'
        : 'Pilih company secara manual (tanpa quotation).');
}

function pjLockAmounts(locked) {
    $('#pj-total-sale, #pj-net-sale').prop('readonly', locked).toggleClass('pj-readonly', locked);
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
        pjRenderQuotationInfo(null);
        $('#pj-company').prop('disabled', false).trigger('change.select2');
        $('#pj-company-hint').text('Pilih company secara manual (tanpa quotation).');
        $('#pj-sync-note').text('Diisi manual bila tanpa quotation.');
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
            pjRenderQuotationInfo(d);
            $('#pj-sync-note').html('<i class="fa fa-lock me-1"></i> Read-only dari quotation ' + pjEsc(d.quotation_number || ('#' + id)) + '.');
            pjRenderSalesSplit();
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat quotation.');
            if (!pjIsEdit) {
                $('#pj-quotation-select').val('').trigger('change');
            }
        });
}

function pjInitSales() {
    $('#pj-sales').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Cari sales...',
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
        $('#pj-sales-preview').html('<div class="pj-empty"><i class="fa-solid fa-user-plus me-1"></i> Belum ada sales dipilih.</div>');
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
        $hint.html('<span class="text-danger"><i class="fa fa-triangle-exclamation me-1"></i>Sebelum tanggal PO.</span>');
    } else {
        $hint.text(days + ' hari dari tanggal PO.');
    }
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
    if (!$.trim($('#pj-project-name').val())) return pjFail('#pj-project-name', 'Nama project wajib diisi.');
    if (!$('#pj-company-id').val()) return pjFail('#pj-company', 'Company wajib dipilih.');
    if (!$.trim($('#pj-po-number').val())) return pjFail('#pj-po-number', 'No PO customer wajib diisi.');
    if (!$('#pj-po-date').val()) return pjFail('#pj-po-date', 'Tanggal PO wajib diisi.');
    if (!$('#pj-po-date-received').val()) return pjFail('#pj-po-date-received', 'Tanggal PO diterima wajib diisi.');
    if (!$('#pj-po-due-date').val()) return pjFail('#pj-po-due-date', 'Jatuh tempo PO wajib diisi.');

    var file = document.getElementById('pj-po-file').files[0];
    if (quotationId && !pjIsEdit && !file) {
        return pjFail('#pj-po-file', 'Dokumen PO wajib diupload untuk project dari quotation.');
    }
    if (file && file.size > pjMaxFileSize) return pjFail('#pj-po-file', 'Ukuran file tidak boleh lebih dari 10MB.');

    if (!$('#pj-net-sale').val() && $('#pj-net-sale').val() !== '0') {
        return pjFail('#pj-net-sale', 'Net Sale Amount wajib diisi.');
    }

    if (($('#pj-sales').val() || []).length === 0) return pjFail('#pj-sales', 'Pilih minimal satu sales.');

    return true;
}

function pjSetSaving(saving) {
    var $btn = $('#pj-save-btn');
    if (saving) {
        $btn.data('label', $btn.html());
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
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
    fd.append('total_sale_amount', pjToRaw($('#pj-total-sale').val()));
    fd.append('net_sale_amount', pjToRaw($('#pj-net-sale').val()));
    fd.append('is_retention', $('#pj-is-retention').val());
    fd.append('type_of_achievement', $('#pj-type').val());

    ($('#pj-sales').val() || []).forEach(function(id, i) {
        fd.append('sales[' + i + ']', id);
    });

    var file = document.getElementById('pj-po-file').files[0];
    if (file) fd.append('po_document', file);

    pjSetSaving(true);

    $.ajax({
        url: pjIsEdit ? pjUpdateUrl : pjStoreUrl,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false
    }).done(function(res) {
        toastr.success(res.message || 'Project tersimpan.');
        var id = pjIsEdit ? pjProjectId : res.id;
        setTimeout(function() {
            window.location.href = id ? pjShowUrl.replace('__ID__', id) : pjIndexUrl;
        }, 700);
    }).fail(function(xhr) {
        pjSetSaving(false);
        var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan project.';
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
    $(document).on('input', '#pj-net-sale', pjRenderSalesSplit);
    $(document).on('input change', 'input, textarea', function() { pjClearInvalid(this); });
    $(document).on('change', '#pj-po-date, #pj-po-due-date', pjUpdateDueHint);

    pjUpdateDueHint();
    pjRenderSalesSplit();
});
</script>
@endsection
