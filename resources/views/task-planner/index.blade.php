@extends('layouts.app')

@section('title', 'Task Planner')
@section('page-title', 'Task Planner')

@section('styles')
<style>
    .modal-task .modal-dialog { max-width: 800px; }
    .task-form-section {
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        margin-bottom: 16px;
        overflow: hidden;
    }
    .task-form-section-header {
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
    .task-form-section-body { padding: 16px; display: none; }
    .task-form-section.open .task-form-section-body { display: block; }
    .task-form-section-header .chevron { transition: transform 0.2s; font-size: 11px; color: var(--text-muted); }
    .task-form-section.open .chevron { transform: rotate(180deg); }
    .task-form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
    .task-form-row .form-group { flex: 1; min-width: 200px; }
    .task-form-row .form-group.small { flex: 0 0 160px; }
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
    .filter-bar {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    .filter-bar select {
        padding: 6px 10px;
        border: 1px solid var(--card-border);
        border-radius: var(--radius-sm);
        font-size: 12px;
        color: var(--text-primary);
        background: #fff;
    }
    .select2-container .select2-selection--multiple {
        min-height: 38px;
        border: 1px solid var(--card-border) !important;
        border-radius: var(--radius-sm) !important;
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
        <h1 class="page-header-title">Task Planner</h1>
        <p class="page-header-sub">Kelola tugas dan pendelegasian lintas divisi</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i>
            <span>Add Task</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <div style="display:flex;align-items:center;justify-content:space-between;width:100%;flex-wrap:wrap;gap:10px">
            <span><i class="fa fa-tasks me-2" style="color:var(--accent)"></i>Tasks List</span>
            <div class="filter-bar">
                <input type="date" id="filter-due-from" onchange="taskTable.ajax.reload(null, false)" style="padding:5px 8px;border:1px solid var(--card-border);border-radius:var(--radius-sm);font-size:12px" placeholder="Due Date From">
                <input type="date" id="filter-due-to" onchange="taskTable.ajax.reload(null, false)" style="padding:5px 8px;border:1px solid var(--card-border);border-radius:var(--radius-sm);font-size:12px" placeholder="Due Date To">
                <select id="filter-status" onchange="taskTable.ajax.reload(null, false)">
                    <option value="">All Status</option>
                    <option value="todo">To Do</option>
                    <option value="in_progress">In Progress</option>
                    <option value="waiting_approval">Waiting Approval</option>
                    <option value="done">Done</option>
                </select>
                <select id="filter-category" onchange="taskTable.ajax.reload(null, false)">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-sm btn-success" style="font-size:12px" onclick="exportTasks()">
                    <i class="fa fa-download me-1"></i>Export
                </button>
                @if($canCreate)
                <button type="button" class="btn btn-sm btn-info" style="font-size:12px;color:#fff" onclick="openImportModal()">
                    <i class="fa fa-upload me-1"></i>Import
                </button>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body-custom p-2">
        <table id="tasks-table" class="table table-custom align-middle mb-0" style="width:100%">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Creator</th>
                    <th>Title</th>
                    <th>Assignee(s)</th>
                    <th>Category</th>
                    <th>Time</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th class="text-center" style="width:120px">Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade modal-task" id="taskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="taskModalTitle">Add Task</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                <form id="task-form" autocomplete="off">
                    <input type="hidden" id="task-edit-id">

                    <div class="task-form-section open">
                        <div class="task-form-section-header" onclick="toggleTaskSection(this)">
                            <span><i class="fa fa-info-circle me-2" style="color:var(--accent)"></i>Task Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="task-form-section-body">
                            <div class="task-form-row">
                                <div class="form-group">
                                    <label>Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" id="task-title" placeholder="Enter task title">
                                </div>
                            </div>
                            <div class="task-form-row">
                                <div class="form-group" style="flex:2">
                                    <label>Description</label>
                                    <textarea name="description" id="task-description" rows="3" placeholder="Task description..."></textarea>
                                </div>
                            </div>
                            <div class="task-form-row">
                                <div class="form-group">
                                    <label>Category <span class="text-danger">*</span></label>
                                    <select name="category_id" id="task-category-id">
                                        <option value="">— Select Category —</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}">
                                                {{ $cat->division_id ? '[' . optional($cat->division)->division_name . '] ' : '[Global] ' }}
                                                {{ $cat->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                            </div>
                            <div class="task-form-row">
                                <div class="form-group">
                                    <label>Due Date <span class="text-danger">*</span></label>
                                    <input type="date" name="due_date" id="task-due-date">
                                </div>
                                <div class="form-group">
                                    <label>Time</label>
                                    <input type="time" name="time" id="task-time">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="task-form-section open">
                        <div class="task-form-section-header" onclick="toggleTaskSection(this)">
                            <span><i class="fa fa-users me-2" style="color:var(--accent)"></i>Assignees & Delegation</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="task-form-section-body">
                            <div class="task-form-row">
                                <div class="form-group">
                                    <label>Assign To</label>
                                    <select name="assignees[]" id="task-assignees" multiple style="width:100%"></select>
                                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
                                        Leave empty to assign to yourself. Filtered by your delegation hierarchy.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="task-form-section">
                        <div class="task-form-section-header" onclick="toggleTaskSection(this)">
                            <span><i class="fa fa-bell me-2" style="color:var(--accent)"></i>Alert Settings</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="task-form-section-body">
                            <div class="task-form-row">
                                <div class="form-group small">
                                    <label>Alert Type</label>
                                    <select name="alert_type" id="task-alert-type">
                                        <option value="none">None</option>
                                        {{-- <option value="email">Email</option> --}}
                                        <option value="whatsapp">WhatsApp</option>
                                        {{-- <option value="both">Both</option> --}}
                                    </select>
                                </div>
                                <div class="form-group small">
                                    <label>Alert Target</label>
                                    <select name="alert_target" id="task-alert-target">
                                        <option value="personal">Personal (Japri)</option>
                                        <option value="group">Group WA</option>
                                        {{-- <option value="both">Both</option> --}}
                                    </select>
                                </div>
                                 <div class="form-group" id="whatsapp-group-container" style="display:none">
                                    <label>WhatsApp Group (for alert group)</label>
                                    <select name="whatsapp_group_id" id="task-whatsapp-group" style="width:100%"></select>
                                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
                                        Pilih grup WA untuk notifikasi.
                                    </div>
                                </div>
                                <div class="form-group small">
                                    <label>Alert Time</label>
                                    <input type="datetime-local" name="alert_time" id="task-alert-time">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-task">
                    <i class="fa fa-save me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:500px">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa fa-upload me-2" style="color:var(--accent)"></i>Import Tasks</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
                    Download template terlebih dahulu, isi data, lalu upload kembali.
                </p>
                <a href="{{ route('task-planner.template') }}" class="btn btn-sm btn-accent mb-3">
                    <i class="fa fa-download me-1"></i> Download Template
                </a>
                <hr>
                <div class="form-group">
                    <label>Upload File (xlsx, xls, csv)</label>
                    <input type="file" id="import-file" accept=".xlsx,.xls,.csv" class="form-control form-control-sm" style="margin-top:4px">
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Max 5MB</div>
                </div>
                <div id="import-result" style="display:none;margin-top:12px"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-import-task">
                    <i class="fa fa-upload me-1"></i> Import
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let taskModalInstance = null;
let taskTable = null;

const taskCanUpdate = {{ $canUpdate ? 'true' : 'false' }};
const taskCanDelete = {{ $canDelete ? 'true' : 'false' }};
const currentUserId = {{ $userId }};
const showUrl = '{{ route("task-planner.show", "__ID__") }}';
const editUrl = '{{ route("task-planner.edit", "__ID__") }}';
const exportUrl = '{{ route("task-planner.export") }}';
const importUrl = '{{ route("task-planner.import") }}';

function toggleTaskSection(header) {
    header.closest('.task-form-section').classList.toggle('open');
}

function resetTaskForm() {
    document.getElementById('task-form').reset();
    document.getElementById('task-edit-id').value = '';
    document.querySelectorAll('.task-form-section').forEach(function(s) {
        s.classList.remove('open');
    });
    document.querySelector('.task-form-section').classList.add('open');
    document.querySelectorAll('.task-form-section')[1].classList.add('open');
    $('#task-form .is-invalid').removeClass('is-invalid');
    $('#task-assignees').val(null).trigger('change');
    $('#task-whatsapp-group').val(null).trigger('change');
    $('#whatsapp-group-container').hide();
    $('#task-alert-target').val('personal');
}

function openCreateModal() {
    resetTaskForm();
    document.getElementById('taskModalTitle').textContent = 'Add Task';
    $('#btn-save-task').prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save');
    if (!taskModalInstance) {
        taskModalInstance = new bootstrap.Modal(document.getElementById('taskModal'));
    }
    taskModalInstance.show();
}

function initTasksTable() {
    if (taskTable) {
        taskTable.destroy();
    }

    taskTable = $('#tasks-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("task-planner.data") }}',
            data: function(d) {
                d.status = $('#filter-status').val();
                d.category_id = $('#filter-category').val();
                d.due_date_from = $('#filter-due-from').val();
                d.due_date_to = $('#filter-due-to').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },

            { data: 'creator_name', orderable: true },
            { data: 'title', orderable: true, searchable: true },

            { data: 'assignees', orderable: true },
            {
                data: null,
                orderable: true,
                render: function(data, type, row) {
                    return '<span class="status-badge status-pending">' + row.category_name + '</span>';
                }
            },
            {
                data:null,
                orderable: true,
                render: function(data, type, row) {
                    var time = row.time ?  row.time : '—';
                    return time;
                }
            },

            {
                data: null,
                render: function(data, type, row) {
                    var cls = row.is_overdue ? 'text-danger fw-bold' : '';
                    return '<span class="' + cls + '">' + row.due_date + (row.is_overdue ? ' <i class="fa fa-exclamation-circle"></i>' : '') + '</span>';
                }
            },
            { data: 'status_label', orderable: true },

            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    var html = '<div style="display:flex;gap:5px;justify-content:center">';
                    html += '<a href="' + showUrl.replace('__ID__', row.id) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    var isCreator = row.creator_id === currentUserId;
                    if (taskCanUpdate && isCreator) {
                        html += ' <a href="' + editUrl.replace('__ID__', row.id) + '" class="btn-icon" title="Edit"><i class="fa fa-pen"></i></a>';
                    }
                    if (taskCanDelete && isCreator) {
                        html += ' <button type="button" class="btn-icon danger btn-delete-task" title="Hapus" data-id="' + row.id + '"><i class="fa fa-trash-can"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 15,
        lengthMenu: [15, 25, 50, 100],
    });
}

$(document).on('click', '#btn-save-task', function() {
    const $btn = $(this);
    const editId = $('#task-edit-id').val();
    const isEdit = !!editId;

    $('#task-form .is-invalid').removeClass('is-invalid');

    const validations = [
        { field: '#task-title', label: 'Title' },
        { field: '#task-category-id', label: 'Category' },
        { field: '#task-due-date', label: 'Due Date' },
    ];

    for (let i = 0; i < validations.length; i++) {
        const v = validations[i];
        const $el = $(v.field);
        const val = $el.val() ? $el.val().trim() : '';
        if (!val) {
            $el.addClass('is-invalid');
            const section = $el.closest('.task-form-section');
            if (section.length && !section.hasClass('open')) {
                section.addClass('open');
            }
            toastr.error(v.label + ' wajib diisi.');
            $el.focus();
            return;
        }
    }

    const formData = new FormData(document.getElementById('task-form'));

    const url = isEdit
        ? '{{ route("task-planner.update", "__ID__") }}'.replace('__ID__', editId)
        : '{{ route("task-planner.store") }}';

    formData.append('_token', '{{ csrf_token() }}');
    if (isEdit) formData.append('_method', 'PUT');

    Swal.fire({
        title: isEdit ? 'Update Task?' : 'Save Task?',
        text: isEdit ? 'Task data will be updated.' : 'A new task will be created.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Yes, update!' : 'Yes, save!',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#10b981',
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
                if (taskModalInstance) taskModalInstance.hide();
                if (taskTable) taskTable.ajax.reload(null, false);
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save');
                var errors = xhr.responseJSON?.errors;
                if (errors) {
                    Object.keys(errors).forEach(function(field) {
                        var $input = $('[name="' + field + '"]');
                        if ($input.length === 0) {
                            $input = $('[name="' + field.replace(/\.\d+/g, '') + '[]"]');
                        }
                        if ($input.length > 0) {
                            $input.addClass('is-invalid');
                        }
                    });
                    var first = Object.values(errors)[0];
                    toastr.error(Array.isArray(first) ? first[0] : first);
                } else {
                    toastr.error(xhr.responseJSON?.message || 'Failed to save task.');
                }
            }
        });
    });
});

$(document).on('change input', '#task-form input.is-invalid, #task-form select.is-invalid', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('click', '.btn-delete-task', function() {
    const id = $(this).data('id');
    Swal.fire({
        title: 'Sure to delete this task?',
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
                url: '{{ route("task-planner.destroy", "__ID__") }}'.replace('__ID__', id),
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    toastr.success(res.message);
                    if (taskTable) taskTable.ajax.reload(null, false);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to delete task.');
                }
            });
        }
    });
});

