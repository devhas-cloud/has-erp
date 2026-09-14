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

        // Modul selalu berupa satu segmen pertama (route_name di tabel modules
        // tidak pernah mengandung titik) — split di titik PERTAMA, bukan
        // terakhir. Route bertingkat 3 segmen (mis. leads-management.activities.destroy)
        // punya 2 titik; split di titik terakhir akan menghasilkan baseName
        // "leads-management.activities" yang tidak cocok modul manapun,
        // sehingga permission check di-skip total untuk route tersebut.
        $firstDot = strpos($routeName, '.');
        $baseName = $firstDot !== false ? substr($routeName, 0, $firstDot) : $routeName;

        $action = $firstDot !== false ? substr($routeName, $firstDot + 1) : 'index';

        $module = Module::where('route_name', $baseName)->first();

        if (! $module) {
            return $next($request);
        }

        $method = $request->method();
        $permissionField = match (true) {
            $action === 'approve', $action === 'reject', $action === 'unlock' => 'can_approve',
            $action === 'upload-po' => 'can_update',
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
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk melakukan aksi ini.',
                ], 403);
            }

            return redirect()->back()->with('error', 'Anda tidak memiliki izin untuk melakukan aksi ini.');
        }

        return $next($request);
    }
}
