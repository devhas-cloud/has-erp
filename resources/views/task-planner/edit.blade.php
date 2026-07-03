@extends('layouts.app')

@section('title', 'Edit Task')
@section('page-title', 'Edit Task')

@section('styles')
<style>
    .field-required { color: var(--danger); font-weight: 700; margin-left: 2px; }
    .field-optional { font-size: 11px; color: var(--text-muted); font-weight: 500; margin-left: 6px; }
    .field-hint { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

    .task-identity {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 18px;
        background: var(--accent-soft);
        border: 1px solid rgba(16,185,129,0.15);
        border-radius: var(--radius);
        margin-bottom: 24px;
    }
    .task-identity-icon {
        width: 46px; height: 46px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--accent), #34d399);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 18px;
        flex-shrink: 0;
        box-shadow: 0 4px 12px var(--accent-glow);
    }
    .task-identity-info { line-height: 1.3; flex: 1; min-width: 0; }
    .task-identity-name { font-size: 15px; font-weight: 700; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .task-identity-meta { font-size: 12px; color: var(--text-muted); font-weight: 500; }
    .task-identity-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        flex-shrink: 0;
    }

    .task-section {
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        margin-bottom: 16px;
        overflow: hidden;
        transition: box-shadow 0.2s var(--ease);
    }
    .task-section:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.03); }

    .task-section-header {
        padding: 12px 18px;
        background: rgba(248,250,252,0.6);
        border-bottom: 1px solid transparent;
        font-weight: 700;
        font-size: 13.5px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: var(--text-primary);
        letter-spacing: -0.2px;
        transition: background 0.2s var(--ease);
        user-select: none;
    }
    .task-section-header:hover { background: rgba(248,250,252,0.9); }
    .task-section-header .section-icon {
        width: 28px; height: 28px;
        border-radius: 7px;
        background: var(--accent-soft);
        color: var(--accent);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 13px;
        margin-right: 10px;
        flex-shrink: 0;
    }
    .task-section.open .task-section-header { border-bottom-color: var(--card-border); }

    .task-section-body { padding: 18px; display: none; }
    .task-section.open .task-section-body { display: block; animation: fadeIn 0.25s ease both; }

    .task-section-header .chevron {
        transition: transform 0.25s var(--ease);
        font-size: 12px;
        color: var(--text-muted);
    }
    .task-section.open .chevron { transform: rotate(180deg); }

    .form-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-top: 20px;
        border-top: 1px solid var(--card-border);
        margin-top: 28px;
    }

    .select2-container .select2-selection--multiple {
        min-height: 42px;
        border: 1px solid var(--card-border) !important;
        border-radius: var(--radius-sm) !important;
    }
    .select2-container .select2-selection--multiple .select2-selection__rendered { padding: 4px 8px; }
    .select2-container .select2-search__field { font-size: 13.5px !important; font-family: inherit !important; }

    @media (max-width: 768px) {
        .form-actions { flex-direction: column; }
        .form-actions .btn-accent, .form-actions .btn-ghost { width: 100%; justify-content: center; }
        .task-identity { flex-wrap: wrap; }
        .task-identity-badge { margin-left: 0; }
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Edit Task</h1>
        <p class="page-header-sub">#{{ $task->id }} — Perbarui informasi tugas</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('task-planner.show', $task->id) }}" class="btn-ghost">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Kembali</span>
        </a>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-pen-to-square me-2" style="color:var(--accent)"></i>Form Edit Task</span>
    </div>
    <div class="card-body-custom">
        <form id="editTaskForm">
            @csrf
            @method('PUT')

            <div class="task-identity">
                <div class="task-identity-icon">
                    <i class="fa-solid fa-list-check"></i>
                </div>
                <div class="task-identity-info">
                    <div class="task-identity-name">{{ $task->title }}</div>
                    <div class="task-identity-meta">
                        #{{ $task->id }} · Dibuat oleh <strong>{{ $task->creator?->username ?? '—' }}</strong>
                        · {{ $task->category?->name ?? 'Tanpa Kategori' }}
                    </div>
                </div>
                @php
                    $statusStyles = [
                        'todo' => ['bg' => 'var(--warning-soft)', 'color' => '#78350f', 'label' => 'To Do'],
                        'in_progress' => ['bg' => 'var(--info-soft)', 'color' => '#1e40af', 'label' => 'In Progress'],
                        'waiting_approval' => ['bg' => '#fef3c7', 'color' => '#92400e', 'label' => 'Waiting Approval'],
                        'done' => ['bg' => 'var(--success-soft)', 'color' => '#065f46', 'label' => 'Done'],
                    ];
                    $ss = $statusStyles[$task->status] ?? $statusStyles['todo'];
                @endphp
                <span class="task-identity-badge" style="background:{{ $ss['bg'] }};color:{{ $ss['color'] }}">
                    {{ $ss['label'] }}
                </span>
            </div>

            <div class="task-section open fade-in stagger-1">
                <div class="task-section-header" onclick="toggleSection(this)">
                    <span><span class="section-icon"><i class="fa-solid fa-circle-info"></i></span>Task Information</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </div>
                <div class="task-section-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Title<span class="field-required">*</span></label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $task->title) }}" placeholder="Masukkan judul tugas" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Deskripsi tugas...">{{ old('description', $task->description) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category<span class="field-required">*</span></label>
                            <select name="category_id" class="form-select" required>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}"
                                        {{ $task->category_id == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->division_id ? '[' . optional($cat->division)->division_name . '] ' : '[Global] ' }}
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Due Date<span class="field-required">*</span></label>
                            <input type="date" name="due_date" class="form-control"
                                value="{{ old('due_date', $task->due_date->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Time<span class="field-optional">(opsional)</span></label>
                            <input type="time" name="time" class="form-control"
                                value="{{ old('time', $task->time) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                @foreach ($statuses as $s)
                                    <option value="{{ $s }}" {{ $task->status == $s ? 'selected' : '' }}>
                                        @if ($s === 'todo') To Do
                                        @elseif ($s === 'in_progress') In Progress
                                        @elseif ($s === 'waiting_approval') Waiting Approval
                                        @else Done
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="task-section open fade-in stagger-2">
                <div class="task-section-header" onclick="toggleSection(this)">
                    <span><span class="section-icon"><i class="fa-solid fa-users"></i></span>Assignees & Delegation</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </div>
                <div class="task-section-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Assign To</label>
                            <select name="assignees[]" id="edit_assignees" multiple class="form-select" style="width:100%">
                                @foreach ($task->assignees as $a)
                                    <option value="{{ $a->id }}" selected>{{ $a->username }}
                                        ({{ optional($a->hierarchyRole)->role_name ?? 'N/A' }})</option>
                                @endforeach
                            </select>
                            <div class="field-hint">Kosongkan untuk menugaskan diri sendiri.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="task-section open fade-in stagger-3">
                <div class="task-section-header" onclick="toggleSection(this)">
                    <span><span class="section-icon"><i class="fa-solid fa-bell"></i></span>Alert Settings</span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </div>
                <div class="task-section-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Alert Type</label>
                            <select name="alert_type" class="form-select">
                                <option value="none" {{ $task->alert_type == 'none' ? 'selected' : '' }}>None</option>
                                {{-- <option value="email" {{ $task->alert_type == 'email' ? 'selected' : '' }}>Email</option> --}}
                                <option value="whatsapp" {{ $task->alert_type == 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                                {{-- <option value="both" {{ $task->alert_type == 'both' ? 'selected' : '' }}>Both</option> --}}
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Alert Target</label>
                            <select name="alert_target" class="form-select">
                                <option value="personal" {{ $task->alert_target == 'personal' ? 'selected' : '' }}>Personal</option>
                                <option value="group" {{ $task->alert_target == 'group' ? 'selected' : '' }}>Group WA</option>
                                {{-- <option value="both" {{ $task->alert_target == 'both' ? 'selected' : '' }}>Both</option> --}}
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">WhatsApp Group<span class="field-optional">(untuk alert group)</span></label>
                            <select name="whatsapp_group_id" id="edit_whatsapp_group" class="form-select" style="width:100%">
                                @if ($task->whatsappGroup)
                                    <option value="{{ $task->whatsappGroup->id }}" selected>
                                        {{ $task->whatsappGroup->group_name }}
                                        ({{ $task->whatsappGroup->division?->division_name }})
                                    </option>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Alert Time</label>
                            <input type="datetime-local" name="alert_time" class="form-control"
                                value="{{ old('alert_time', $task->alert_time ? $task->alert_time->format('Y-m-d\TH:i') : '') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-accent" id="btn-submit">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Update Task</span>
                </button>
                <a href="{{ route('task-planner.show', $task->id) }}" class="btn-ghost">
                    <span>Batal</span>
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function toggleSection(header) {
        $(header).closest('.task-section').toggleClass('open');
    }

    $(function() {
        $('#edit_assignees').select2({
            placeholder: 'Cari assignee...',
            ajax: {
                url: '{{ route('task-planner.fetch-assignees') }}',
                dataType: 'json',
                delay: 300,
                data: function(params) {
                    return { q: params.term };
                },
                processResults: function(data) {
                    return { results: data.results };
                }
            },
            minimumInputLength: 1
        });

        $('#edit_whatsapp_group').select2({
            placeholder: 'Cari WhatsApp group...',
            allowClear: true,
            ajax: {
                url: '{{ route('task-planner.fetch-whatsapp-groups') }}',
                dataType: 'json',
                delay: 300,
                data: function(params) {
                    return { q: params.term };
                },
                processResults: function(data) {
                    return { results: data.results };
                }
            },
            minimumInputLength: 0
        });

        $('#editTaskForm').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $('#btn-submit');

            Swal.fire({
                title: 'Update Task?',
                text: 'Perubahan data tugas akan disimpan.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, update',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
            }).then(function(result) {
                if (!result.isConfirmed) return;

                $btn.prop('disabled', true).html(
                    '<i class="fa-solid fa-spinner fa-spin"></i><span>Menyimpan...</span>'
                );

                var formData = new FormData($form[0]);
                formData.append('_method', 'PUT');
                formData.append('_token', '{{ csrf_token() }}');

                $.ajax({
                    url: '{{ route('task-planner.update', $task->id) }}',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        toastr.success(res.message);
                        setTimeout(function() {
                            window.location.href = '{{ route('task-planner.show', $task->id) }}';
                        }, 500);
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON?.message || 'Gagal memperbarui task.';
                        toastr.error(msg);
                        $btn.prop('disabled', false).html(
                            '<i class="fa-solid fa-floppy-disk"></i><span>Update Task</span>'
                        );
                    }
                });
            });
        });
    });
</script>
@endsection
