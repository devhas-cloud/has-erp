@extends('layouts.app')

@section('title', $project ? 'Edit IMS Project' : 'Tambah IMS Project')
@section('page-title', $project ? 'Edit IMS Project' : 'Tambah IMS Project')

@section('styles')
<style>
    .ip-hint { font-size: 12px; color: var(--text-muted); }
    .ip-file-current {
        display: flex; align-items: center; gap: 8px; margin-top: 8px;
        font-size: 13px; padding: 6px 10px; border: 1px solid var(--card-border); border-radius: var(--radius-sm);
    }
    .ip-file-current i { color: var(--danger); }
    .ip-doc-list { display: flex; flex-direction: column; gap: 6px; }
    .ip-doc-row {
        display: flex; align-items: center; gap: 10px; padding: 7px 10px;
        border: 1px solid var(--card-border); border-radius: var(--radius-sm); font-size: 13px;
    }
    .ip-doc-row > i { color: var(--danger); font-size: 16px; }
    .ip-doc-row .meta { font-size: 11.5px; color: var(--text-muted); }
    .ip-doc-row .name { font-weight: 600; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ip-doc-row.pending { border-style: dashed; background: var(--accent-subtle); }
    .ip-chip {
        display: inline-flex; align-items: center; gap: 4px; cursor: pointer;
        padding: 3px 10px; border-radius: 999px; font-size: 12px;
        border: 1px dashed var(--card-border); color: var(--text-muted); background: transparent;
    }
    .ip-chip:hover { border-color: var(--accent); color: var(--accent); }
    .ip-note {
        display: flex; gap: 10px; align-items: flex-start; font-size: 12.5px;
        background: var(--accent-subtle); border: 1px dashed var(--card-border);
        border-radius: var(--radius-sm); padding: 10px 12px;
    }
    .ip-note i { color: var(--accent); margin-top: 2px; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $project ? 'Edit IMS Project' : 'Tambah IMS Project' }}</h1>
        <p class="page-header-sub">
            @if($project)
                {{ $project->project_number }} &middot; {{ $project->project_name }}
            @else
                Input project hybrid yang sudah berjalan: data PO, dokumen, dan masa garansi.
            @endif
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $project ? route('ims-project.show', $project->id) : route('ims-project.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        {{-- 1. Informasi project --}}
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi Project</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="ip-project-name">Nama Project <span class="text-danger">*</span></label>
                        <input type="text" id="ip-project-name" class="form-control" value="{{ $project?->project_name }}" maxlength="255">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ip-project-description">Deskripsi Project</label>
                        <textarea id="ip-project-description" class="form-control" rows="3" placeholder="Ruang lingkup, lokasi, perangkat terpasang...">{{ $project?->project_description }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. PO customer --}}
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>PO Customer</span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Company <span class="text-danger">*</span></label>
                        <select id="ip-company" class="form-select" style="width:100%"></select>
                        <input type="hidden" id="ip-company-id" value="{{ $project?->company_id }}">
                        @if($project?->quotation_id)
                            <div class="ip-hint mt-1"><i class="fa fa-lock me-1"></i> Terkunci mengikuti opportunity quotation {{ $project->quotation?->quotation_number }}.</div>
                        @endif
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="ip-po-number">No PO Customer <span class="text-danger">*</span></label>
                        <input type="text" id="ip-po-number" class="form-control" value="{{ $project?->po_number_company }}" maxlength="100" placeholder="Contoh: 016/PK/DSDA/2025">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ip-po-date">Tanggal PO <span class="text-danger">*</span></label>
                        <input type="date" id="ip-po-date" class="form-control" value="{{ $project?->po_date?->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ip-po-date-received">Tanggal PO Diterima <span class="text-danger">*</span></label>
                        <input type="date" id="ip-po-date-received" class="form-control" value="{{ $project?->po_date_received?->format('Y-m-d') ?? now()->toDateString() }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ip-po-due-date">Jatuh Tempo <span class="text-danger">*</span></label>
                        <input type="date" id="ip-po-due-date" class="form-control" value="{{ $project?->po_due_date?->format('Y-m-d') }}">
                        <div class="ip-hint mt-1" id="ip-due-hint"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ip-po-file">Dokumen PO <span class="ip-hint">opsional</span></label>
                        <input type="file" id="ip-po-file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="ip-hint mt-1">
                            PDF, JPG, atau PNG, maksimal 10MB.
                            @if($project?->hasPoDocument()) Pilih file baru hanya bila ingin mengganti. @endif
                        </div>
                        @if($project?->hasPoDocument())
                        <a href="{{ route('ims-project.po', $project->id) }}" target="_blank" class="ip-file-current text-decoration-none">
                            <i class="fa fa-file-pdf"></i>
                            <span>{{ $project->po_document_name }}</span>
                            <span class="ms-auto ip-hint">Lihat <i class="fa fa-arrow-up-right-from-square ms-1" style="color:inherit"></i></span>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Dokumen pendukung --}}
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-file-signature me-2" style="color:var(--accent)"></i>Dokumen Pendukung <span class="ip-hint ms-1">opsional, bisa lebih dari satu</span></span>
            </div>
            <div class="card-body-custom">
                <div class="ip-doc-list mb-2" id="ip-agreement-list">
                    @foreach($agreements as $doc)
                        <div class="ip-doc-row">
                            <i class="fa {{ $doc['icon'] }}"></i>
                            <div style="min-width:0;flex:1">
                                <div class="name">{{ $doc['file_name'] }}</div>
                                <div class="meta">{{ $doc['notes'] ? $doc['notes'].' · ' : '' }}{{ $doc['size'] }} · {{ $doc['uploaded_by'] ?? '—' }}, {{ $doc['uploaded_at'] }}</div>
                            </div>
                            <a href="{{ $doc['view_url'] }}" target="_blank" class="btn-icon" title="Lihat"><i class="fa fa-eye"></i></a>
                            @if($canDelete ?? false)
                                <button type="button" class="btn-icon text-danger" title="Hapus" onclick="ipDeleteAgreement(this, '{{ $doc['delete_url'] }}')"><i class="fa fa-trash"></i></button>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="row g-2">
                    <div class="col-md-7">
                        <input type="file" id="ip-agreement-files" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple>
                        <div class="ip-hint mt-1">PDF, JPG, atau PNG, maksimal 10MB per file. Bisa pilih beberapa file sekaligus.</div>
                    </div>
                    <div class="col-md-5">
                        <input type="text" id="ip-agreement-notes" class="form-control" maxlength="255" placeholder="Keterangan, mis. BAST / Kontrak maintenance">
                    </div>
                </div>
                <div class="ip-doc-list mt-2" id="ip-agreement-pending"></div>
            </div>
        </div>

        {{-- 4. Masa garansi --}}
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-shield-halved me-2" style="color:var(--accent)"></i>Masa Garansi <span class="ip-hint ms-1">opsional</span></span>
            </div>
            <div class="card-body-custom">
                <div class="row g-3 align-items-start">
                    <div class="col-md-4">
                        <label class="form-label" for="ip-warranty-start">Mulai Garansi</label>
                        <input type="date" id="ip-warranty-start" class="form-control" value="{{ $project?->warranty?->start_date?->format('Y-m-d') }}">
                        <div class="ip-hint mt-1">Mis. tanggal instalasi selesai / BAST.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ip-warranty-end">Akhir Garansi</label>
                        <input type="date" id="ip-warranty-end" class="form-control" value="{{ $project?->warranty?->end_date?->format('Y-m-d') }}">
                        <div class="ip-hint mt-1" id="ip-warranty-hint"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Durasi cepat</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="ip-chip" onclick="ipWarrantyMonths(6)">6 bulan</button>
                            <button type="button" class="ip-chip" onclick="ipWarrantyMonths(12)">1 tahun</button>
                            <button type="button" class="ip-chip" onclick="ipWarrantyMonths(24)">2 tahun</button>
                            <button type="button" class="ip-chip" onclick="ipClearWarranty()" title="Kosongkan masa garansi"><i class="fa fa-xmark"></i> Kosongkan</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-custom fade-in" style="position:sticky;top:84px">
            <div class="card-body-custom">
                <div class="ip-note mb-3">
                    <i class="fa fa-circle-info"></i>
                    <div>
                        @if(! $project || $project->isImsHybrid())
                            Project {{ $project ? 'ini belum punya' : 'dibuat tanpa' }} <strong>nilai kontrak dan sales</strong>, jadi belum bisa ditagih dan tidak masuk achievement.
                            Nilai kontrak, sales, dan timeline penagihan dilengkapi lewat modul <strong>Project</strong>.
                        @else
                            Di sini hanya data PO, dokumen, dan masa garansi yang diubah. Nilai kontrak, sales, dan timeline penagihan tetap dikelola di modul <strong>Project</strong>.
                        @endif
                    </div>
                </div>
                <button type="button" id="ip-save-btn" class="btn-accent w-100 justify-content-center" onclick="ipSave()">
                    <i class="fa fa-floppy-disk"></i>
                    <span>{{ $project ? 'Simpan Perubahan' : 'Simpan Project' }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const ipIsEdit = {{ $project ? 'true' : 'false' }};
const ipProjectId = {{ $project?->id ?? 'null' }};
const ipStoreUrl = '{{ route("ims-project.store") }}';
const ipUpdateUrl = '{{ $project ? route("ims-project.update", $project->id) : "" }}';
const ipShowUrl = '{{ route("ims-project.show", "__ID__") }}';
const ipSearchCompanyUrl = '{{ route("ims-project.search-companies") }}';
const ipInitialCompanyId = @json($project?->company_id);
const ipInitialCompanyName = @json($project?->company?->account_name);
const ipCompanyLocked = {{ $project?->quotation_id ? 'true' : 'false' }};
const ipMaxFileSize = 10 * 1024 * 1024;

function ipEsc(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function ipInitCompany() {
    $('#ip-company').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Cari company...',
        allowClear: true,
        ajax: {
            url: ipSearchCompanyUrl,
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term || '' }; },
            processResults: function(data) { return data; }
        }
    });

    if (ipInitialCompanyId) {
        $('#ip-company').append(new Option(ipInitialCompanyName || ('Company #' + ipInitialCompanyId), ipInitialCompanyId, true, true));
    }

    if (ipCompanyLocked) $('#ip-company').prop('disabled', true).trigger('change.select2');

    $('#ip-company').on('select2:select', function(e) {
        $('#ip-company-id').val(e.params.data.id);
        ipClearInvalid(this);
    }).on('select2:clear', function() {
        $('#ip-company-id').val('');
    });
}

