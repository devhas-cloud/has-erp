<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypesAccountsCompany extends Model
{
    protected $table = 'types_accounts_companies';

    protected $fillable = [
        'type_name',
        'description',
        'status',
    ];

    public function accountCompanies(): HasMany
    {
        return $this->hasMany(AccountCompany::class, 'types_accounts_companies_id');
    }
}
