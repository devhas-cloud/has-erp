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
                        <th class="text-center" style="width:170px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let quotationTable = null;

const quotationShowUrl = '{{ route("quotation.show", "__ID__") }}';
const quotationEditUrl = '{{ route("quotation.edit", "__ID__") }}';
const quotationDeleteUrl = '{{ route("quotation.destroy", "__ID__") }}';
const quotationIssueUrl = '{{ route("quotation.issue", "__ID__") }}';
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
                render: function(data) { return '<strong style="color:var(--text-primary)">' + data + '</strong>'; }
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
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    btn += '<a href="' + quotationShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    btn += '<a href="' + quotationPdfUrl.replace('__ID__', data) + '" target="_blank" class="btn-icon" title="View PDF"><i class="fa fa-file-pdf"></i></a>';
                    if (row.status === 'draft') {
                        btn += '<a href="' + quotationEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Edit"><i class="fa fa-pen"></i></a>';
                        btn += '<button class="btn-icon" title="Terbitkan (Issue)" onclick="issueQuotation(' + data + ')"><i class="fa fa-paper-plane"></i></button>';
                        btn += '<button class="btn-icon" title="Hapus" onclick="deleteQuotation(' + data + ')"><i class="fa fa-trash"></i></button>';
                    }
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
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
        if (!result.isConfirmed) {
            return;
        }
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

function issueQuotation(id) {
    Swal.fire({
        title: 'Terbitkan Quotation?',
        text: 'Quotation akan diterbitkan (Issued) dan terkunci. Pastikan data sudah final.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Terbitkan',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }
        $.post(quotationIssueUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Quotation diterbitkan.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menerbitkan.');
            });
    });
}

$(document).ready(function() {
    initQuotationTable();
});
</script>
@endsection