function ipUpdateDueHint() {
    var po = $('#ip-po-date').val();
    var due = $('#ip-po-due-date').val();
    var $hint = $('#ip-due-hint');
    if (!po || !due) { $hint.text(''); return; }

    var days = Math.round((new Date(due + 'T00:00:00') - new Date(po + 'T00:00:00')) / 86400000);
    if (days < 0) {
        $hint.html('<span class="text-danger"><i class="fa fa-triangle-exclamation me-1"></i>Sebelum tanggal PO.</span>');
    } else {
        $hint.text(days + ' hari dari tanggal PO.');
    }
}

// ── Masa garansi ──
function ipWarrantyMonths(months) {
    var start = $('#ip-warranty-start').val();
    if (!start) {
        toastr.info('Isi tanggal mulai garansi dulu.');
        $('#ip-warranty-start').trigger('focus');
        return;
    }
    var d = new Date(start + 'T00:00:00');
    var day = d.getDate();
    d.setMonth(d.getMonth() + months);
    if (d.getDate() !== day) d.setDate(0); // mis. 31 Jan + 1 bulan -> akhir Februari
    var pad = function(n) { return (n < 10 ? '0' : '') + n; };
    $('#ip-warranty-end').val(d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()));
    ipUpdateWarrantyHint();
}

