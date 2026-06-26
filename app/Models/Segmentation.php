<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Segmentation extends Model
{
    protected $fillable = [
        'segmentation_name',
        'description',
        'status',
    ];

    public function accountCompanies(): HasMany
    {
        return $this->hasMany(AccountCompany::class, 'segmentation_id');
    }
}
