@extends('layouts.app')

@section('title', 'Supplier')
@section('page-title', 'Supplier')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Supplier</h1>
        <p class="page-header-sub">Data master supplier — dipakai sebagai sumber pilihan supplier saat membuat Purchase Order</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i>
            <span>Tambah Supplier</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-truck me-2" style="color:var(--accent)"></i>Daftar Supplier</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="supplier-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Nama</th>
                        <th>Alamat</th>
                        <th>Telepon</th>
                        <th>Attn</th>
                        <th>Status</th>
                        <th class="text-center" style="width:130px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Modal Create / Edit --}}
<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="supplierModalTitle">Tambah Supplier</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="supplier-form" autocomplete="off">
                    <input type="hidden" id="supplier-edit-id" value="">
                    <div class="mb-3">
                        <label class="form-label">Nama Supplier <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="supplier-name" class="form-control" maxlength="200"
                            placeholder="Contoh: CV. Riztech Engineering" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea id="supplier-address" class="form-control" rows="2"
                            placeholder="Alamat lengkap, dicetak pada dokumen PO"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Telepon</label>
                                <input type="text" id="supplier-phone" class="form-control" maxlength="50">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Fax</label>
                                <input type="text" id="supplier-fax" class="form-control" maxlength="50">
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Attn (Contact Person)</label>
                                <input type="text" id="supplier-attn-name" class="form-control" maxlength="150"
                                    placeholder="Nama yang dituju pada dokumen PO">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" id="supplier-email" class="form-control" maxlength="150">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status <span style="color:var(--danger)">*</span></label>
                        <select id="supplier-status" class="form-select" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-supplier">
                    <i class="fa fa-save me-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let supplierModalInstance = null;
let supplierTable = null;

const supplierEditUrl = '{{ route("supplier.edit", "__ID__") }}';
const supplierUpdateUrl = '{{ route("supplier.update", "__ID__") }}';
const supplierDeleteUrl = '{{ route("supplier.destroy", "__ID__") }}';

function initSupplierTable() {
    if (supplierTable) {
        supplierTable.destroy();
    }

    supplierTable = $('#supplier-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("supplier.data") }}',
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'name', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + data + '</strong>'; }
            },
            { data: 'address', orderable: false, searchable: false,
                render: function(data) {
                    if (!data) return '<span style="color:var(--text-muted)">—</span>';
                    var oneLine = data.replace(/\n/g, ', ');
                    return '<span style="font-size:12px">' + (oneLine.length > 60 ? oneLine.substring(0, 60) + '…' : oneLine) + '</span>';
                }
            },
            { data: 'phone', orderable: false, searchable: false,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'attn_name', orderable: false, searchable: false,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'status', orderable: false, searchable: false,
                render: function(data) {
                    if (data === 'Active') {
                        return '<span class="status-badge status-active">Active</span>';
                    }
                    return '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Inactive</span>';
                }
            },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    @if($canUpdate)
                    btn += '<button class="btn-icon" title="Edit" onclick="openEditModal(' + data + ')"><i class="fa-solid fa-pen"></i></button>';
                    @endif
                    @if($canDelete)
                    btn += '<button class="btn-icon danger" title="Hapus" onclick="deleteSupplier(' + data + ', \'' + row.name.replace(/'/g, "\\'") + '\')"><i class="fa-solid fa-trash-can"></i></button>';
                    @endif
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function resetSupplierForm() {
    document.getElementById('supplier-form').reset();
    document.getElementById('supplier-edit-id').value = '';
    document.getElementById('supplier-status').value = 'Active';
    $('#supplier-form .is-invalid').removeClass('is-invalid');
}

function openCreateModal() {
    resetSupplierForm();
    document.getElementById('supplierModalTitle').textContent = 'Tambah Supplier';
    if (!supplierModalInstance) {
        supplierModalInstance = new bootstrap.Modal(document.getElementById('supplierModal'));
    }
    supplierModalInstance.show();
}

function openEditModal(id) {
    resetSupplierForm();
    document.getElementById('supplierModalTitle').textContent = 'Edit Supplier';

    $.get(supplierEditUrl.replace('__ID__', id), function(res) {
        var s = res.data;
        $('#supplier-edit-id').val(s.id);
        $('#supplier-name').val(s.name || '');
        $('#supplier-address').val(s.address || '');
        $('#supplier-phone').val(s.phone || '');
        $('#supplier-fax').val(s.fax || '');
        $('#supplier-attn-name').val(s.attn_name || '');
        $('#supplier-email').val(s.email || '');
        $('#supplier-status').val(s.status || 'Active');

        if (!supplierModalInstance) {
            supplierModalInstance = new bootstrap.Modal(document.getElementById('supplierModal'));
        }
        supplierModalInstance.show();
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat data supplier.');
    });
}

$(document).on('click', '#btn-save-supplier', function() {
    var name = $('#supplier-name').val().trim();
    var status = $('#supplier-status').val();

    if (!name) { toastr.error('Nama supplier wajib diisi.'); $('#supplier-name').addClass('is-invalid'); return; }
    if (!status) { toastr.error('Status wajib diisi.'); return; }

    var id = $('#supplier-edit-id').val();
    var isEdit = !!id;

    var payload = {
        name: name,
        address: $('#supplier-address').val().trim(),
        phone: $('#supplier-phone').val().trim(),
        fax: $('#supplier-fax').val().trim(),
        attn_name: $('#supplier-attn-name').val().trim(),
        email: $('#supplier-email').val().trim(),
        status: status
    };

    $('#btn-save-supplier').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...');

    $.ajax({
        url: isEdit ? supplierUpdateUrl.replace('__ID__', id) : '{{ route("supplier.store") }}',
        method: isEdit ? 'PUT' : 'POST',
        data: payload,
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(function(res) {
        toastr.success(res.message || 'Supplier berhasil disimpan.');
        supplierModalInstance.hide();
        supplierTable.ajax.reload();
    }).fail(function(xhr) {
        var msg = 'Terjadi kesalahan saat menyimpan.';
        if (xhr.responseJSON) {
            if (xhr.responseJSON.errors) {
                var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                msg = xhr.responseJSON.errors[firstKey][0];
                var fieldMap = {
                    name: 'supplier-name',
                    address: 'supplier-address',
                    phone: 'supplier-phone',
                    fax: 'supplier-fax',
                    attn_name: 'supplier-attn-name',
                    email: 'supplier-email',
                    status: 'supplier-status'
                };
                $('#supplier-form .is-invalid').removeClass('is-invalid');
                var target = fieldMap[firstKey] || firstKey;
                $('#' + target).addClass('is-invalid');
            } else if (xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
        }
        toastr.error(msg);
    }).always(function() {
        $('#btn-save-supplier').prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan');
    });
});

function deleteSupplier(id, name) {
    Swal.fire({
        title: 'Hapus Supplier?',
        text: 'Supplier "' + name + '" akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }
        $.ajax({
            url: supplierDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Supplier dihapus.');
            supplierTable.ajax.reload();
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}

$(document).ready(function() {
    initSupplierTable();
});
</script>
@endsection
