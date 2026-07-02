<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskRole extends Model
{
    protected $table = 'task_roles';

    protected $fillable = [
        'role_name',
        'hierarchy_level',
        'is_global_delegator',
    ];

    protected $casts = [
        'is_global_delegator' => 'boolean',
        'hierarchy_level' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'task_role_id');
    }
}
