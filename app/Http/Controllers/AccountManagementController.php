<?php

namespace App\Http\Controllers;

use App\Models\AccountCompany;
use App\Models\BusinessEntity;
use App\Models\BusinessValue;
use App\Models\InteractionLevel;
use App\Models\Segmentation;
use App\Models\Source;
use App\Models\TypesAccountsCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountManagementController extends Controller
{
    public function index()
    {
        $accountCompanies = AccountCompany::where('status', 'Active')->orderBy('account_name')->get();
        $typesAccountsCompanies = TypesAccountsCompany::where('status', 'Active')->get();
        $sources = Source::where('status', 'Active')->get();
        $segmentations = Segmentation::where('status', 'Active')->get();
        $businessEntities = BusinessEntity::where('status', 'Active')->get();
        $businessValues = BusinessValue::where('status', 'Active')->get();
        $interactionLevels = InteractionLevel::where('status', 'Active')->get();

        return view('accounts-management.index', compact(
            'accountCompanies', 'typesAccountsCompanies', 'sources',
            'segmentations', 'businessEntities', 'businessValues', 'interactionLevels'
        ));
    }

    public function data(Request $request): JsonResponse
    {
        $query = AccountCompany::with(['accountOwner']);

        $recordsTotal = AccountCompany::count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('account_name', 'like', "%{$searchValue}%")
                    ->orWhere('phone', 'like', "%{$searchValue}%")
                    ->orWhereHas('accountOwner', function ($q) use ($searchValue) {
                        $q->where('username', 'like', "%{$searchValue}%");
                    });
            });
        }

        $recordsFiltered = $query->count();

        $orderColumnIndex = $request->input('order.0.column', 1);
        $orderDirection = $request->input('order.0.dir', 'asc');

        $columnOrderMap = [
            1 => 'account_name',
            2 => 'phone',
        ];

        if (isset($columnOrderMap[$orderColumnIndex])) {
            $query->orderBy($columnOrderMap[$orderColumnIndex], $orderDirection);
        }
        $query->orderBy('id', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $accounts = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($accounts as $i => $account) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $account->id,
                'account_name' => $account->account_name ?? '—',
                'initials' => strtoupper(substr($account->account_name ?? '?', 0, 2)),
                'icon' => $account->icon,
                'name_display' => $account->account_name ?? '—',
                'phone' => $account->phone ?? '—',
                'owner_name' => $account->accountOwner?->username ?? '—',
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
            'account_name' => 'required|string|max:150',
            'types_accounts_companies_id' => 'required|exists:types_accounts_companies,id',
            'sources_id' => 'required|exists:sources,id',
            'segmentation_id' => 'required|exists:segmentations,id',
            'business_entities_id' => 'required|exists:business_entities,id',
            'business_values_id' => 'required|exists:business_values,id',
            'interaction_levels_id' => 'required|exists:interaction_levels,id',
            'website' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'end_user' => 'nullable|integer',
            'parent_account_id' => 'nullable|exists:account_companies,id',
            'phone' => 'nullable|string|max:30',
            'address_billing_street' => 'nullable|string',
            'address_billing_city' => 'nullable|string|max:100',
            'address_billing_province' => 'nullable|string|max:100',
            'address_billing_postal_code' => 'nullable|string|max:10',
            'address_billing_country' => 'nullable|string|max:100',
            'address_shipping_street' => 'nullable|string',
            'address_shipping_city' => 'nullable|string|max:100',
            'address_shipping_province' => 'nullable|string|max:100',
            'address_shipping_postal_code' => 'nullable|string|max:10',
            'address_shipping_country' => 'nullable|string|max:100',
        ]);

        $validated['account_owner_id'] = Auth::id();
        $validated['status'] = 'Active';

        AccountCompany::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Account berhasil ditambahkan.',
        ]);
    }

    public function edit($id): JsonResponse
    {
        $account = AccountCompany::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $account->toArray(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $account = AccountCompany::findOrFail($id);

        $validated = $request->validate([
            'account_name' => 'required|string|max:150',
            'types_accounts_companies_id' => 'required|exists:types_accounts_companies,id',
            'sources_id' => 'required|exists:sources,id',
            'segmentation_id' => 'required|exists:segmentations,id',
            'business_entities_id' => 'required|exists:business_entities,id',
            'business_values_id' => 'required|exists:business_values,id',
            'interaction_levels_id' => 'required|exists:interaction_levels,id',
            'website' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'end_user' => 'nullable|integer',
            'parent_account_id' => 'nullable|exists:account_companies,id',
            'phone' => 'nullable|string|max:30',
            'address_billing_street' => 'nullable|string',
            'address_billing_city' => 'nullable|string|max:100',
            'address_billing_province' => 'nullable|string|max:100',
            'address_billing_postal_code' => 'nullable|string|max:10',
            'address_billing_country' => 'nullable|string|max:100',
            'address_shipping_street' => 'nullable|string',
            'address_shipping_city' => 'nullable|string|max:100',
            'address_shipping_province' => 'nullable|string|max:100',
            'address_shipping_postal_code' => 'nullable|string|max:10',
            'address_shipping_country' => 'nullable|string|max:100',
        ]);

        $account->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Account berhasil diupdate.',
        ]);
    }

    public function show($id)
    {
        $account = AccountCompany::with([
            'source', 'typesAccountsCompany', 'segmentation',
            'businessEntity', 'businessValue', 'interactionLevel',
            'accountOwner', 'parentAccount',
        ])->findOrFail($id);

        return view('accounts-management.show', compact('account'));
    }

    public function destroy($id): JsonResponse
    {
        $account = AccountCompany::findOrFail($id);
        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account berhasil dihapus.',
        ]);
    }
}
