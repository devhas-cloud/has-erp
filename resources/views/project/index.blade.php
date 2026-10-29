@extends('layouts.app')

@section('title', 'Project')
@section('page-title', 'Project')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Project</h1>
        <p class="page-header-sub">Project dari quotation yang sudah deal. Sales dipilih di form project; rincian device dikelola di tab Achievements.</p>
    </div>
    @if($canCreate ?? false)
    <div class="page-header-actions">
        <a href="{{ route('project.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Project</span>
        </a>
    </div>
    @endif
</div>

<ul class="nav nav-tabs mb-3" id="pj-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="pj-tab-list-btn" data-bs-toggle="tab" data-bs-target="#pj-tab-list" type="button" role="tab">
            <i class="fa fa-list me-1"></i> Daftar
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="pj-tab-ach-btn" data-bs-toggle="tab" data-bs-target="#pj-tab-ach" type="button" role="tab">
            <i class="fa fa-trophy me-1"></i> Achievements
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="pj-tab-list" role="tabpanel">
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-diagram-project me-2" style="color:var(--accent)"></i>Daftar Project</span>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table id="pj-table" class="table table-custom align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:50px">#</th>
                                <th>No Project</th>
                                <th>Nama Project</th>
                                <th>Quotation</th>
                                <th>Company</th>
                                <th>No PO Customer</th>
                                <th>Tgl PO</th>
                                <th class="text-end">Net Sale</th>
                                <th>Tipe</th>
                                <th>Status</th>
                                <th class="text-center" style="width:120px">Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="pj-tab-ach" role="tabpanel">
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-trophy me-2" style="color:var(--accent)"></i>Rincian Achievement per Device</span>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table id="pj-ach-table" class="table table-custom align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:50px">#</th>
                                <th>No Project</th>
                                <th>Company Name</th>
                                <th>Nama Device</th>
                                <th>Qty </th>
                                <th class="text-end">Amount</th>
                                <th>Date PO</th>
                                <th>Divisi</th>
                                <th>Sales</th>
                                <th>PO number</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
@include('project.partials._number-format')
let pjTable = null;
let pjAchTable = null;

const pjShowUrl = '{{ route("project.show", "__ID__") }}';
const pjEditUrl = '{{ route("project.edit", "__ID__") }}';
const pjDeleteUrl = '{{ route("project.destroy", "__ID__") }}';
const pjAchievementsUrl = '{{ route("project.achievements", "__ID__") }}';
const pjCanUpdate = {{ $canUpdate ? 'true' : 'false' }};
const pjCanDelete = {{ $canDelete ? 'true' : 'false' }};

function initPjTable() {
    if (pjTable) pjTable.destroy();

    pjTable = $('#pj-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("project.data") }}',
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'project_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + data + '</strong>'; }
            },
            { data: 'project_name', orderable: false, searchable: true },
            { data: 'quotation_number', orderable: false, searchable: true },
            { data: 'company_name', orderable: false, searchable: true },
            { data: 'po_number_company', orderable: false, searchable: true },
            { data: 'po_date', orderable: false, searchable: false },
            { data: 'net_sale_amount', orderable: false, searchable: false, className: 'text-end',
                render: function(data) { return pjFormatMoney(data); }
            },
            { data: 'type_label', orderable: false, searchable: false },
            { data: 'status_badge', orderable: false, searchable: false },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) {
                    var btn = '<div class="d-flex justify-content-center gap-1 flex-wrap">';
                    btn += '<a href="' + pjShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (pjCanUpdate) {
                        btn += '<a href="' + pjEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Edit"><i class="fa fa-pen"></i></a>';
                    }
                    if (pjCanDelete) {
                        btn += '<button class="btn-icon text-danger" title="Hapus" onclick="pjDelete(' + data + ')"><i class="fa fa-trash"></i></button>';
                    }
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function initPjAchTable() {
    if (pjAchTable) return;

    pjAchTable = $('#pj-ach-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("project.achievements-data") }}',
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'project_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<a href="' + pjAchievementsUrl.replace('__ID__', row.project_id) + '" style="color:var(--accent);font-weight:600">' + data + '</a>';
                }
            },
            { data: 'company_name', orderable: false, searchable: true },
            { data: 'device_name', orderable: false, searchable: true },
            { data: 'qty', orderable: false, searchable: false,
                render: function(data, type, row) {
                    var qty = pjFormatNumber(data);
                    return row.unit ? qty + ' ' + row.unit : qty;
                }
            },
            { data: 'amount', orderable: false, searchable: false, className: 'text-end',
                render: function(data) { return pjFormatMoney(data); }
            },
            { data: 'po_date', orderable: false, searchable: false },
            { data: 'division_name', orderable: false, searchable: true },
            { data: 'sales_names', orderable: false, searchable: true },
            { data: 'po_number_company', orderable: false, searchable: true }
        ]
    });
}

function pjDelete(id) {
    Swal.fire({
        title: 'Hapus Project?',
        text: 'Data project, achievement, dan pembagian sales akan dihapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({ url: pjDeleteUrl.replace('__ID__', id), method: 'DELETE', data: { _token: '{{ csrf_token() }}' } })
            .done(function(res) {
                toastr.success(res.message || 'Project dihapus.');
                pjTable.ajax.reload(null, false);
                if (pjAchTable) pjAchTable.ajax.reload(null, false);
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus project.');
            });
    });
}

$(document).ready(function() {
    initPjTable();

    $('#pj-tab-ach-btn').on('shown.bs.tab', function() {
        initPjAchTable();
    });
});
</script>
@endsection
