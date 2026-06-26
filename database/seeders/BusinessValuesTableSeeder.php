<?php

namespace Database\Seeders;

use App\Models\BusinessValue;
use Illuminate\Database\Seeder;

class BusinessValuesTableSeeder extends Seeder
{
    public function run(): void
    {
        $values = [
            ['value_name' => 'High Value Account', 'description' => 'Nilai bisnis tinggi', 'status' => 'Active'],
            ['value_name' => 'Medium Value Account', 'description' => 'Nilai bisnis menengah', 'status' => 'Active'],
            ['value_name' => 'Low Value Account', 'description' => 'Nilai bisnis rendah', 'status' => 'Active'],
            ['value_name' => 'Strategic Account', 'description' => 'Nilai strategis', 'status' => 'Active'],
            ['value_name' => 'Transactional Account', 'description' => 'Nilai transaksional', 'status' => 'Active'],
        ];

        foreach ($values as $value) {
            BusinessValue::create($value);
        }
    }
}
