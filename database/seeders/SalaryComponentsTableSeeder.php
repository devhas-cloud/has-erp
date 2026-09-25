<?php

namespace Database\Seeders;

use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class SalaryComponentsTableSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            [
                'code' => 'TJ_JABATAN',
                'name' => 'Tunjangan Jabatan',
                'type' => SalaryComponent::TYPE_ALLOWANCE,
                'calculation' => SalaryComponent::CALC_FIXED,
                'frequency' => SalaryComponent::FREQUENCY_MONTHLY,
                'prorate_basis' => SalaryComponent::BASIS_WORKING_DAYS,
                'amount' => 1500000,
                'prorate_on_absence' => true,
                'is_taxable' => true,
                'is_globally_assigned' => true,
            ],
            [
                'code' => 'UANG_MAKAN',
                'name' => 'Uang Makan',
                'type' => SalaryComponent::TYPE_ALLOWANCE,
                'calculation' => SalaryComponent::CALC_FIXED_DAILY,
                'frequency' => SalaryComponent::FREQUENCY_DAILY,
                'prorate_basis' => SalaryComponent::BASIS_ATTENDED_DAYS,
                'amount' => 50000,
                'prorate_on_absence' => false,
                'is_taxable' => false,
                'is_globally_assigned' => true,
            ],
            [
                'code' => 'UANG_TRANSPORT',
                'name' => 'Uang Transportasi',
                'type' => SalaryComponent::TYPE_ALLOWANCE,
                'calculation' => SalaryComponent::CALC_FIXED_DAILY,
                'frequency' => SalaryComponent::FREQUENCY_DAILY,
                'prorate_basis' => SalaryComponent::BASIS_ATTENDED_DAYS,
                'amount' => 50000,
                'prorate_on_absence' => false,
                'is_taxable' => false,
                'is_globally_assigned' => true,
            ],
            [
                // THR hanya di bayar pada periode is_thr = true (bulannya set di config)
                'code' => 'THR',
                'name' => 'Tunjangan Hari Raya',
                'type' => SalaryComponent::TYPE_BONUS,
                'calculation' => SalaryComponent::CALC_PERCENT_BASE,
                'frequency' => SalaryComponent::FREQUENCY_MONTHLY,
                'prorate_basis' => SalaryComponent::BASIS_CALENDAR_DAYS,
                'percent' => 100,
                'prorate_on_absence' => false,
                'is_taxable' => true,
                'is_globally_assigned' => true,
            ],
            [
                // BPJS — potongan karyawan (potongan sisi perusahaan dicatat sebagai info benefit)
                'code' => 'BPJS_KS_EMP',
                'name' => 'BPJS Kesehatan (Karyawan)',
                'type' => SalaryComponent::TYPE_DEDUCTION,
                'calculation' => SalaryComponent::CALC_BPJS_KESEHATAN_EMPLOYEE,
                'frequency' => SalaryComponent::FREQUENCY_MONTHLY,
                'prorate_basis' => SalaryComponent::BASIS_CALENDAR_DAYS,
                'percent' => 1.0,
                'prorate_on_absence' => false,
                'is_taxable' => false,
                'is_globally_assigned' => true,
            ],
            [
                'code' => 'BPJS_JHT_EMP',
                'name' => 'BPJS JHT (Karyawan)',
                'type' => SalaryComponent::TYPE_DEDUCTION,
                'calculation' => SalaryComponent::CALC_BPJS_JHT_EMPLOYEE,
                'frequency' => SalaryComponent::FREQUENCY_MONTHLY,
                'prorate_basis' => SalaryComponent::BASIS_CALENDAR_DAYS,
                'percent' => 2.0,
                'prorate_on_absence' => false,
                'is_taxable' => false,
                'is_globally_assigned' => true,
            ],
            [
                'code' => 'BPJS_JP_EMP',
                'name' => 'BPJS JP (Karyawan)',
                'type' => SalaryComponent::TYPE_DEDUCTION,
                'calculation' => SalaryComponent::CALC_BPJS_JP_EMPLOYEE,
                'frequency' => SalaryComponent::FREQUENCY_MONTHLY,
                'prorate_basis' => SalaryComponent::BASIS_CALENDAR_DAYS,
                'percent' => 1.0,
                'prorate_on_absence' => false,
                'is_taxable' => false,
                'is_globally_assigned' => true,
            ],
            [
                // BPJS company share — info benefit slip (tidak masuk gross)
                'code' => 'BPJS_KS_COMPANY',
                'name' => 'BPJS Kesehatan (Perusahaan)',
                'type' => SalaryComponent::TYPE_DEDUCTION,
                'calculation' => SalaryComponent::CALC_BPJS_KESEHATAN_COMPANY,
                'frequency' => SalaryComponent::FREQUENCY_MONTHLY,
                'prorate_basis' => SalaryComponent::BASIS_CALENDAR_DAYS,
                'percent' => 4.0,
                'prorate_on_absence' => false,
                'is_taxable' => false,
                'is_globally_assigned' => true,
            ],
            [
                'code' => 'BPJS_JHT_COMPANY',
                'name' => 'BPJS JHT (Perusahaan)',
                'type' => SalaryComponent::TYPE_DEDUCTION,
                'calculation' => SalaryComponent::CALC_BPJS_JHT_COMPANY,
                'frequency' => SalaryComponent::FREQUENCY_MONTHLY,
                'prorate_basis' => SalaryComponent::BASIS_CALENDAR_DAYS,
                'percent' => 3.67,
                'prorate_on_absence' => false,
                'is_taxable' => false,
                'is_globally_assigned' => true,
            ],
            [
                'code' => 'BPJS_JP_COMPANY',
                'name' => 'BPJS JP (Perusahaan)',
                'type' => SalaryComponent::TYPE_DEDUCTION,
                'calculation' => SalaryComponent::CALC_BPJS_JP_COMPANY,
                'frequency' => SalaryComponent::FREQUENCY_MONTHLY,
                'prorate_basis' => SalaryComponent::BASIS_CALENDAR_DAYS,
                'percent' => 2.0,
                'prorate_on_absence' => false,
                'is_taxable' => false,
                'is_globally_assigned' => true,
            ],
        ];

        foreach ($components as $component) {
            SalaryComponent::firstOrCreate(['code' => $component['code']], $component);
        }
    }
}
