@extends('layouts.app')

@section('title', 'Permintaan Barang')
@section('page-title', 'Permintaan Barang')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Permintaan Barang</h1>
        <p class="page-header-sub">Pengajuan permintaan barang ke purchasing, terikat pada quotation yang PO Supplier Approval-nya sudah disetujui 2 approver</p>
    </div>
    @if($canCreate ?? false)
    <div class="page-header-actions">
        <a href="{{ route('goods-request.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Permintaan Barang</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-dolly me-2" style="color:var(--accent)"></i>Daftar Permintaan Barang</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="gr-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Opportunity</th>
                        <th>Quotation</th>
                        <th>To (Company)</th>
                        <th>Divisi</th>
                        <th class="text-center">Jml Item</th>
                        <th>Dibuat Oleh</th>
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
let grTable = null;

const grShowUrl = '{{ route("goods-request.show", "__ID__") }}';
const grEditUrl = '{{ route("goods-request.edit", "__ID__") }}';
const grDeleteUrl = '{{ route("goods-request.destroy", "__ID__") }}';
const grSubmitUrl = '{{ route("goods-request.submit", "__ID__") }}';
const grApproveUrl = '{{ route("goods-request.approve", "__ID__") }}';

function initGrTable() {
    if (grTable) grTable.destroy();

    grTable = $('#gr-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("goods-request.data") }}',
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'opportunity_name', orderable: false, searchable: true },
            { data: 'quotation_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + data + '</strong>'; }
            },
            { data: 'to_name', orderable: false, searchable: true,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'division_name', orderable: false, searchable: true },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'creator_name', orderable: false, searchable: true },
            { data: 'status_badge', orderable: false, searchable: false },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1 flex-wrap">';
                    btn += '<a href="' + grShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (row.status === 'draft' && row.is_creator) {
                        btn += '<a href="' + grEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Edit"><i class="fa fa-pen"></i></a>';
                        btn += '<button class="btn-icon" title="Submit Approval" onclick="submitGr(' + data + ')"><i class="fa fa-paper-plane"></i></button>';
                        btn += '<button class="btn-icon text-danger" title="Hapus" onclick="deleteGr(' + data + ')"><i class="fa fa-trash"></i></button>';
                    }
                    if (row.can_approve) {
                        btn += '<button class="btn-icon" title="Approve" onclick="approveGr(' + data + ')"><i class="fa fa-check"></i></button>';
                    }
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function submitGr(id) {
    Swal.fire({
        title: 'Submit Approval?',
        text: 'Permintaan barang akan dikirim untuk approval.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Submit',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(grSubmitUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Dikirim untuk approval.');
                grTable.ajax.reload();
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal submit.');
            });
    });
}

function approveGr(id) {
    Swal.fire({
        title: 'Approve Permintaan Barang?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(grApproveUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Disetujui.');
                grTable.ajax.reload();
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal approve.');
            });
    });
}

function deleteGr(id) {
    Swal.fire({
        title: 'Hapus Permintaan Barang?',
        text: 'Permintaan barang #' + id + ' akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: grDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Dihapus.');
            grTable.ajax.reload();
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}

$(document).ready(function() {
    initGrTable();
});
</script>
@endsection
