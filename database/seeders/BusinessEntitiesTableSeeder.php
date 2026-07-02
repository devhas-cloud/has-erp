<?php

namespace Database\Seeders;

use App\Models\BusinessEntity;
use Illuminate\Database\Seeder;

class BusinessEntitiesTableSeeder extends Seeder
{
    public function run(): void
    {
        $entities = [
            ['entity_name' => 'BUMN', 'description' => 'Badan Usaha Milik Negara', 'status' => 'Active'],
            ['entity_name' => 'Perguruan Tinggi Negri', 'description' => 'Perguruan Tinggi Negeri', 'status' => 'Active'],
            ['entity_name' => 'Perguruan Tinggi Swasta', 'description' => 'Perguruan Tinggi Swasta', 'status' => 'Active'],
            ['entity_name' => 'Government', 'description' => 'Pemerintah', 'status' => 'Active'],
            ['entity_name' => 'Personal', 'description' => 'Personal', 'status' => 'Active'],
            ['entity_name' => 'Yayasan', 'description' => 'Yayasan', 'status' => 'Active'],
            ['entity_name' => 'Perusahaan Swasta Nasional', 'description' => 'Perusahaan Swasta Nasional', 'status' => 'Active'],
            ['entity_name' => 'Perusahaan Swasta Multinasional', 'description' => 'Perusahaan Swasta Multinasional', 'status' => 'Active'],
        ];

        foreach ($entities as $entity) {
            BusinessEntity::firstOrCreate(
                ['entity_name' => $entity['entity_name']],
                $entity
            );
        }
    }
}
