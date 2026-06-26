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
}
