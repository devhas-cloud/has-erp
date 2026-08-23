@extends('layouts.app')

@section('title', 'Product Management')
@section('page-title', 'Product Management')

@section('styles')
<style>
    .info-table td { padding: 7px 0; vertical-align: top; line-height: 1.45; }
    .info-table td:first-child {
        color: var(--text-muted);
        width: 140px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        padding-right: 12px;
    }
    .info-table td:last-child {
        font-size: 13px;
        color: var(--text-primary);
        word-break: break-word;
    }
    .info-table tr + tr td { border-top: 1px solid var(--card-border); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Product Management</h1>
        <p class="page-header-sub">Kelola data master produk</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('product-management.export') }}" class="btn btn-outline-secondary btn-sm me-2" title="Export Data">
            <i class="fa fa-download"></i>
            <span>Export</span>
        </a>
        <button type="button" class="btn btn-outline-success btn-sm me-2" onclick="openImportModal()">
            <i class="fa fa-upload"></i>
            <span>Import</span>
        </button>
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i>
            <span>Tambah Product</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-box me-2" style="color:var(--accent)"></i>Daftar Produk</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="product-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Nama Produk</th>
                        <th>Code</th>
                        <th>Brand</th>
                        <th>Kategori</th>
                        <th>Divisi</th>
                        <th>Harga</th>
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
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="productModalTitle">Tambah Product</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="product-form" autocomplete="off">
                    <input type="hidden" id="product-edit-id" value="">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Nama Produk <small class="text-muted">(opsional)</small></label>
                                <input type="text" id="product-name" class="form-control" placeholder="Nama produk (boleh kosong; jika diisi tidak boleh sama)">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Code <span style="color:var(--danger)">*</span></label>
                                <input type="text" id="product-code" class="form-control" placeholder="Kode unik produk" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Brand</label>
                                <input type="text" id="product-brand" class="form-control" placeholder="Merk / brand">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Kategori</label>
                                <input type="text" id="product-category" class="form-control" placeholder="Kategori produk">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Divisi</label>
                                <select id="product-division" class="form-select">
                                    <option value="">— Pilih Divisi —</option>
                                    @foreach($divisions as $division)
                                        <option value="{{ $division->id }}">{{ $division->division_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Harga (Rp) <span style="color:var(--danger)">*</span></label>
                                <input type="number" id="product-price" class="form-control" min="0" step="0.01" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status <span style="color:var(--danger)">*</span></label>
                                <select id="product-status" class="form-select" required>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Gambar Produk</label>
                                <input type="file" id="product-image" class="form-control" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                                <div class="form-text">Maks 2 MB. Format: jpeg, png, jpg, gif, webp.</div>
                                <div id="product-image-preview" class="mt-2"></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Deskripsi</label>
                                <textarea id="product-description" class="form-control" rows="3" placeholder="Deskripsi produk"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-product">
                    <i class="fa fa-save me-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Import --}}
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Import Produk</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fa fa-file-excel" style="font-size:40px;color:#217346"></i>
                    <p class="mt-2 mb-0" style="font-size:13px;color:var(--text-muted)">
                        Download template, isi data, lalu upload file CSV.
                        Baris dengan code yang sudah ada akan diupdate.
                    </p>
                    <a href="{{ route('product-management.template') }}" class="btn btn-sm btn-outline-success mt-2">
                        <i class="fa fa-download me-1"></i> Download Template (.xlsx)
                    </a>
                </div>
                <hr>
                <form id="import-form" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:12px;font-weight:600">Pilih File CSV</label>
                        <input type="file" name="file" id="import-file" class="form-control" accept=".csv,.txt" required>
                        <small class="text-muted">Maksimal 5MB. Format: CSV (Save As dari Excel).</small>
                    </div>
                    <div id="import-result" style="display:none;font-size:13px;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-import">
                    <i class="fa fa-upload me-1"></i> Upload & Import
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Detail --}}
<div class="modal fade" id="productDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Detail Produk</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-md-4 text-center">
                        <div id="pd-image-wrap" style="display:flex;justify-content:center;"></div>
                    </div>
                    <div class="col-md-8">
                        <table class="info-table w-100">
                            <tr>
                                <td>Nama Produk</td>
                                <td><strong id="pd-name">—</strong></td>
                            </tr>
                            <tr>
                                <td>Code</td>
                                <td><code id="pd-code" style="color:var(--accent)">—</code></td>
                            </tr>
                            <tr>
                                <td>Brand</td>
                                <td id="pd-brand">—</td>
                            </tr>
                            <tr>
                                <td>Kategori</td>
                                <td id="pd-category">—</td>
                            </tr>
                            <tr>
                                <td>Divisi</td>
                                <td id="pd-division">—</td>
                            </tr>
                            <tr>
                                <td>Harga</td>
                                <td><strong id="pd-price">—</strong></td>
                            </tr>
                            <tr>
                                <td>Status</td>
                                <td id="pd-status">—</td>
                            </tr>
                            <tr>
                                <td>Deskripsi</td>
                                <td id="pd-description">—</td>
                            </tr>
                            <tr>
                                <td>Dibuat</td>
                                <td id="pd-created">—</td>
                            </tr>
                            <tr>
                                <td>Terakhir Update</td>
                                <td id="pd-updated">—</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                @if($canUpdate)
                <button type="button" class="btn btn-primary btn-sm" id="btn-detail-edit">
                    <i class="fa-solid fa-pen me-1"></i> Edit
                </button>
                @endif
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let productModalInstance = null;
let productDetailModalInstance = null;
let productTable = null;

