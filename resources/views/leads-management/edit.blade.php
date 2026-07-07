@extends('layouts.app')

@section('title', 'Edit Lead')
@section('page-title', 'Edit Lead')

@section('styles')
<style>
    .lead-edit-section { border: 1px solid var(--card-border); border-radius: var(--radius); margin-bottom: 16px; overflow: hidden; }
    .lead-edit-section-header { padding: 12px 18px; background: #f8fafc; border-bottom: 1px solid var(--card-border); font-weight: 700; font-size: 13.5px; }
    .lead-edit-section-body { padding: 18px; }
    .lead-edit-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
    .lead-edit-row .field { flex: 1; min-width: 200px; }
    .lead-edit-row .field.small { flex: 0 0 160px; }
    .field label { display: block; font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
    .field input, .field select, .field textarea { width: 100%; padding: 8px 12px; border: 1px solid var(--card-border); border-radius: var(--radius-sm); font-size: 13px; font-family: inherit; color: var(--text-primary); }
    .field input:focus, .field select:focus, .field textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); outline: none; }
    .field-check { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; padding: 6px 12px; border: 1px solid var(--card-border); border-radius: var(--radius-sm); }
    .field-check input { width: auto; margin: 0; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Edit Lead</h1>
        <p class="page-header-sub">Perbarui data lead #{{ $lead->id }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('leads-management.index') }}" class="btn-ghost">
            <i class="fa fa-arrow-left"></i><span>Kembali</span>
        </a>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-body-custom">
        <form action="{{ route('leads-management.update', $lead->id) }}" method="POST">
            @csrf @method('PUT')

            <div class="lead-edit-section">
                <div class="lead-edit-section-header"><i class="fa fa-user me-2" style="color:var(--accent)"></i>Lead Information</div>
                <div class="lead-edit-section-body">
                    <div class="lead-edit-row">
                        <div class="field small">
                            <label>Lead Status</label>
                            <select name="lead_status">
                                @foreach(['New','Approach','Qualified','Unqualified'] as $s)
                                <option value="{{ $s }}" {{ $lead->lead_status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field small">
                            <label>Salutation</label>
                            <select name="salutation">
                                <option value="">—</option>
                                @foreach(['Bapak','Ibu','Saudara'] as $s)
                                <option value="{{ $s }}" {{ $lead->accountContact?->salutation === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" value="{{ $lead->accountContact?->full_name }}" required>
                        </div>
                    </div>
                    <div class="lead-edit-row">
                        <div class="field">
                            <label>Email</label>
                            <input type="email" name="email" value="{{ $lead->accountContact?->email }}">
                        </div>
                        <div class="field">
                            <label>Mobile</label>
                            <input type="text" name="mobile" value="{{ $lead->accountContact?->mobile }}">
                        </div>
                    </div>
                    <div class="lead-edit-row">
                        <div class="field">
                            <label>Job Title</label>
                            <select name="job_titles_id">
                                <option value="">— Pilih —</option>
                                @foreach($jobTitles as $jt)
                                <option value="{{ $jt->id }}" {{ $lead->accountContact?->job_titles_id == $jt->id ? 'selected' : '' }}>{{ $jt->title_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Department</label>
                            <select name="divisions_id">
                                <option value="">— Pilih —</option>
                                @foreach($divisions as $div)
                                <option value="{{ $div->id }}" {{ $lead->accountContact?->divisions_id == $div->id ? 'selected' : '' }}>{{ $div->division_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="lead-edit-row">
                        <div class="field">
                            <label>Lead Source</label>
                            <select name="source_id">
                                <option value="">— Pilih —</option>
                                @foreach($sources as $src)
                                <option value="{{ $src->id }}" {{ $lead->source_id == $src->id ? 'selected' : '' }}>{{ $src->source_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Preferred Contact Method</label>
                            <select name="contact_methods_id">
                                <option value="">— Pilih —</option>
                                @foreach($contactMethods as $cm)
                                <option value="{{ $cm->id }}" {{ $lead->accountContact?->contact_methods_id == $cm->id ? 'selected' : '' }}>{{ $cm->method_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Role in Project</label>
                            <select name="role_in_projects_id">
                                <option value="">— Pilih —</option>
                                @foreach($roleInProjects as $rp)
                                <option value="{{ $rp->id }}" {{ $lead->accountContact?->role_in_projects_id == $rp->id ? 'selected' : '' }}>{{ $rp->role_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="lead-edit-row">
                        <div class="field small">
                            <label>Close Date</label>
                            <input type="date" name="closed_date" value="{{ $lead->closed_date?->format('Y-m-d') }}">
                        </div>
                        <div class="field" style="display:flex;align-items:flex-end;padding-bottom:8px;">
                            <label class="field-check">
                                <input type="checkbox" name="all_filed_completed" value="1" {{ $lead->all_filed_completed ? 'checked' : '' }}>
                                All Field Completed
                            </label>
                        </div>
                    </div>
                    <div class="lead-edit-row">
                        <div class="field">
                            <label>Unqualified Reason</label>
                            <textarea name="unqualified_reason" rows="2">{{ $lead->unqualified_reason }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lead-edit-section">
                <div class="lead-edit-section-header"><i class="fa fa-building me-2" style="color:var(--accent)"></i>Account Information</div>
                <div class="lead-edit-section-body">
                    <div class="lead-edit-row">
                        <div class="field">
                            <label>Title</label>
                            <input type="text" name="lead_title" value="{{ $lead->lead_title }}">
                        </div>
                        <div class="field">
                            <label>Company</label>
                            <input type="text" name="company" value="{{ $lead->accountCompany?->account_name }}">
                        </div>
                    </div>
                    <div class="lead-edit-row">
                        <div class="field">
                            <label>Segmentation</label>
                            <select name="segmentation_id">
                                <option value="">— Pilih —</option>
                                @foreach($segmentations as $s)
                                <option value="{{ $s->id }}" {{ $lead->accountCompany?->segmentation_id == $s->id ? 'selected' : '' }}>{{ $s->segmentation_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Account Type</label>
                            <select name="account_types_id">
                                <option value="">— Pilih —</option>
                                @foreach($accountTypes as $at)
                                <option value="{{ $at->id }}" {{ $lead->accountCompany?->account_types_id == $at->id ? 'selected' : '' }}>{{ $at->type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="lead-edit-row">
                        <div class="field">
                            <label>Business Entity</label>
                            <select name="business_entities_id">
                                <option value="">— Pilih —</option>
                                @foreach($businessEntities as $be)
                                <option value="{{ $be->id }}" {{ $lead->accountCompany?->business_entities_id == $be->id ? 'selected' : '' }}>{{ $be->entity_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Business Value</label>
                            <select name="business_values_id">
                                <option value="">— Pilih —</option>
                                @foreach($businessValues as $bv)
                                <option value="{{ $bv->id }}" {{ $lead->accountCompany?->business_values_id == $bv->id ? 'selected' : '' }}>{{ $bv->value_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Interaction Level</label>
                            <select name="interaction_levels_id">
                                <option value="">— Pilih —</option>
                                @foreach($interactionLevels as $il)
                                <option value="{{ $il->id }}" {{ $lead->accountCompany?->interaction_levels_id == $il->id ? 'selected' : '' }}>{{ $il->level_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="lead-edit-row">
                        <div class="field">
                            <label>Address Street</label>
                            <input type="text" name="address_street" value="{{ $lead->accountCompany?->address_billing_street }}">
                        </div>
                    </div>
                    <div class="lead-edit-row">
                        <div class="field">
                            <label>City</label>
                            <input type="text" name="address_city" value="{{ $lead->accountCompany?->address_billing_city }}">
                        </div>
                        <div class="field">
                            <label>Province</label>
                            <input type="text" name="address_province" value="{{ $lead->accountCompany?->address_billing_province }}">
                        </div>
                        <div class="field small">
                            <label>Zip</label>
                            <input type="text" name="address_zip" value="{{ $lead->accountCompany?->address_billing_postal_code }}">
                        </div>
                        <div class="field">
                            <label>Country</label>
                            <input type="text" name="address_country" value="{{ $lead->accountCompany?->address_billing_country }}">
                        </div>
                    </div>
                    <div class="lead-edit-row">
                        <div class="field small">
                            <label>End User</label>
                            <input type="number" name="end_user" value="{{ $lead->accountCompany?->end_user }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="lead-edit-section">
                <div class="lead-edit-section-header"><i class="fa fa-info-circle me-2" style="color:var(--accent)"></i>Additional Information</div>
                <div class="lead-edit-section-body">
                    <div class="lead-edit-row">
                        <label class="field-check">
                            <input type="checkbox" name="lead_can_be_contacted" value="1" {{ $lead->lead_can_be_contacted ? 'checked' : '' }}>
                            Lead Can Be Contacted
                        </label>
                        <label class="field-check">
                            <input type="checkbox" name="lead_appoinment" value="1" {{ $lead->lead_appoinment ? 'checked' : '' }}>
                            Lead Appointment
                        </label>
                        <label class="field-check">
                            <input type="checkbox" name="identification" value="1" {{ $lead->identification ? 'checked' : '' }}>
                            Need Identification
                        </label>
                    </div>
                    <div class="lead-edit-row">
                        <div class="field small">
                            <label>Follow Up Date</label>
                            <input type="date" name="lead_follow_up_date" value="{{ $lead->lead_follow_up_date?->format('Y-m-d') }}">
                        </div>
                        <div class="field">
                            <label>Assign To</label>
                            <select name="assigned_to">
                                <option value="">— Pilih —</option>
                                @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ $lead->assigned_to == $u->id ? 'selected' : '' }}>{{ $u->username }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-accent mt-3">
                <i class="fa fa-save me-1"></i> Update Lead
            </button>
        </form>
    </div>
</div>
@endsection
