<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Module;
use App\Models\TaskRole;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::with('division')->paginate(15);

        return view('user-management.index', compact('users'));
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
            'role' => 'required|in:Admin,Manager,Staff',
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
            'role' => 'required|in:Admin,Manager,Staff',
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
