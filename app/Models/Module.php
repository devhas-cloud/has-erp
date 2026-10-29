<?php

namespace App\Models;

use App\Support\SidebarMenu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $fillable = [
        'module_code',
        'module_name',
        'description',
        'route_name',
        'icon',
        'group',
        'sort_order',
    ];

    public function accessControls(): HasMany
    {
        return $this->hasMany(UserAccessControl::class);
    }

    /**
     * Modul bertaut route yang boleh dibaca user (Admin: semua). Urutannya
     * diatur modules.sort_order lalu module_name.
     */
    public function scopeAccessibleFor(Builder $query, User $user): Builder
    {
        $query->whereNotNull('route_name')->where('route_name', '!=', '');

        if ($user->role !== 'Admin') {
            $query->whereIn('id', UserAccessControl::where('user_id', $user->id)
                ->where('can_read', true)
                ->pluck('module_id'));
        }

        return $query;
    }

    /**
     * Modul pertama yang boleh dibaca user, urut sama seperti sidebar
     * (lihat \App\Support\SidebarMenu). Admin dianggap boleh membaca semua
     * modul. Null bila user tidak punya akses can_read ke modul manapun.
     */
    public static function firstAccessibleFor(User $user): ?self
    {
        return SidebarMenu::firstModuleFor($user);
    }
}
