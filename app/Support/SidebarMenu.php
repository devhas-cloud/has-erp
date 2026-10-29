<?php

namespace App\Support;

use App\Models\Module;
use App\Models\ModuleGroup;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menu sidebar per user: modul yang boleh dibaca, dikelompokkan per grup,
 * diurutkan menurut module_groups.sort_order lalu modules.sort_order.
 * Grup yang belum terdaftar di module_groups tetap tampil di akhir.
 */
class SidebarMenu
{
    /**
     * @return Collection<string, Collection<int, Module>>
     */
    public static function forUser(User $user): Collection
    {
        $modules = Module::accessibleFor($user)
            ->orderBy('sort_order')
            ->orderBy('module_name')
            ->get();

        $groupOrder = ModuleGroup::orderBy('sort_order')
            ->orderBy('group_name')
            ->pluck('sort_order', 'group_name');

        return $modules
            ->groupBy(fn (Module $module) => $module->group ?: 'Lainnya')
            ->sortBy(fn (Collection $group, string $name) => $groupOrder[$name] ?? PHP_INT_MAX)
            ->map(fn (Collection $group) => $group->values());
    }

    /**
     * Modul pertama yang boleh dibaca user, urut sama seperti sidebar.
     */
    public static function firstModuleFor(User $user): ?Module
    {
        return self::forUser($user)->flatten(1)->first();
    }
}
