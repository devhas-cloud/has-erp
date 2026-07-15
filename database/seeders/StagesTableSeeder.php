<?php

namespace Database\Seeders;

use App\Models\Stage;
use Illuminate\Database\Seeder;

class StagesTableSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['stage_name' => 'New', 'description' => 'Lead baru'],
            ['stage_name' => 'Proposal & Quote', 'description' => 'Proposal dan penawaran'],
            ['stage_name' => 'In Review', 'description' => 'Dalam proses review'],
            ['stage_name' => 'Negotiation', 'description' => 'Negosiasi'],
            ['stage_name' => 'Closed Won', 'description' => 'Berhasil ditutup'],
            ['stage_name' => 'Closed Lost', 'description' => 'Gagal ditutup'],
        ];

        foreach ($items as $item) {
            Stage::firstOrCreate(['stage_name' => $item['stage_name']], $item);
        }
    }
}
