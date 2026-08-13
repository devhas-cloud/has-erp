@extends('layouts.app')

@section('title', 'Quotation')
@section('page-title', 'Quotation')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Quotation</h1>
        <p class="page-header-sub">Dokumen quotation resmi yang dibuat dari quote configuration yang sudah disetujui</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('quotation.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Quotation</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Daftar Quotation</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="quotation-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Nomor Quotation</th>
                        <th>To (Company)</th>
                        <th>Tanggal</th>
                        <th>Sales</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-center" style="width:220px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="qtRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Tolak Quotation</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea id="qt-reject-note" class="form-control" rows="3" placeholder="Wajib diisi alasan penolakan"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-reject-qt">
                    <i class="fa fa-xmark me-1"></i> Tolak
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="qtTrackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--accent)"></i>Riwayat Versi</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Versi</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Item</th>
                                <th>Dibuat Oleh</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="qt-track-body">
                            <tr>
                                <td colspan="6" class="config-card-empty"><span class="config-spinner"></span>Memuat...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let quotationTable = null;
let qtRejectId = null;
let qtRejectModalInstance = null;
let qtTrackModalInstance = null;

const quotationShowUrl = '{{ route("quotation.show", "__ID__") }}';
const quotationEditUrl = '{{ route("quotation.edit", "__ID__") }}';
const quotationDeleteUrl = '{{ route("quotation.destroy", "__ID__") }}';
const quotationSubmitUrl = '{{ route("quotation.submit", "__ID__") }}';
const quotationApproveUrl = '{{ route("quotation.approve", "__ID__") }}';
const quotationRejectUrl = '{{ route("quotation.reject", "__ID__") }}';
const quotationUnlockUrl = '{{ route("quotation.unlock", "__ID__") }}';
const quotationReviseUrl = '{{ route("quotation.revise", "__ID__") }}';
const quotationVersionsUrl = '{{ route("quotation.versions", "__ID__") }}';
const quotationPdfUrl = '{{ route("quotation.pdf", "__ID__") }}';

function initQuotationTable() {
    if (quotationTable) {
        quotationTable.destroy();
    }

    quotationTable = $('#quotation-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("quotation.data") }}',
        order: [[3, 'desc']],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'quotation_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    var badge = row.version > 1 ? ' <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:10px;vertical-align:middle">v' + row.version + '</span>' : '';
                    return '<strong style="color:var(--text-primary)">' + data + '</strong>' + badge;
                }
            },
            { data: 'to_name', orderable: false, searchable: true,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'date', orderable: true, searchable: false },
            { data: 'sales_name', orderable: false, searchable: true },
            { data: 'grand_total_label', orderable: true, searchable: false, className: 'text-end' },
            { data: 'status_badge', orderable: false, searchable: false },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1 flex-wrap">';
                    btn += '<a href="' + quotationShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    btn += '<a href="' + quotationPdfUrl.replace('__ID__', data) + '" target="_blank" class="btn-icon" title="View PDF"><i class="fa fa-file-pdf"></i></a>';
                    btn += '<button class="btn-icon" title="Riwayat" onclick="openQtTrack(' + data + ')"><i class="fa fa-clock-rotate-left"></i></button>';

                    if (row.status === 'draft') {
                        btn += '<a href="' + quotationEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Edit"><i class="fa fa-pen"></i></a>';
                        btn += '<button class="btn-icon" title="Submit Approval" onclick="submitQuotation(' + data + ')"><i class="fa fa-paper-plane"></i></button>';
                        btn += '<button class="btn-icon" title="Hapus" onclick="deleteQuotation(' + data + ')"><i class="fa fa-trash"></i></button>';
                    }
                    if (row.status === 'waiting_approval' && row.can_approve) {
                        btn += '<button class="btn-icon" title="Approve" onclick="approveQuotation(' + data + ')"><i class="fa fa-check"></i></button>';
                        btn += '<button class="btn-icon text-danger" title="Reject" onclick="openQtReject(' + data + ')"><i class="fa fa-xmark"></i></button>';
                    }
                    if (row.status === 'approved' && row.locked && row.can_approve) {
                        btn += '<button class="btn-icon" title="Buka Kunci" onclick="unlockQuotation(' + data + ')"><i class="fa fa-lock-open"></i></button>';
                    }
                    if (row.can_revise) {
                        btn += '<button class="btn-icon" title="Buat Revisi" onclick="reviseQuotation(' + data + ')"><i class="fa fa-copy"></i></button>';
                    }
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function submitQuotation(id) {
    Swal.fire({
        title: 'Submit Approval?',
        text: 'Quotation akan dikirim untuk approval. Setelah di-submit tidak bisa diedit.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Submit',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(quotationSubmitUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Quotation dikirim untuk approval.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal submit.');
            });
    });
}

