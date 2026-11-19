@extends('layouts.app')

@section('title', 'Material Request')
@section('page-title', 'Material Request')

@section('styles')
@include('partials.list-toolbar')
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Material Request</h1>
        <p class="page-header-sub">Permintaan material project untuk produksi</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('material-request.create') }}" class="btn-accent" id="mr-action-list">
            <i class="fa fa-plus"></i>
            <span>Buat Material Request</span>
        </a>
        <a href="{{ route('material-request.template-create') }}" class="btn-accent" id="mr-action-template" style="display:none">
            <i class="fa fa-plus"></i>
            <span>Buat Template</span>
        </a>
    </div>
    @endif
</div>

<ul class="nav nav-tabs mb-3" id="mr-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="mr-tab-list-btn" data-bs-toggle="tab" data-bs-target="#mr-tab-list" data-tab="list" type="button" role="tab">
            <i class="fa-solid fa-clipboard-list me-1"></i> List Material
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="mr-tab-template-btn" data-bs-toggle="tab" data-bs-target="#mr-tab-template" data-tab="templates" type="button" role="tab">
            <i class="fa-solid fa-layer-group me-1"></i> Template Material
        </button>
    </li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="mr-tab-list" role="tabpanel">
<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-clipboard-list me-2" style="color:var(--accent)"></i>Daftar Material Request</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="mr-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. MR, project, material…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" aria-label="Status">
                <option value="">Semua Status</option>
                @foreach(\App\Models\MaterialRequest::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <select class="form-select" data-filter="project" data-searchable aria-label="Project">
                <option value="">Semua Project</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->project_number }} — {{ $p->project_name }}</option>
                @endforeach
            </select>
            @if($branches->count() > 1)
            <select class="form-select" data-filter="branch" aria-label="Branch">
                <option value="">Semua Branch</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                @endforeach
            </select>
            @endif
            <div class="lt-date" title="Tanggal MR">
                <input type="date" class="form-control" data-filter="date_from" aria-label="Dari tanggal">
                <span>–</span>
                <input type="date" class="form-control" data-filter="date_to" aria-label="Sampai tanggal">
            </div>
            <select class="form-select" data-filter="mine" aria-label="Pembuat">
                <option value="">Semua Pembuat</option>
                <option value="1">Dibuat oleh Saya</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="mr-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. MR</th>
                        <th>Tanggal</th>
                        <th>Project</th>
                        <th>Branch</th>
                        <th class="text-center">Material</th>
                        <th style="width:130px">Diserahkan</th>
                        <th>Status</th>
                        <th class="text-center" style="width:90px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
</div>

<div class="tab-pane fade" id="mr-tab-template" role="tabpanel">
<div class="card-custom">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-layer-group me-2" style="color:var(--accent)"></i>Template Material</span>
        <span style="font-size:12px;color:var(--text-muted)">Dipakai bersama semua user lewat tombol "Ambil dari Template" di form Material Request.</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="mrt-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari nama template, keterangan, material…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" aria-label="Status">
                <option value="">Semua Status</option>
                <option value="Active">Aktif</option>
                <option value="Inactive">Nonaktif</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
        </div>
        <div class="table-responsive">
            <table id="mrt-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Nama Template</th>
                        <th class="text-center" style="width:100px">Material</th>
                        <th style="width:180px">Terakhir Diubah</th>
                        <th style="width:100px">Status</th>
                        <th class="text-center" style="width:150px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
</div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="mrtPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-layer-group me-2" style="color:var(--accent)"></i><span id="mrt-preview-title">Template</span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="mrt-preview-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                @if($canUpdate)
                <a href="#" class="btn btn-primary btn-sm" id="mrt-preview-edit"><i class="fa fa-pen me-1"></i> Ubah Template</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let mrTable = null;
let mrToolbar = null;
const mrShowUrl = '{{ route("material-request.show", "__ID__") }}';
const mrEditUrl = '{{ route("material-request.edit", "__ID__") }}';

function mrEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

