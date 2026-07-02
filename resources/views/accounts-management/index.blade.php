@extends('layouts.app')

@section('title', 'Account Management')
@section('page-title', 'Account Management')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .modal-account .modal-dialog { max-width: 900px; }
    .account-form-section {
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        margin-bottom: 16px;
        overflow: hidden;
    }
    .account-form-section-header {
        padding: 10px 16px;
        background: #f8fafc;
        border-bottom: 1px solid var(--card-border);
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .account-form-section-body { padding: 16px; display: none; }
    .account-form-section.open .account-form-section-body { display: block; }
    .account-form-section-header .chevron { transition: transform 0.2s; font-size: 11px; color: var(--text-muted); }
    .account-form-section.open .chevron { transform: rotate(180deg); }
    .account-form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
    .account-form-row .form-group { flex: 1; min-width: 200px; }
    .account-form-row .form-group.small { flex: 0 0 160px; }
    .form-group label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--card-border);
        border-radius: var(--radius-sm);
        font-size: 13px;
        font-family: inherit;
        color: var(--text-primary);
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-soft);
        outline: none;
    }
    .form-group input.is-invalid,
    .form-group select.is-invalid,
    .form-group textarea.is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
    }
    select.is-invalid + .select2-container .select2-selection {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Account Management</h1>
        <p class="page-header-sub">Kelola data akun perusahaan</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i>
            <span>Add Account</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Accounts List</span>
    </div>
    <div class="card-body-custom p-0">
        <table id="accounts-table" class="table table-custom align-middle mb-0" style="width:100%">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Account Name</th>
                    <th>Phone</th>
                    <th>Owner</th>
                    <th class="text-center" style="width:120px">Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade modal-account" id="accountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="accountModalTitle">Add Account</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                <form id="account-form" autocomplete="off">
                    <input type="hidden" id="account-edit-id">

                    <div class="account-form-section open">
                        <div class="account-form-section-header" onclick="toggleAccountSection(this)">
                            <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Company Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="account-form-section-body">
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Account Name <span class="text-danger">*</span></label>
                                    <input type="text" name="account_name" id="account-name" required>
                                </div>
                                <div class="form-group">
                                    <label>Field Type <span class="text-danger">*</span></label>
                                    <select name="types_accounts_companies_id" id="account-type">
                                        <option value="">— Pilih —</option>
                                        @foreach($typesAccountsCompanies as $tac)
                                        <option value="{{ $tac->id }}">{{ $tac->type_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Account Source <span class="text-danger">*</span></label>
                                    <select name="sources_id" id="account-source">
                                        <option value="">— Pilih —</option>
                                        @foreach($sources as $src)
                                        <option value="{{ $src->id }}">{{ $src->source_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Website</label>
                                    <input type="text" name="website" id="account-website" placeholder="https://">
                                </div>
                                <div class="form-group" style="flex: 2">
                                    <label>Description</label>
                                    <textarea name="description" id="account-description" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Segmentation <span class="text-danger">*</span></label>
                                    <select name="segmentation_id" id="account-segmentation">
                                        <option value="">— Pilih —</option>
                                        @foreach($segmentations as $seg)
                                        <option value="{{ $seg->id }}">{{ $seg->segmentation_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Business Entity <span class="text-danger">*</span></label>
                                    <select name="business_entities_id" id="account-biz-entity">
                                        <option value="">— Pilih —</option>
                                        @foreach($businessEntities as $be)
                                        <option value="{{ $be->id }}">{{ $be->entity_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>End User</label>
                                    <select name="end_user" id="account-end-user">
                                        <option value="">— Pilih —</option>
                                        @foreach($accountCompanies as $ac)
                                        <option value="{{ $ac->id }}">{{ $ac->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Parent Account</label>
                                    <select name="parent_account_id" id="account-parent">
                                        <option value="">— Pilih —</option>
                                        @foreach($accountCompanies as $ac)
                                        <option value="{{ $ac->id }}">{{ $ac->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" name="phone" id="account-phone">
                                </div>
                                <div class="form-group">
                                    <label>Business Value <span class="text-danger">*</span></label>
                                    <select name="business_values_id" id="account-biz-value">
                                        <option value="">— Pilih —</option>
                                        @foreach($businessValues as $bv)
                                        <option value="{{ $bv->id }}">{{ $bv->value_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Interaction Level <span class="text-danger">*</span></label>
                                    <select name="interaction_levels_id" id="account-interaction">
                                        <option value="">— Pilih —</option>
                                        @foreach($interactionLevels as $il)
                                        <option value="{{ $il->id }}">{{ $il->level_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="account-form-section">
                        <div class="account-form-section-header" onclick="toggleAccountSection(this)">
                            <span><i class="fa fa-file-invoice me-2" style="color:var(--accent)"></i>Billing Address</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="account-form-section-body">
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Billing Street</label>
                                    <input type="text" name="address_billing_street" id="account-bill-street">
                                </div>
                            </div>
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Billing City</label>
                                    <input type="text" name="address_billing_city" id="account-bill-city">
                                </div>
                                <div class="form-group">
                                    <label>Billing Province</label>
                                    <input type="text" name="address_billing_province" id="account-bill-province">
                                </div>
                                <div class="form-group small">
                                    <label>Billing Zip</label>
                                    <input type="text" name="address_billing_postal_code" id="account-bill-zip">
                                </div>
                                <div class="form-group">
                                    <label>Billing Country</label>
                                    <input type="text" name="address_billing_country" id="account-bill-country">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="account-form-section">
                        <div class="account-form-section-header" onclick="toggleAccountSection(this)">
                            <span><i class="fa fa-truck me-2" style="color:var(--accent)"></i>Shipping Address</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="account-form-section-body">
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Shipping Street</label>
                                    <input type="text" name="address_shipping_street" id="account-ship-street">
                                </div>
                            </div>
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Shipping City</label>
                                    <input type="text" name="address_shipping_city" id="account-ship-city">
                                </div>
                                <div class="form-group">
                                    <label>Shipping Province</label>
                                    <input type="text" name="address_shipping_province" id="account-ship-province">
                                </div>
                                <div class="form-group small">
                                    <label>Shipping Zip</label>
                                    <input type="text" name="address_shipping_postal_code" id="account-ship-zip">
                                </div>
                                <div class="form-group">
                                    <label>Shipping Country</label>
                                    <input type="text" name="address_shipping_country" id="account-ship-country">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-account">
                    <i class="fa fa-save me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
let accountModalInstance = null;
let accountsTable = null;

const accountsCanUpdate = {{ $canUpdate ? 'true' : 'false' }};
const accountsCanDelete = {{ $canDelete ? 'true' : 'false' }};
const showUrl = '{{ route("accounts-management.show", "__ID__") }}';

function toggleAccountSection(header) {
    header.closest('.account-form-section').classList.toggle('open');
}

function resetAccountForm() {
    document.getElementById('account-form').reset();
    document.getElementById('account-edit-id').value = '';
    document.querySelectorAll('.account-form-section').forEach(function(s) {
        s.classList.remove('open');
    });
    document.querySelector('.account-form-section').classList.add('open');
    $('#account-form .is-invalid').removeClass('is-invalid');
    $('#account-end-user').val('').trigger('change');
    $('#account-parent').val('').trigger('change');
}

function openCreateModal() {
    resetAccountForm();
    document.getElementById('accountModalTitle').textContent = 'Add Account';
    if (!accountModalInstance) {
        accountModalInstance = new bootstrap.Modal(document.getElementById('accountModal'));
    }
    accountModalInstance.show();
}

function openEditModal(id) {
    $.ajax({
        url: '{{ route("accounts-management.edit", "__ID__") }}'.replace('__ID__', id),
        type: 'GET',
        success: function(res) {
            resetAccountForm();
            document.getElementById('accountModalTitle').textContent = 'Edit Account';
            document.getElementById('account-edit-id').value = res.data.id;
            $('#account-name').val(res.data.account_name);
            $('#account-type').val(res.data.types_accounts_companies_id);
            $('#account-source').val(res.data.sources_id);
            $('#account-website').val(res.data.website);
            $('#account-description').val(res.data.description);
            $('#account-segmentation').val(res.data.segmentation_id);
            $('#account-biz-entity').val(res.data.business_entities_id);
            $('#account-end-user').val(res.data.end_user).trigger('change');
            $('#account-parent').val(res.data.parent_account_id).trigger('change');
            $('#account-phone').val(res.data.phone);
            $('#account-biz-value').val(res.data.business_values_id);
            $('#account-interaction').val(res.data.interaction_levels_id);
            $('#account-bill-street').val(res.data.address_billing_street);
            $('#account-bill-city').val(res.data.address_billing_city);
            $('#account-bill-province').val(res.data.address_billing_province);
            $('#account-bill-zip').val(res.data.address_billing_postal_code);
            $('#account-bill-country').val(res.data.address_billing_country);
            $('#account-ship-street').val(res.data.address_shipping_street);
            $('#account-ship-city').val(res.data.address_shipping_city);
            $('#account-ship-province').val(res.data.address_shipping_province);
            $('#account-ship-zip').val(res.data.address_shipping_postal_code);
            $('#account-ship-country').val(res.data.address_shipping_country);
            if (!accountModalInstance) {
                accountModalInstance = new bootstrap.Modal(document.getElementById('accountModal'));
            }
            accountModalInstance.show();
        },
        error: function() {
            toastr.error('Gagal memuat data akun.');
        }
    });
}

function initAccountsTable() {
    if (accountsTable) {
        accountsTable.destroy();
    }

    accountsTable = $('#accounts-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("accounts-management.data") }}',
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            {
                data: 'account_name', orderable: true, searchable: true,
                render: function(data, type, row) {
                    var avatar = row.icon
                        ? '<img src="' + row.icon + '" class="avatar-circle" alt="" style="background:transparent">'
                        : '<div class="avatar-circle">' + row.initials + '</div>';
                    return '<div style="display:flex;align-items:center;gap:10px">' +
                        avatar +
                        '<strong style="color:var(--text-primary);font-weight:600">' + row.name_display + '</strong>' +
                        '</div>';
                }
            },
            { data: 'phone' },
            { data: 'owner_name', orderable: false },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    var html = '<div style="display:flex;gap:5px;justify-content:center">';
                    html += '<a href="' + showUrl.replace('__ID__', row.id) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (accountsCanUpdate) {
                        html += ' <button type="button" class="btn-icon" title="Edit" onclick="openEditModal(' + row.id + ')"><i class="fa fa-pen"></i></button>';
                    }
                    if (accountsCanDelete) {
                        html += ' <button type="button" class="btn-icon danger btn-delete-account" title="Hapus" data-id="' + row.id + '"><i class="fa fa-trash-can"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        order: [[1, 'asc']],
        pageLength: 10,
        lengthMenu: [10, 15, 25, 50, 100],
    });
}

$(document).on('click', '#btn-save-account', function() {
    const $btn = $(this);
    const editId = $('#account-edit-id').val();
    const isEdit = !!editId;

    $('#account-form .is-invalid').removeClass('is-invalid');

    const validations = [
        { field: '#account-name', label: 'Account Name' },
        { field: '#account-type', label: 'Field Type' },
        { field: '#account-source', label: 'Account Source' },
        { field: '#account-segmentation', label: 'Segmentation' },
        { field: '#account-biz-entity', label: 'Business Entity' },
        { field: '#account-biz-value', label: 'Business Value' },
        { field: '#account-interaction', label: 'Interaction Level' },
    ];

    for (let i = 0; i < validations.length; i++) {
        const v = validations[i];
        const $el = $(v.field);
        const val = $el.val() ? $el.val().trim() : '';
        if (!val) {
            $el.addClass('is-invalid');
            const section = $el.closest('.account-form-section');
            if (section.length && !section.hasClass('open')) {
                section.addClass('open');
            }
            toastr.error(v.label + ' wajib diisi.');
            $el.focus();
            return;
        }
    }

    const formData = new FormData(document.getElementById('account-form'));

    const url = isEdit
        ? '{{ route("accounts-management.update", "__ID__") }}'.replace('__ID__', editId)
        : '{{ route("accounts-management.store") }}';

    formData.append('_token', '{{ csrf_token() }}');
    if (isEdit) formData.append('_method', 'PUT');

    Swal.fire({
        title: isEdit ? 'Update Account?' : 'Save Account?',
        text: isEdit ? 'Account data will be updated.' : 'A new account will be added.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Yes, update!' : 'Yes, save!',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
    }).then(function(result) {
        if (!result.isConfirmed) return;

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Saving...');

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                toastr.success(res.message);
                if (accountModalInstance) accountModalInstance.hide();
                if (accountsTable) accountsTable.ajax.reload(null, false);
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save');
                var errors = xhr.responseJSON?.errors;
                if (errors) {
                    var first = Object.values(errors)[0];
                    toastr.error(Array.isArray(first) ? first[0] : first);
                } else {
                    toastr.error(xhr.responseJSON?.message || 'Failed to save data.');
                }
            }
        });
    });
});

$(document).on('change input', '#account-form input.is-invalid, #account-form select.is-invalid', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('change', '#account-end-user', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('change', '#account-parent', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('shown.bs.modal', '#accountModal', function() {
    if (!$('#account-end-user').hasClass('select2-hidden-accessible')) {
        $('#account-end-user').select2({
            theme: 'bootstrap-5',
            placeholder: '— Pilih —',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#accountModal')
        });
    }
    if (!$('#account-parent').hasClass('select2-hidden-accessible')) {
        $('#account-parent').select2({
            theme: 'bootstrap-5',
            placeholder: '— Pilih —',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#accountModal')
        });
    }
});

$(document).on('click', '.btn-delete-account', function() {
    const id = $(this).data('id');
    Swal.fire({
        title: 'Sure to delete this account?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route("accounts-management.destroy", "__ID__") }}'.replace('__ID__', id),
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    toastr.success(res.message);
                    if (accountsTable) accountsTable.ajax.reload(null, false);
                },
                error: function() {
                    toastr.error('Failed to delete data.');
                }
            });
        }
    });
});

initAccountsTable();
</script>
@endsection
