{{--
    Task Planner yang dikaitkan ke project (semua task, tanpa filter pembuat/assignee),
    plus form "Tambah Task" yang menyimpan lewat task-planner.store dengan project terisi.
    Variabel: $project, $tasks, $canCreateTask, $projectActive, $taskCategories, $class (opsional),
    $taskDivision (opsional): bila diisi, $tasks sudah difilter ke divisi tsb dan judul/keterangan menyesuaikan.
--}}
@php
    $taskStatus = [
        'todo' => ['To Do', 'background:var(--warning-soft);color:#78350f'],
        'in_progress' => ['In Progress', 'background:var(--info-soft);color:#1e40af'],
        'waiting_approval' => ['Waiting Approval', 'background:#fef3c7;color:#92400e'],
        'done' => ['Done', 'background:var(--success-soft);color:#065f46'],
    ];
    $openTasks = $tasks->where('status', '!=', 'done')->count();
    $canAddTask = ($canCreateTask ?? false) && ($projectActive ?? false);
@endphp
<div class="card-custom fade-in {{ $class ?? 'mb-4' }}" id="project-tasks">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="fa-solid fa-list-check me-2" style="color:var(--accent)"></i>{{ ! empty($taskDivision) ? 'Task '.$taskDivision : 'Related Tasks' }}
            <span style="font-size:12px;color:var(--text-muted);font-weight:500">
                {{ $tasks->count() }} tasks{{ $openTasks ? ' · '.$openTasks.' incomplete' : '' }}
            </span>
        </span>
        @if($canAddTask)
            <button type="button" class="btn btn-accent btn-sm" onclick="ptkOpen()">
                <i class="fa fa-plus me-1"></i> Add Task
            </button>
        @elseif(($canCreateTask ?? false) && ! ($projectActive ?? false))
            <span style="font-size:12px;color:var(--text-muted)"><i class="fa fa-lock me-1"></i>New tasks only for Active projects</span>
        @endif
    </div>
    @if(! empty($taskDivision))
        <div style="font-size:12px;color:var(--text-muted);padding:8px 16px 0">
            <i class="fa fa-filter me-1"></i>Only tasks created by / assigned to the {{ $taskDivision }} division.
        </div>
    @endif
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Category</th>
                        <th>Assignee</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $t)
                        @php
                            [$stLabel, $stStyle] = $taskStatus[$t->status] ?? $taskStatus['todo'];
                            $overdue = $t->due_date && $t->due_date->copy()->endOfDay()->isPast() && $t->status !== 'done';
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('task-planner.show', $t->id) }}" style="font-weight:600;color:var(--text-primary);text-decoration:none">{{ $t->title }}</a>
                                <div style="font-size:11.5px;color:var(--text-muted)">by {{ $t->creator?->display_name ?? '—' }}</div>
                            </td>
                            <td>{{ $t->category?->name ?? '—' }}</td>
                            <td style="font-size:13px">{{ $t->assignees->pluck('display_name')->join(', ') ?: '—' }}</td>
                            <td class="{{ $overdue ? 'text-danger fw-bold' : '' }}" style="white-space:nowrap">
                                {{ $t->due_date?->format('d M Y') ?? '—' }}@if($t->time) <span style="font-weight:400;color:var(--text-muted)">{{ $t->time }}</span>@endif
                                @if($overdue) <i class="fa fa-exclamation-circle" title="Overdue"></i>@endif
                            </td>
                            <td><span class="status-badge" style="{{ $stStyle }}">{{ $stLabel }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4" style="color:var(--text-muted)">
                                <i class="fa-solid fa-inbox me-1"></i> No tasks yet {{ ! empty($taskDivision) ? 'for the '.$taskDivision.' division ' : '' }}for this project.
                                @if($canAddTask)
                                    <a href="javascript:void(0)" onclick="ptkOpen()" class="text-decoration-none">Add first task</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canAddTask)