$(document).on('shown.bs.modal', '#taskModal', function() {
    if (!$('#task-assignees').hasClass('select2-hidden-accessible')) {
        $('#task-assignees').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search and select assignees...',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#taskModal'),
            ajax: {
                url: '{{ route("task-planner.fetch-assignees") }}',
                dataType: 'json',
                delay: 300,
                data: function(params) { return { q: params.term }; },
                processResults: function(data) { return { results: data.results }; }
            },
            minimumInputLength: 1
        });
    }

    if (!$('#task-whatsapp-group').hasClass('select2-hidden-accessible')) {
        $('#task-whatsapp-group').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search and select WhatsApp group...',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#taskModal'),
            ajax: {
                url: '{{ route("task-planner.fetch-whatsapp-groups") }}',
                dataType: 'json',
                delay: 300,
                data: function(params) { return { q: params.term }; },
                processResults: function(data) { return { results: data.results }; }
            },
            minimumInputLength: 0
        });
    }
});

$(document).on('change', '#task-alert-target', function() {
    var val = $(this).val();
    if (val === 'group' || val === 'both') {
        $('#whatsapp-group-container').show();
    } else {
        $('#whatsapp-group-container').hide();
    }
});

initTasksTable();

function openImportModal() {
    $('#import-file').val('');
    $('#import-result').hide().empty();
    new bootstrap.Modal(document.getElementById('importModal')).show();
}

