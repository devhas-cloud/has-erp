@extends('layouts.app')

@section('title', 'Quote Configuration')
@section('page-title', 'Quote Configuration')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Quote Configuration</h1>
        <p class="page-header-sub">Kelola konfigurasi quotation (pH, Ammonia, COD, TSS dan Debit) dari task quote</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('water-configuration.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Configuration</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-droplet me-2" style="color:var(--accent)"></i>Daftar Configuration</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="water-config-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Opportunity</th>
                        <th>Company</th>
                        <th>To (Contact)</th>
                        <th>Tanggal</th>
                        <th>Item</th>
                        <th>Dibuat Oleh</th>
                        <th>Status</th>
                        <th class="text-center" style="width:150px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Modal Reject --}}
<div class="modal fade" id="wcRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Tolak Quotation</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea id="wc-reject-note" class="form-control" rows="3" placeholder="Wajib diisi alasan penolakan"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-reject-wc">
                    <i class="fa fa-xmark me-1"></i> Tolak
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let wcRejectModalInstance = null;
let wcTable = null;
let wcRejectId = null;

const wcShowUrl = '{{ route("water-configuration.show", "__ID__") }}';
const wcEditUrl = '{{ route("water-configuration.edit", "__ID__") }}';
const wcDeleteUrl = '{{ route("water-configuration.destroy", "__ID__") }}';
const wcSubmitUrl = '{{ route("water-configuration.submit", "__ID__") }}';
const wcApproveUrl = '{{ route("water-configuration.approve", "__ID__") }}';
const wcRejectUrl = '{{ route("water-configuration.reject", "__ID__") }}';

function initWcTable() {
    if (wcTable) {
        wcTable.destroy();
    }

    wcTable = $('#water-config-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("water-configuration.data") }}',
        order: [[1, 'desc']],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'opportunity_name', orderable: false, searchable: true,
                render: function(data) {
                    return '<strong style="color:var(--text-primary)">' + data + '</strong>';
                }
            },
            { data: 'location', orderable: false, searchable: true,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'to_name', orderable: false, searchable: true,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'date', orderable: true, searchable: false },
            { data: 'item_count', orderable: false, searchable: false, className: 'text-center' },
            { data: 'creator_name', orderable: false, searchable: true },
            { data: 'status_badge', orderable: false, searchable: false },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    btn += '<a href="' + wcShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (row.status === 'draft') {
                        btn += '<a href="' + wcEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Edit"><i class="fa fa-pen"></i></a>';
                        btn += '<button class="btn-icon" title="Submit Approval" onclick="submitWc(' + data + ')"><i class="fa fa-paper-plane"></i></button>';
                        btn += '<button class="btn-icon" title="Hapus" onclick="deleteWc(' + data + ')"><i class="fa fa-trash"></i></button>';
                    }
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function deleteWc(id) {
    Swal.fire({
        title: 'Hapus Configuration?',
        text: 'Configuration #' + id + ' akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }
        $.ajax({
            url: wcDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Quotation dihapus.');
            wcTable.ajax.reload();
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}

function submitWc(id) {
    Swal.fire({
        title: 'Submit Approval?',
        text: 'Quotation akan dikirim untuk approval. Setelah di-submit tidak bisa diedit.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Submit',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }
        $.post(wcSubmitUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Quotation dikirim untuk approval.');
                wcTable.ajax.reload();
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal submit.');
            });
    });
}

function approveWc(id) {
    Swal.fire({
        title: 'Approve Quotation?',
        text: 'Anda yakin menyetujui quotation ini?',
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }
        $.post(wcApproveUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Quotation disetujui.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal approve.');
            });
    });
}

function openRejectModal(id) {
    wcRejectId = id;
    $('#wc-reject-note').val('');
    if (!wcRejectModalInstance) {
        wcRejectModalInstance = new bootstrap.Modal(document.getElementById('wcRejectModal'));
    }
    wcRejectModalInstance.show();
}

$('#btn-reject-wc').on('click', function() {
    var note = $('#wc-reject-note').val().trim();
    if (!note) {
        toastr.error('Alasan penolakan wajib diisi.');
        return;
    }

    $('#btn-reject-wc').prop('disabled', true);

    $.ajax({
        url: wcRejectUrl.replace('__ID__', wcRejectId),
        method: 'POST',
        data: { _token: '{{ csrf_token() }}', approval_note: note }
    }).done(function(res) {
        toastr.success(res.message || 'Quotation ditolak.');
        wcRejectModalInstance.hide();
        setTimeout(function() { window.location.reload(); }, 800);
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menolak.');
    }).always(function() {
        $('#btn-reject-wc').prop('disabled', false);
    });
});

$(document).ready(function() {
    initWcTable();
});
</script>
@endsection