@push('modals')
<div class="modal fade" id="ptkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:640px">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h6 class="modal-title mb-0"><i class="fa-solid fa-list-check me-2" style="color:var(--accent)"></i>Add Task</h6>
                    <div style="font-size:12px;color:var(--text-muted)">{{ $project->project_number }} — {{ $project->project_name }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="ptk-title">Title <span class="text-danger">*</span></label>
                        <input type="text" id="ptk-title" class="form-control" maxlength="150" placeholder="E.g. Routine maintenance Q1 / Inverter check">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ptk-category">Category <span class="text-danger">*</span></label>
                        <select id="ptk-category" class="form-select">
                            <option value="">— Select category —</option>
                            @foreach($taskCategories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->division_id ? '['.optional($cat->division)->division_name.'] ' : '' }}{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label" for="ptk-due">Due Date <span class="text-danger">*</span></label>
                        <input type="date" id="ptk-due" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label" for="ptk-time">Time</label>
                        <input type="time" id="ptk-time" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ptk-assignees">Assign to</label>
                        <select id="ptk-assignees" multiple style="width:100%"></select>
                        <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px">
                            Leave empty to assign to yourself.
                            @if(! empty($taskDivision))
                                Task appears on this page when the creator or assignee is from the {{ $taskDivision }} division.
                            @endif
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ptk-description">Description</label>
                        <textarea id="ptk-description" class="form-control" rows="3" placeholder="Work details, location, devices..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ route('task-planner.index') }}" class="me-auto" style="font-size:12px;color:var(--text-muted);text-decoration:none">
                    Full settings (WA alerts, etc.) in Task Planner <i class="fa fa-arrow-up-right-from-square ms-1"></i>
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-accent btn-sm" id="ptk-save" onclick="ptkSave()">
                    <i class="fa fa-floppy-disk me-1"></i> Save Task
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('page-scripts')
<script>
const ptkProjectId = {{ $project->id }};
let ptkModal = null;

function ptkOpen() {
    if (!ptkModal) {
        ptkModal = new bootstrap.Modal(document.getElementById('ptkModal'));
        $('#ptk-assignees').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Search users...',
            allowClear: true,
            dropdownParent: $('#ptkModal'),
            minimumInputLength: 0,
            ajax: {
                url: '{{ route("task-planner.fetch-assignees") }}',
                dataType: 'json',
                delay: 300,
                data: function(params) { return { q: params.term }; },
                processResults: function(data) { return { results: data.results }; }
            }
        });
    }
    $('#ptkModal .is-invalid').removeClass('is-invalid');
    ptkModal.show();
    setTimeout(function() { $('#ptk-title').trigger('focus'); }, 300);
}

function ptkFail(selector, message) {
    $(selector).addClass('is-invalid').trigger('focus');
    toastr.error(message);
    return false;
}

function ptkSave() {
    $('#ptkModal .is-invalid').removeClass('is-invalid');
    if (!$.trim($('#ptk-title').val())) return ptkFail('#ptk-title', 'Task title is required.');
    if (!$('#ptk-category').val()) return ptkFail('#ptk-category', 'Category is required.');
    if (!$('#ptk-due').val()) return ptkFail('#ptk-due', 'Due date is required.');

    var data = {
        _token: '{{ csrf_token() }}',
        project_id: ptkProjectId,
        title: $('#ptk-title').val(),
        category_id: $('#ptk-category').val(),
        due_date: $('#ptk-due').val(),
        time: $('#ptk-time').val() || '',
        description: $('#ptk-description').val(),
        assignees: $('#ptk-assignees').val() || [],
        alert_type: 'none',
        alert_target: 'personal'
    };
    if (!data.time) delete data.time;

    var $btn = $('#ptk-save');
    var label = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

    $.ajax({ url: '{{ route("task-planner.store") }}', method: 'POST', data: data, headers: { Accept: 'application/json' } })
        .done(function(res) {
            toastr.success(res.message || 'Task created.');
            ptkModal.hide();
            setTimeout(function() { window.location.hash = 'project-tasks'; window.location.reload(); }, 500);
        })
        .fail(function(xhr) {
            $btn.prop('disabled', false).html(label);
            var json = xhr.responseJSON || {};
            var message = json.message || 'Failed to save task.';
            if (json.errors) {
                var first = Object.keys(json.errors)[0];
                if (first) message = json.errors[first][0];
            }
            toastr.error(message);
        });
}

$(document).on('input change', '#ptkModal .is-invalid', function() { $(this).removeClass('is-invalid'); });
</script>
@endpush
@endif