function exportTasks() {
    var params = new URLSearchParams();
    var status = $('#filter-status').val();
    var category = $('#filter-category').val();
    var dueFrom = $('#filter-due-from').val();
    var dueTo = $('#filter-due-to').val();
    if (status) params.set('status', status);
    if (category) params.set('category_id', category);
    if (dueFrom) params.set('due_date_from', dueFrom);
    if (dueTo) params.set('due_date_to', dueTo);
    window.open(exportUrl + '?' + params.toString(), '_blank');
}

$(document).on('click', '#btn-import-task', function() {
    var $btn = $(this);
    var file = $('#import-file')[0].files[0];
    if (!file) {
        toastr.error('Pilih file terlebih dahulu.');
        return;
    }

    var formData = new FormData();
    formData.append('file', file);
    formData.append('_token', '{{ csrf_token() }}');

    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Importing...');
    $('#import-result').hide().empty();

    $.ajax({
        url: importUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            var html = '';
            if (res.success) {
                html += '<div class="alert alert-success py-2 px-3 mb-2" style="font-size:13px">' + res.message + '</div>';
            } else {
                html += '<div class="alert alert-warning py-2 px-3 mb-2" style="font-size:13px">' + res.message + '</div>';
            }
            if (res.result && res.result.errors && res.result.errors.length) {
                html += '<ul style="font-size:12px;color:#dc3545;margin:0;padding-left:16px;max-height:150px;overflow-y:auto">';
                res.result.errors.forEach(function(e) {
                    html += '<li>' + e + '</li>';
                });
                html += '</ul>';
            }
            $('#import-result').html(html).show();
            if (taskTable) taskTable.ajax.reload(null, false);
        },
        error: function(xhr) {
            var msg = xhr.responseJSON?.message || 'Gagal mengimpor.';
            $('#import-result').html('<div class="alert alert-danger py-2 px-3 mb-0" style="font-size:13px">' + msg + '</div>').show();
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="fa fa-upload me-1"></i> Import');
        }
    });
});
</script>
@endsection
