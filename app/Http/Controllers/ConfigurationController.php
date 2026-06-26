<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\BusinessEntity;
use App\Models\BusinessValue;
use App\Models\ContactMethod;
use App\Models\Division;
use App\Models\InteractionLevel;
use App\Models\JobTitle;
use App\Models\RoleInProject;
use App\Models\Segmentation;
use App\Models\Source;
use App\Models\TypesAccountsCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    private array $config;

    public function __construct()
    {
        $this->config = [
            'divisions' => [
                'model' => Division::class,
                'label' => 'Division',
                'slug' => 'divisions',
                'columns' => ['division_name', 'description', 'type', 'status'],
                'rules' => [
                    'division_name' => 'required|string|max:100',
                    'description' => 'nullable|string',
                    'type' => 'required|in:Internal,External',
                    'status' => 'required|in:Active,Inactive',
                ],
                'extra_fields' => [
                    'type' => [
                        'label' => 'Type',
                        'type' => 'select',
                        'options' => ['Internal', 'External'],
                        'default' => 'Internal',
                    ],
                ],
            ],
            'job-titles' => [
                'model' => JobTitle::class,
                'label' => 'Job Title',
                'slug' => 'job-titles',
                'columns' => ['title_name', 'description', 'status'],
                'rules' => [
                    'title_name' => 'required|string|max:100',
                    'description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive',
                ],
            ],
            'sources' => [
                'model' => Source::class,
                'label' => 'Source',
                'slug' => 'sources',
                'columns' => ['source_name', 'description', 'status'],
                'rules' => [
                    'source_name' => 'required|string|max:100',
                    'description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive',
                ],
            ],
            'contact-methods' => [
                'model' => ContactMethod::class,
                'label' => 'Contact Method',
                'slug' => 'contact-methods',
                'columns' => ['method_name', 'description', 'status'],
                'rules' => [
                    'method_name' => 'required|string|max:100',
                    'description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive',
                ],
            ],
            'segmentations' => [
                'model' => Segmentation::class,
                'label' => 'Segmentation',
                'slug' => 'segmentations',
                'columns' => ['segmentation_name', 'description', 'status'],
                'rules' => [
                    'segmentation_name' => 'required|string|max:100',
                    'description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive',
                ],
            ],
            'business-entities' => [
                'model' => BusinessEntity::class,
                'label' => 'Business Entity',
                'slug' => 'business-entities',
                'columns' => ['entity_name', 'description', 'status'],
                'rules' => [
                    'entity_name' => 'required|string|max:100',
                    'description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive',
                ],
            ],
            'business-values' => [
                'model' => BusinessValue::class,
                'label' => 'Business Value',
                'slug' => 'business-values',
                'columns' => ['value_name', 'description', 'status'],
                'rules' => [
                    'value_name' => 'required|string|max:100',
                    'description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive',
                ],
            ],
            'role-in-projects' => [
                'model' => RoleInProject::class,
                'label' => 'Role In Project',
                'slug' => 'role-in-projects',
                'columns' => ['role_name', 'description', 'status'],
                'rules' => [
                    'role_name' => 'required|string|max:100',
                    'description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive',
                ],
            ],
            'interaction-levels' => [
                'model' => InteractionLevel::class,
                'label' => 'Interaction Level',
                'slug' => 'interaction-levels',
                'columns' => ['level_name', 'description', 'status'],
                'rules' => [
                    'level_name' => 'required|string|max:100',
                    'description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive',
                ],
            ],
            'types-accounts' => [
                'model' => TypesAccountsCompany::class,
                'label' => 'Types Accounts Company',
                'slug' => 'types-accounts',
                'columns' => ['type_name', 'description', 'status'],
                'rules' => [
                    'type_name' => 'required|string|max:100',
                    'description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive',
                ],
            ],
            'account-types' => [
                'model' => AccountType::class,
                'label' => 'Account Type',
                'slug' => 'account-types',
                'columns' => ['type_name', 'description', 'status'],
                'rules' => [
                    'type_name' => 'required|string|max:100',
                    'description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive',
                ],
            ],
        ];
    }

    public function index()
    {
        return view('configuration.index', [
            'config' => $this->config,
        ]);
    }

    public function list(Request $request, string $table): JsonResponse
    {
        $cfg = $this->getConfig($table);
        if (! $cfg) {
            return response()->json(['error' => 'Invalid table'], 404);
        }

        $search = $request->get('search', '');
        $nameCol = $cfg['columns'][0];

        $query = $cfg['model']::query();

        if ($search) {
            $query->where($nameCol, 'like', "%{$search}%");
        }

        $records = $query->orderBy('id', 'desc')->paginate(15);

        return response()->json([
            'data' => $records->items(),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
            ],
            'columns' => $cfg['columns'],
            'label' => $cfg['label'],
        ]);
    }

    public function store(Request $request, string $table): JsonResponse
    {
        $cfg = $this->getConfig($table);
        if (! $cfg) {
            return response()->json(['error' => 'Invalid table'], 404);
        }

        $validated = $request->validate($cfg['rules']);

        $record = $cfg['model']::create($validated);

        return response()->json([
            'success' => true,
            'message' => $cfg['label'].' berhasil ditambahkan.',
            'data' => $record,
        ]);
    }

    public function update(Request $request, string $table, int $id): JsonResponse
    {
        $cfg = $this->getConfig($table);
        if (! $cfg) {
            return response()->json(['error' => 'Invalid table'], 404);
        }

        $record = $cfg['model']::findOrFail($id);

        $validated = $request->validate($cfg['rules']);

        $record->update($validated);

        return response()->json([
            'success' => true,
            'message' => $cfg['label'].' berhasil diupdate.',
            'data' => $record,
        ]);
    }

    public function destroy(Request $request, string $table, int $id): JsonResponse
    {
        $cfg = $this->getConfig($table);
        if (! $cfg) {
            return response()->json(['error' => 'Invalid table'], 404);
        }

        $record = $cfg['model']::findOrFail($id);
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => $cfg['label'].' berhasil dihapus.',
        ]);
    }

    private function getConfig(string $table): ?array
    {
        return $this->config[$table] ?? null;
    }
}
