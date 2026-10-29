@extends('layouts.app')

@section('title', 'Permintaan Barang')
@section('page-title', 'Permintaan Barang')

@section('styles')
<style>
    .gr-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
    .gr-tab {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 12px; border-radius: 999px;
        border: 1px solid var(--card-border); background: var(--card);
        font-size: 12.5px; font-weight: 500; color: var(--text-muted);
        cursor: pointer; transition: all .15s;
    }
    .gr-tab:hover { border-color: var(--accent); color: var(--accent); }
    .gr-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }
    .gr-tab .count {
        min-width: 20px; padding: 0 6px; border-radius: 999px;
        background: var(--accent-soft); color: var(--accent);
        font-size: 11px; font-weight: 700; text-align: center;
    }
    .gr-tab.active .count { background: rgba(255,255,255,.25); color: #fff; }
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
        <div class="gr-tabs" id="gr-status-tabs">
            @php $initialStatus = array_key_exists(request('status'), \App\Models\GoodsRequest::STATUS_LABELS) ? request('status') : ''; @endphp
            <span class="gr-tab {{ $initialStatus === '' ? 'active' : '' }}" data-status="">Semua <span class="count" data-count="all">0</span></span>
            @foreach(\App\Models\GoodsRequest::STATUS_LABELS as $key => $label)
                <span class="gr-tab {{ $initialStatus === $key ? 'active' : '' }}" data-status="{{ $key }}">{{ $label }} <span class="count" data-count="{{ $key }}">0</span></span>
            @endforeach
        </div>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="gr-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
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
let grStatusFilter = @json($initialStatus);

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
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("goods-request.data") }}',
            data: function(d) { d.status = grStatusFilter; }
        },
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'project_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    var html = '<a href="' + grShowUrl.replace('__ID__', row.id) + '" class="gr-cell-main" style="text-decoration:none">' + grEsc(data) + '</a>';
                    html += '<div style="font-size:13px">' + grEsc(row.project_name) + '</div>';
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
                    if (row.status !== 'waiting_approval') return data;
                    return data + '<div class="gr-cell-sub mt-1"><i class="fa fa-user-check me-1"></i>Approval ' + row.approval_count + '/' + row.required_approvals + '</div>';
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

    grTable.on('xhr.dt', function(e, settings, json) {
        if (!json || !json.status_counts) return;
        var all = 0;
        $.each(json.status_counts, function(key, total) {
            all += total;
            $('#gr-status-tabs [data-count="' + key + '"]').text(total);
        });
        $('#gr-status-tabs [data-count="all"]').text(all);
    });
}

$(document).on('click', '#gr-status-tabs .gr-tab', function() {
    $('#gr-status-tabs .gr-tab').removeClass('active');
    $(this).addClass('active');
    grStatusFilter = $(this).data('status') || '';
    grTable.ajax.reload();
});

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

$(document).ready(function() {
    initGrTable();
});
</script>
@endsection
