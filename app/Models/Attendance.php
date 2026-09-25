<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use Loggable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PRESENT = 'present';
    public const STATUS_LATE = 'late';
    public const STATUS_LEAVE = 'leave';
    public const STATUS_SICK = 'sick';
    public const STATUS_ABSENT = 'absent';

    public const METHOD_FINGERPRINT = 'fingerprint';
    public const METHOD_GPS = 'gps';
    public const METHOD_FACE = 'face';
    public const METHOD_MANUAL = 'manual';
    public const METHOD_LEAVE_INTEGRATION = 'leave_integration';

    public const SOURCE_WEB = 'web';
    public const SOURCE_DEVICE = 'device';
    public const SOURCE_API = 'api';
    public const SOURCE_AUTO = 'auto';

    protected $fillable = [
        'employee_id',
        'work_date',
        'shift_id',
        'clock_in',
        'clock_out',
        'late_minutes',
        'status',
        'check_in_method',
        'source',
        'office_id',
        'check_in_lat',
        'check_in_lng',
        'photo_path',
        'face_matched',
        'note',
        'created_by',
    ];

    protected $casts = [
        'work_date' => 'date',
        'clock_in' => 'datetime:H:i',
        'clock_out' => 'datetime:H:i',
        'late_minutes' => 'integer',
        'face_matched' => 'boolean',
        'check_in_lat' => 'decimal:8',
        'check_in_lng' => 'decimal:8',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AttendanceApproval::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
