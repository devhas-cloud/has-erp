@extends('layouts.app')

@section('title', 'Permintaan Barang')
@section('page-title', 'Permintaan Barang')

@section('styles')
@include('partials.list-toolbar')
<style>
    .gr-cell-main { font-weight: 600; color: var(--text-primary); }
    .gr-cell-sub { font-size: 12px; color: var(--text-muted); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Permintaan Barang</h1>
        <p class="page-header-sub">Pengajuan permintaan barang ke purchasing, terikat pada project yang PO Supplier Approval-nya sudah disetujui 2 approver</p>
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
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-dolly me-2" style="color:var(--accent)"></i>Daftar Permintaan Barang</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="list-toolbar" id="gr-toolbar">
            <div class="lt-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" class="form-control" data-search placeholder="Cari no. permintaan, project, customer, divisi, pembuat…" autocomplete="off">
                <button type="button" class="lt-clear" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
            </div>
            <select class="form-select" data-filter="status" data-status-select aria-label="Status">
                <option value="">Semua Status</option>
                @foreach(\App\Models\GoodsRequest::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}" data-label="{{ $label }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="form-select" data-filter="purpose" aria-label="Tujuan">
                <option value="">Semua Tujuan</option>
                @foreach(\App\Models\GoodsRequest::PURPOSE_LABELS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            @if($projects->isNotEmpty())
            <select class="form-select" data-filter="project_id" data-searchable data-hide-when="purpose:internal" aria-label="Project">
                <option value="">Semua Project</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->project_number }} — {{ $p->project_name }}</option>
                @endforeach
            </select>
            @endif
            @if($divisions->count() > 1)
            <select class="form-select" data-filter="division_id" aria-label="Divisi">
                <option value="">Semua Divisi</option>
                @foreach($divisions as $d)
                    <option value="{{ $d->id }}">{{ $d->division_name }}</option>
                @endforeach
            </select>
            @endif
            <div class="lt-date" title="Rentang tanggal">
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
            <table id="gr-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>No. Permintaan</th>
                        <th>Project</th>
                        <th>Customer</th>
                        <th>Divisi</th>
                        <th class="text-center">Item</th>
                        <th>Dibuat</th>
                        <th>Status</th>
                        <th class="text-center" style="width:150px">Aksi</th>
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

function grEsc(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function initGrTable() {
    if (grTable) grTable.destroy();

    grTable = $('#gr-table').DataTable({
        layout: ListToolbar.layout,
        language: ListToolbar.language,
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("goods-request.data") }}',
            data: function(d) { return grToolbar.params(d); }
        },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'request_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<a href="' + grShowUrl.replace('__ID__', row.id) + '" class="gr-cell-main" style="text-decoration:none">' + grEsc(data) + '</a>';
                }
            },
            { data: 'project_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    var html = '<div class="gr-cell-main">' + grEsc(data) + '</div>';
                    html += '<div style="font-size:13px">' + grEsc(row.project_name) + '</div>';
                    if (row.purpose === 'internal') html += '<div class="gr-cell-sub"><i class="fa fa-warehouse me-1"></i>Tujuan stok / internal</div>';
                    if (row.opportunity_name) html += '<div class="gr-cell-sub"><i class="fa fa-bullseye me-1"></i>' + grEsc(row.opportunity_name) + '</div>';
                    return html;
                }
            },
            { data: 'to_name', orderable: false, searchable: true,
                render: function(data) { return data ? grEsc(data) : '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'division_name', orderable: false, searchable: true,
                render: function(data) { return grEsc(data); }
            },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    if (!data) return '<span style="color:var(--text-muted)">—</span>';
                    return '<div class="gr-cell-main">' + data + ' item</div><div class="gr-cell-sub">Qty ' + Number(row.total_qty || 0).toLocaleString('en-US') + '</div>';
                }
            },
            { data: 'creator_name', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<div>' + grEsc(data) + '</div><div class="gr-cell-sub">' + grEsc(row.created_at || '') + '</div>';
                }
            },
            { data: 'status_badge', orderable: false, searchable: false,
                render: function(data, type, row) {
                    if (row.status === 'waiting_approval') {
                        return data + '<div class="gr-cell-sub mt-1"><i class="fa fa-user-check me-1"></i>Approval ' + row.approval_count + '/' + row.required_approvals + '</div>';
                    }
                    if (row.fulfillment) {
                        var labels = { none: 'Belum diproses', partial: 'Terpenuhi sebagian', done: 'Terpenuhi' };
                        var colors = { none: '#6b7280', partial: '#b45309', done: '#166534' };
                        return data + '<div class="gr-cell-sub mt-1" style="color:' + colors[row.fulfillment] + '"><i class="fa fa-boxes-stacked me-1"></i>' + labels[row.fulfillment] + '</div>';
                    }
                    return data;
                }
            },
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
                        btn += '<button class="btn-icon text-success" title="Approve (' + row.approval_count + '/' + row.required_approvals + ')" onclick="approveGr(' + data + ')"><i class="fa fa-check"></i></button>';
                    }
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });

    grTable.on('xhr.dt', function(e, settings, json) { grToolbar.statusCounts(json); });
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
    var row = grTable.rows().data().toArray().find(function(r) { return r.id === id; });
    if (!row) return;
    var isLast = row.approval_count + 1 >= row.required_approvals;

    Swal.fire({
        title: 'Approve Permintaan Barang?',
        text: isLast
            ? 'Ini approval terakhir — permintaan barang menjadi Approved dan siap diproses PO.'
            : 'Approval ' + (row.approval_count + 1) + '/' + row.required_approvals + '. Masih menunggu approver berikutnya.',
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

let grToolbar = null;

$(document).ready(function() {
    grToolbar = ListToolbar('#gr-toolbar', function() { return grTable; });
    initGrTable();
});
</script>
@endsection
