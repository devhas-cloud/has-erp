<?php

namespace Database\Seeders;

use App\Models\Segmentation;
use Illuminate\Database\Seeder;

class SegmentationsTableSeeder extends Seeder
{
    public function run(): void
    {
        $segments = [
            ['segmentation_name' => 'Enterprise', 'description' => 'Perusahaan besar', 'status' => 'Active'],
            ['segmentation_name' => 'SME', 'description' => 'Usaha Kecil Menengah', 'status' => 'Active'],
            ['segmentation_name' => 'Startup', 'description' => 'Perusahaan rintisan', 'status' => 'Active'],
            ['segmentation_name' => 'Government', 'description' => 'Instansi pemerintah', 'status' => 'Active'],
            ['segmentation_name' => 'Non-Profit', 'description' => 'Organisasi non-profit', 'status' => 'Active'],
        ];

        foreach ($segments as $segment) {
            Segmentation::firstOrCreate(
                ['segmentation_name' => $segment['segmentation_name']],
                $segment
            );
        }
    }
}
