@extends('layouts.app')

@section('title', 'Gudang')
@section('page-title', 'Gudang')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Gudang</h1>
        <p class="page-header-sub">Data master gudang/lokasi penyimpanan stok</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i>
            <span>Tambah Gudang</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-warehouse me-2" style="color:var(--accent)"></i>Daftar Gudang</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="warehouse-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Jenis</th>
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
<div class="modal fade" id="warehouseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="warehouseModalTitle">Tambah Gudang</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="warehouse-form" autocomplete="off">
                    <input type="hidden" id="warehouse-edit-id" value="">
                    <div class="mb-3">
                        <label class="form-label">Kode <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="warehouse-code" class="form-control" maxlength="50" placeholder="Contoh: G1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="warehouse-name" class="form-control" maxlength="150" placeholder="Contoh: Gudang Pusat" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Gudang <span style="color:var(--danger)">*</span></label>
                        <select id="warehouse-type" class="form-select" required>
                            @foreach(\App\Models\Warehouse::TYPE_LABELS as $typeKey => $typeLabel)
                                <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                        <small style="color:var(--text-muted)">Gudang Stok untuk stok bebas; Gudang Transit untuk barang project yang menunggu Delivery Order.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status <span style="color:var(--danger)">*</span></label>
                        <select id="warehouse-status" class="form-select" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-warehouse">
                    <i class="fa fa-save me-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let warehouseModalInstance = null;
let warehouseTable = null;

const warehouseEditUrl = '{{ route("warehouse.edit", "__ID__") }}';
const warehouseUpdateUrl = '{{ route("warehouse.update", "__ID__") }}';
const warehouseDeleteUrl = '{{ route("warehouse.destroy", "__ID__") }}';

function initWarehouseTable() {
    if (warehouseTable) warehouseTable.destroy();

    warehouseTable = $('#warehouse-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("warehouse.data") }}',
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'code', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + data + '</strong>'; }
            },
            { data: 'name', orderable: false, searchable: true },
            { data: 'type_label', orderable: false, searchable: false,
                render: function(data, type, row) {
                    return row.type === 'transit'
                        ? '<span class="status-badge" style="background:#fef3c7;color:#92400e;">' + data + '</span>'
                        : '<span class="status-badge" style="background:var(--accent-soft);color:var(--accent);">' + data + '</span>';
                }
            },
            { data: 'status', orderable: false, searchable: false,
                render: function(data) {
                    return data === 'Active'
                        ? '<span class="status-badge status-active">Active</span>'
                        : '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Inactive</span>';
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
                    btn += '<button class="btn-icon danger" title="Hapus" onclick="deleteWarehouse(' + data + ', \'' + row.name.replace(/'/g, "\\'") + '\')"><i class="fa-solid fa-trash-can"></i></button>';
                    @endif
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function resetWarehouseForm() {
    document.getElementById('warehouse-form').reset();
    document.getElementById('warehouse-edit-id').value = '';
    document.getElementById('warehouse-status').value = 'Active';
    document.getElementById('warehouse-type').value = 'stock';
}

function openCreateModal() {
    resetWarehouseForm();
    document.getElementById('warehouseModalTitle').textContent = 'Tambah Gudang';
    if (!warehouseModalInstance) warehouseModalInstance = new bootstrap.Modal(document.getElementById('warehouseModal'));
    warehouseModalInstance.show();
}

function openEditModal(id) {
    resetWarehouseForm();
    document.getElementById('warehouseModalTitle').textContent = 'Edit Gudang';
    $.get(warehouseEditUrl.replace('__ID__', id), function(res) {
        var w = res.data;
        $('#warehouse-edit-id').val(w.id);
        $('#warehouse-code').val(w.code || '');
        $('#warehouse-name').val(w.name || '');
        $('#warehouse-status').val(w.status || 'Active');
        $('#warehouse-type').val(w.type || 'stock');
        if (!warehouseModalInstance) warehouseModalInstance = new bootstrap.Modal(document.getElementById('warehouseModal'));
        warehouseModalInstance.show();
    });
}

$(document).on('click', '#btn-save-warehouse', function() {
    var code = $('#warehouse-code').val().trim();
    var name = $('#warehouse-name').val().trim();
    var status = $('#warehouse-status').val();
    var id = $('#warehouse-edit-id').val();
    var isEdit = !!id;

    if (!code) { toastr.error('Kode wajib diisi.'); return; }
    if (!name) { toastr.error('Nama wajib diisi.'); return; }

    var payload = { code: code, name: name, type: $('#warehouse-type').val(), status: status };

    $('#btn-save-warehouse').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...');

    $.ajax({
        url: isEdit ? warehouseUpdateUrl.replace('__ID__', id) : '{{ route("warehouse.store") }}',
        method: isEdit ? 'PUT' : 'POST',
        data: payload,
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(function(res) {
        toastr.success(res.message || 'Gudang disimpan.');
        warehouseModalInstance.hide();
        warehouseTable.ajax.reload();
    }).fail(function(xhr) {
        var msg = 'Terjadi kesalahan saat menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
        if (xhr.responseJSON && xhr.responseJSON.errors) {
            var firstKey = Object.keys(xhr.responseJSON.errors)[0];
            msg = xhr.responseJSON.errors[firstKey][0];
        }
        toastr.error(msg);
    }).always(function() {
        $('#btn-save-warehouse').prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan');
    });
});

function deleteWarehouse(id, name) {
    Swal.fire({
        title: 'Hapus Gudang?',
        text: 'Gudang "' + name + '" akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: warehouseDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Gudang dihapus.');
            warehouseTable.ajax.reload();
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}

$(document).ready(function() {
    initWarehouseTable();
});
</script>
@endsection
