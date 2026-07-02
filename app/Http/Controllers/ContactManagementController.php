<?php

namespace App\Http\Controllers;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\ContactMethod;
use App\Models\Division;
use App\Models\JobTitle;
use App\Models\RoleInProject;
use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactManagementController extends Controller
{
    public function index()
    {
        $accountCompanies = AccountCompany::where('status', 'Active')->orderBy('account_name')->get();
        $jobTitles = JobTitle::where('status', 'Active')->get();
        $sources = Source::where('status', 'Active')->get();
        $divisions = Division::where('status', 'Active')->get();
        $contactMethods = ContactMethod::where('status', 'Active')->get();
        $roleInProjects = RoleInProject::where('status', 'Active')->get();

        return view('contacts-management.index', compact(
            'accountCompanies', 'jobTitles', 'sources', 'divisions',
            'contactMethods', 'roleInProjects'
        ));
    }

    public function data(Request $request): JsonResponse
    {
        $query = AccountContact::with(['accountCompany', 'contactOwner', 'jobTitle']);

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
            'mobile' => 'required|string|max:30',
            'job_titles_id' => 'required|exists:job_titles,id',
            'sources_id' => 'required|exists:sources,id',
            'divisions_id' => 'required|exists:divisions,id',
            'contact_methods_id' => 'required|exists:contact_methods,id',
            'role_in_projects_id' => 'required|exists:role_in_projects,id',
            'address_street' => 'nullable|string',
            'address_city' => 'nullable|string|max:100',
            'address_province' => 'nullable|string|max:100',
            'address_postal_code' => 'nullable|string|max:10',
            'address_country' => 'nullable|string|max:100',
        ]);

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
            'mobile' => 'required|string|max:30',
            'job_titles_id' => 'required|exists:job_titles,id',
            'sources_id' => 'required|exists:sources,id',
            'divisions_id' => 'required|exists:divisions,id',
            'contact_methods_id' => 'required|exists:contact_methods,id',
            'role_in_projects_id' => 'required|exists:role_in_projects,id',
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
        $contact = AccountContact::with([
            'accountCompany', 'contactOwner', 'jobTitle',
            'source', 'division', 'contactMethod', 'roleInProject',
        ])->findOrFail($id);

        return view('contacts-management.show', compact('contact'));
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
