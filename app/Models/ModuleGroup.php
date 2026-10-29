<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Urutan grup sidebar. Grup yang belum terdaftar sengaja tetap tampil
 * (lihat SidebarMenu) di urutan paling akhir.
 */
class ModuleGroup extends Model
{
    protected $fillable = [
        'group_name',
        'sort_order',
    ];

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class, 'group', 'group_name');
    }
}
