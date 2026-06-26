<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $fillable = [
        'source_name',
        'description',
        'status',
    ];

    public function accountCompanies(): HasMany
    {
        return $this->hasMany(AccountCompany::class, 'sources_id');
    }

    public function accountContacts(): HasMany
    {
        return $this->hasMany(AccountContact::class, 'sources_id');
    }
}
