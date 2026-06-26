<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    protected $fillable = [
        'lead_status',
        'lead_title',
        'account_contacts_id',
        'account_companies_id',
        'source_id',
        'unqualified_reason',
        'closed_date',
        'all_filed_completed',
        'lead_owner_id',
        'assigned_to',
        'lead_can_be_contacted',
        'lead_follow_up_date',
        'lead_appoinment',
        'identification',
    ];

    protected function casts(): array
    {
        return [
            'lead_can_be_contacted' => 'boolean',
            'lead_follow_up_date' => 'date',
            'closed_date' => 'date',
            'lead_appoinment' => 'boolean',
            'identification' => 'boolean',
            'all_filed_completed' => 'boolean',
        ];
    }

    public function accountContact(): BelongsTo
    {
        return $this->belongsTo(AccountContact::class, 'account_contacts_id');
    }

    public function accountCompany(): BelongsTo
    {
        return $this->belongsTo(AccountCompany::class, 'account_companies_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function leadOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_owner_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
