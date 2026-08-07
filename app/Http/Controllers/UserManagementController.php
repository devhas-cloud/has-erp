<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Module;
use App\Models\TaskRole;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index()
    {
        return view('user-management.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = User::with(['division', 'hierarchyRole']);

        $recordsTotal = User::count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('username', 'like', "%{$searchValue}%")
                    ->orWhere('email', 'like', "%{$searchValue}%")
                    ->orWhere('role', 'like', "%{$searchValue}%")
                    ->orWhereHas('division', function ($q) use ($searchValue) {
                        $q->where('division_name', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('hierarchyRole', function ($q) use ($searchValue) {
                        $q->where('role_name', 'like', "%{$searchValue}%");
                    });
            });
        }

        $recordsFiltered = $query->count();

        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'asc');

        $columnOrderMap = [
            1 => 'username',
            2 => 'email',
            6 => 'created_at',
        ];

        if (isset($columnOrderMap[$orderColumnIndex])) {
            $query->orderBy($columnOrderMap[$orderColumnIndex], $orderDirection);
        }
        $query->orderBy('id', 'asc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $users = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($users as $i => $user) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $user->id,
                'username' => $user->username,
                'initials' => strtoupper(substr($user->username, 0, 2)),
                'icon' => $user->icon,
                'email' => $user->email,
                'division_name' => $user->division?->division_name,
                'role' => $user->role,
                'task_role_name' => $user->hierarchyRole?->role_name,
                'created_at' => $user->created_at->format('d M Y'),
                'created_at_raw' => $user->created_at->toISOString(),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create()
    {
        $divisions = Division::where('status', 'Active')->get();
        $taskRoles = TaskRole::all();
        $modules = Module::get()->groupBy('group');

        return view('user-management.create', compact('divisions', 'taskRoles', 'modules'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone_number' => 'nullable|string|max:20',
            'division_id' => 'nullable|exists:divisions,id',
            'role' => 'required|in:Admin,User',
            'task_role_id' => 'nullable|exists:task_roles,id',
        ]);

        $roleRecord = TaskRole::where('role_name', $validated['role'])->first();
        if ($roleRecord) {
            $validated['task_role_id'] = $roleRecord->id;
        }

        $user = User::create($validated);

        $modules = $request->input('modules', []);
        foreach ($modules as $moduleId => $perms) {
            UserAccessControl::create([
                'user_id' => $user->id,
                'module_id' => $moduleId,
                'can_create' => isset($perms['can_create']),
                'can_read' => isset($perms['can_read']),
                'can_update' => isset($perms['can_update']),
                'can_delete' => isset($perms['can_delete']),
                'can_approve' => isset($perms['can_approve']),
            ]);
        }

        return redirect()->route('user-management.index')
            ->with('success', 'User berhasil ditambahkan.');
    }

    public function show($id)
    {
        $user = User::with(['division', 'accessControls.module'])->findOrFail($id);

        return view('user-management.show', compact('user'));
    }

    public function edit($id)
    {
        $user = User::with('accessControls')->findOrFail($id);
        $divisions = Division::where('status', 'Active')->get();
        $taskRoles = TaskRole::all();
        $modules = Module::get()->groupBy('group');

        $userPermissions = $user->accessControls->keyBy('module_id');

        return view('user-management.edit', compact('user', 'divisions', 'taskRoles', 'modules', 'userPermissions'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users,username,'.$user->id,
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'phone_number' => 'nullable|string|max:20',
            'division_id' => 'nullable|exists:divisions,id',
            'role' => 'required|in:Admin,User',
            'task_role_id' => 'nullable|exists:task_roles,id',
        ]);

        if ($request->filled('password')) {
            $validated['password'] = $request->validate(['password' => 'string|min:6'])['password'];
        }

        $roleRecord = TaskRole::where('role_name', $validated['role'])->first();
        if ($roleRecord) {
            $validated['task_role_id'] = $roleRecord->id;
        }

        $user->update($validated);

        UserAccessControl::where('user_id', $user->id)->delete();

        $modules = $request->input('modules', []);
        foreach ($modules as $moduleId => $perms) {
            UserAccessControl::create([
                'user_id' => $user->id,
                'module_id' => $moduleId,
                'can_create' => isset($perms['can_create']),
                'can_read' => isset($perms['can_read']),
                'can_update' => isset($perms['can_update']),
                'can_delete' => isset($perms['can_delete']),
                'can_approve' => isset($perms['can_approve']),
            ]);
        }

        return redirect()->route('user-management.index')
            ->with('success', 'User berhasil diupdate.');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('user-management.index')
            ->with('success', 'User berhasil dihapus.');
    }
}
