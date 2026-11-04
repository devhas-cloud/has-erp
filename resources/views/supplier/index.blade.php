@extends('layouts.app')

@section('title', 'Supplier')
@section('page-title', 'Supplier')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Supplier</h1>
        <p class="page-header-sub">Data master supplier — kontak, termin bayar, pajak, dan rekening; dipakai di Purchase Order, Direct Purchase, dan modul Finance</p>
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
                        <th>Termin</th>
                        <th>Keuangan</th>
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
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
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
                    <div class="pt-2 mb-2" style="border-top:1px dashed var(--card-border)">
                        <div class="fw-semibold" style="font-size:13px"><i class="fa-solid fa-building-columns me-1" style="color:var(--accent)"></i>Keuangan &amp; Pajak</div>
                        <small style="color:var(--text-muted)">Dipakai untuk jatuh tempo tagihan dan transfer pembayaran di modul Finance.</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Termin Bayar (hari)</label>
                                <input type="number" id="supplier-payment-term-days" class="form-control" min="0" max="365" step="1" value="30">
                                <small style="color:var(--text-muted)">0 = tunai / bayar di muka</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">NPWP</label>
                                <input type="text" id="supplier-npwp" class="form-control" maxlength="30" placeholder="00.000.000.0-000.000">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label d-block">Status Pajak</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="supplier-is-pkp">
                                    <label class="form-check-label" for="supplier-is-pkp">PKP (menerbitkan faktur pajak)</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Bank</label>
                                <input type="text" id="supplier-bank-name" class="form-control" maxlength="100" placeholder="Contoh: BCA">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">No. Rekening</label>
                                <input type="text" id="supplier-bank-account-no" class="form-control" maxlength="50">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Atas Nama</label>
                                <input type="text" id="supplier-bank-account-name" class="form-control" maxlength="150">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="supplier-default-pph">Saran Tarif PPh</label>
                                <div class="input-group">
                                    <input type="number" id="supplier-default-pph" class="form-control" min="0" max="100" step="any" placeholder="Kosong = tidak dipotong">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small style="color:var(--text-muted)">Hanya saran awal di tagihan; tarif tetap bisa diubah per tagihan.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Maks. Uang Muka per PO</label>
                                <div class="input-group">
                                    <input type="number" id="supplier-max-advance" class="form-control" min="0" max="100" step="any" value="0" @disabled(! $canApprove)>
                                    <span class="input-group-text">%</span>
                                </div>
                                <small style="color:var(--text-muted)">{{ $canApprove ? '0 = tidak boleh uang muka; 100 = boleh bayar penuh di muka.' : 'Hanya bisa diubah approver Supplier.' }}</small>
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

const supplierAdvanceUrl = '{{ route("supplier.advance.approve", "__ID__") }}';
const supplierCanApprove = {{ $canApprove ? 'true' : 'false' }};
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
            { data: 'payment_term_label', orderable: false, searchable: false,
                render: function(data) { return '<span style="font-size:12.5px;white-space:nowrap">' + (data || '—') + '</span>'; }
            },
            { data: 'max_advance_percent', orderable: false, searchable: false,
                render: function(data, type, row) {
                    var chips = [];
                    if (row.is_pkp) chips.push('<span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:10px">PKP</span>');
                    if (Number(data) > 0) chips.push('<span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px" title="Batas uang muka per PO">Uang muka ≤ ' + Number(data) + '%</span>');
                    if (!row.has_bank) chips.push('<span class="badge" style="background:#f3f4f6;color:#6b7280;font-size:10px">Rekening belum ada</span>');
                    return chips.join(' ') || '<span style="color:var(--text-muted)">—</span>';
                }
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
    $('#supplier-payment-term-days').val(30);
    $('#supplier-max-advance').val(0).attr('data-original', 0);
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
        $('#supplier-payment-term-days').val(s.payment_term_days != null ? s.payment_term_days : 30);
        $('#supplier-npwp').val(s.npwp || '');
        $('#supplier-is-pkp').prop('checked', !!s.is_pkp);
        $('#supplier-bank-name').val(s.bank_name || '');
        $('#supplier-bank-account-no').val(s.bank_account_no || '');
        $('#supplier-bank-account-name').val(s.bank_account_name || '');
        $('#supplier-default-pph').val(s.default_pph_percent != null ? Number(s.default_pph_percent) : '');
        $('#supplier-max-advance').val(Number(s.max_advance_percent || 0)).attr('data-original', Number(s.max_advance_percent || 0));

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
        payment_term_days: $('#supplier-payment-term-days').val(),
        npwp: $('#supplier-npwp').val().trim(),
        is_pkp: $('#supplier-is-pkp').is(':checked') ? 1 : 0,
        bank_name: $('#supplier-bank-name').val().trim(),
        bank_account_no: $('#supplier-bank-account-no').val().trim(),
        bank_account_name: $('#supplier-bank-account-name').val().trim(),
        default_pph_percent: $('#supplier-default-pph').val(),
        status: status
    };
    var advance = parseFloat($('#supplier-max-advance').val()) || 0;
    var advanceChanged = supplierCanApprove && advance !== (parseFloat($('#supplier-max-advance').attr('data-original')) || 0);

    $('#btn-save-supplier').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...');

    $.ajax({
        url: isEdit ? supplierUpdateUrl.replace('__ID__', id) : '{{ route("supplier.store") }}',
        method: isEdit ? 'PUT' : 'POST',
        data: payload,
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).then(function(res) {
        // Batas uang muka disimpan lewat route khusus approver (can_approve).
        if (!advanceChanged) return res;
        return $.ajax({
            url: supplierAdvanceUrl.replace('__ID__', isEdit ? id : res.id),
            method: 'POST',
            data: { max_advance_percent: advance },
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).then(function() { return res; });
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
                    payment_term_days: 'supplier-payment-term-days',
                    npwp: 'supplier-npwp',
                    max_advance_percent: 'supplier-max-advance',
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
