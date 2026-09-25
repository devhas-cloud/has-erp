<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'quota_days',
        'paid',
        'is_proof_required',
    ];

    protected $casts = [
        'paid' => 'boolean',
        'is_proof_required' => 'boolean',
        'quota_days' => 'integer',
    ];

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
