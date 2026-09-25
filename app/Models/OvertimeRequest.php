<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRequest extends Model
{
    use Loggable;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'request_no',
        'employee_id',
        'work_date',
        'attendance_id',
        'shift_end_time',
        'start_time',
        'end_time',
        'hours',
        'multiplier',
        'estimated_amount',
        'reason',
        'status',
        'submitted_by',
        'approved_by',
        'approved_at',
        'decision_note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'shift_end_time' => 'datetime:H:i:s',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'hours' => 'decimal:2',
        'multiplier' => 'decimal:2',
        'estimated_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
