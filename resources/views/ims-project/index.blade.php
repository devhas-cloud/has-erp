@extends('layouts.app')

@section('title', 'IMS Project')
@section('page-title', 'IMS Project')

@section('styles')
<style>
    .ip-pill { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
    .ip-pill.ok { background: var(--accent-soft); color: var(--accent); }
    .ip-pill.warn { background: #fef3c7; color: #92400e; }
    .ip-pill.danger { background: var(--danger-soft); color: #7f1d1d; }
    .ip-pill.none { background: transparent; border: 1px dashed var(--card-border); color: var(--text-muted); font-weight: 500; }
    .ip-period { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; white-space: nowrap; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">IMS Project</h1>
        <p class="page-header-sub">Daftar sama dengan modul Project. IMS mengelola data PO, dokumen, dan masa garansi untuk maintenance; nilai kontrak &amp; sales dilengkapi di modul Project.</p>
    </div>
    @if($canCreate ?? false)
    <div class="page-header-actions">
        <a href="{{ route('ims-project.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Tambah Project</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-shield-halved me-2" style="color:var(--accent)"></i>Daftar Project</span>
        <span style="font-size:12px;color:var(--text-muted)"><span class="ip-pill warn">Belum lengkap</span> = belum ada nilai kontrak &amp; sales</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="ip-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No Project</th>
                        <th>Nama Project</th>
                        <th>Quotation</th>
                        <th>Company</th>
                        <th>No PO Customer</th>
                        <th>Tgl PO</th>
                        <th>Jatuh Tempo</th>
                        <th>Garansi</th>
                        <th>Status</th>
                        <th class="text-center" style="width:120px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let ipTable = null;

const ipShowUrl = '{{ route("ims-project.show", "__ID__") }}';
const ipEditUrl = '{{ route("ims-project.edit", "__ID__") }}';
const ipDeleteUrl = '{{ route("ims-project.destroy", "__ID__") }}';
const ipCanUpdate = {{ ($canUpdate ?? false) ? 'true' : 'false' }};
const ipCanDelete = {{ ($canDelete ?? false) ? 'true' : 'false' }};

function ipEsc(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function ipInitTable() {
    ipTable = $('#ip-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("ims-project.data") }}',
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'project_number', orderable: false,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + ipEsc(data) + '</strong>'; }
            },
            { data: 'project_name', orderable: false,
                render: function(data, type, row) {
                    return ipEsc(data) + (row.is_hybrid ? '<div><span class="ip-pill warn mt-1">Belum lengkap</span></div>' : '');
                }
            },
            { data: 'quotation_number', orderable: false, render: function(data) { return data ? ipEsc(data) : '—'; } },
            { data: 'company_name', orderable: false, render: function(data) { return ipEsc(data); } },
            { data: 'po_number_company', orderable: false, render: function(data) { return ipEsc(data); } },
            { data: 'po_date', orderable: false },
            { data: 'po_due_date', orderable: false },
            { data: 'warranty_label', orderable: false,
                render: function(data, type, row) {
                    if (!data) return '<span class="ip-pill none">Belum diisi</span>';
                    return '<span class="ip-pill ' + row.warranty_class + '"><i class="fa-solid fa-shield-halved"></i>' + ipEsc(data) + '</span>'
                        + '<div class="ip-period">' + ipEsc(row.warranty_period) + '</div>';
                }
            },
            { data: 'status_badge', orderable: false },
            {
                data: 'id', orderable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1 flex-wrap">';
                    btn += '<a href="' + ipShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (ipCanUpdate) {
                        btn += '<a href="' + ipEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Edit"><i class="fa fa-pen"></i></a>';
                    }
                    if (ipCanDelete && row.is_hybrid) {
                        btn += '<button class="btn-icon text-danger" title="Hapus" onclick="ipDelete(' + data + ')"><i class="fa fa-trash"></i></button>';
                    }
                    return btn + '</div>';
                }
            }
        ]
    });
}

function ipDelete(id) {
    Swal.fire({
        title: 'Hapus Project?',
        text: 'Data project, dokumen, dan masa garansi akan dihapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({ url: ipDeleteUrl.replace('__ID__', id), method: 'DELETE', data: { _token: '{{ csrf_token() }}' } })
            .done(function(res) {
                toastr.success(res.message || 'Project dihapus.');
                ipTable.ajax.reload(null, false);
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus project.');
            });
    });
}

$(document).ready(ipInitTable);
</script>
@endsection
