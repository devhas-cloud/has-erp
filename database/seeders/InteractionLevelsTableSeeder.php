<?php

namespace Database\Seeders;

use App\Models\InteractionLevel;
use Illuminate\Database\Seeder;

class InteractionLevelsTableSeeder extends Seeder
{
    public function run(): void
    {
        // Nilai dari export Salesforce (Account.Interaction_Level__c).
        $levels = [
            ['level_name' => 'Hot Account', 'status' => 'Active'],
            ['level_name' => 'Warm Account', 'status' => 'Active'],
            ['level_name' => 'Cold Account', 'status' => 'Active'],
            ['level_name' => 'New Account', 'status' => 'Active'],
            ['level_name' => 'Existing Account', 'status' => 'Active'],
            ['level_name' => 'Dormant Account', 'status' => 'Active'],
            ['level_name' => 'Strategic Account', 'status' => 'Active'],
        ];

        foreach ($levels as $level) {
            InteractionLevel::firstOrCreate(
                ['level_name' => $level['level_name']],
                $level + ['description' => $level['level_name']]
            );
        }
    }
}
