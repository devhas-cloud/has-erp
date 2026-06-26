<?php

namespace App\Http\Controllers;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\AccountType;
use App\Models\BusinessEntity;
use App\Models\BusinessValue;
use App\Models\ContactMethod;
use App\Models\Division;
use App\Models\InteractionLevel;
use App\Models\JobTitle;
use App\Models\Lead;
use App\Models\RoleInProject;
use App\Models\Segmentation;
use App\Models\Source;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeadsManagementController extends Controller
{
    public function index()
    {
        $leads = Lead::with(['accountContact', 'accountCompany', 'leadOwner', 'source'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        $jobTitles = JobTitle::where('status', 'Active')->get();
        $divisions = Division::where('status', 'Active')->get();
        $sources = Source::where('status', 'Active')->get();
        $contactMethods = ContactMethod::where('status', 'Active')->get();
        $roleInProjects = RoleInProject::where('status', 'Active')->get();
        $segmentations = Segmentation::where('status', 'Active')->get();
        $accountTypes = AccountType::where('status', 'Active')->get();
        $businessEntities = BusinessEntity::where('status', 'Active')->get();
        $businessValues = BusinessValue::where('status', 'Active')->get();
        $interactionLevels = InteractionLevel::where('status', 'Active')->get();
        $users = User::all();

        return view('leads-management.index', compact(
            'leads', 'jobTitles', 'divisions', 'sources', 'contactMethods',
            'roleInProjects', 'segmentations', 'accountTypes', 'businessEntities',
            'businessValues', 'interactionLevels', 'users'
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lead_status' => 'required|in:New,Contacted,Qualified,Unqualified',
            'salutation' => 'required|in:Ibu,Bapak,Saudara',
            'full_name' => 'required|string|max:150',
            'email' => 'required|email|max:100',
            'mobile' => 'nullable|string|max:30',
            'job_titles_id' => 'required|exists:job_titles,id',
            'divisions_id' => 'required|exists:divisions,id',
            'source_id' => 'nullable|exists:sources,id',
            'contact_methods_id' => 'nullable|exists:contact_methods,id',
            'role_in_projects_id' => 'nullable|exists:role_in_projects,id',
            'unqualified_reason' => 'nullable|string',
            'closed_date' => 'nullable|date',
            'all_filed_completed' => 'boolean',
            'lead_title' => 'required|string|max:500',
            'company' => 'nullable|string|max:150',
            'segmentation_id' => 'required|exists:segmentations,id',
            'account_types_id' => 'required|exists:account_types,id',
            'business_entities_id' => 'nullable|exists:business_entities,id',
            'business_values_id' => 'nullable|exists:business_values,id',
            'interaction_levels_id' => 'nullable|exists:interaction_levels,id',
            'address_street' => 'nullable|string',
            'address_city' => 'nullable|string|max:100',
            'address_province' => 'nullable|string|max:100',
            'address_zip' => 'nullable|string|max:10',
            'address_country' => 'nullable|string|max:100',
            'end_user' => 'nullable|integer',
            'lead_can_be_contacted' => 'boolean',
            'lead_follow_up_date' => 'required|date',
            'lead_appoinment' => 'boolean',
            'identification' => 'boolean',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $company = AccountCompany::create([
                'account_name' => $request->company ?: ($request->full_name.' - Company'),
                'segmentation_id' => $request->segmentation_id,
                'account_types_id' => $request->account_types_id,
                'business_entities_id' => $request->business_entities_id,
                'business_values_id' => $request->business_values_id,
                'interaction_levels_id' => $request->interaction_levels_id,
                'address_billing_street' => $request->address_street,
                'address_billing_city' => $request->address_city,
                'address_billing_province' => $request->address_province,
                'address_billing_postal_code' => $request->address_zip,
                'address_billing_country' => $request->address_country,
                'end_user' => $request->end_user,
                'phone' => $request->mobile,
                'account_owner_id' => Auth::id(),
                'status' => 'Active',
            ]);

            $contact = AccountContact::create([
                'account_companies_id' => $company->id,
                'full_name' => $request->full_name,
                'salutation' => $request->salutation,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'job_titles_id' => $request->job_titles_id,
                'divisions_id' => $request->divisions_id,
                'contact_methods_id' => $request->contact_methods_id,
                'role_in_projects_id' => $request->role_in_projects_id,
                'contact_owner_id' => Auth::id(),
                'status' => 'Active',
            ]);

            Lead::create([
                'lead_status' => $request->lead_status,
                'lead_title' => $request->lead_title,
                'account_companies_id' => $company->id,
                'account_contacts_id' => $contact->id,
                'source_id' => $request->source_id,
                'unqualified_reason' => $request->unqualified_reason,
                'closed_date' => $request->closed_date,
                'all_filed_completed' => $request->has('all_filed_completed'),
                'lead_owner_id' => Auth::id(),
                'assigned_to' => $request->assigned_to,
                'lead_can_be_contacted' => $request->has('lead_can_be_contacted'),
                'lead_follow_up_date' => $request->lead_follow_up_date,
                'lead_appoinment' => $request->has('lead_appoinment'),
                'identification' => $request->has('identification'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lead berhasil ditambahkan.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan lead: '.$e->getMessage(),
            ], 500);
        }
    }

    public function edit($id)
    {
        $lead = Lead::with(['accountContact', 'accountCompany'])->findOrFail($id);

        $jobTitles = JobTitle::where('status', 'Active')->get();
        $divisions = Division::where('status', 'Active')->get();
        $sources = Source::where('status', 'Active')->get();
        $contactMethods = ContactMethod::where('status', 'Active')->get();
        $roleInProjects = RoleInProject::where('status', 'Active')->get();
        $segmentations = Segmentation::where('status', 'Active')->get();
        $accountTypes = AccountType::where('status', 'Active')->get();
        $businessEntities = BusinessEntity::where('status', 'Active')->get();
        $businessValues = BusinessValue::where('status', 'Active')->get();
        $interactionLevels = InteractionLevel::where('status', 'Active')->get();
        $users = User::all();

        return view('leads-management.edit', compact(
            'lead', 'jobTitles', 'divisions', 'sources', 'contactMethods',
            'roleInProjects', 'segmentations', 'accountTypes', 'businessEntities',
            'businessValues', 'interactionLevels', 'users'
        ));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);

        $validated = $request->validate([
            'lead_status' => 'required|in:New,Contacted,Qualified,Unqualified',
            'salutation' => 'required|in:Ibu,Bapak,Saudara',
            'full_name' => 'required|string|max:150',
            'email' => 'required|email|max:100',
            'mobile' => 'nullable|string|max:30',
            'job_titles_id' => 'required|exists:job_titles,id',
            'divisions_id' => 'required|exists:divisions,id',
            'source_id' => 'nullable|exists:sources,id',
            'contact_methods_id' => 'nullable|exists:contact_methods,id',
            'role_in_projects_id' => 'nullable|exists:role_in_projects,id',
            'unqualified_reason' => 'nullable|string',
            'closed_date' => 'nullable|date',
            'all_filed_completed' => 'boolean',
            'lead_title' => 'required|string|max:500',
            'company' => 'nullable|string|max:150',
            'segmentation_id' => 'required|exists:segmentations,id',
            'account_types_id' => 'required|exists:account_types,id',
            'business_entities_id' => 'nullable|exists:business_entities,id',
            'business_values_id' => 'nullable|exists:business_values,id',
            'interaction_levels_id' => 'nullable|exists:interaction_levels,id',
            'address_street' => 'nullable|string',
            'address_city' => 'nullable|string|max:100',
            'address_province' => 'nullable|string|max:100',
            'address_zip' => 'nullable|string|max:10',
            'address_country' => 'nullable|string|max:100',
            'end_user' => 'nullable|integer',
            'lead_can_be_contacted' => 'boolean',
            'lead_follow_up_date' => 'required|date',
            'lead_appoinment' => 'boolean',
            'identification' => 'boolean',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $lead->accountCompany->update([
                'account_name' => $request->company ?: $lead->accountCompany->account_name,
                'segmentation_id' => $request->segmentation_id,
                'account_types_id' => $request->account_types_id,
                'business_entities_id' => $request->business_entities_id,
                'business_values_id' => $request->business_values_id,
                'interaction_levels_id' => $request->interaction_levels_id,
                'address_billing_street' => $request->address_street,
                'address_billing_city' => $request->address_city,
                'address_billing_province' => $request->address_province,
                'address_billing_postal_code' => $request->address_zip,
                'address_billing_country' => $request->address_country,
                'end_user' => $request->end_user,
                'phone' => $request->mobile,
            ]);

            $lead->accountContact->update([
                'full_name' => $request->full_name,
                'salutation' => $request->salutation,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'job_titles_id' => $request->job_titles_id,
                'divisions_id' => $request->divisions_id,
                'contact_methods_id' => $request->contact_methods_id,
                'role_in_projects_id' => $request->role_in_projects_id,
            ]);

            $lead->update([
                'lead_status' => $request->lead_status,
                'lead_title' => $request->lead_title,
                'source_id' => $request->source_id,
                'unqualified_reason' => $request->unqualified_reason,
                'closed_date' => $request->closed_date,
                'all_filed_completed' => $request->has('all_filed_completed'),
                'assigned_to' => $request->assigned_to,
                'lead_can_be_contacted' => $request->has('lead_can_be_contacted'),
                'lead_follow_up_date' => $request->lead_follow_up_date,
                'lead_appoinment' => $request->has('lead_appoinment'),
                'identification' => $request->has('identification'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lead berhasil diupdate.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate lead: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $lead = Lead::with([
            'accountContact.jobTitle',
            'accountContact.division',
            'accountContact.contactMethod',
            'accountContact.roleInProject',
            'accountCompany.segmentation',
            'accountCompany.accountType',
            'accountCompany.businessEntity',
            'accountCompany.businessValue',
            'accountCompany.interactionLevel',
            'leadOwner',
            'assignedTo',
            'source',
        ])->findOrFail($id);

        return view('leads-management.show', compact('lead'));
    }

    public function destroy($id): JsonResponse
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead berhasil dihapus.',
        ]);
    }
}
