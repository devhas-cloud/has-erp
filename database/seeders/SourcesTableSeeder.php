<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourcesTableSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['source_name' => 'WhatsApp', 'description' => 'Melalui WhatsApp', 'status' => 'Active'],
            ['source_name' => 'Phone Call', 'description' => 'Melalui panggilan telepon', 'status' => 'Active'],
            ['source_name' => 'Walk-in', 'description' => 'Datang langsung ke kantor', 'status' => 'Active'],
            ['source_name' => 'Website', 'description' => 'Melalui website perusahaan', 'status' => 'Active'],
            ['source_name' => 'Referral', 'description' => 'Referensi dari relasi', 'status' => 'Active'],
            ['source_name' => 'Advertisement', 'description' => 'Melalui iklan', 'status' => 'Active'],
            ['source_name' => 'Social Media', 'description' => 'Melalui media sosial', 'status' => 'Active'],
            ['source_name' => 'Event', 'description' => 'Melalui pameran/event', 'status' => 'Active'],
        ];

        foreach ($sources as $source) {
            Source::create($source);
        }
    }
}
