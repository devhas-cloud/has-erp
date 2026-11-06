{{--
    Kartu arsip dokumen pembelian (opsional) — dipakai di detail PO, Direct
    Purchase, dan Penerimaan Barang.
    Parameter: $documents (Document::toListItem[]), $types ([type => label]),
               $uploadUrl, $canUpload (bool), $hint (opsional).
--}}
<div class="card-custom fade-in mb-3" id="pdoc-card">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-folder-open me-2" style="color:var(--accent)"></i>Purchase Documents <span style="color:var(--text-muted);font-size:12px" id="pdoc-count">({{ count($documents) }})</span></span>
        @if($canUpload)
            <button type="button" class="btn btn-soft btn-sm" onclick="$('#pdoc-form').slideToggle(150)"><i class="fa fa-upload me-1"></i> Upload Document</button>
        @endif
    </div>
    <div class="card-body-custom">
        @if($canUpload)
        <form id="pdoc-form" class="mb-3 p-3" style="display:none;background:var(--accent-subtle);border:1px dashed var(--card-border);border-radius:var(--radius-sm)" onsubmit="return pdocUpload(event)">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Document Type <span class="text-danger">*</span></label>
                    <select id="pdoc-type" class="form-select form-select-sm">
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">File <span class="text-danger">*</span></label>
                    <input type="file" id="pdoc-files" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" multiple>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Notes</label>
                    <input type="text" id="pdoc-notes" class="form-control form-control-sm" maxlength="255" placeholder="Optional">
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                <small style="color:var(--text-muted)">PDF / JPG / PNG, max 10MB per file, {{ \App\Models\Document::MAX_FILES }} files per upload.</small>
                <button type="submit" class="btn btn-primary btn-sm" id="pdoc-submit"><i class="fa fa-upload me-1"></i> Upload</button>
            </div>
        </form>
        @endif

        <div id="pdoc-list"></div>
        <small class="d-block mt-2" style="color:var(--text-muted)"><i class="fa fa-circle-info me-1"></i>{{ $hint ?? 'Optional — purchase archive reference.' }}</small>
    </div>
</div>

@push('page-scripts')
<script>
let pdocDocuments = @json($documents);
const pdocUploadUrl = @json($uploadUrl);

function pdocEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function pdocRender() {
    $('#pdoc-count').text('(' + pdocDocuments.length + ')');
    if (!pdocDocuments.length) {
        $('#pdoc-list').html('<div style="color:var(--text-muted);font-size:13px"><i class="fa-solid fa-inbox me-1"></i>No documents yet.</div>');
        return;
    }
    var html = '<div class="d-flex flex-column gap-2">';
    pdocDocuments.forEach(function(d) {
        html += '<div class="d-flex align-items-center gap-2 p-2" style="border:1px solid var(--card-border);border-radius:var(--radius-sm)">';
        html += '<i class="fa ' + d.icon + '" style="font-size:20px;color:var(--accent);width:24px;text-align:center"></i>';
        html += '<div style="flex:1;min-width:0">';
        html += '<a href="' + d.view_url + '" target="_blank" class="fw-semibold text-decoration-none" style="font-size:13px;word-break:break-all">' + pdocEsc(d.file_name) + '</a>';
        html += '<div style="font-size:11.5px;color:var(--text-muted)">';
        if (d.type_label) html += '<span class="badge me-1" style="background:var(--accent-soft);color:var(--accent);font-size:10px">' + pdocEsc(d.type_label) + '</span>';
        html += pdocEsc(d.size) + ' · ' + pdocEsc(d.uploaded_by || '—') + ' · ' + pdocEsc(d.uploaded_at || '');
        if (d.notes) html += ' · ' + pdocEsc(d.notes);
        html += '</div></div>';
        if (d.delete_url) html += '<button type="button" class="btn-icon danger" title="Delete" onclick="pdocDelete(\'' + d.delete_url + '\')"><i class="fa fa-trash-can"></i></button>';
        else html += '<i class="fa fa-lock" style="color:var(--text-muted);font-size:12px" title="Archive — cannot be deleted"></i>';
        html += '</div>';
    });
    $('#pdoc-list').html(html + '</div>');
}

function pdocError(xhr, fallback) {
    var msg = fallback;
    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
    if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
    toastr.error(msg);
}

function pdocUpload(e) {
    e.preventDefault();
    var files = $('#pdoc-files')[0].files;
    if (!files.length) { toastr.error('Select at least one file.'); return false; }

    var fd = new FormData();
    fd.append('type', $('#pdoc-type').val());
    fd.append('notes', $('#pdoc-notes').val());
    for (var i = 0; i < files.length; i++) fd.append('files[]', files[i]);

    $('#pdoc-submit').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Uploading...');
    $.ajax({ url: pdocUploadUrl, method: 'POST', data: fd, processData: false, contentType: false, headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
        .done(function(res) {
            toastr.success(res.message || 'Documents uploaded.');
            pdocDocuments = res.documents || [];
            pdocRender();
            $('#pdoc-files').val('');
            $('#pdoc-notes').val('');
            $('#pdoc-form').slideUp(150);
        })
        .fail(function(xhr) { pdocError(xhr, 'Failed to upload documents.'); })
        .always(function() { $('#pdoc-submit').prop('disabled', false).html('<i class="fa fa-upload me-1"></i> Upload'); });
    return false;
}

function pdocDelete(url) {
    Swal.fire({ title: 'Delete document?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, Delete', cancelButtonText: 'Cancel' })
        .then(function(result) {
            if (!result.isConfirmed) return;
            $.ajax({ url: url, method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
                .done(function(res) { toastr.success(res.message || 'Deleted.'); pdocDocuments = res.documents || []; pdocRender(); })
                .fail(function(xhr) { pdocError(xhr, 'Failed to delete document.'); });
        });
}

$(document).ready(pdocRender);
</script>
@endpush
