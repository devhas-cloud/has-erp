<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            ['division_name' => 'IT', 'description' => 'Teknologi Informasi', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'HR', 'description' => 'Sumber Daya Manusia', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'Finance', 'description' => 'Keuangan', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'Marketing', 'description' => 'Pemasaran', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'Sales', 'description' => 'Penjualan', 'type' => 'Internal', 'status' => 'Active'],
        ];

        foreach ($divisions as $division) {
            Division::firstOrCreate(
                ['division_name' => $division['division_name']],
                $division
            );
        }
    }
}
