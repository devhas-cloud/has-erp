<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InteractionLevel extends Model
{
    protected $fillable = [
        'level_name',
        'description',
        'status',
    ];

    public function accountCompanies(): HasMany
    {
        return $this->hasMany(AccountCompany::class, 'interaction_levels_id');
    }
}
