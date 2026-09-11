<?php

namespace App\Models;

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
    ];

    public function accessControls(): HasMany
    {
        return $this->hasMany(UserAccessControl::class);
    }

    /**
     * Modul pertama yang boleh dibaca user, urut sama seperti sidebar
     * (group lalu module_name). Admin dianggap boleh membaca semua modul.
     * Dipakai sebagai halaman awal universal setelah login (bukan hardcode
     * ke satu modul tertentu), karena tiap akun bisa punya modul berbeda.
     * Null bila user tidak punya akses can_read ke modul manapun.
     */
    public static function firstAccessibleFor(User $user): ?self
    {
        $query = static::query()
            ->whereNotNull('route_name')
            ->where('route_name', '!=', '');

        if ($user->role !== 'Admin') {
            $accessibleIds = UserAccessControl::where('user_id', $user->id)
                ->where('can_read', true)
                ->pluck('module_id');

            $query->whereIn('id', $accessibleIds);
        }

        return $query->orderBy('group')->orderBy('module_name')->first();
    }
}