function ipClearWarranty() {
    $('#ip-warranty-start, #ip-warranty-end').val('');
    ipUpdateWarrantyHint();
}

function ipUpdateWarrantyHint() {
    var start = $('#ip-warranty-start').val();
    var end = $('#ip-warranty-end').val();
    var $hint = $('#ip-warranty-hint');
    if (!start || !end) { $hint.text(start || end ? 'Isi kedua tanggal.' : ''); return; }

    var s = new Date(start + 'T00:00:00');
    var e = new Date(end + 'T00:00:00');
    if (e < s) {
        $hint.html('<span class="text-danger"><i class="fa fa-triangle-exclamation me-1"></i>Sebelum tanggal mulai.</span>');
        return;
    }
    var months = (e.getFullYear() - s.getFullYear()) * 12 + (e.getMonth() - s.getMonth()) - (e.getDate() < s.getDate() ? 1 : 0);
    var today = new Date(); today.setHours(0, 0, 0, 0);
    var left = Math.round((e - today) / 86400000);
    var status = today < s ? 'belum mulai' : (left < 0 ? 'sudah berakhir' : 'sisa ' + left + ' hari');
    $hint.text((months > 0 ? '± ' + months + ' bulan' : Math.round((e - s) / 86400000) + ' hari') + ' · ' + status + '.');
}

