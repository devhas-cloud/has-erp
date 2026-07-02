<?php

namespace Database\Seeders;

use App\Models\InteractionLevel;
use Illuminate\Database\Seeder;

class InteractionLevelsTableSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['level_name' => 'Hot Account', 'description' => 'Akun dengan interaksi tinggi dan peluang bisnis yang besar', 'status' => 'Active'],
            ['level_name' => 'Warm Account', 'description' => 'Akun dengan interaksi sedang dan peluang bisnis yang moderat', 'status' => 'Active'],
            ['level_name' => 'Cold Account', 'description' => 'Akun dengan interaksi rendah dan peluang bisnis yang kecil', 'status' => 'Active'],
        ];

        foreach ($levels as $level) {
            InteractionLevel::firstOrCreate(
                ['level_name' => $level['level_name']],
                $level
            );
        }
    }
}
