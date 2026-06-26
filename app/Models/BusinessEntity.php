<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessEntity extends Model
{
    protected $fillable = [
        'entity_name',
        'description',
        'status',
    ];

    public function accountCompanies(): HasMany
    {
        return $this->hasMany(AccountCompany::class, 'business_entities_id');
    }
}
