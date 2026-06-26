<?php

namespace App\Providers;

use App\Models\Module;
use App\Models\UserAccessControl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            if (! Auth::check()) {
                return;
            }

            $routeName = request()->route()?->getName();
            $baseName = null;

            if ($routeName) {
                $lastDot = strrpos($routeName, '.');
                $baseName = $lastDot !== false ? substr($routeName, 0, $lastDot) : $routeName;
            }

            $isAdmin = Auth::user()->role === 'Admin';

            $access = null;
            if ($baseName) {
                $module = Module::where('route_name', $baseName)->first();
                if ($module) {
                    $access = UserAccessControl::where('user_id', Auth::id())
                        ->where('module_id', $module->id)
                        ->first();
                }
            }

            $view->with('canCreate', $isAdmin || ($access->can_create ?? false));
            $view->with('canRead', $isAdmin || ($access->can_read ?? false));
            $view->with('canUpdate', $isAdmin || ($access->can_update ?? false));
            $view->with('canDelete', $isAdmin || ($access->can_delete ?? false));
            $view->with('canApprove', $isAdmin || ($access->can_approve ?? false));
        });
    }
}
