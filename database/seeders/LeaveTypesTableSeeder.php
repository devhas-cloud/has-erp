<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypesTableSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'ANN',
                'name' => 'Cuti Tahunan',
                'quota_days' => 12,
                'paid' => true,
                'is_proof_required' => false,
            ],
            [
                'code' => 'IZN',
                'name' => 'Izin',
                'quota_days' => null,
                'paid' => true,
                'is_proof_required' => false,
            ],
            [
                'code' => 'SCK',
                'name' => 'Sakit',
                'quota_days' => null,
                'paid' => true,
                'is_proof_required' => true,
            ],
            [
                'code' => 'UNP',
                'name' => 'Cuti Unpaid',
                'quota_days' => null,
                'paid' => false,
                'is_proof_required' => false,
            ],
            [
                'code' => 'SPL',
                'name' => 'Cuti Special',
                'quota_days' => null,
                'paid' => true,
                'is_proof_required' => false,
            ],
        ];

        foreach ($types as $type) {
            LeaveType::firstOrCreate(['code' => $type['code']], $type);
        }
    }
}
