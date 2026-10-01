<?php

namespace Database\Seeders;

use App\Models\BusinessEntity;
use Illuminate\Database\Seeder;

class BusinessEntitiesTableSeeder extends Seeder
{
    public function run(): void
    {
        // Referensi dari export Salesforce (Account.Business_Entity__c) — 13 nilai.
        BusinessEntity::query()->delete();

        $entities = [
            ['entity_name' => 'BUMN'],
            ['entity_name' => 'CV'],
            ['entity_name' => 'Dinas'],
            ['entity_name' => 'Government'],
            ['entity_name' => 'Ltd'],
            ['entity_name' => 'Others'],
            ['entity_name' => 'Perguruan Tinggi Negeri'],
            ['entity_name' => 'Perguruan Tinggi Swasta'],
            ['entity_name' => 'Personal'],
            ['entity_name' => 'Private Sector'],
            ['entity_name' => 'PT'],
            ['entity_name' => 'University'],
            ['entity_name' => 'Vocational Education'],
        ];

        foreach ($entities as $entity) {
            BusinessEntity::create($entity + ['description' => $entity['entity_name'], 'status' => 'Active']);
        }
    }
}
