<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\BusinessEntity;
use App\Models\BusinessValue;
use App\Models\ContactMethod;
use App\Models\Division;
use App\Models\Forecast;
use App\Models\InteractionLevel;
use App\Models\JobTitle;
use App\Models\LossReason;
use App\Models\RoleInProject;
use App\Models\Segmentation;
use App\Models\Source;
use App\Models\Stage;
use App\Models\TaskCategory;
use App\Models\TaskRole;
use App\Models\TypesAccountsCompany;
use App\Models\User;
use App\Models\WhatsAppGroup;
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
            'users' => [
                'model' => User::class,
                'label' => 'User',
                'slug' => 'users',
                'columns' => ['username'],
                'hidden' => true,
            ],
            'division-handlers' => [
                'model' => Division::class,
                'label' => 'Divisi Penanganan',
                'slug' => 'division-handlers',
                'columns' => ['division_name', 'members'],
                'column_labels' => [
                    'division_name' => 'Divisi',
                    'members' => 'Anggota Penanganan',
                ],
                'rules' => [
                    'division_id' => 'required|exists:divisions,id',
                    'user_ids' => 'nullable|array',
                    'user_ids.*' => 'exists:users,id',
                ],
                'no_name_field' => true,
                'extra_fields' => [
                    'division_id' => [
                        'label' => 'Divisi',
                        'type' => 'select_fk',
                        'source' => 'divisions',
                        'source_key' => 'division_name',
                    ],
                    'user_ids' => [
                        'label' => 'Anggota Terlibat',
                        'type' => 'multi_select',
                        'source' => 'users',
                        'source_key' => 'username',
                    ],
                ],
            ],
            'whatsapp-groups' => [
                'model' => WhatsAppGroup::class,
                'label' => 'WhatsApp Group',
                'slug' => 'whatsapp-groups',
                'columns' => ['group_name', 'group_id', 'division_id', 'description', 'status'],
                'rules' => [
                    'group_name' => 'required|string|max:100',
                    'group_id' => 'nullable|string|max:100',
                    'division_id' => 'required|exists:divisions,id',
                    'description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive',
                ],
                'display_map' => [
                    'division_id' => 'division.division_name',
                ],
                'extra_fields' => [
                    'group_id' => [
                        'label' => 'Group ID',
                        'type' => 'text',
                    ],
                    'division_id' => [
                        'label' => 'Division',
                        'type' => 'select_fk',
                        'source' => 'divisions',
                        'source_key' => 'division_name',
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
            'task-roles' => [
                'model' => TaskRole::class,
                'label' => 'Task Role',
                'slug' => 'task-roles',
                'columns' => ['role_name', 'hierarchy_level', 'is_global_delegator'],
                'rules' => [
                    'role_name' => 'required|string|max:50',
                    'hierarchy_level' => 'required|integer|min:1',
                    'is_global_delegator' => 'required|in:Yes,No',
                ],
                'extra_fields' => [
                    'hierarchy_level' => [
                        'label' => 'Hierarchy Level',
                        'type' => 'number',
                        'min' => 1,
                        'default' => 40,
                    ],
                    'is_global_delegator' => [
                        'label' => 'Global Delegator',
                        'type' => 'select',
                        'options' => ['No', 'Yes'],
                        'default' => 'No',
                    ],
                ],
            ],
            'task-categories' => [
                'model' => TaskCategory::class,
                'label' => 'Task Category',
                'slug' => 'task-categories',
                'columns' => ['name', 'description', 'division_id', 'use_division_handler'],
                'rules' => [
                    'name' => 'required|string|max:50',
                    'description' => 'nullable|string',
                    'division_id' => 'nullable|exists:divisions,id',
                    'use_division_handler' => 'required|in:Yes,No',
                ],
                'display_map' => [
                    'division_id' => 'division.division_name',
                ],
                'extra_fields' => [
                    'division_id' => [
                        'label' => 'Division',
                        'type' => 'select_fk',
                        'source' => 'divisions',
                        'source_key' => 'division_name',
                    ],
                    'use_division_handler' => [
                        'label' => 'Divisi Penanganan',
                        'type' => 'select',
                        'options' => ['No', 'Yes'],
                        'default' => 'No',
                    ],
                ],
            ],
            'forecasts' => [
                'model' => Forecast::class,
                'label' => 'Forecast',
                'slug' => 'forecasts',
                'columns' => ['forecast_name', 'description', 'status'],
                'rules' => [
                    'forecast_name' => 'required|string|max:100',
                    'description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive',
                ],
            ],
            'loss-reasons' => [
                'model' => LossReason::class,
                'label' => 'Loss Reason',
                'slug' => 'loss-reasons',
                'columns' => ['reason_name', 'description', 'status'],
                'rules' => [
                    'reason_name' => 'required|string|max:100',
                    'description' => 'nullable|string',
                    'status' => 'required|in:Active,Inactive',
                ],
            ],
            'stages' => [
                'model' => Stage::class,
                'label' => 'Stage',
                'slug' => 'stages',
                'columns' => ['stage_name', 'description', 'status'],
                'rules' => [
                    'stage_name' => 'required|string|max:100',
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

        if ($table === 'division-handlers') {
            return $this->listDivisionHandlers($request);
        }

        $search = $request->get('search', '');
        $nameCol = $cfg['columns'][0];

        $query = $cfg['model']::query();

        if ($search) {
            $query->where($nameCol, 'like', "%{$search}%");
        }

        $displayMap = $cfg['display_map'] ?? [];
        $relations = [];
        foreach ($displayMap as $col => $path) {
            $relation = explode('.', $path)[0];
            if (! in_array($relation, $relations)) {
                $relations[] = $relation;
            }
        }
        if (! empty($relations)) {
            $query->with($relations);
        }

        $records = $query->orderBy('id', 'desc')->paginate(15);

        $data = $records->items();

        if (! empty($displayMap)) {
            foreach ($data as $record) {
                foreach ($displayMap as $col => $path) {
                    $segments = explode('.', $path);
                    $value = $record;
                    foreach ($segments as $seg) {
                        if ($value === null) {
                            break;
                        }
                        $value = $value->{$seg} ?? null;
                    }
                    $record->{$col} = $value ?? $record->{$col};
                }
            }
        }

        return response()->json([
            'data' => $data,
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
            'display_map' => $cfg['display_map'] ?? null,
        ]);
    }

    public function store(Request $request, string $table): JsonResponse
    {
        $cfg = $this->getConfig($table);
        if (! $cfg) {
            return response()->json(['error' => 'Invalid table'], 404);
        }

        if ($table === 'division-handlers') {
            $validated = $request->validate($cfg['rules']);
            $division = Division::findOrFail($validated['division_id']);
            $division->handlerUsers()->sync($validated['user_ids'] ?? []);

            return response()->json([
                'success' => true,
                'message' => "Divisi Penanganan {$division->division_name} berhasil disimpan.",
                'data' => $division,
            ]);
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

        if ($table === 'division-handlers') {
            $validated = $request->validate($cfg['rules']);
            $division = Division::findOrFail($id);
            $division->handlerUsers()->sync($validated['user_ids'] ?? []);

            return response()->json([
                'success' => true,
                'message' => "Divisi Penanganan {$division->division_name} berhasil diperbarui.",
                'data' => $division,
            ]);
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

        if ($table === 'division-handlers') {
            $division = Division::findOrFail($id);
            $division->handlerUsers()->sync([]);

            return response()->json([
                'success' => true,
                'message' => "Divisi Penanganan {$division->division_name} berhasil dikosongkan.",
            ]);
        }

        $record = $cfg['model']::findOrFail($id);
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => $cfg['label'].' berhasil dihapus.',
        ]);
    }

    private function listDivisionHandlers(Request $request): JsonResponse
    {
        $search = $request->get('search', '');

        $query = Division::whereHas('handlerUsers')->with('handlerUsers')->orderBy('division_name');

        if ($search) {
            $query->where('division_name', 'like', "%{$search}%");
        }

        $records = $query->paginate(15);

        $data = collect($records->items())->map(function (Division $division) {
            $division->members = $division->handlerUsers->pluck('username')->join(', ');
            $division->division_id = $division->id;
            $division->user_ids = $division->handlerUsers->pluck('id')->all();

            return $division;
        })->all();

        return response()->json([
            'data' => $data,
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
            ],
            'columns' => ['division_name', 'members'],
            'label' => 'Divisi Penanganan',
            'display_map' => null,
        ]);
    }

    private function getConfig(string $table): ?array
    {
        return $this->config[$table] ?? null;
    }
}
