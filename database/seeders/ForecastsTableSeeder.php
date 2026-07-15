<?php

namespace Database\Seeders;

use App\Models\Forecast;
use Illuminate\Database\Seeder;

class ForecastsTableSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['forecast_name' => 'Omitted', 'description' => 'Tidak diikutkan dalam forecast'],
            ['forecast_name' => 'Pipeline', 'description' => 'Total keseluruhan pipeline'],
            ['forecast_name' => 'Best Case', 'description' => 'Skenario terbaik'],
            ['forecast_name' => 'Commit', 'description' => 'Sudah dikomitmenkan'],
            ['forecast_name' => 'Closed', 'description' => 'Sudah ditutup / won'],
        ];

        foreach ($items as $item) {
            Forecast::firstOrCreate(['forecast_name' => $item['forecast_name']], $item);
        }
    }
}
