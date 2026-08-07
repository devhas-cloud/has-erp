<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\Module;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\UserAccessControl;
use App\Observers\ActivityObserver;
use App\Observers\TaskActivityObserver;
use App\Observers\TaskObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
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
        if (env('FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }

        Task::observe(TaskObserver::class);
        TaskActivity::observe(TaskActivityObserver::class);
        Activity::observe(ActivityObserver::class);

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
