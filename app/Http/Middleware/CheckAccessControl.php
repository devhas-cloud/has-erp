<?php

namespace App\Http\Middleware;

use App\Models\Module;
use App\Models\UserAccessControl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckAccessControl
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->role === 'Admin') {
            return $next($request);
        }

        $routeName = $request->route()->getName();

        if (! $routeName) {
            return $next($request);
        }

        $lastDot = strrpos($routeName, '.');
        $baseName = $lastDot !== false ? substr($routeName, 0, $lastDot) : $routeName;

        $action = $lastDot !== false ? substr($routeName, $lastDot + 1) : 'index';

        $module = Module::where('route_name', $baseName)->first();

        if (! $module) {
            return $next($request);
        }

        $method = $request->method();
        $permissionField = match (true) {
            $action === 'approve' => 'can_approve',
            $method === 'POST' => 'can_create',
            $method === 'PUT', $method === 'PATCH' => 'can_update',
            $method === 'DELETE' => 'can_delete',
            default => 'can_read',
        };

        $hasAccess = UserAccessControl::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where($permissionField, true)
            ->exists();

        if (! $hasAccess && $method === 'GET') {
            return redirect()->back()->with('error', 'Anda tidak memiliki akses ke modul ini.');
        }

        if (! $hasAccess) {
            return redirect()->back()->with('error', 'Anda tidak memiliki izin untuk melakukan aksi ini.');
        }

        return $next($request);
    }
}
