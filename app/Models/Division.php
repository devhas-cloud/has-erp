<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Division extends Model
{
    protected $fillable = [
        'division_name',
        'description',
        'type',
        'status',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function accountContacts(): HasMany
    {
        return $this->hasMany(AccountContact::class, 'divisions_id');
    }

    public function whatsappGroup(): HasOne
    {
        return $this->hasOne(WhatsAppGroup::class);
    }
}