function approveQuotation(id) {
    Swal.fire({
        title: 'Approve Quotation?',
        text: 'Anda yakin menyetujui quotation ini?',
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(quotationApproveUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Quotation disetujui.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal approve.');
            });
    });
}

function openQtReject(id) {
    qtRejectId = id;
    $('#qt-reject-note').val('');
    if (!qtRejectModalInstance) {
        qtRejectModalInstance = new bootstrap.Modal(document.getElementById('qtRejectModal'));
    }
    qtRejectModalInstance.show();
}

$(document).on('click', '#btn-reject-qt', function() {
    var note = $('#qt-reject-note').val().trim();
    if (!note) {
        toastr.error('Alasan penolakan wajib diisi.');
        return;
    }
    $('#btn-reject-qt').prop('disabled', true);
    $.ajax({
        url: quotationRejectUrl.replace('__ID__', qtRejectId),
        method: 'POST',
        data: { _token: '{{ csrf_token() }}', approval_note: note }
    }).done(function(res) {
        toastr.success(res.message || 'Quotation ditolak.');
        qtRejectModalInstance.hide();
        setTimeout(function() { window.location.reload(); }, 800);
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menolak.');
    }).always(function() {
        $('#btn-reject-qt').prop('disabled', false);
    });
});

function unlockQuotation(id) {
    Swal.fire({
        title: 'Buka Kunci?',
        text: 'Pembuat akan dapat membuat revisi baru dari quotation ini.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Buka Kunci',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(quotationUnlockUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Kunci dibuka.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal membuka kunci.');
            });
    });
}

function reviseQuotation(id) {
    Swal.fire({
        title: 'Buat Revisi?',
        text: 'Header & detail akan disalin menjadi versi baru (Draft). Versi lama tetap sebagai riwayat.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Buat Revisi',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(quotationReviseUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Revisi dibuat.');
                setTimeout(function() {
                    window.location.href = quotationEditUrl.replace('__ID__', res.id);
                }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal membuat revisi.');
            });
    });
}

function openQtTrack(id) {
    $('#qt-track-body').html('<tr><td colspan="6" class="config-card-empty"><span class="config-spinner"></span>Memuat...</td></tr>');
    $.get(quotationVersionsUrl.replace('__ID__', id), function(res) {
        var versions = res.versions || [];
        if (versions.length === 0) {
            $('#qt-track-body').html('<tr><td colspan="6" class="config-card-empty"><i class="fa-solid fa-inbox"></i>Belum ada riwayat versi.</td></tr>');
        } else {
            var html = '';
            versions.forEach(function(v) {
                var current = v.is_current ? ' <span class="badge" style="background:var(--accent);color:#fff;font-size:10px">AKTIF</span>' : '';
                html += '<tr>';
                html += '<td><strong>Versi ' + v.version + '</strong>' + current + '</td>';
                html += '<td>' + v.status_badge + '</td>';
                html += '<td>' + v.date + '</td>';
                html += '<td class="text-center">' + v.item_count + '</td>';
                html += '<td>' + v.creator_name + '</td>';
                html += '<td class="text-center"><a href="' + v.show_url + '" class="btn-icon" title="View"><i class="fa fa-eye"></i></a></td>';
                html += '</tr>';
            });
            $('#qt-track-body').html(html);
        }
    }).fail(function() {
        $('#qt-track-body').html('<tr><td colspan="6" class="config-card-empty"><i class="fa-solid fa-triangle-exclamation"></i>Gagal memuat riwayat.</td></tr>');
    });

    if (!qtTrackModalInstance) {
        qtTrackModalInstance = new bootstrap.Modal(document.getElementById('qtTrackModal'));
    }
    qtTrackModalInstance.show();
}

function deleteQuotation(id) {
    Swal.fire({
        title: 'Hapus Quotation?',
        text: 'Quotation #' + id + ' akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: quotationDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Quotation dihapus.');
            quotationTable.ajax.reload();
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}

$(document).ready(function() {
    initQuotationTable();
});
</script>
@endsection
