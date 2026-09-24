<?php

namespace App\Http\Controllers;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\ContactMethod;
use App\Models\Division;
use App\Models\Forecast;
use App\Models\JobTitle;
use App\Models\Lead;
use App\Models\Log;
use App\Models\Opportunity;
use App\Models\RoleInProject;
use App\Models\Source;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ContactManagementController extends Controller
{
    public function index()
    {
        $accountCompanies = AccountCompany::where('status', 'Active')->orderBy('account_name')->get();
        $jobTitles = JobTitle::where('status', 'Active')->get();
        $sources = Source::where('status', 'Active')->get();
        $divisions = Division::where('type','External')->where('status', 'Active')->get();
        $contactMethods = ContactMethod::where('status', 'Active')->get();
        $roleInProjects = RoleInProject::where('status', 'Active')->get();

        // Pilihan "Assigned To" hanya user divisi Sales — kontak pada
        // akhirnya harus dikerjakan oleh sales, siapa pun yang membuatnya.
        $assignableUsers = User::whereHas('division', fn ($q) => $q->whereRaw('LOWER(division_name) = ?', ['sales']))
            ->orderBy('username')
            ->get();

        return view('contacts-management.index', compact(
            'accountCompanies', 'jobTitles', 'sources', 'divisions',
            'contactMethods', 'roleInProjects', 'assignableUsers'
        ));
    }

    public function data(Request $request): JsonResponse
    {

        $query = AccountContact::with([
            'accountCompany',
            'contactOwner',
            'assignedTo',
            'jobTitle'
        ]);

        // Jika divisi sales dan bukan manager, filter hanya kontak yang dimiliki oleh user saat ini
        if (strtolower(Auth::user()->division?->division_name) === 'sales' && Auth::user()->taskRole?->role_name !== 'Manager') {
            $query->where('assigned_to_id', Auth::id());
        }
        $recordsTotal = AccountContact::count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('full_name', 'like', "%{$searchValue}%")
                    ->orWhere('email', 'like', "%{$searchValue}%")
                    ->orWhere('phone', 'like', "%{$searchValue}%")
                    ->orWhere('mobile', 'like', "%{$searchValue}%")
                    ->orWhereHas('accountCompany', function ($q) use ($searchValue) {
                        $q->where('account_name', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('contactOwner', function ($q) use ($searchValue) {
                        $q->where('username', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('jobTitle', function ($q) use ($searchValue) {
                        $q->where('title_name', 'like', "%{$searchValue}%");
                    });
            });
        }

        $query->where('status', 'Active');
        $recordsFiltered = $query->count();

        $orderColumnIndex = $request->input('order.0.column', 1);
        $orderDirection = $request->input('order.0.dir', 'asc');

        $columnOrderMap = [
            1 => 'full_name',
            4 => 'phone',
            5 => 'email',
        ];

        if (isset($columnOrderMap[$orderColumnIndex])) {
            $query->orderBy($columnOrderMap[$orderColumnIndex], $orderDirection);
        }
        $query->orderBy('id', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $contacts = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($contacts as $i => $contact) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $contact->id,
                'full_name' => $contact->full_name ?? '—',
                'initials' => strtoupper(substr($contact->full_name ?? '?', 0, 2)),
                'icon' => $contact->icon,
                'name_display' => $contact->full_name ?? '—',
                'title' => $contact->jobTitle?->title_name ?? '—',
                'account_name' => $contact->accountCompany?->account_name ?? '—',
                'phone' => $contact->phone ?? '—',
                'email' => $contact->email ?? '—',
                'owner_name' => $contact->contactOwner?->username ?? '—',
                'assigned_to_name' => $contact->assignedTo?->username ?? '—',
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'salutation' => 'required|in:Ibu,Bapak',
            'full_name' => 'required|string|max:150',
            'account_companies_id' => 'required|exists:account_companies,id',
            'email' => 'required|email|max:100|unique:account_contacts,email',
            'phone' => 'nullable|string|max:30',
            'mobile' => 'required|string|max:30|unique:account_contacts,mobile',
            'job_titles_id' => 'required|exists:job_titles,id',
            'sources_id' => 'required|exists:sources,id',
            'divisions_id' => 'required|exists:divisions,id',
            'contact_methods_id' => 'required|exists:contact_methods,id',
            'role_in_projects_id' => 'required|exists:role_in_projects,id',
            'assigned_to_id' => 'nullable|exists:users,id',
            'address_street' => 'nullable|string',
            'address_city' => 'nullable|string|max:100',
            'address_province' => 'nullable|string|max:100',
            'address_postal_code' => 'nullable|string|max:10',
            'address_country' => 'nullable|string|max:100',
        ]);

        // Default assigned_to ke pembuat kontak kalau tidak diisi (field ini
        // disembunyikan untuk user divisi Sales, jadi selalu jatuh ke sini).
        $validated['assigned_to_id'] = $validated['assigned_to_id'] ?? Auth::id();

        AccountContact::create(array_merge($validated, [
            'contact_owner_id' => Auth::id(),
            'status' => 'Active',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Contact berhasil ditambahkan.',
        ]);
    }

    public function edit($id): JsonResponse
    {
        $contact = AccountContact::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $contact->toArray(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $contact = AccountContact::findOrFail($id);

        $validated = $request->validate([
            'salutation' => 'required|in:Ibu,Bapak',
            'full_name' => 'required|string|max:150',
            'account_companies_id' => 'required|exists:account_companies,id',
            'email' => 'required|email|max:100|unique:account_contacts,email,'.$contact->id,
            'phone' => 'nullable|string|max:30',
            'mobile' => 'required|string|max:30|unique:account_contacts,mobile,'.$contact->id,
            'job_titles_id' => 'required|exists:job_titles,id',
            'sources_id' => 'required|exists:sources,id',
            'divisions_id' => 'required|exists:divisions,id',
            'contact_methods_id' => 'required|exists:contact_methods,id',
            'role_in_projects_id' => 'required|exists:role_in_projects,id',
            'assigned_to_id' => 'nullable|exists:users,id',
            'address_street' => 'nullable|string',
            'address_city' => 'nullable|string|max:100',
            'address_province' => 'nullable|string|max:100',
            'address_postal_code' => 'nullable|string|max:10',
            'address_country' => 'nullable|string|max:100',
        ]);

        $contact->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Contact berhasil diupdate.',
        ]);
    }

    public function show($id)
    {
        $user = Auth::user();
        $isSales = strtolower($user->division?->division_name) === 'sales' && strtolower($user->hierarchyRole?->role_name) !== 'manager';
        $contact = AccountContact::with([
            'accountCompany', 'contactOwner', 'assignedTo', 'jobTitle',
            'source', 'division', 'contactMethod', 'roleInProject',
            'leads.leadOwner',
            'leads.source',
        ])->findOrFail($id);

        $opportunities = Opportunity::where('account_contacts_id', $contact->id)
            ->with(['stage', 'owner', 'forecast'])
            ->orderByDesc('created_at')
            ->get();

$sources = Source::where('status', 'Active')->get();
        $stages = Stage::where('status', 'Active')->get();
        $forecasts = Forecast::where('status', 'Active')->get();
        $users = User::orderBy('username')->get();
        $accountCompanies = AccountCompany::where('status', 'Active')->orderBy('account_name')->get();

        $user = Auth::user();
        $isSales = strtolower($user->division?->division_name ?? '') === 'sales'
            && strtolower($user->hierarchyRole?->role_name ?? '') !== 'manager';

        return view('contacts-management.show', compact(
            'contact', 'opportunities',
            'sources', 'stages', 'forecasts', 'users', 'isSales', 'accountCompanies'
        ));
    }

    public function storeLead(Request $request, $id): JsonResponse
    {
        $contact = AccountContact::findOrFail($id);

        $isReferralSource = str_contains(strtolower((string) Source::find($request->source_id)?->source_name), 'referral');

        $validated = $request->validate([
            'lead_title' => 'required|string|max:500',
            'lead_status' => 'required|in:New,Approach,Qualified,Unqualified',
            'source_id' => 'required|exists:sources,id',
            'name_referral' => ['nullable', 'string', 'max:150', Rule::requiredIf($isReferralSource)],
            'lead_follow_up_date' => 'required|date',
            'assigned_to' => 'nullable|exists:users,id',
            'unqualified_reason' => 'nullable|string|max:1000',
        ]);

        $lead = Lead::create([
            'lead_status' => $validated['lead_status'],
            'lead_title' => $validated['lead_title'],
            'account_companies_id' => $contact->account_companies_id,
            'account_contacts_id' => $contact->id,
            'source_id' => $validated['source_id'],
            'name_referral' => trim((string) ($validated['name_referral'] ?? '')) ?: null,
            'unqualified_reason' => $validated['unqualified_reason'] ?? null,
            'lead_follow_up_date' => $validated['lead_follow_up_date'] ?? null,
            'lead_owner_id' => Auth::id(),
            'assigned_to' => $validated['assigned_to'] ?? null,
        ]);

        Log::record('create_lead', "Lead #{$lead->id}: {$lead->lead_title} dibuat dari contact #{$contact->id} ({$contact->full_name})", 'MOD_CONTACT_MANAGEMENT', $lead);

        return response()->json([
            'success' => true,
            'message' => 'Lead berhasil dibuat.',
            'data' => $lead,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $contact = AccountContact::findOrFail($id);
        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact berhasil dihapus.',
        ]);
    }
}
