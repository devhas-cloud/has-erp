@extends('layouts.app')

@section('title', 'Estimasi PL')
@section('page-title', 'Estimasi Perhitungan Pendapatan')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Estimasi PL</h1>
        <p class="page-header-sub">Estimasi Perhitungan Pendapatan (profit &amp; loss) per quotation</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('profit-estimate.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Estimasi PL</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-chart-line me-2" style="color:var(--accent)"></i>Daftar Estimasi PL</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="pl-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Nomor Quotation</th>
                        <th>Project / Company</th>
                        <th>Tanggal</th>
                        <th class="text-end">Nilai Real Project</th>
                        <th class="text-end">Total Biaya</th>
                        <th class="text-end">Real Profit</th>
                        <th>Status</th>
                        <th class="text-center" style="width:160px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let plTable = null;
const plShowUrl = '{{ route("profit-estimate.show", "__ID__") }}';
const plEditUrl = '{{ route("profit-estimate.edit", "__ID__") }}';
const plPdfUrl = '{{ route("profit-estimate.pdf", "__ID__") }}';
const plDeleteUrl = '{{ route("profit-estimate.destroy", "__ID__") }}';
const plCanUpdate = {{ $canUpdate ? 'true' : 'false' }};
const plCanDelete = {{ $canDelete ? 'true' : 'false' }};

function initPlTable() {
    if (plTable) plTable.destroy();

    plTable = $('#pl-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("profit-estimate.data") }}',
        order: [[3, 'desc']],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'quotation_number', orderable: false, render: function(d) { return '<strong style="color:var(--text-primary)">' + d + '</strong>'; } },
            { data: 'project_name', orderable: false },
            { data: 'date', orderable: true, searchable: false },
            { data: 'real_project_value', orderable: true, searchable: false, className: 'text-end' },
            { data: 'total_cost', orderable: true, searchable: false, className: 'text-end' },
            { data: 'real_profit', orderable: true, searchable: false, className: 'text-end',
                render: function(d, t, row) { return d + ' <span style="color:var(--text-muted);font-size:11px">(' + row.real_profit_percent + ')</span>'; } },
            { data: 'status_badge', orderable: false, searchable: false },
            { data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(id) {
                    var btn = '<div class="d-flex justify-content-center gap-1 flex-wrap">';
                    btn += '<a href="' + plShowUrl.replace('__ID__', id) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    btn += '<a href="' + plPdfUrl.replace('__ID__', id) + '" target="_blank" class="btn-icon" title="View PDF"><i class="fa fa-file-pdf"></i></a>';
                    if (plCanUpdate) btn += '<a href="' + plEditUrl.replace('__ID__', id) + '" class="btn-icon" title="Edit"><i class="fa fa-pen"></i></a>';
                    if (plCanDelete) btn += '<button class="btn-icon" title="Hapus" onclick="deletePl(' + id + ')"><i class="fa fa-trash"></i></button>';
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function deletePl(id) {
    Swal.fire({
        title: 'Hapus Estimasi PL?',
        text: 'Estimasi PL #' + id + ' akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: plDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Estimasi PL dihapus.');
            plTable.ajax.reload();
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}

$(document).ready(function() { initPlTable(); });
</script>
@endsection
