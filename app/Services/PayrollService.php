<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollLog;
use App\Models\Payslip;
use App\Models\PayslipComponent;
use App\Models\SalaryComponent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PayrollService
{
    public const CUTOFF_START_DAY = 25;
    public const CUTOFF_END_DAY = 24;

    /**
     * Nama periode auto dari cutoff 25→24: "2026-09-25 s/d 2026-10-24".
     */
    public static function nextPeriodRange(): array
    {
        $lastPeriod = PayrollPeriod::orderByDesc('end_date')->first();

        if ($lastPeriod) {
            $start = $lastPeriod->end_date->copy()->addDay();
        } else {
            $now = now();
            $start = $now->day >= self::CUTOFF_START_DAY
                ? $now->copy()->startOfMonth()->setDay(self::CUTOFF_START_DAY)
                : $now->copy()->subMonthNoOverflow()->startOfMonth()->setDay(self::CUTOFF_START_DAY);
        }

        $end = $start->copy()->addMonthNoOverflow()->startOfMonth()->setDay(self::CUTOFF_END_DAY);

        return [$start, $end];
    }

    /**
     * Daftar karyawan aktif + ringkasan data untuk Step Compile preview.
     */
    public static function compilePreview(PayrollPeriod $period): array
    {
        $start = $period->start_date->copy()->setDay(self::CUTOFF_START_DAY)->month;
        $employees = Employee::where('status', Employee::STATUS_ACTIVE)->get();

        $rows = [];
        foreach ($employees as $employee) {
            $attendanceSummary = self::attendanceSummary($period, $employee);
            $leaveUnpaid = self::unpaidLeaveDays($period, $employee);
            $loanInstallment = LoanInstallment::whereHas('loan', fn ($q) => $q
                ->where('employee_id', $employee->id)
                ->where('status', Loan::STATUS_ACTIVE))
                ->where('status', LoanInstallment::STATUS_UNPAID)
                ->orderBy('installment_no')
                ->first();
            $overtimeAmount = OvertimeRequest::where('employee_id', $employee->id)
                ->where('status', OvertimeRequest::STATUS_APPROVED)
                ->whereBetween('work_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
                ->sum('estimated_amount');

            $rows[] = [
                'id' => $employee->id,
                'employee_no' => $employee->employee_no,
                'name' => $employee->name,
                'base_salary' => (float) $employee->base_salary,
                'base_prorata' => round($employee->base_salary * $attendanceSummary['working_days'] / max(1, $attendanceSummary['calendar_days']), 2),
                'attendance' => $attendanceSummary,
                'leave_unpaid' => $leaveUnpaid,
                'loan_installment' => $loanInstallment ? [
                    'id' => $loanInstallment->id,
                    'due' => $loanInstallment->due_date->format('d-m-Y'),
                    'amount' => (float) $loanInstallment->amount,
                ] : null,
                'overtime_amount' => (float) $overtimeAmount,
            ];
        }

        return $rows;
    }

    /**
     * Generate payslips draft utk seluruh karyawan active di periode.
     */
    public static function generateDraft(PayrollPeriod $period, ?array $employeeIds = null): int
    {
        $period->update(['status' => PayrollPeriod::STATUS_PROCESSING]);

        $query = Employee::where('status', Employee::STATUS_ACTIVE);
        if ($employeeIds) {
            $query->whereIn('id', $employeeIds);
        }

        $created = 0;

        $query->get()->each(function (Employee $employee) use ($period, &$created) {
            $summary = self::attendanceSummary($period, $employee);

            $existing = Payslip::updateOrCreate([
                'payroll_period_id' => $period->id,
                'employee_id' => $employee->id,
            ], [
                'present_days' => $summary['present_days'],
                'working_days' => $summary['working_days'],
                'attended_days' => $summary['attended_days'],
                'late_count' => $summary['late_count'],
                'sick_days' => $summary['sick_days'],
                'leave_days' => $summary['leave_days'],
                'absent_days' => $summary['absent_days'],
                'base_salary_prorata' => round($employee->base_salary * $summary['working_days'] / max(1, $summary['calendar_days']), 2),
            ]);

            PayslipComponent::where('payslip_id', $existing->id)->delete();
            self::composeComponents($period, $employee, $existing, $summary);

            $created++;
        });

        return $created;
    }

    /**
     * susun komponen per payslip — utama (monthly + daily + THR + overtime) & potongan.
     */
    public static function composeComponents(PayrollPeriod $period, Employee $employee, Payslip $payslip, array $summary): void
    {
        DB::transaction(function () use ($period, $employee, $payslip, $summary) {
            PayslipComponent::where('payslip_id', $payslip->id)->delete();

            $sortOrder = 1;
            $totalGross = (float) $payslip->base_salary_prorata;
            $totalDeduction = $totalBpjsCompany = $totalThr = 0.0;
            $taxableBase = (float) $payslip->base_salary_prorata;
            $nonTaxable = 0.0;

            $masterComponents = \App\Models\SalaryComponent::where('is_globally_assigned', true)->get();

            /** @var \App\Models\EmployeeSalaryComponent $overrideRow */
            $overrides = $employee->salaryComponents->keyBy('salary_component_id');

            foreach ($masterComponents as $component) {
                /** @var \App\Models\SalaryComponent $component */
                $override = $overrides->get($component->id);
                $amountBase = $override && $override->is_active
                    ? (float) ($override->override_amount ?? $component->amount)
                    : (float) $component->amount;

                $payAmount = self::computeComponent($component, $amountBase, $summary, $payslip, $employee);

                // BPJS potongan karyawan & benefit perusahaan — rate dari config (tidak hardcode)
                $bpjsRates = config('er.payroll.bpjs', []);

                $bpjsRateByCalc = [
                    SalaryComponent::CALC_BPJS_JHT_EMPLOYEE => (float) ($bpjsRates['jht_employee'] ?? 2.0),
                    SalaryComponent::CALC_BPJS_JP_EMPLOYEE => (float) ($bpjsRates['jp_employee'] ?? 1.0),
                    SalaryComponent::CALC_BPJS_KESEHATAN_EMPLOYEE => (float) ($bpjsRates['kesehatan_employee'] ?? 1.0),
                    SalaryComponent::CALC_BPJS_JHT_COMPANY => (float) ($bpjsRates['jht_company'] ?? 3.67),
                    SalaryComponent::CALC_BPJS_JP_COMPANY => (float) ($bpjsRates['jp_company'] ?? 2.0),
                    SalaryComponent::CALC_BPJS_KESEHATAN_COMPANY => (float) ($bpjsRates['kesehatan_company'] ?? 4.0),
                ];

                if (isset($bpjsRateByCalc[$component->calculation])) {
                    $payAmount = min((float) $employee->base_salary, (float) config('er.payroll.bpjs.salary_cap', 12000000))
                        * $bpjsRateByCalc[$component->calculation] / 100;
                }

                if ($component->calculation === SalaryComponent::CALC_PERCENT_BASE) {
                    // THR: percent dari base salary, hanya periode is_thr
                    if (! $period->is_thr) {
                        continue;
                    }

                    $payAmount = round($employee->base_salary * (float) $component->percent / 100, 2);
                    $totalThr += $payAmount;
                }

                if ($payAmount <= 0) {
                    continue;
                }

                $bpjsShare = in_array($component->calculation, [
                    SalaryComponent::CALC_BPJS_JHT_COMPANY,
                    SalaryComponent::CALC_BPJS_JP_COMPANY,
                    SalaryComponent::CALC_BPJS_KESEHATAN_COMPANY,
                ], true);

                if ($bpjsShare) {
                    $totalBpjsCompany += $payAmount;
                }

                PayslipComponent::create([
                    'payslip_id' => $payslip->id,
                    'salary_component_id' => $component->id,
                    'label' => $component->name,
                    'type' => $component->type,
                    'amount' => $payAmount,
                    'sort_order' => $sortOrder++,
                    'source_type' => 'salary_component',
                    'source_id' => $component->id,
                ]);

                if ($bpjsShare) {
                    continue; // benefit perusahaan — info slip, bukan pendapatan gross
                }

                if ($component->type === SalaryComponent::TYPE_DEDUCTION) {
                    // BPJS karyawan dll — masuk potongan, bukan pendapatan
                    $totalDeduction += $payAmount;
                    continue;
                }

                // thr dua jalur
                if ($component->calculation === SalaryComponent::CALC_PERCENT_BASE) {
                    $taxableBase += $payAmount;
                    $totalGross += $payAmount;
                    continue;
                }

                if ($component->is_taxable) {
                    $taxableBase += $payAmount;
                } else {
                    $nonTaxable += $payAmount;
                }

                $totalGross += $payAmount;
            }

            // Upah lembur (approved dalam periode)
            $overtime = OvertimeRequest::where('employee_id', $employee->id)
                ->where('status', OvertimeRequest::STATUS_APPROVED)
                ->whereBetween('work_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
                ->get();

            foreach ($overtime as $ot) {
                PayslipComponent::create([
                    'payslip_id' => $payslip->id,
                    'label' => 'Upah Lembur '.$ot->work_date->format('d-m-Y'),
                    'type' => 'overtime',
                    'amount' => (float) $ot->estimated_amount,
                    'sort_order' => $sortOrder++,
                    'source_type' => 'overtime_request',
                    'source_id' => $ot->id,
                ]);

                $totalGross += (float) $ot->estimated_amount;
                $taxableBase += (float) $ot->estimated_amount;
            }

            // Potongan angsuran loan jatuh tempo periode — langsung tandai PAID
            // dan tautkan paid_payslip_id agar tidak terpotong ganda di periode
            // berikutnya dan progress bar loan ikut maju.
            $loanInstallments = LoanInstallment::whereHas('loan', fn ($q) => $q
                ->where('employee_id', $employee->id)
                ->where('status', Loan::STATUS_ACTIVE))
                ->where('status', LoanInstallment::STATUS_UNPAID)
                ->whereBetween('due_date', [$period->start_date, $period->end_date])
                ->get();

            foreach ($loanInstallments as $installment) {
                PayslipComponent::create([
                    'payslip_id' => $payslip->id,
                    'label' => 'Angsuran Pinjaman (Inst. '.$installment->installment_no.')',
                    'type' => 'deduction',
                    'amount' => (float) $installment->amount,
                    'sort_order' => $sortOrder++,
                    'source_type' => 'loan_installment',
                    'source_id' => $installment->id,
                ]);

                $totalDeduction += (float) $installment->amount;

                $installment->update([
                    'status' => LoanInstallment::STATUS_PAID,
                    'paid_payslip_id' => $payslip->id,
                    'paid_at' => now(),
                ]);
            }

            // otomatis lunas bila semua angsuran selesai
            if ($loanInstallments->isNotEmpty()) {
                $lastInstallment = $loanInstallments->last();
                $lastInstallment->loan?->refresh();
                if ($lastInstallment->loan && $lastInstallment->loan->installments()->where('status', LoanInstallment::STATUS_UNPAID)->doesntExist()) {
                    $lastInstallment->loan->update(['status' => Loan::STATUS_PAID_OFF]);
                }
            }

            // PPH21 sederhana: (taxable income − monthly deductible) × rate
            $pph21 = self::pph21($taxableBase, $employee->ptkp_status);
            if ($pph21 > 0) {
                PayslipComponent::create([
                    'payslip_id' => $payslip->id,
                    'label' => 'PPh 21',
                    'type' => 'deduction',
                    'amount' => $pph21,
                    'sort_order' => $sortOrder++,
                    'source_type' => 'pph21',
                ]);
                $totalDeduction += $pph21;
            }

            $payslip->update([
                'total_gross' => round($totalGross, 2),
                'total_deduction' => round($totalDeduction, 2),
                'total_bpjs_company' => round($totalBpjsCompany, 2),
                'total_pph21' => round($pph21, 2),
                'total_thr' => round($totalThr, 2),
                'total_net' => round($totalGross - $totalDeduction, 2),
            ]);
        });
    }

    private static function computeComponent(\App\Models\SalaryComponent $component, float $amount, array $summary, Payslip $payslip, Employee $employee): float
    {
        // daily (uang makan/transport): dibayar per hari HADIR saja
        if ($component->frequency === SalaryComponent::FREQUENCY_DAILY
            || $component->calculation === SalaryComponent::CALC_FIXED_DAILY) {
            return round($amount * $summary['attended_days'], 2);
        }

        if ($component->calculation === SalaryComponent::CALC_FIXED) {
            // prorata saat berstatus kerja (working_days vs standard) bila flag aktif
            if ($component->prorate_on_absence) {
                $stdWorking = max(1, (int) config('er.payroll.standard_working_days', 22));

                return round($amount * min(1, $summary['working_days'] / $stdWorking), 2);
            }

            return round($amount, 2);
        }

        return 0.0;
    }

    /**
     * PPH21 sederhana per bulan: (taxable − PTKP bulanan) dikenai tarif progresif
     * dari config. PTKP bulanan diturunkan dari status ptkp karyawan.
     */
    private static function pph21(float $taxableBase, string $ptkp = 'TK0'): float
    {
        $ptkpYearly = [
            'TK0' => 54000000, 'TK1' => 58500000, 'TK2' => 63000000, 'TK3' => 67500000,
            'K0' => 58500000, 'K1' => 63000000, 'K2' => 67500000, 'K3' => 72000000,
        ];

        $monthlyExemption = ($ptkpYearly[$ptkp] ?? $ptkpYearly['TK0']) / 12;

        $penghasilanKenaPajak = max(0, $taxableBase - $monthlyExemption);

        if ($penghasilanKenaPajak <= 0) {
            return 0.0;
        }

        // tarif progresif bulanan (rate config, default 5% flat utk tarif lapis pertama)
        $rates = config('er.payroll.pph21_rates', [['limit_up_to' => null, 'rate' => 5.0]]);

        $tax = 0.0;
        $remaining = $penghasilanKenaPajak;

        foreach ($rates as $i => $band) {
            if ($remaining <= 0) {
                break;
            }

            $limit = $band['limit_up_to'] ?? null;
            $rate = (float) $band['rate'];

            if ($limit === null) {
                $tax += $remaining * $rate / 100;
                $remaining = 0;
            } else {
                $taxableBand = min($remaining, $limit);
                $tax += $taxableBand * $rate / 100;
                $remaining -= $taxableBand;
            }
        }

        return round($tax, 2);
    }

    public static function attendanceSummary(PayrollPeriod $period, Employee $employee): array
    {
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
            ->get();

        $presentDays = $attendances->where('status', Attendance::STATUS_PRESENT)
            ->where('check_in_method', '!=', Attendance::METHOD_LEAVE_INTEGRATION)
            ->count();
        $lateCount = $attendances->where('status', Attendance::STATUS_LATE)->count();

        return [
            'calendar_days' => (int) $period->start_date->diffInDays($period->end_date) + 1,
            'working_days' => self::countWorkingDays($period->start_date, $period->end_date),
            'attended_days' => $presentDays + $lateCount,
            'present_days' => $presentDays,
            'late_count' => $lateCount,
            'sick_days' => $attendances->where('status', Attendance::STATUS_SICK)->count(),
            'leave_days' => $attendances->where('status', Attendance::STATUS_LEAVE)->count(),
            'absent_days' => $attendances->where('status', Attendance::STATUS_ABSENT)->count(),
        ];
    }

    public static function countWorkingDays(Carbon $start, Carbon $end): int
    {
        $count = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (! in_array($date->dayOfWeekIso, [6, 7], true)) {
                $count++;
            }
        }

        return $count;
    }

    public static function unpaidLeaveDays(PayrollPeriod $period, Employee $employee): int
    {
        // Hari absen (alpa) dalam periode
        $absent = Attendance::where('employee_id', $employee->id)
            ->where('status', Attendance::STATUS_ABSENT)
            ->whereBetween('work_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
            ->count();

        // Hari cuti UNPAID (leave type paid=false) yang approved — via leave_requests
        // yg terintegrasi ke attendance status leave.
        $unpaidDays = \App\Models\LeaveRequest::where('employee_id', $employee->id)
            ->where('status', \App\Models\LeaveRequest::STATUS_APPROVED)
            ->whereHas('leaveType', fn ($q) => $q->where('paid', false))
            ->whereBetween('end_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
            ->get()
            ->sum('days');

        return $absent + (int) $unpaidDays;
    }

    public static function logPeriod(PayrollPeriod $period, string $action, string $note = null): void
    {
        PayrollLog::create([
            'payroll_period_id' => $period->id,
            'action' => $action,
            'note' => $note,
            'actor_id' => auth()->id(),
        ]);
    }
}
