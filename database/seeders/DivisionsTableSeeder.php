<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            ['division_name' => 'Admin', 'description' => 'Administrasi', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'WATER', 'description' => 'Water Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'IMS', 'description' => 'IMS Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'PD', 'description' => 'PD Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'Marketing', 'description' => 'Marketing Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'Sales', 'description' => 'Sales Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'Finance', 'description' => 'Finance Management', 'type' => 'Internal', 'status' => 'Active'],
            ['division_name' => 'ER', 'description' => 'ER Management', 'type' => 'External', 'status' => 'Active'],
            ['division_name' => 'GA', 'description' => 'GA Management', 'type' => 'External', 'status' => 'Active'],
        ];


        foreach ($divisions as $division) {
            Division::firstOrCreate(
                ['division_name' => $division['division_name']],
                $division
            );
        }
    }
}
