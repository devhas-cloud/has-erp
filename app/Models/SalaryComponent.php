<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    public const TYPE_ALLOWANCE = 'allowance';
    public const TYPE_DEDUCTION = 'deduction';
    public const TYPE_BONUS = 'bonus';
    public const TYPE_OVERTIME = 'overtime';

    public const CALC_FIXED = 'fixed';
    public const CALC_PERCENT_BASE = 'percent_base';
    public const CALC_FIXED_DAILY = 'fixed_daily';
    public const CALC_BPJS_JHT_COMPANY = 'bpjs_jht_company';
    public const CALC_BPJS_JHT_EMPLOYEE = 'bpjs_jht_employee';
    public const CALC_BPJS_JP_COMPANY = 'bpjs_jp_company';
    public const CALC_BPJS_JP_EMPLOYEE = 'bpjs_jp_employee';
    public const CALC_BPJS_KESEHATAN_COMPANY = 'bpjs_kesehatan_company';
    public const CALC_BPJS_KESEHATAN_EMPLOYEE = 'bpjs_kesehatan_employee';
    public const CALC_BPJS_PENALTY = 'bpjs_penalty';

    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_DAILY = 'daily';

    public const BASIS_CALENDAR_DAYS = 'calendar_days';
    public const BASIS_WORKING_DAYS = 'working_days';
    public const BASIS_ATTENDED_DAYS = 'attended_days';

    protected $fillable = [
        'code',
        'name',
        'type',
        'calculation',
        'amount',
        'percent',
        'amount_cap',
        'is_taxable',
        'prorate_on_absence',
        'is_globally_assigned',
        'frequency',
        'prorate_basis',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percent' => 'decimal:3',
        'amount_cap' => 'decimal:2',
        'is_taxable' => 'boolean',
        'prorate_on_absence' => 'boolean',
        'is_globally_assigned' => 'boolean',
    ];

    public function employeeComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }
}