$(document).ready(function() {
    mrInitTabs();
    mrToolbar = ListToolbar('#mr-toolbar', function() { return mrTable; });
    mrTable = $('#mr-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Tidak ada Material Request yang cocok dengan pencarian / filter.</span>',
            emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Belum ada Material Request.</span>'
        },
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("material-request.data") }}', data: function(d) { return mrToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'request_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<strong style="color:var(--text-primary);font-variant-numeric:tabular-nums">' + mrEsc(data) + '</strong>'
                        + '<div style="font-size:11.5px;color:var(--text-muted)">' + mrEsc(row.creator) + '</div>';
                } },
            { data: 'request_date', orderable: false, searchable: false },
            { data: 'project', orderable: false, searchable: true, render: function(data) { return mrEsc(data); } },
            { data: 'branch', orderable: false, searchable: false, render: function(data) { return mrEsc(data); } },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'progress', orderable: false, searchable: false,
                render: function(data) {
                    return '<div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:6px"><div class="progress-bar" style="width:' + data + '%;background:var(--accent)"></div></div>'
                        + '<span style="font-size:11.5px;color:var(--text-muted);min-width:32px">' + data + '%</span></div>';
                } },
            { data: 'status_badge', orderable: false, searchable: false },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    btn += '<a href="' + mrShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    @if($canUpdate)
                    if (row.can_edit) btn += '<a href="' + mrEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Ubah"><i class="fa fa-pen"></i></a>';
                    @endif
                    return btn + '</div>';
                } }
        ]
    });
});

/* ── Tab ── */
const MR_TAB_KEY = 'material-request-tab';
function mrInitTabs() {
    var want = window.location.hash === '#templates' ? 'templates' : null;
    if (!want) { try { want = localStorage.getItem(MR_TAB_KEY); } catch (e) {} }
    $('#mr-tabs [data-bs-toggle="tab"]').on('shown.bs.tab', function() {
        var tab = $(this).data('tab');
        $('#mr-action-list').toggle(tab === 'list');
        $('#mr-action-template').toggle(tab === 'templates');
        try { localStorage.setItem(MR_TAB_KEY, tab); } catch (e) {}
        if (tab === 'templates') mrtInit(); else if (mrTable) mrTable.columns.adjust();
        history.replaceState(null, '', tab === 'templates' ? '#templates' : window.location.pathname + window.location.search);
    });
    if (want === 'templates') bootstrap.Tab.getOrCreateInstance(document.getElementById('mr-tab-template-btn')).show();
}

/* ── Template Material ── */
let mrtTable = null;
let mrtToolbar = null;
let mrtPreviewModal = null;
const mrtEditUrl = '{{ route("material-request.template-edit", "__ID__") }}';
const mrtItemsUrl = '{{ route("material-request.template-items", "__ID__") }}';
const mrtDuplicateUrl = '{{ route("material-request.template-duplicate", "__ID__") }}';
const mrtDestroyUrl = '{{ route("material-request.template-destroy", "__ID__") }}';
const mrtCsrf = '{{ csrf_token() }}';

function mrtInit() {
    if (mrtTable) { mrtTable.columns.adjust(); return; }
    mrtToolbar = ListToolbar('#mrt-toolbar', function() { return mrtTable; });
    mrtTable = $('#mrt-table').DataTable({
        layout: ListToolbar.layout,
        language: {
            zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Tidak ada template yang cocok dengan pencarian / filter.</span>',
            emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Belum ada template. Buat dari tombol "Buat Template" atau "Simpan sebagai Template" di form Material Request.</span>'
        },
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route("material-request.template-data") }}', data: function(d) { return mrtToolbar.params(d); } },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'name', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<a href="javascript:void(0)" onclick="mrtPreview(' + row.id + ')" style="font-weight:600;color:var(--text-primary)">' + mrEsc(data) + '</a>'
                        + (row.description ? '<div style="font-size:11.5px;color:var(--text-muted)">' + mrEsc(row.description) + '</div>' : '');
                } },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'updated_at', orderable: false, searchable: false,
                render: function(data, type, row) {
                    return '<div style="font-size:12.5px">' + mrEsc(data) + '</div><div style="font-size:11.5px;color:var(--text-muted)">' + mrEsc(row.updated_by) + '</div>';
                } },
            { data: 'status', orderable: false, searchable: false,
                render: function(data) {
                    return data === 'Active'
                        ? '<span class="status-badge status-active">Aktif</span>'
                        : '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Nonaktif</span>';
                } },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    btn += '<button type="button" class="btn-icon" title="Lihat isi" onclick="mrtPreview(' + data + ')"><i class="fa fa-eye"></i></button>';
                    @if($canUpdate)
                    btn += '<a href="' + mrtEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Ubah"><i class="fa fa-pen"></i></a>';
                    @endif
                    @if($canCreate)
                    btn += '<button type="button" class="btn-icon" title="Duplikat" onclick="mrtDuplicate(' + data + ')"><i class="fa fa-copy"></i></button>';
                    @endif
                    @if($canDelete)
                    btn += '<button type="button" class="btn-icon danger" title="Hapus" data-name="' + mrEsc(row.name) + '" onclick="mrtDelete(' + data + ', this)"><i class="fa fa-trash-can"></i></button>';
                    @endif
                    return btn + '</div>';
                } }
        ]
    });
}