const productEditUrl = '{{ route("product-management.edit", "__ID__") }}';
const productUpdateUrl = '{{ route("product-management.update", "__ID__") }}';
const productDeleteUrl = '{{ route("product-management.destroy", "__ID__") }}';

function initProductTable() {
    if (productTable) {
        productTable.destroy();
    }

    productTable = $('#product-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("product-management.data") }}',
        order: [[1, 'asc']],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            {
                data: 'name_display', orderable: true, searchable: true,
                render: function(data, type, row) {
                    var img = row.image_url
                        ? '<img src="' + row.image_url + '" class="avatar-circle" alt="" style="background:transparent">'
                        : '<div class="avatar-circle">' + row.initials + '</div>';
                    return '<div style="display:flex;align-items:center;gap:10px">' +
                        img +
                        '<strong style="color:var(--text-primary);font-weight:600">' + data + '</strong>' +
                        '</div>';
                }
            },
            { data: 'code', orderable: true, searchable: true,
                render: function(data) {
                    return '<code style="color:var(--accent)">' + data + '</code>';
                }
            },
            { data: 'brand', orderable: true, searchable: true,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'category', orderable: true, searchable: true,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'division_name', orderable: false, searchable: true,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'price_formatted', orderable: true, searchable: false, className: 'text-end',
                render: function(data) { return '<strong>' + data + '</strong>'; }
            },
            { data: 'status', orderable: true, searchable: false,
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
                    btn += '<button class="btn-icon" title="Detail" onclick="openDetailModal(' + data + ')"><i class="fa-solid fa-eye"></i></button>';
                    @if($canUpdate)
                    btn += '<button class="btn-icon" title="Edit" onclick="openEditModal(' + data + ')"><i class="fa-solid fa-pen"></i></button>';
                    @endif
                    @if($canDelete)
                    btn += '<button class="btn-icon danger" title="Hapus" onclick="deleteProduct(' + data + ', \'' + row.name + '\')"><i class="fa-solid fa-trash-can"></i></button>';
                    @endif
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function resetProductForm() {
    document.getElementById('product-form').reset();
    document.getElementById('product-edit-id').value = '';
    document.getElementById('product-status').value = 'Active';
    $('#product-image-preview').empty();
    $('#product-form .is-invalid').removeClass('is-invalid');
}

function showImagePreview(input) {
    var preview = $('#product-image-preview');
    preview.empty();

    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.html('<img src="' + e.target.result + '" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid var(--border);" alt="Preview">');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

$('#product-image').on('change', function() {
    showImagePreview(this);
});

function openCreateModal() {
    resetProductForm();
    document.getElementById('productModalTitle').textContent = 'Tambah Product';
    if (!productModalInstance) {
        productModalInstance = new bootstrap.Modal(document.getElementById('productModal'));
    }
    productModalInstance.show();
}

function openEditModal(id) {
    resetProductForm();
    document.getElementById('productModalTitle').textContent = 'Edit Product';

    $.get(productEditUrl.replace('__ID__', id), function(res) {
        var p = res.data;
        $('#product-edit-id').val(p.id);
        $('#product-name').val(p.name || '');
        $('#product-code').val(p.code || '');
        $('#product-brand').val(p.brand || '');
        $('#product-category').val(p.category || '');
        $('#product-division').val(p.division_id || '');
        $('#product-price').val(p.price || '');
        $('#product-status').val(p.status || 'Active');
        $('#product-description').val(p.description || '');
        if (p.image_url) {
            $('#product-image-preview').html('<img src="' + p.image_url + '" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid var(--border);" alt="Preview">');
        }

        if (!productModalInstance) {
            productModalInstance = new bootstrap.Modal(document.getElementById('productModal'));
        }
        productModalInstance.show();
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat data produk.');
    });
}

function openDetailModal(id) {
    $.get(productEditUrl.replace('__ID__', id), function(res) {
        var p = res.data;

        $('#pd-name').text(p.name || '—');
        $('#pd-code').text(p.code || '—');
        $('#pd-brand').text(p.brand || '—');
        $('#pd-category').text(p.category || '—');
        $('#pd-division').text(p.division_name || '—');
        $('#pd-price').text('Rp ' + Number(p.price).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }));
        $('#pd-status').html(p.status === 'Active'
            ? '<span class="status-badge status-active">Active</span>'
            : '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Inactive</span>');
        $('#pd-description').text(p.description || '—');
        $('#pd-created').text(p.created_at ? new Date(p.created_at).toLocaleString('id-ID') : '—');
        $('#pd-updated').text(p.updated_at ? new Date(p.updated_at).toLocaleString('id-ID') : '—');

        var wrap = $('#pd-image-wrap');
        wrap.empty();
        if (p.image_url) {
            wrap.html('<img src="' + p.image_url + '" style="max-width:180px;max-height:180px;border-radius:12px;border:1px solid var(--card-border);object-fit:cover;" alt="">');
        } else {
            var initials = (p.name || '?').substring(0, 2).toUpperCase();
            wrap.html('<div class="avatar-circle" style="width:120px;height:120px;font-size:36px;">' + initials + '</div>');
        }

        $('#btn-detail-edit').data('id', p.id);

        if (!productDetailModalInstance) {
            productDetailModalInstance = new bootstrap.Modal(document.getElementById('productDetailModal'));
        }
        productDetailModalInstance.show();
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat detail produk.');
    });
}

$('#btn-detail-edit').on('click', function() {
    var id = $(this).data('id');
    if (!id) {
        return;
    }
    productDetailModalInstance.hide();
    openEditModal(id);
});

$(document).on('click', '#btn-save-product', function() {
    var name = $('#product-name').val().trim();
    var code = $('#product-code').val().trim();
    var price = $('#product-price').val();
    var status = $('#product-status').val();

    if (!code) { toastr.error('Code produk wajib diisi.'); $('#product-code').addClass('is-invalid'); return; }
    if (price === '' || isNaN(parseFloat(price))) { toastr.error('Harga wajib diisi.'); $('#product-price').addClass('is-invalid'); return; }
    if (!status) { toastr.error('Status wajib diisi.'); return; }

    var id = $('#product-edit-id').val();
    var isEdit = !!id;

    var fd = new FormData();
    fd.append('name', name);
    fd.append('code', code);
    fd.append('brand', $('#product-brand').val().trim());
    fd.append('category', $('#product-category').val().trim());
    fd.append('division_id', $('#product-division').val());
    fd.append('description', $('#product-description').val().trim());
    fd.append('price', price);
    fd.append('status', status);
    var imageFile = $('#product-image')[0].files[0];
    if (imageFile) {
        fd.append('image', imageFile);
    }

    var url = '{{ route("product-management.store") }}';
    if (isEdit) {
        url = productUpdateUrl.replace('__ID__', id);
        fd.append('_method', 'PUT');
    }

    $('#btn-save-product').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...');

    $.ajax({
        url: url,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(function(res) {
        toastr.success(res.message || 'Produk berhasil disimpan.');
        productModalInstance.hide();
        productTable.ajax.reload();
    }).fail(function(xhr) {
        var msg = 'Terjadi kesalahan saat menyimpan.';
        if (xhr.responseJSON) {
            if (xhr.responseJSON.errors) {
                var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                msg = xhr.responseJSON.errors[firstKey][0];
                var fieldMap = {
                    code: 'product-code',
                    price: 'product-price',
                    status: 'product-status',
                    name: 'product-name',
                    brand: 'product-brand',
                    category: 'product-category',
                    division_id: 'product-division',
                    image: 'product-image',
                    description: 'product-description'
                };
                $('#product-form .is-invalid').removeClass('is-invalid');
                var target = fieldMap[firstKey] || firstKey;
                $('#' + target).addClass('is-invalid');
            } else if (xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
        }
        toastr.error(msg);
    }).always(function() {
        $('#btn-save-product').prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan');
    });
});

function deleteProduct(id, name) {
    Swal.fire({
        title: 'Hapus Produk?',
        text: 'Produk "' + name + '" akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }
        $.ajax({
            url: productDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Produk dihapus.');
            productTable.ajax.reload();
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}

let importModalInstance = null;

function openImportModal() {
    document.getElementById('import-form').reset();
    document.getElementById('import-result').style.display = 'none';
    document.getElementById('import-result').innerHTML = '';
    if (!importModalInstance) {
        importModalInstance = new bootstrap.Modal(document.getElementById('importModal'));
    }
    importModalInstance.show();
}

$(document).on('click', '#btn-import', function() {
    const $btn = $(this);
    const fileInput = document.getElementById('import-file');
    const file = fileInput.files[0];

    if (!file) {
        toastr.error('Pilih file CSV terlebih dahulu.');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('_token', '{{ csrf_token() }}');

    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Importing...');

    $.ajax({
        url: '{{ route("product-management.import") }}',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            $btn.prop('disabled', false).html('<i class="fa fa-upload me-1"></i> Upload & Import');

            const resultDiv = document.getElementById('import-result');
            resultDiv.style.display = 'block';

            let html = '<div class="alert alert-success py-2 mb-2">' + res.message + '</div>';
            if (res.result && res.result.errors && res.result.errors.length > 0) {
                html += '<div class="alert alert-warning py-2"><strong>Detail error:</strong><br>' +
                    res.result.errors.map(function(e) { return '&bull; ' + e; }).join('<br>') +
                    '</div>';
            }
            resultDiv.innerHTML = html;

            if (productTable) productTable.ajax.reload(null, false);
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html('<i class="fa fa-upload me-1"></i> Upload & Import');
            var msg = xhr.responseJSON?.message || 'Gagal import file.';
            toastr.error(msg);
        }
    });
});

$(document).ready(function() {
    initProductTable();

    // Auto open edit modal ketika datang dari halaman detail (?edit=ID)
    var editParam = new URLSearchParams(window.location.search).get('edit');
    if (editParam) {
        openEditModal(editParam);
    }
});
</script>
@endsection
