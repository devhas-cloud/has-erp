<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoleInProject extends Model
{
    protected $table = 'role_in_projects';

    protected $fillable = [
        'role_name',
        'description',
        'status',
    ];

    public function accountContacts(): HasMany
    {
        return $this->hasMany(AccountContact::class, 'role_in_projects_id');
    }
}
