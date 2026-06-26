@extends('layouts.app')

@section('title', 'Leads Management')
@section('page-title', 'Leads Management')

@section('styles')
<style>
    .modal-lead .modal-dialog { max-width: 800px; }
    .lead-form-section {
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        margin-bottom: 16px;
        overflow: hidden;
    }
    .lead-form-section-header {
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
    .lead-form-section-body { padding: 16px; display: none; }
    .lead-form-section.open .lead-form-section-body { display: block; }
    .lead-form-section-header .chevron { transition: transform 0.2s; font-size: 11px; color: var(--text-muted); }
    .lead-form-section.open .chevron { transform: rotate(180deg); }
    .lead-form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
    .lead-form-row .form-group { flex: 1; min-width: 200px; }
    .lead-form-row .form-group.small { flex: 0 0 160px; }
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
    .form-check-inline {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 13px; font-weight: 500;
        padding: 6px 12px;
        border: 1px solid var(--card-border);
        border-radius: var(--radius-sm);
        cursor: pointer;
    }
    .form-check-inline input { width: auto; margin: 0; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Leads Management</h1>
        <p class="page-header-sub">Kelola data leads dan calon pelanggan</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i>
            <span>Add Lead</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa fa-bullhorn me-2" style="color:var(--accent)"></i>Leads List</span>
        <span style="font-size:12px;color:var(--text-muted);font-weight:500;">{{ $leads->total() }} total data</span>
    </div>
    <div class="card-body-custom p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Name</th>
                        <th>Title</th>
                        <th>Account</th>
                        <th>Phone</th>
                        <th>Mobile</th>
                        <th>Status</th>
                        <th>Owner</th>
                        <th class="text-center" style="width:120px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leads as $lead)
                    <tr>
                        <td style="color:var(--text-muted);font-weight:500">{{ $loop->iteration + $leads->firstItem() - 1 }}</td>
                        <td>
                            <strong style="color:var(--text-primary);font-weight:600">
                                {{ $lead->accountContact?->full_name ?? '—' }}
                            </strong>
                        </td>
                        <td>{{ $lead->lead_title ?? '—' }}</td>
                        <td>{{ $lead->accountCompany?->account_name ?? '—' }}</td>
                        <td>{{ $lead->accountContact?->phone ?? '—' }}</td>
                        <td>{{ $lead->accountContact?->mobile ?? '—' }}</td>
                        <td>
                            @php $ls = $lead->lead_status; @endphp
                            <span class="status-badge {{ $ls === 'New' ? 'status-pending' : ($ls === 'Qualified' ? 'status-active' : ($ls === 'Unqualified' ? 'status-inactive' : '')) }}"
                                  style="{{ $ls === 'Contacted' ? 'background:var(--info-soft);color:#1e40af;' : '' }}">
                                @if($ls === 'Contacted') <i class="fa fa-phone" style="font-size:10px"></i> @endif
                                {{ $ls }}
                            </span>
                        </td>
                        <td style="color:var(--text-secondary);font-size:13px">{{ $lead->leadOwner?->username ?? '—' }}</td>
                        <td class="text-center">
                            <div style="display:flex;gap:5px;justify-content:center">
                                <a href="{{ route('leads-management.show', $lead->id) }}" class="btn-icon" title="Detail">
                                    <i class="fa fa-eye"></i>
                                </a>
                                @if($canUpdate)
                                <a href="{{ route('leads-management.edit', $lead->id) }}" class="btn-icon" title="Edit">
                                    <i class="fa fa-pen"></i>
                                </a>
                                @endif
                                @if($canDelete)
                                <button type="button" class="btn-icon danger btn-delete-lead" title="Hapus" data-id="{{ $lead->id }}">
                                    <i class="fa fa-trash-can"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <i class="fa fa-bullhorn"></i>
                                <p>Belum ada data leads.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($leads->hasPages())
    <div style="padding:14px 22px;border-top:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <span style="font-size:12.5px;color:var(--text-muted);font-weight:500">
            Menampilkan {{ $leads->firstItem() }}–{{ $leads->lastItem() }} dari {{ $leads->total() }}
        </span>
        <div>{{ $leads->links() }}</div>
    </div>
    @endif
</div>
@endsection

@push('modals')
<div class="modal fade modal-lead" id="leadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="leadModalTitle">Tambah Lead</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                <form id="lead-form" autocomplete="off">
                    <input type="hidden" id="lead-edit-id">

                    <div class="lead-form-section open">
                        <div class="lead-form-section-header" onclick="toggleLeadSection(this)">
                            <span><i class="fa fa-user me-2" style="color:var(--accent)"></i>Lead Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="lead-form-section-body">
                            <div class="lead-form-row">
                                <div class="form-group small">
                                    <label>Lead Status *</label>
                                    <select name="lead_status" id="lead-status">
                                        <option value="New">New</option>
                                        <option value="Contacted">Contacted</option>
                                        <option value="Qualified">Qualified</option>
                                        <option value="Unqualified">Unqualified</option>
                                    </select>
                                </div>
                                <div class="form-group small">
                                    <label>Salutation *</label>
                                    <select name="salutation" id="lead-salutation">
                                        <option value="">—</option>
                                        <option value="Bapak">Bapak</option>
                                        <option value="Ibu">Ibu</option>
                                        <option value="Saudara">Saudara</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Full Name *</label>
                                    <input type="text" name="full_name" id="lead-full-name" required>
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" name="email" id="lead-email">
                                </div>
                                <div class="form-group">
                                    <label>Mobile</label>
                                    <input type="text" name="mobile" id="lead-mobile">
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Job Title *</label>
                                    <select name="job_titles_id" id="lead-job-title">
                                        <option value="">— Pilih —</option>
                                        @foreach($jobTitles as $jt)
                                        <option value="{{ $jt->id }}">{{ $jt->title_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Department *</label>
                                    <select name="divisions_id" id="lead-division">
                                        <option value="">— Pilih —</option>
                                        @foreach($divisions as $div)
                                        <option value="{{ $div->id }}">{{ $div->division_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Lead Source</label>
                                    <select name="source_id" id="lead-source">
                                        <option value="">— Pilih —</option>
                                        @foreach($sources as $src)
                                        <option value="{{ $src->id }}">{{ $src->source_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Preferred Contact Method</label>
                                    <select name="contact_methods_id" id="lead-contact-method">
                                        <option value="">— Pilih —</option>
                                        @foreach($contactMethods as $cm)
                                        <option value="{{ $cm->id }}">{{ $cm->method_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Role in Project</label>
                                    <select name="role_in_projects_id" id="lead-role">
                                        <option value="">— Pilih —</option>
                                        @foreach($roleInProjects as $rp)
                                        <option value="{{ $rp->id }}">{{ $rp->role_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group" style="flex:0 0 180px;">
                                    <label>Close Date</label>
                                    <input type="date" name="closed_date" id="lead-close-date">
                                </div>
                                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:8px;">
                                    <label class="form-check-inline">
                                        <input type="checkbox" name="all_filed_completed" id="lead-all-complete" value="1">
                                        All Field Completed
                                    </label>
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Unqualified Reason</label>
                                    <textarea name="unqualified_reason" id="lead-unqualified" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lead-form-section">
                        <div class="lead-form-section-header" onclick="toggleLeadSection(this)">
                            <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Account Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="lead-form-section-body">
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Title *</label>
                                    <input type="text" name="lead_title" id="lead-title-acc">
                                </div>
                                <div class="form-group">
                                    <label>Company</label>
                                    <input type="text" name="company" id="lead-company">
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Segmentation *</label>
                                    <select name="segmentation_id" id="lead-segmentation">
                                        <option value="">— Pilih —</option>
                                        @foreach($segmentations as $seg)
                                        <option value="{{ $seg->id }}">{{ $seg->segmentation_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Account Type *</label>
                                    <select name="account_types_id" id="lead-account-type">
                                        <option value="">— Pilih —</option>
                                        @foreach($accountTypes as $at)
                                        <option value="{{ $at->id }}">{{ $at->type_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Business Entity</label>
                                    <select name="business_entities_id" id="lead-biz-entity">
                                        <option value="">— Pilih —</option>
                                        @foreach($businessEntities as $be)
                                        <option value="{{ $be->id }}">{{ $be->entity_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Business Value</label>
                                    <select name="business_values_id" id="lead-biz-value">
                                        <option value="">— Pilih —</option>
                                        @foreach($businessValues as $bv)
                                        <option value="{{ $bv->id }}">{{ $bv->value_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Interaction Level</label>
                                    <select name="interaction_levels_id" id="lead-interaction">
                                        <option value="">— Pilih —</option>
                                        @foreach($interactionLevels as $il)
                                        <option value="{{ $il->id }}">{{ $il->level_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>Address Street</label>
                                    <input type="text" name="address_street" id="lead-addr-street">
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group">
                                    <label>City</label>
                                    <input type="text" name="address_city" id="lead-addr-city">
                                </div>
                                <div class="form-group">
                                    <label>Province</label>
                                    <input type="text" name="address_province" id="lead-addr-province">
                                </div>
                                <div class="form-group small">
                                    <label>Zip</label>
                                    <input type="text" name="address_zip" id="lead-addr-zip">
                                </div>
                                <div class="form-group">
                                    <label>Country</label>
                                    <input type="text" name="address_country" id="lead-addr-country">
                                </div>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group small">
                                    <label>End User</label>
                                    <input type="number" name="end_user" id="lead-end-user" placeholder="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lead-form-section">
                        <div class="lead-form-section-header" onclick="toggleLeadSection(this)">
                            <span><i class="fa fa-info-circle me-2" style="color:var(--accent)"></i>Additional Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="lead-form-section-body">
                            <div class="lead-form-row">
                                <label class="form-check-inline">
                                    <input type="checkbox" name="lead_can_be_contacted" id="lead-can-contact" value="1">
                                    Lead Can Be Contacted
                                </label>
                                <label class="form-check-inline">
                                    <input type="checkbox" name="lead_appoinment" id="lead-appointment" value="1">
                                    Lead Appointment
                                </label>
                                <label class="form-check-inline">
                                    <input type="checkbox" name="identification" id="lead-identification" value="1">
                                    Need Identification
                                </label>
                            </div>
                            <div class="lead-form-row">
                                <div class="form-group small">
                                    <label>Follow Up Date *</label>
                                    <input type="date" name="lead_follow_up_date" id="lead-follow-up">
                                </div>
                                <div class="form-group">
                                    <label>Assign To</label>
                                    <select name="assigned_to" id="lead-assigned">
                                        <option value="">— Pilih —</option>
                                        @foreach($users as $u)
                                        <option value="{{ $u->id }}">{{ $u->username }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-lead">
                    <i class="fa fa-save me-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let leadModalInstance = null;

function toggleLeadSection(header) {
    header.closest('.lead-form-section').classList.toggle('open');
}

function resetLeadForm() {
    document.getElementById('lead-form').reset();
    document.getElementById('lead-edit-id').value = '';
    document.querySelectorAll('.lead-form-section').forEach(function(s) {
        s.classList.remove('open');
    });
    document.querySelector('.lead-form-section').classList.add('open');
}

function openCreateModal() {
    resetLeadForm();
    document.getElementById('leadModalTitle').textContent = 'Add Lead';
    if (!leadModalInstance) {
        leadModalInstance = new bootstrap.Modal(document.getElementById('leadModal'));
    }
    leadModalInstance.show();
}

$('#btn-save-lead').on('click', function() {
    const $btn = $(this);
    const editId = $('#lead-edit-id').val();
    const isEdit = !!editId;

    const validations = [
        { field: '#lead-status', label: 'Lead Status' },
        { field: '#lead-salutation', label: 'Salutation' },
        { field: '#lead-full-name', label: 'Full Name' },
        { field: '#lead-email', label: 'Email' },
        { field: '#lead-job-title', label: 'Job Title' },
        { field: '#lead-division', label: 'Department' },
        { field: '#lead-title-acc', label: 'Title' },
        { field: '#lead-segmentation', label: 'Segmentation' },
        { field: '#lead-account-type', label: 'Account Type' },
        { field: '#lead-follow-up', label: 'Follow Up Date' },
    ];

    for (let i = 0; i < validations.length; i++) {
        const v = validations[i];
        const $el = $(v.field);
        const val = $el.val() ? $el.val().trim() : '';
        if (!val) {
            toastr.error(v.label + ' wajib diisi.');
            $el.focus();
            return;
        }
    }

    const formData = new FormData(document.getElementById('lead-form'));
    formData.set('all_filed_completed', $('#lead-all-complete').is(':checked') ? '1' : '0');
    formData.set('lead_can_be_contacted', $('#lead-can-contact').is(':checked') ? '1' : '0');
    formData.set('lead_appoinment', $('#lead-appointment').is(':checked') ? '1' : '0');
    formData.set('identification', $('#lead-identification').is(':checked') ? '1' : '0');

    const url = isEdit
        ? '{{ route("leads-management.update", "__ID__") }}'.replace('__ID__', editId)
        : '{{ route("leads-management.store") }}';

    formData.append('_token', '{{ csrf_token() }}');
    if (isEdit) formData.append('_method', 'PUT');

    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...');

    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            toastr.success(res.message);
            if (leadModalInstance) leadModalInstance.hide();
            setTimeout(function() { location.reload(); }, 500);
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan');
            var errors = xhr.responseJSON?.errors;
            if (errors) {
                var first = Object.values(errors)[0];
                toastr.error(Array.isArray(first) ? first[0] : first);
            } else {
                toastr.error(xhr.responseJSON?.message || 'Gagal menyimpan data.');
            }
        }
    });
});

$(document).on('click', '.btn-delete-lead', function() {
    const id = $(this).data('id');
    Swal.fire({
        title: 'Yakin?',
        text: 'Data lead akan dihapus beserta kontak dan perusahaan terkait.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route("leads-management.destroy", "__ID__") }}'.replace('__ID__', id),
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    toastr.success(res.message);
                    setTimeout(function() { location.reload(); }, 500);
                },
                error: function() {
                    toastr.error('Gagal menghapus data.');
                }
            });
        }
    });
});
</script>
@endsection
