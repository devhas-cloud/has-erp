@extends('layouts.app')

@php
    $isEdit = (bool) $mr;
    $initialItems = $mr
        ? $mr->items->map(fn ($item) => [
            'master_product_id' => $item->master_product_id,
            'code' => $item->masterProduct?->code,
            'name' => $item->masterProduct?->name,
            'unit' => $item->masterProduct?->unit ?? $item->unit,
            'is_material' => (bool) $item->masterProduct?->is_material,
            'qty' => $item->qty,
            'note' => $item->note,
        ])->values()
        : collect();
@endphp

@section('title', $isEdit ? 'Ubah Material Request' : 'Buat Material Request')
@section('page-title', $isEdit ? 'Ubah Material Request' : 'Buat Material Request')

@section('styles')
<style>
    .mr-tp-list { border: 1px solid var(--card-border, #e5e7eb); border-radius: 6px; max-height: 50vh; overflow-y: auto; }
    .mr-tp-item { padding: 9px 12px; border-bottom: 1px solid var(--card-border, #e5e7eb); cursor: pointer; transition: background-color .15s; }
    .mr-tp-item:last-child { border-bottom: 0; }
    .mr-tp-item:hover { background: #d1fae5; }
    .mr-tp-item.active { background: #ecfdf5; box-shadow: inset 3px 0 0 var(--accent); }
    .mr-tp-item .mr-tp-name { font-weight: 600; font-size: 13px; }
    .mr-tp-item .mr-tp-meta { font-size: 11.5px; color: var(--text-muted); }
    .mr-tp-preview { border: 1px solid var(--card-border, #e5e7eb); border-radius: 6px; max-height: 50vh; overflow-y: auto; }
    .mr-tp-preview table th { position: sticky; top: 0; background: var(--card, #fff); font-size: 11.5px; text-transform: uppercase; color: var(--text-muted); }
    .mr-tp-preview tr.is-inactive td { opacity: .5; text-decoration: line-through; }
    .mr-tp-qty-new { color: #065f46; font-weight: 600; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Ubah '.$mr->request_number : 'Buat Material Request' }}</h1>
        <p class="page-header-sub">
            @if($isEdit && $mr->status === \App\Models\MaterialRequest::STATUS_REJECTED)
                Perbaiki lalu simpan — Material Request akan diajukan ulang untuk approval.
            @else
                Material yang dibutuhkan project untuk produksi. Disimpan langsung diajukan untuk approval.
            @endif
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $isEdit ? route('material-request.show', $mr->id) : route('material-request.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

@if($isEdit && $mr->reject_reason)
<div class="alert alert-danger py-2" style="font-size:13px"><i class="fa fa-circle-xmark me-1"></i>Ditolak: {{ $mr->reject_reason }}</div>
@endif

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-diagram-project me-2" style="color:var(--accent)"></i>Project &amp; Branch</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="mr-project">Project <span class="text-danger">*</span></label>
                        <select id="mr-project" class="form-select">
                            <option value="">— Pilih Project —</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" @selected(($mr->project_id ?? null) === $p->id)>{{ $p->project_number }} — {{ $p->project_name }}</option>
                            @endforeach
                        </select>
                        <small style="color:var(--text-muted)">Hanya project aktif.</small>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="mr-branch">Branch <span class="text-danger">*</span></label>
                        <select id="mr-branch" class="form-select">
                            <option value="">— Pilih Branch —</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" @selected($defaultBranchId === $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                        <small style="color:var(--text-muted)">Tempat produksi.</small>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="mr-date">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" id="mr-date" class="form-control" value="{{ $mr?->request_date?->toDateString() ?? now()->toDateString() }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="mr-note">Catatan</label>
                        <input type="text" id="mr-note" class="form-control" maxlength="2000" value="{{ $mr->note ?? '' }}" placeholder="Opsional">
                    </div>
                </div>
            </div>
        </div>

        @include('material-request.partials.items-editor', [
            'editorTitle' => 'Material',
            'editorExtraButtons' => '<button type="button" class="btn btn-sm btn-outline-primary" onclick="MrTemplatePicker.open()"><i class="fa fa-layer-group me-1"></i>Ambil dari Template</button>',
        ])
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in" style="position:sticky;top:80px">
            <div class="card-header-custom"><span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Jumlah material</td><td class="text-end fw-semibold" id="mr-sum-lines">0</td></tr>
                    <tr><td><span class="mr-kind mr-kind-material">Material</span></td><td class="text-end" id="mr-sum-material">0</td></tr>
                    <tr><td><span class="mr-kind mr-kind-serial">Ber-SN</span></td><td class="text-end" id="mr-sum-serial">0</td></tr>
                    <tr><td>Qty belum diisi</td><td class="text-end" id="mr-sum-empty">0</td></tr>
                </table>
                <div id="mr-sum-templates" class="mt-2" style="font-size:12px;display:none">
                    <div style="color:var(--text-muted)">Template dipakai:</div>
                    <div id="mr-sum-templates-list"></div>
                </div>
                <button type="button" class="btn btn-primary w-100 mt-3" id="mr-save" onclick="mrSave()">
                    <i class="fa fa-paper-plane me-1"></i> {{ $isEdit ? 'Simpan & Ajukan' : 'Ajukan' }}
                </button>
                @if($canCreate)
                <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2" onclick="mrOpenSaveTemplate()">
                    <i class="fa fa-floppy-disk me-1"></i> Simpan sebagai Template
                </button>
                @endif
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Satuan mengikuti master produk. Setelah disetujui, gudang menyerahkan material lewat Serah Terima Material.</div>
            </div>
        </div>
    </div>
</div>
@push('modals')
<div class="modal fade" id="mrTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-layer-group me-2" style="color:var(--accent)"></i>Ambil dari Template</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <div class="mr-pp-search mb-2">
                            <i class="fa fa-magnifying-glass"></i>
                            <input type="text" class="form-control form-control-sm" id="mr-tp-search" placeholder="Cari nama template atau material…" autocomplete="off">
                        </div>
                        <div class="mr-tp-list" id="mr-tp-list"></div>
                    </div>
                    <div class="col-md-7">
                        <div id="mr-tp-preview" class="mr-tp-preview">
                            <div class="mr-pp-empty"><i class="fa-solid fa-hand-pointer me-1"></i>Pilih template di sebelah kiri untuk melihat isinya.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <small style="color:var(--text-muted);font-size:12px"><i class="fa fa-circle-info me-1"></i>Material yang sudah ada di form akan dijumlahkan qty-nya.</small>
                <div class="d-flex gap-2 align-items-center">
                    <label for="mr-tp-multiplier" style="font-size:12.5px;color:var(--text-muted)" class="mb-0">Jumlah set</label>
                    <div class="input-group input-group-sm" style="width:120px">
                        <span class="input-group-text">&times;</span>
                        <input type="text" inputmode="decimal" class="form-control text-end" id="mr-tp-multiplier" value="1">
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary btn-sm" id="mr-tp-apply" disabled><i class="fa fa-download me-1"></i> Ambil Material</button>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canCreate)
<div class="modal fade" id="mrSaveTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-floppy-disk me-2" style="color:var(--accent)"></i>Simpan sebagai Template</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="mr-st-name">Nama Template <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="mr-st-name" maxlength="150" placeholder="mis. Panel Kontrol Standar">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="mr-st-desc">Keterangan</label>
                    <textarea class="form-control" id="mr-st-desc" rows="2" maxlength="2000" placeholder="Opsional"></textarea>
                </div>
                <div style="font-size:12px;color:var(--text-muted)"><i class="fa fa-circle-info me-1"></i><span id="mr-st-info"></span> Qty disimpan sebagai kebutuhan per 1 set.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="mr-st-save" onclick="mrSaveTemplate()"><i class="fa fa-check me-1"></i> Simpan Template</button>
            </div>
        </div>
    </div>
</div>
@endif
@endpush
@endsection

@section('scripts')
<script>
const mrSaveUrl = '{{ $isEdit ? route("material-request.update", $mr->id) : route("material-request.store") }}';
const mrSaveMethod = '{{ $isEdit ? "PUT" : "POST" }}';
const mrShowUrl = '{{ route("material-request.show", "__ID__") }}';
const mrTemplateOptionsUrl = '{{ route("material-request.template-options") }}';
const mrTemplateItemsUrl = '{{ route("material-request.template-items", "__ID__") }}';
const mrTemplateStoreUrl = '{{ route("material-request.template-store") }}';
const mrCsrf = '{{ csrf_token() }}';
const mrInitial = @json($initialItems);
let mrSaveLabel = '';
let mrUsedTemplates = [];

function mrSummary(items) {
    var material = 0, serial = 0, empty = 0;
    items.forEach(function(it) {
        if (it.is_material) material++; else serial++;
        if (!(parseFloat(it.qty) > 0)) empty++;
    });
    $('#mr-sum-lines').text(items.length);
    $('#mr-sum-material').text(material);
    $('#mr-sum-serial').text(serial);
    $('#mr-sum-empty').html(empty ? '<span class="text-danger fw-semibold">' + empty + '</span>' : '0');
}

/* ── Popup Ambil dari Template ── */
var MrTemplatePicker = (function() {
    var modal = null, timer = null, current = null;

    function multiplier() {
        var m = parseFloat(String($('#mr-tp-multiplier').val()).replace(/,/g, ''));
        return m > 0 ? m : 0;
    }

    function loadList() {
        $('#mr-tp-list').html('<div class="mr-pp-empty"><span class="spinner-border spinner-border-sm me-1"></span>Memuat…</div>');
        $.getJSON(mrTemplateOptionsUrl, { q: $('#mr-tp-search').val() }).done(function(res) {
            var list = res.data || [];
            if (!list.length) {
                $('#mr-tp-list').html('<div class="mr-pp-empty"><i class="fa-solid fa-inbox me-1"></i>Belum ada template aktif' + ($('#mr-tp-search').val() ? ' yang cocok' : '') + '.</div>');
                return;
            }
            var html = '';
            list.forEach(function(t) {
                html += '<div class="mr-tp-item' + (current && current.template.id === t.id ? ' active' : '') + '" data-id="' + t.id + '">'
                    + '<div class="mr-tp-name">' + mrEsc(t.name) + '</div>'
                    + '<div class="mr-tp-meta">' + t.item_count + ' material' + (t.description ? ' · ' + mrEsc(t.description) : '') + '</div></div>';
            });
            $('#mr-tp-list').html(html);
        });
    }

    function renderPreview() {
        if (!current) return;
        var m = multiplier(), existing = MrItems.ids(), usable = 0;
        var html = '<div class="px-3 pt-2 pb-1"><div class="fw-semibold">' + mrEsc(current.template.name) + '</div>'
            + (current.template.description ? '<div style="font-size:12px;color:var(--text-muted)">' + mrEsc(current.template.description) + '</div>' : '') + '</div>'
            + '<table class="table table-custom table-sm align-middle mb-0"><thead><tr><th>Produk</th><th class="text-end">Per set</th><th class="text-end">Diambil</th><th></th></tr></thead><tbody>';
        current.items.forEach(function(it) {
            var qty = mrRound(it.qty * m);
            if (!it.is_material) qty = Math.round(qty);
            var inForm = existing.indexOf(String(it.master_product_id)) !== -1;
            if (it.active) usable++;
            html += '<tr class="' + (it.active ? '' : 'is-inactive') + '">'
                + '<td><div class="mr-prod-code">' + mrEsc(it.code) + '</div><div class="mr-prod-name">' + mrEsc(it.name || '') + '</div></td>'
                + '<td class="text-end">' + Num.format(it.qty, 4) + ' <span style="color:var(--text-muted);font-size:11px">' + mrEsc(it.unit || '') + '</span></td>'
                + '<td class="text-end mr-tp-qty-new">' + (it.active ? Num.format(qty, 4) : '—') + '</td>'
                + '<td>' + (!it.active ? '<span class="mr-pp-existing" title="Produk tidak aktif di master, tidak diambil">Nonaktif</span>' : (inForm ? '<span class="mr-pp-existing" title="Qty akan dijumlahkan dengan baris yang sudah ada">+ dijumlahkan</span>' : '')) + '</td>'
                + '</tr>';
        });
        html += '</tbody></table>';
        $('#mr-tp-preview').html(html);
        $('#mr-tp-apply').prop('disabled', !(usable > 0 && m > 0)).html('<i class="fa fa-download me-1"></i> Ambil ' + usable + ' Material');
    }

    function select(id) {
        $('#mr-tp-list .mr-tp-item').removeClass('active').filter('[data-id="' + id + '"]').addClass('active');
        $('#mr-tp-preview').html('<div class="mr-pp-empty"><span class="spinner-border spinner-border-sm me-1"></span>Memuat isi template…</div>');
        $.getJSON(mrTemplateItemsUrl.replace('__ID__', id)).done(function(res) {
            current = res;
            renderPreview();
        }).fail(function() { toastr.error('Gagal memuat isi template.'); });
    }

    function apply() {
        if (!current) return;
        var m = multiplier();
        if (!(m > 0)) { toastr.error('Jumlah set harus lebih dari 0.'); return; }
        var list = current.items.filter(function(it) { return it.active; }).map(function(it) {
            return $.extend({}, it, { qty: mrRound(it.qty * m) });
        });
        var res = MrItems.add(list, { focusEmpty: false });
        var label = current.template.name + (m !== 1 ? ' × ' + Num.format(m, 4) : '');
        mrUsedTemplates.push(label);
        $('#mr-sum-templates').show();
        $('#mr-sum-templates-list').html(mrUsedTemplates.map(function(t) { return '<div><i class="fa fa-layer-group me-1" style="color:var(--accent)"></i>' + mrEsc(t) + '</div>'; }).join(''));
        modal.hide();
        toastr.success('Template "' + current.template.name + '": ' + res.added + ' material ditambahkan' + (res.merged ? ', ' + res.merged + ' dijumlahkan' : '') + '.');
    }

    $(document).on('click', '#mr-tp-list .mr-tp-item', function() { select($(this).data('id')); });
    $(document).on('dblclick', '#mr-tp-list .mr-tp-item', function() { setTimeout(apply, 300); });

    $(function() {
        $('#mr-tp-search').on('input', function() { clearTimeout(timer); timer = setTimeout(loadList, 300); });
        $('#mr-tp-multiplier').on('input', function() {
            this.value = this.value.replace(/[^\d.]/g, '');
            renderPreview();
        });
        $('#mr-tp-apply').on('click', apply);
        $('#mrTemplateModal').on('shown.bs.modal', function() { $('#mr-tp-search').trigger('focus'); });
    });

    return {
        open: function() {
            if (!modal) modal = new bootstrap.Modal(document.getElementById('mrTemplateModal'));
            current = null;
            $('#mr-tp-multiplier').val('1');
            $('#mr-tp-apply').prop('disabled', true).html('<i class="fa fa-download me-1"></i> Ambil Material');
            $('#mr-tp-preview').html('<div class="mr-pp-empty"><i class="fa-solid fa-hand-pointer me-1"></i>Pilih template di sebelah kiri untuk melihat isinya.</div>');
            modal.show();
            loadList();
        }
    };
})();

/* ── Simpan isi form sebagai template ── */
let mrSaveTemplateModal = null;
function mrOpenSaveTemplate() {
    var res = MrItems.collect();
    if (res.error) { toastr.error(res.error); return; }
    $('#mr-st-info').text(res.items.length + ' material akan disimpan.');
    if (!mrSaveTemplateModal) mrSaveTemplateModal = new bootstrap.Modal(document.getElementById('mrSaveTemplateModal'));
    mrSaveTemplateModal.show();
    setTimeout(function() { $('#mr-st-name').trigger('focus'); }, 300);
}

function mrSaveTemplate() {
    var res = MrItems.collect();
    if (res.error) { toastr.error(res.error); return; }
    var name = $('#mr-st-name').val().trim();
    if (!name) { toastr.error('Nama template wajib diisi.'); $('#mr-st-name').trigger('focus'); return; }
    var label = $('#mr-st-save').html();
    $('#mr-st-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: mrTemplateStoreUrl, method: 'POST', contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': mrCsrf, 'Accept': 'application/json' },
        data: JSON.stringify({ name: name, description: $('#mr-st-desc').val(), items: res.items })
    }).done(function(r) {
        toastr.success(r.message || 'Template tersimpan.');
        mrSaveTemplateModal.hide();
        $('#mr-st-name, #mr-st-desc').val('');
    }).fail(function(xhr) {
        toastr.error(mrErrorMessage(xhr, 'Gagal menyimpan template.'));
    }).always(function() {
        $('#mr-st-save').prop('disabled', false).html(label);
    });
}

function mrErrorMessage(xhr, fallback) {
    var j = xhr.responseJSON || {};
    if (j.errors) return j.errors[Object.keys(j.errors)[0]][0];
    return j.message || fallback;
}

function mrSave() {
    if (!$('#mr-project').val()) { toastr.error('Pilih project.'); return; }
    if (!$('#mr-branch').val()) { toastr.error('Pilih branch.'); return; }
    var res = MrItems.collect();
    if (res.error) { toastr.error(res.error); return; }

    $('#mr-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: mrSaveUrl, method: mrSaveMethod, contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': mrCsrf, 'Accept': 'application/json' },
        data: JSON.stringify({
            project_id: $('#mr-project').val(),
            branch_id: $('#mr-branch').val(),
            request_date: $('#mr-date').val(),
            note: $('#mr-note').val(),
            items: res.items
        })
    }).done(function(r) {
        toastr.success(r.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = mrShowUrl.replace('__ID__', r.id); }, 600);
    }).fail(function(xhr) {
        toastr.error(mrErrorMessage(xhr, 'Gagal menyimpan.'));
        $('#mr-save').prop('disabled', false).html(mrSaveLabel);
    });
}

$(document).ready(function() {
    mrSaveLabel = $('#mr-save').html();
    $('#mr-project').select2({ theme: 'bootstrap-5', width: '100%' });
    MrItems.init(mrInitial, mrSummary);
});
</script>
@endsection
