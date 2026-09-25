<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    use Loggable;

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'present_days',
        'working_days',
        'attended_days',
        'late_count',
        'sick_days',
        'leave_days',
        'absent_days',
        'base_salary_prorata',
        'total_gross',
        'total_deduction',
        'total_bpjs_company',
        'total_pph21',
        'total_thr',
        'total_net',
        'bank_name',
        'bank_account_no',
        'bank_account_name',
        'locked_at',
    ];

    protected $casts = [
        'present_days' => 'integer',
        'working_days' => 'integer',
        'attended_days' => 'integer',
        'late_count' => 'integer',
        'sick_days' => 'integer',
        'leave_days' => 'integer',
        'absent_days' => 'integer',
        'base_salary_prorata' => 'decimal:2',
        'total_gross' => 'decimal:2',
        'total_deduction' => 'decimal:2',
        'total_bpjs_company' => 'decimal:2',
        'total_pph21' => 'decimal:2',
        'total_thr' => 'decimal:2',
        'total_net' => 'decimal:2',
        'locked_at' => 'datetime',
    ];

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(PayslipComponent::class);
    }

    public function paidInstallments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class, 'paid_payslip_id');
    }
}
