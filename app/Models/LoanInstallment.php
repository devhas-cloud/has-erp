<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInstallment extends Model
{
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'loan_id',
        'installment_no',
        'due_date',
        'amount',
        'status',
        'paid_payslip_id',
        'paid_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'installment_no' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function paidPayslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class, 'paid_payslip_id');
    }
}
