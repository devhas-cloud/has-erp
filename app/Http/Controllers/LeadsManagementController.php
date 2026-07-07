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
use App\Models\TypesAccountsCompany;
use App\Models\User;
use App\Services\LeadImportService;
use App\Services\XlsxTemplateGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeadsManagementController extends Controller
{
    public function index()
    {
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
        $accountCompanies = AccountCompany::where('status', 'Active')->orderBy('account_name')->get();
        $typesAccountsCompanies = TypesAccountsCompany::where('status', 'Active')->get();

        return view('leads-management.index', compact(
            'jobTitles', 'divisions', 'sources', 'contactMethods',
            'roleInProjects', 'segmentations', 'accountTypes', 'businessEntities',
            'businessValues', 'interactionLevels', 'users', 'accountCompanies',
            'typesAccountsCompanies'
        ));
    }

    public function data(Request $request): JsonResponse
    {
        $query = Lead::with(['accountContact', 'accountCompany', 'leadOwner']);

        $recordsTotal = Lead::count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('lead_title', 'like', "%{$searchValue}%")
                    ->orWhere('lead_status', 'like', "%{$searchValue}%")
                    ->orWhereHas('accountContact', function ($q) use ($searchValue) {
                        $q->where('full_name', 'like', "%{$searchValue}%")
                            ->orWhere('phone', 'like', "%{$searchValue}%")
                            ->orWhere('mobile', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('accountCompany', function ($q) use ($searchValue) {
                        $q->where('account_name', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('leadOwner', function ($q) use ($searchValue) {
                        $q->where('username', 'like', "%{$searchValue}%");
                    });
            });
        }

        $recordsFiltered = $query->count();

        $orderColumnIndex = $request->input('order.0.column', 1);
        $orderDirection = $request->input('order.0.dir', 'desc');

        $columnOrderMap = [
            2 => 'lead_title',
            6 => 'lead_status',
        ];

        if (isset($columnOrderMap[$orderColumnIndex])) {
            $query->orderBy($columnOrderMap[$orderColumnIndex], $orderDirection);
        }
        $query->orderBy('id', $orderDirection === 'desc' ? 'desc' : 'asc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $leads = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($leads as $i => $lead) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $lead->id,
                'full_name' => $lead->accountContact?->full_name ?? '—',
                'initials' => strtoupper(substr($lead->accountContact?->full_name ?? '?', 0, 2)),
                'icon' => $lead->accountContact?->icon,
                'name_display' => $lead->accountContact?->full_name ?? '—',
                'lead_title' => $lead->lead_title ?? '—',
                'account_name' => $lead->accountCompany?->account_name ?? '—',
                'company_initials' => strtoupper(substr($lead->accountCompany?->account_name ?? '?', 0, 2)),
                'company_icon' => $lead->accountCompany?->icon,
                'company_name_display' => $lead->accountCompany?->account_name ?? '—',
                'phone' => $lead->accountContact?->phone ?? '—',
                'mobile' => $lead->accountContact?->mobile ?? '—',
                'status_badge' => $this->renderStatusBadge($lead->lead_status),
                'owner_name' => $lead->leadOwner?->username ?? '—',
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function renderStatusBadge(string $status): string
    {
        $badgeClass = match ($status) {
            'New' => 'status-pending',
            'Qualified' => 'status-active',
            'Unqualified' => 'status-inactive',
            default => '',
        };

        $style = $status === 'Contacted' ? 'background:var(--info-soft);color:#1e40af;' : '';
        $icon = $status === 'Contacted' ? '<i class="fa fa-phone" style="font-size:10px"></i> ' : '';

        return sprintf(
            '<span class="status-badge %s" style="%s">%s%s</span>',
            $badgeClass,
            $style,
            $icon,
            $status
        );
    }

    public function searchCompanies(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        $companies = AccountCompany::where('account_name', 'like', "%{$q}%")
            ->limit(20)
            ->get();

        $data = $companies->map(fn ($c) => [
            'id' => $c->id,
            'text' => $c->account_name,
            'segmentation_id' => $c->segmentation_id,
            'account_types_id' => $c->account_types_id,
            'types_accounts_companies_id' => $c->types_accounts_companies_id,
            'business_entities_id' => $c->business_entities_id,
            'business_values_id' => $c->business_values_id,
            'interaction_levels_id' => $c->interaction_levels_id,
            'address_billing_street' => $c->address_billing_street,
            'address_billing_city' => $c->address_billing_city,
            'address_billing_province' => $c->address_billing_province,
            'address_billing_postal_code' => $c->address_billing_postal_code,
            'address_billing_country' => $c->address_billing_country,
        ]);

        return response()->json(['results' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lead_status' => 'required|in:New,Approach,Qualified,Unqualified',
            'salutation' => 'required|in:Ibu,Bapak',
            'full_name' => 'required|string|max:150',
            'email' => 'required|email|max:100|unique:account_contacts,email',
            'mobile' => 'nullable|string|max:30',
            'phone' => 'nullable|string|max:30',
            'job_titles_id' => 'required|exists:job_titles,id',
            'divisions_id' => 'required|exists:divisions,id',
            'source_id' => 'required|exists:sources,id',
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
            'types_accounts_companies_id' => 'nullable|exists:types_accounts_companies,id',
            'account_companies_id' => 'nullable|exists:account_companies,id',
            'lead_can_be_contacted' => 'boolean',
            'lead_follow_up_date' => 'required|date',
            'lead_appoinment' => 'boolean',
            'identification' => 'boolean',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            if ($request->filled('account_companies_id')) {
                $company = AccountCompany::findOrFail($request->account_companies_id);
            } else {
                $company = AccountCompany::create([
                    'account_name' => $request->company ?: ($request->full_name.' - Company'),
                    'segmentation_id' => $request->segmentation_id,
                    'account_types_id' => $request->account_types_id,
                    'types_accounts_companies_id' => $request->types_accounts_companies_id,
                    'business_entities_id' => $request->business_entities_id,
                    'business_values_id' => $request->business_values_id,
                    'interaction_levels_id' => $request->interaction_levels_id,
                    'address_billing_street' => $request->address_street,
                    'address_billing_city' => $request->address_city,
                    'address_billing_province' => $request->address_province,
                    'address_billing_postal_code' => $request->address_zip,
                    'address_billing_country' => $request->address_country,
                    'end_user' => $request->end_user,
                    'phone' => $request->phone,
                    'account_owner_id' => Auth::id(),
                    'status' => 'Active',
                ]);
            }

            $contact = AccountContact::create([
                'account_companies_id' => $company->id,
                'full_name' => $request->full_name,
                'salutation' => $request->salutation,
                'email' => $request->email,
                'phone' => $request->phone,
                'mobile' => $request->mobile,
                'job_titles_id' => $request->job_titles_id,
                'divisions_id' => $request->divisions_id,
                'contact_methods_id' => $request->contact_methods_id,
                'role_in_projects_id' => $request->role_in_projects_id,
                'contact_owner_id' => Auth::id(),
                'lead_status' => $request->lead_status,
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
                'all_filed_completed' => $request->input('all_filed_completed') === '1',
                'lead_owner_id' => Auth::id(),
                'assigned_to' => $request->assigned_to,
                'lead_can_be_contacted' => $request->input('lead_can_be_contacted') === '1',
                'lead_follow_up_date' => $request->lead_follow_up_date,
                'lead_appoinment' => $request->input('lead_appoinment') === '1',
                'identification' => $request->input('identification') === '1',

            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lead successfully added.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to add lead: '.$e->getMessage(),
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

    public function fetch($id): JsonResponse
    {
        $lead = Lead::with(['accountContact', 'accountCompany'])->findOrFail($id);

        return response()->json([
            'lead' => $lead,
            'contact' => $lead->accountContact,
            'company' => $lead->accountCompany,
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);

        $validated = $request->validate([
            'lead_status' => 'required|in:New,Approach,Qualified,Unqualified',
            'salutation' => 'required|in:Ibu,Bapak',
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
            'account_companies_id' => 'nullable|exists:account_companies,id',
            'lead_can_be_contacted' => 'boolean',
            'lead_follow_up_date' => 'required|date',
            'lead_appoinment' => 'boolean',
            'identification' => 'boolean',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            if ($request->filled('account_companies_id')) {
                $company = AccountCompany::findOrFail($request->account_companies_id);
                $lead->accountCompany()->associate($company);
                $lead->save();
            } else {
                $lead->accountCompany->update([
                    'account_name' => $request->company ?: $lead->accountCompany->account_name,
                    'segmentation_id' => $request->segmentation_id,
                    'account_types_id' => $request->account_types_id,
                    'types_accounts_companies_id' => $request->types_accounts_companies_id,
                    'business_entities_id' => $request->business_entities_id,
                    'business_values_id' => $request->business_values_id,
                    'interaction_levels_id' => $request->interaction_levels_id,
                    'address_billing_street' => $request->address_street,
                    'address_billing_city' => $request->address_city,
                    'address_billing_province' => $request->address_province,
                    'address_billing_postal_code' => $request->address_zip,
                    'address_billing_country' => $request->address_country,
                    'end_user' => $request->end_user,
                    'phone' => $request->phone,
                ]);
            }

            $lead->accountContact->update([
                'full_name' => $request->full_name,
                'salutation' => $request->salutation,
                'email' => $request->email,
                'phone' => $request->phone,
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
                'all_filed_completed' => $request->input('all_filed_completed') === '1',
                'assigned_to' => $request->assigned_to,
                'lead_can_be_contacted' => $request->input('lead_can_be_contacted') === '1',
                'lead_follow_up_date' => $request->lead_follow_up_date,
                'lead_appoinment' => $request->input('lead_appoinment') === '1',
                'identification' => $request->input('identification') === '1',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lead successfully updated.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update lead: '.$e->getMessage(),
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
            'accountCompany.typesAccountsCompany',
            'leadOwner',
            'assignedTo',
            'source',
        ])->findOrFail($id);

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
        $accountCompanies = AccountCompany::where('status', 'Active')->orderBy('account_name')->get();
        $typesAccountsCompanies = TypesAccountsCompany::where('status', 'Active')->get();

        return view('leads-management.show', compact(
            'lead', 'jobTitles', 'divisions', 'sources', 'contactMethods',
            'roleInProjects', 'segmentations', 'accountTypes', 'businessEntities',
            'businessValues', 'interactionLevels', 'users', 'accountCompanies',
            'typesAccountsCompanies'
        ));
    }

    public function destroy($id): JsonResponse
    {
        $lead = Lead::with('accountContact')->findOrFail($id);

        DB::beginTransaction();
        try {
            $lead->accountContact?->delete();
            $lead->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lead and contact successfully deleted.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete lead.',
            ], 500);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $path = $request->file('file')->storeAs('imports', 'leads_'.time().'.csv');

        try {
            $service = new LeadImportService;
            $result = $service->import(
                storage_path('app/private/'.$path),
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => "Import selesai: {$result['success']} berhasil, {$result['failed']} gagal dari ".($result['success'] + $result['failed']).' data.',
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal import: '.$e->getMessage(),
            ], 500);
        } finally {
            Storage::delete($path);
        }
    }

    public function downloadTemplate()
    {
        $path = storage_path('app/private/templates/lead_import_template.xlsx');

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $references = LeadImportService::getReferenceData();

        $generator = new XlsxTemplateGenerator;
        $generator->generate($references, $path);

        return response()->download($path, 'Lead_Import_Template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
