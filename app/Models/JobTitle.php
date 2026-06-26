<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobTitle extends Model
{
    protected $fillable = [
        'title_name',
        'description',
        'status',
    ];

    public function accountContacts(): HasMany
    {
        return $this->hasMany(AccountContact::class, 'job_titles_id');
    }
}