// ── Dokumen pendukung ──
function ipRenderPendingAgreements() {
    var files = document.getElementById('ip-agreement-files').files;
    var html = '';
    for (var i = 0; i < files.length; i++) {
        var kb = files[i].size / 1024;
        html += '<div class="ip-doc-row pending"><i class="fa fa-cloud-arrow-up" style="color:var(--accent)"></i>'
            + '<div style="min-width:0;flex:1"><div class="name">' + ipEsc(files[i].name) + '</div>'
            + '<div class="meta">' + (kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : Math.max(1, Math.round(kb)) + ' KB') + ' · akan diupload saat disimpan</div></div></div>';
    }
    $('#ip-agreement-pending').html(html);
}

function ipDeleteAgreement(btn, url) {
    Swal.fire({
        title: 'Hapus dokumen?',
        text: 'File dokumen pendukung ini akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $.ajax({ url: url, method: 'DELETE', data: { _token: '{{ csrf_token() }}' } })
            .done(function(res) {
                $(btn).closest('.ip-doc-row').remove();
                toastr.success(res.message || 'Dokumen dihapus.');
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus dokumen.');
            });
    });
}

// ── Validasi & simpan ──
function ipFieldTarget(selector) {
    var $el = $(selector);
    return $el.hasClass('select2-hidden-accessible') ? $el.next('.select2-container').find('.select2-selection') : $el;
}

function ipClearInvalid(el) {
    ipFieldTarget(el).removeClass('is-invalid');
}

function ipFail(selector, message) {
    var $target = ipFieldTarget(selector);
    $target.addClass('is-invalid');
    toastr.error(message);

    var top = $target.offset() ? $target.offset().top - 120 : 0;
    $('html, body').animate({ scrollTop: Math.max(top, 0) }, 250);
    if (!$(selector).hasClass('select2-hidden-accessible')) $(selector).trigger('focus');

    return false;
}

