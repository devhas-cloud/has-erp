<?php

namespace Database\Seeders;

use App\Models\LossReason;
use Illuminate\Database\Seeder;

class LossReasonsTableSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['reason_name' => 'Lost to Competitor', 'description' => 'Kalah dari kompetitor'],
            ['reason_name' => 'No Budget / Lost Funding', 'description' => 'Tidak ada budget atau kehilangan pendanaan'],
            ['reason_name' => 'No Decision / Non-Responsive', 'description' => 'Tidak ada keputusan atau tidak responsif'],
            ['reason_name' => 'Price', 'description' => 'Masalah harga'],
            ['reason_name' => 'Other', 'description' => 'Alasan lainnya'],
        ];

        foreach ($items as $item) {
            LossReason::firstOrCreate(['reason_name' => $item['reason_name']], $item);
        }
    }
}
