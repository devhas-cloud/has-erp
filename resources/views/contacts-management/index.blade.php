@extends('layouts.app')

@section('title', 'Contact Management')
@section('page-title', 'Contact Management')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .modal-contact .modal-dialog { max-width: 800px; }
    .contact-form-section {
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        margin-bottom: 16px;
        overflow: hidden;
    }
    .contact-form-section-header {
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
    .contact-form-section-body { padding: 16px; display: none; }
    .contact-form-section.open .contact-form-section-body { display: block; }
    .contact-form-section-header .chevron { transition: transform 0.2s; font-size: 11px; color: var(--text-muted); }
    .contact-form-section.open .chevron { transform: rotate(180deg); }
    .contact-form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
    .contact-form-row .form-group { flex: 1; min-width: 200px; }
    .contact-form-row .form-group.small { flex: 0 0 160px; }
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
        <h1 class="page-header-title">Contact Management</h1>
        <p class="page-header-sub">Kelola data kontak dan relasi perusahaan</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i>
            <span>Add Contact</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa fa-address-book me-2" style="color:var(--accent)"></i>Contacts List</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
        <table id="contacts-table" class="table table-custom align-middle mb-0" style="width:100%">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Name</th>
                    <th>Title</th>
                    <th>Account Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Owner</th>
                    <th class="text-center" style="width:120px">Action</th>
                </tr>
            </thead>
        </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade modal-contact" id="contactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="contactModalTitle">Add Contact</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                <form id="contact-form" autocomplete="off">
                    <input type="hidden" id="contact-edit-id">

                    <div class="contact-form-section open">
                        <div class="contact-form-section-header" onclick="toggleContactSection(this)">
                            <span><i class="fa fa-user me-2" style="color:var(--accent)"></i>Contact Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="contact-form-section-body">
                            <div class="contact-form-row">
                                <div class="form-group small">
                                    <label>Salutation <span class="text-danger">*</span></label>
                                    <select name="salutation" id="contact-salutation">
                                        <option value="">—</option>
                                        <option value="Bapak">Bapak</option>
                                        <option value="Ibu">Ibu</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" id="contact-full-name" required>
                                </div>
                            </div>
                            <div class="contact-form-row">
                                <div class="form-group">
                                    <label>Account Name <span class="text-danger">*</span></label>
                                    <select name="account_companies_id" id="contact-account">
                                        <option value="">— Pilih —</option>
                                        @foreach($accountCompanies as $ac)
                                        <option value="{{ $ac->id }}">{{ $ac->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="contact-form-row">
                                <div class="form-group">
                                    <label>Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" id="contact-email">
                                </div>
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" name="phone" id="contact-phone">
                                </div>
                                <div class="form-group">
                                    <label>Mobile <span class="text-danger">*</span></label>
                                    <input type="text" name="mobile" id="contact-mobile">
                                </div>
                            </div>
                            <div class="contact-form-row">
                                <div class="form-group">
                                    <label>Job Title <span class="text-danger">*</span></label>
                                    <select name="job_titles_id" id="contact-job-title">
                                        <option value="">— Pilih —</option>
                                        @foreach($jobTitles as $jt)
                                        <option value="{{ $jt->id }}">{{ $jt->title_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Contact Source <span class="text-danger">*</span></label>
                                    <select name="sources_id" id="contact-source">
                                        <option value="">— Pilih —</option>
                                        @foreach($sources as $src)
                                        <option value="{{ $src->id }}">{{ $src->source_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Department <span class="text-danger">*</span></label>
                                    <select name="divisions_id" id="contact-division">
                                        <option value="">— Pilih —</option>
                                        @foreach($divisions as $div)
                                        <option value="{{ $div->id }}">{{ $div->division_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="contact-form-row">
                                <div class="form-group">
                                    <label>Preferred Contact Method <span class="text-danger">*</span></label>
                                    <select name="contact_methods_id" id="contact-method">
                                        <option value="">— Pilih —</option>
                                        @foreach($contactMethods as $cm)
                                        <option value="{{ $cm->id }}">{{ $cm->method_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Role in Project <span class="text-danger">*</span></label>
                                    <select name="role_in_projects_id" id="contact-role">
                                        <option value="">— Pilih —</option>
                                        @foreach($roleInProjects as $rp)
                                        <option value="{{ $rp->id }}">{{ $rp->role_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="contact-form-section">
                        <div class="contact-form-section-header" onclick="toggleContactSection(this)">
                            <span><i class="fa fa-map-marker-alt me-2" style="color:var(--accent)"></i>Address Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="contact-form-section-body">
                            <div class="contact-form-row">
                                <div class="form-group">
                                    <label>Address Street</label>
                                    <input type="text" name="address_street" id="contact-addr-street">
                                </div>
                            </div>
                            <div class="contact-form-row">
                                <div class="form-group">
                                    <label>City</label>
                                    <input type="text" name="address_city" id="contact-addr-city">
                                </div>
                                <div class="form-group">
                                    <label>Province</label>
                                    <input type="text" name="address_province" id="contact-addr-province">
                                </div>
                                <div class="form-group small">
                                    <label>Zip</label>
                                    <input type="text" name="address_postal_code" id="contact-addr-zip">
                                </div>
                                <div class="form-group">
                                    <label>Country</label>
                                    <input type="text" name="address_country" id="contact-addr-country">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-contact">
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
let contactModalInstance = null;
let contactsTable = null;

const contactsCanUpdate = {{ $canUpdate ? 'true' : 'false' }};
const contactsCanDelete = {{ $canDelete ? 'true' : 'false' }};
const showUrl = '{{ route("contact-management.show", "__ID__") }}';

function toggleContactSection(header) {
    header.closest('.contact-form-section').classList.toggle('open');
}

function resetContactForm() {
    document.getElementById('contact-form').reset();
    document.getElementById('contact-edit-id').value = '';
    document.querySelectorAll('.contact-form-section').forEach(function(s) {
        s.classList.remove('open');
    });
    document.querySelector('.contact-form-section').classList.add('open');
    $('#contact-form .is-invalid').removeClass('is-invalid');
    $('#contact-account').val('').trigger('change');
}

function openCreateModal() {
    resetContactForm();
    document.getElementById('contactModalTitle').textContent = 'Add Contact';
    if (!contactModalInstance) {
        contactModalInstance = new bootstrap.Modal(document.getElementById('contactModal'));
    }
    contactModalInstance.show();
}

function openEditModal(id) {
    $.ajax({
        url: '{{ route("contact-management.edit", "__ID__") }}'.replace('__ID__', id),
        type: 'GET',
        success: function(res) {
            resetContactForm();
            document.getElementById('contactModalTitle').textContent = 'Edit Contact';
            document.getElementById('contact-edit-id').value = res.data.id;
            $('#contact-salutation').val(res.data.salutation);
            $('#contact-full-name').val(res.data.full_name);
            $('#contact-account').val(res.data.account_companies_id).trigger('change');
            $('#contact-email').val(res.data.email);
            $('#contact-phone').val(res.data.phone);
            $('#contact-mobile').val(res.data.mobile);
            $('#contact-job-title').val(res.data.job_titles_id);
            $('#contact-source').val(res.data.sources_id);
            $('#contact-division').val(res.data.divisions_id);
            $('#contact-method').val(res.data.contact_methods_id);
            $('#contact-role').val(res.data.role_in_projects_id);
            $('#contact-addr-street').val(res.data.address_street);
            $('#contact-addr-city').val(res.data.address_city);
            $('#contact-addr-province').val(res.data.address_province);
            $('#contact-addr-zip').val(res.data.address_postal_code);
            $('#contact-addr-country').val(res.data.address_country);
            if (!contactModalInstance) {
                contactModalInstance = new bootstrap.Modal(document.getElementById('contactModal'));
            }
            contactModalInstance.show();
        },
        error: function() {
            toastr.error('Gagal memuat data kontak.');
        }
    });
}

function initContactsTable() {
    if (contactsTable) {
        contactsTable.destroy();
    }

    contactsTable = $('#contacts-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("contact-management.data") }}',
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            {
                data: 'full_name', orderable: true, searchable: true,
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
            { data: 'title', orderable: false },
            { data: 'account_name', orderable: false },
            { data: 'phone' },
            { data: 'email' },
            { data: 'owner_name', orderable: false },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    var html = '<div style="display:flex;gap:5px;justify-content:center">';
                    html += '<a href="' + showUrl.replace('__ID__', row.id) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (contactsCanUpdate) {
                        html += ' <button type="button" class="btn-icon" title="Edit" onclick="openEditModal(' + row.id + ')"><i class="fa fa-pen"></i></button>';
                    }
                    if (contactsCanDelete) {
                        html += ' <button type="button" class="btn-icon danger btn-delete-contact" title="Hapus" data-id="' + row.id + '"><i class="fa fa-trash-can"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        order: [[1, 'asc']],
        pageLength: 10,
        lengthMenu: [10, 15, 25, 50, 100],
        searching : true,
        // language: {
        //     processing: '<i class="fa fa-spinner fa-spin"></i> Loading...',
        //     search: '',
        //     searchPlaceholder: 'Search...',
        //     lengthMenu: '_MENU_',
        //     info: 'Show _START_–_END_ of _TOTAL_',
        //     infoEmpty: 'No data available.',
        //     infoFiltered: '(filtered from _MAX_ total entries)',
        //     zeroRecords: 'No data found.',
        //     paginate: {
        //         first: '<i class="fa fa-angle-double-left"></i>',
        //         last: '<i class="fa fa-angle-double-right"></i>',
        //         previous: '<i class="fa fa-angle-left"></i>',
        //         next: '<i class="fa fa-angle-right"></i>'
        //     }
        // },
    });
}

$(document).on('click', '#btn-save-contact', function() {
    const $btn = $(this);
    const editId = $('#contact-edit-id').val();
    const isEdit = !!editId;

    $('#contact-form .is-invalid').removeClass('is-invalid');

    const validations = [
        { field: '#contact-salutation', label: 'Salutation' },
        { field: '#contact-full-name', label: 'Full Name' },
        { field: '#contact-account', label: 'Account Name' },
        { field: '#contact-email', label: 'Email' },
        { field: '#contact-mobile', label: 'Mobile' },
        { field: '#contact-job-title', label: 'Job Title' },
        { field: '#contact-source', label: 'Contact Source' },
        { field: '#contact-division', label: 'Department' },
        { field: '#contact-method', label: 'Preferred Contact Method' },
        { field: '#contact-role', label: 'Role in Project' },
    ];

    for (let i = 0; i < validations.length; i++) {
        const v = validations[i];
        const $el = $(v.field);
        const val = $el.val() ? $el.val().trim() : '';
        if (!val) {
            $el.addClass('is-invalid');
            const section = $el.closest('.contact-form-section');
            if (section.length && !section.hasClass('open')) {
                section.addClass('open');
            }
            toastr.error(v.label + ' wajib diisi.');
            $el.focus();
            return;
        }
    }

    const formData = new FormData(document.getElementById('contact-form'));

    const url = isEdit
        ? '{{ route("contact-management.update", "__ID__") }}'.replace('__ID__', editId)
        : '{{ route("contact-management.store") }}';

    formData.append('_token', '{{ csrf_token() }}');
    if (isEdit) formData.append('_method', 'PUT');

    Swal.fire({
        title: isEdit ? 'Update Contact?' : 'Save Contact?',
        text: isEdit ? 'Contact data will be updated.' : 'A new contact will be added.',
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
                if (contactModalInstance) contactModalInstance.hide();
                if (contactsTable) contactsTable.ajax.reload(null, false);
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

$(document).on('change input', '#contact-form input.is-invalid, #contact-form select.is-invalid', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('click', '.btn-delete-contact', function() {
    const id = $(this).data('id');
    Swal.fire({
        title: 'Sure to delete this contact?',
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
                url: '{{ route("contact-management.destroy", "__ID__") }}'.replace('__ID__', id),
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    toastr.success(res.message);
                    if (contactsTable) contactsTable.ajax.reload(null, false);
                },
                error: function() {
                    toastr.error('Failed to delete data.');
                }
            });
        }
    });
});

$(document).on('change', '#contact-account', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('shown.bs.modal', '#contactModal', function() {
    if (!$('#contact-account').hasClass('select2-hidden-accessible')) {
        $('#contact-account').select2({
            theme: 'bootstrap-5',
            placeholder: '— Pilih —',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#contactModal')
        });
    }
});

initContactsTable();
</script>
@endsection
