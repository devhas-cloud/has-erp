<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountContact extends Model
{
    protected $fillable = [
        'account_companies_id',
        'full_name',
        'icon',
        'salutation',
        'email',
        'phone',
        'mobile',
        'job_titles_id',
        'sources_id',
        'role_in_projects_id',
        'contact_methods_id',
        'divisions_id',
        'contact_owner_id',
        'address_street',
        'address_city',
        'address_province',
        'address_postal_code',
        'address_country',
        'lead_status',
        'status',
    ];

    public function accountCompany(): BelongsTo
    {
        return $this->belongsTo(AccountCompany::class, 'account_companies_id');
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class, 'job_titles_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'sources_id');
    }

    public function roleInProject(): BelongsTo
    {
        return $this->belongsTo(RoleInProject::class, 'role_in_projects_id');
    }

    public function contactMethod(): BelongsTo
    {
        return $this->belongsTo(ContactMethod::class, 'contact_methods_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'divisions_id');
    }

    public function contactOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_owner_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'account_contacts_id');
    }
}