function mrtPreview(id) {
    if (!mrtPreviewModal) mrtPreviewModal = new bootstrap.Modal(document.getElementById('mrtPreviewModal'));
    $('#mrt-preview-title').text('Memuat…');
    $('#mrt-preview-body').html('<div class="text-center py-4" style="color:var(--text-muted)"><span class="spinner-border spinner-border-sm me-1"></span>Memuat…</div>');
    $('#mrt-preview-edit').attr('href', mrtEditUrl.replace('__ID__', id));
    mrtPreviewModal.show();
    $.getJSON(mrtItemsUrl.replace('__ID__', id)).done(function(res) {
        $('#mrt-preview-title').text(res.template.name);
        var html = (res.template.description ? '<p style="font-size:13px;color:var(--text-muted)">' + mrEsc(res.template.description) + '</p>' : '')
            + '<table class="table table-custom table-sm align-middle mb-0"><thead><tr><th style="width:40px">#</th><th>Produk</th><th>Jenis</th><th class="text-end">Qty / set</th><th>Catatan</th></tr></thead><tbody>';
        res.items.forEach(function(it, i) {
            html += '<tr' + (it.active ? '' : ' style="opacity:.55" title="Produk nonaktif di master — tidak ikut diambil ke MR"') + '>'
                + '<td style="color:var(--text-muted)">' + (i + 1) + '</td>'
                + '<td><div style="font-weight:600;font-size:13px">' + mrEsc(it.code) + '</div><div style="font-size:12px;color:var(--text-muted)">' + mrEsc(it.name || '') + '</div></td>'
                + '<td><span class="badge" style="font-size:10px;' + (it.is_material ? 'background:#e0f2fe;color:#075985' : 'background:#fef3c7;color:#92400e') + '">' + (it.is_material ? 'Material' : 'Ber-SN') + '</span>' + (it.active ? '' : ' <span class="badge bg-secondary" style="font-size:10px">Nonaktif</span>') + '</td>'
                + '<td class="text-end">' + Num.format(it.qty, 4) + ' <span style="font-size:11px;color:var(--text-muted)">' + mrEsc(it.unit || '') + '</span></td>'
                + '<td style="font-size:12px">' + mrEsc(it.note || '') + '</td></tr>';
        });
        $('#mrt-preview-body').html(html + '</tbody></table>');
    }).fail(function() { $('#mrt-preview-body').html('<div class="text-danger">Gagal memuat template.</div>'); });
}

function mrtDuplicate(id) {
    $.ajax({ url: mrtDuplicateUrl.replace('__ID__', id), method: 'POST', headers: { 'X-CSRF-TOKEN': mrtCsrf, 'Accept': 'application/json' } })
        .done(function(r) { toastr.success(r.message || 'Template diduplikat.'); mrtTable.ajax.reload(null, false); })
        .fail(function(xhr) { toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menduplikat.'); });
}

function mrtDelete(id, btn) {
    var name = $(btn).data('name');
    Swal.fire({
        title: 'Hapus template?', html: 'Template <strong>' + mrEsc(name) + '</strong> akan dihapus. Material Request yang sudah dibuat tidak terpengaruh.',
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({ url: mrtDestroyUrl.replace('__ID__', id), method: 'DELETE', headers: { 'X-CSRF-TOKEN': mrtCsrf, 'Accept': 'application/json' } })
            .done(function(r) { toastr.success(r.message || 'Template dihapus.'); mrtTable.ajax.reload(null, false); })
            .fail(function(xhr) { toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menghapus.'); });
    });
}
</script>
@endsection