function ipValidate() {
    $('.is-invalid').removeClass('is-invalid');

    if (!$.trim($('#ip-project-name').val())) return ipFail('#ip-project-name', 'Nama project wajib diisi.');
    if (!$('#ip-company-id').val()) return ipFail('#ip-company', 'Company wajib dipilih.');
    if (!$.trim($('#ip-po-number').val())) return ipFail('#ip-po-number', 'No PO customer wajib diisi.');
    if (!$('#ip-po-date').val()) return ipFail('#ip-po-date', 'Tanggal PO wajib diisi.');
    if (!$('#ip-po-date-received').val()) return ipFail('#ip-po-date-received', 'Tanggal PO diterima wajib diisi.');
    if (!$('#ip-po-due-date').val()) return ipFail('#ip-po-due-date', 'Jatuh tempo wajib diisi.');
    if ($('#ip-po-due-date').val() < $('#ip-po-date').val()) return ipFail('#ip-po-due-date', 'Jatuh tempo tidak boleh sebelum tanggal PO.');

    var file = document.getElementById('ip-po-file').files[0];
    if (file && file.size > ipMaxFileSize) return ipFail('#ip-po-file', 'Ukuran file tidak boleh lebih dari 10MB.');

    var agreementFiles = document.getElementById('ip-agreement-files').files;
    if (agreementFiles.length > 10) return ipFail('#ip-agreement-files', 'Maksimal 10 file sekali upload.');
    for (var f = 0; f < agreementFiles.length; f++) {
        if (agreementFiles[f].size > ipMaxFileSize) return ipFail('#ip-agreement-files', 'File "' + agreementFiles[f].name + '" lebih dari 10MB.');
    }

    var wStart = $('#ip-warranty-start').val();
    var wEnd = $('#ip-warranty-end').val();
    if (wStart && !wEnd) return ipFail('#ip-warranty-end', 'Tanggal akhir garansi wajib diisi bila tanggal mulai diisi.');
    if (wEnd && !wStart) return ipFail('#ip-warranty-start', 'Tanggal mulai garansi wajib diisi bila tanggal akhir diisi.');
    if (wStart && wEnd && wEnd < wStart) return ipFail('#ip-warranty-end', 'Tanggal akhir garansi tidak boleh sebelum tanggal mulai.');

    return true;
}

function ipSetSaving(saving) {
    var $btn = $('#ip-save-btn');
    if (saving) {
        $btn.data('label', $btn.html());
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    } else {
        $btn.prop('disabled', false).html($btn.data('label'));
    }
}

function ipSave() {
    if (!ipValidate()) return;

    var fd = new FormData();
    fd.append('_token', '{{ csrf_token() }}');
    if (ipIsEdit) fd.append('_method', 'PUT');

    fd.append('project_name', $('#ip-project-name').val());
    fd.append('project_description', $('#ip-project-description').val());
    fd.append('company_id', $('#ip-company-id').val());
    fd.append('po_number_company', $('#ip-po-number').val());
    fd.append('po_date', $('#ip-po-date').val());
    fd.append('po_date_received', $('#ip-po-date-received').val());
    fd.append('po_due_date', $('#ip-po-due-date').val());
    fd.append('warranty_start_date', $('#ip-warranty-start').val());
    fd.append('warranty_end_date', $('#ip-warranty-end').val());

    var file = document.getElementById('ip-po-file').files[0];
    if (file) fd.append('po_document', file);

    var agreementFiles = document.getElementById('ip-agreement-files').files;
    for (var a = 0; a < agreementFiles.length; a++) fd.append('agreement_files[' + a + ']', agreementFiles[a]);
    fd.append('agreement_notes', $('#ip-agreement-notes').val());

    ipSetSaving(true);

    $.ajax({
        url: ipIsEdit ? ipUpdateUrl : ipStoreUrl,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false
    }).done(function(res) {
        toastr.success(res.message || 'Project tersimpan.');
        var id = ipIsEdit ? ipProjectId : res.id;
        setTimeout(function() { window.location.href = ipShowUrl.replace('__ID__', id); }, 700);
    }).fail(function(xhr) {
        ipSetSaving(false);
        var json = xhr.responseJSON || {};
        var message = json.message || 'Gagal menyimpan project.';
        if (json.errors) {
            var first = Object.keys(json.errors)[0];
            if (first) message = json.errors[first][0];
        }
        toastr.error(message);
    });
}

$(document).ready(function() {
    ipInitCompany();
    ipUpdateDueHint();
    ipUpdateWarrantyHint();

    $(document).on('input change', 'input, textarea', function() { ipClearInvalid(this); });
    $(document).on('change', '#ip-po-date, #ip-po-due-date', ipUpdateDueHint);
    $(document).on('change', '#ip-warranty-start, #ip-warranty-end', ipUpdateWarrantyHint);
    $('#ip-agreement-files').on('change', ipRenderPendingAgreements);
});
</script>
@endsection
