@extends('layouts.app')

@section('title', 'Edit Task')
@section('page-title', 'Edit Task')

@section('styles')
<style>
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
    .form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
    .form-row .form-group { flex: 1; min-width: 200px; }
    .form-row .form-group.small { flex: 0 0 160px; }
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
    .select2-container .select2-selection--multiple {
        min-height: 38px;
        border: 1px solid var(--card-border) !important;
        border-radius: var(--radius-sm) !important;
    }
</style>
@endsection

@section('content')
<div class="fade-in" style="max-width:800px">
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Edit Task</h1>
            <p class="page-header-sub">#{{ $task->id }} — {{ $task->title }}</p>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('task-planner.show', $task->id) }}" class="btn-ghost">
                <i class="fa fa-arrow-left"></i><span>Back</span>
            </a>
        </div>
    </div>

    <form id="editTaskForm">
        @csrf
        @method('PUT')

        <div class="task-form-section open">
            <div class="task-form-section-header" onclick="toggleSection(this)">
                <span><i class="fa fa-info-circle me-1"></i> Task Information</span>
                <i class="fa fa-chevron-down chevron"></i>
            </div>
            <div class="task-form-section-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Title <span style="color:#dc3545">*</span></label>
                        <input type="text" name="title" value="{{ old('title', $task->title) }}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:2">
                        <label>Description</label>
                        <textarea name="description" rows="3">{{ old('description', $task->description) }}</textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category <span style="color:#dc3545">*</span></label>
                        <select name="category_id" required>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ $task->category_id == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->division_id ? '[' . optional($cat->division)->division_name . '] ' : '[Global] ' }}
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Division (for alert group)</label>
                        <select name="division_id">
                            <option value="">— Select —</option>
                            @foreach($divisions as $div)
                                <option value="{{ $div->id }}" {{ $task->division_id == $div->id ? 'selected' : '' }}>
                                    {{ $div->division_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date <span style="color:#dc3545">*</span></label>
                        <input type="datetime-local" name="start_date" value="{{ old('start_date', $task->start_date->format('Y-m-d\TH:i')) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Due Date <span style="color:#dc3545">*</span></label>
                        <input type="datetime-local" name="due_date" value="{{ old('due_date', $task->due_date->format('Y-m-d\TH:i')) }}" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group small">
                        <label>Status</label>
                        <select name="status" required>
                            @foreach($statuses as $s)
                                <option value="{{ $s }}" {{ $task->status == $s ? 'selected' : '' }}>
                                    {{ $s === 'todo' ? 'To Do' : ($s === 'in_progress' ? 'In Progress' : ($s === 'waiting_approval' ? 'Waiting Approval' : 'Done')) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="task-form-section open">
            <div class="task-form-section-header" onclick="toggleSection(this)">
                <span><i class="fa fa-users me-1"></i> Assignees & Delegation</span>
                <i class="fa fa-chevron-down chevron"></i>
            </div>
            <div class="task-form-section-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Assign To</label>
                        <select name="assignees[]" id="edit_assignees" multiple style="width:100%">
                            @foreach($task->assignees as $a)
                                <option value="{{ $a->id }}" selected>{{ $a->username }} ({{ optional($a->hierarchyRole)->role_name ?? 'N/A' }})</option>
                            @endforeach
                        </select>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
                            Leave empty to assign to yourself.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="task-form-section open">
            <div class="task-form-section-header" onclick="toggleSection(this)">
                <span><i class="fa fa-bell me-1"></i> Alert Settings</span>
                <i class="fa fa-chevron-down chevron"></i>
            </div>
            <div class="task-form-section-body">
                <div class="form-row">
                    <div class="form-group small">
                        <label>Alert Type</label>
                        <select name="alert_type">
                            <option value="none" {{ $task->alert_type == 'none' ? 'selected' : '' }}>None</option>
                            <option value="email" {{ $task->alert_type == 'email' ? 'selected' : '' }}>Email</option>
                            <option value="whatsapp" {{ $task->alert_type == 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                            <option value="both" {{ $task->alert_type == 'both' ? 'selected' : '' }}>Both</option>
                        </select>
                    </div>
                    <div class="form-group small">
                        <label>Alert Target</label>
                        <select name="alert_target">
                            <option value="personal" {{ $task->alert_target == 'personal' ? 'selected' : '' }}>Personal</option>
                            <option value="group" {{ $task->alert_target == 'group' ? 'selected' : '' }}>Group WA</option>
                            <option value="both" {{ $task->alert_target == 'both' ? 'selected' : '' }}>Both</option>
                        </select>
                    </div>
                    <div class="form-group small">
                        <label>Alert Time</label>
                        <input type="datetime-local" name="alert_time" value="{{ old('alert_time', $task->alert_time ? $task->alert_time->format('Y-m-d\TH:i') : '') }}">
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
            <a href="{{ route('task-planner.show', $task->id) }}" class="btn-ghost">Cancel</a>
            <button type="submit" class="btn-accent">
                <i class="fa fa-save me-1"></i> Update Task
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    function toggleSection(header) {
        $(header).closest('.task-form-section').toggleClass('open');
    }

    $(function() {
        $('#edit_assignees').select2({
            placeholder: 'Search assignees...',
            ajax: {
                url: '{{ route("task-planner.fetch-assignees") }}',
                dataType: 'json',
                delay: 300,
                data: function(params) { return { q: params.term }; },
                processResults: function(data) { return { results: data.results }; }
            },
            minimumInputLength: 1
        });
    });

    $('#editTaskForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append('_method', 'PUT');
        formData.append('_token', '{{ csrf_token() }}');

        $.ajax({
            url: '{{ route("task-planner.update", $task->id) }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                toastr.success(res.message);
                setTimeout(function() {
                    window.location.href = '{{ route("task-planner.show", $task->id) }}';
                }, 500);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Failed to update task.';
                toastr.error(msg);
            }
        });
    });
</script>
@endsection
