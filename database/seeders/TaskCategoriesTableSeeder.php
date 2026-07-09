<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\TaskCategory;
use Illuminate\Database\Seeder;

class TaskCategoriesTableSeeder extends Seeder
{
    public function run(): void
    {
        $itDivision = Division::where('division_name', 'IT')->first();
        $marketingDivision = Division::where('division_name', 'Marketing')->first();

        $categories = [
            [
                'name' => 'Visit',
                'description' => 'Rapat dan meeting umum',
                'division_id' => null,
            ],
            [
                'name' => 'Training',
                'description' => 'Kegiatan pelatihan dan workshop',
                'division_id' => null,
            ],
            [
                'name' => 'Seminar',
                'description' => 'Kegiatan seminar dan konferensi',
                'division_id' => null,
            ],


        ];

        foreach ($categories as $category) {
            TaskCategory::firstOrCreate(
                ['name' => $category['name'], 'division_id' => $category['division_id']],
                $category
            );
        }
    }
}
