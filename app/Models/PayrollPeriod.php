<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use Loggable;

    public const STATUS_OPEN = 'open';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'is_thr',
        'processed_at',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_thr' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PayrollLog::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
