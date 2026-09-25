<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceApproval extends Model
{
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT = 'reject';

    protected $fillable = [
        'attendance_id',
        'action',
        'note',
        'actor_id',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
