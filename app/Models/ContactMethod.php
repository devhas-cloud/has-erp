<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactMethod extends Model
{
    protected $fillable = [
        'method_name',
        'description',
        'status',
    ];

    public function accountContacts(): HasMany
    {
        return $this->hasMany(AccountContact::class, 'contact_methods_id');
    }
}
