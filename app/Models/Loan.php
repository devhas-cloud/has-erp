<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use Loggable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAID_OFF = 'paid_off';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SETTLED = 'settled';

    protected $fillable = [
        'loan_no',
        'employee_id',
        'amount',
        'tenor_months',
        'installment_amount',
        'fee_amount',
        'purpose',
        'disburse_date',
        'status',
        'approved_by',
        'approved_at',
        'decision_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'tenor_months' => 'integer',
        'disburse_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }
}
