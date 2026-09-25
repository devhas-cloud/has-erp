<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $fillable = [
        'employee_id',
        'year',
        'leave_type_id',
        'entitlement',
        'used',
        'carried_over',
    ];

    protected $casts = [
        'year' => 'integer',
        'entitlement' => 'integer',
        'used' => 'integer',
        'carried_over' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
