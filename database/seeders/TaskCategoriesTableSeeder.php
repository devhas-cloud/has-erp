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
                'name' => 'General Meeting',
                'description' => 'Rapat dan meeting umum',
                'division_id' => null,
            ],
            [
                'name' => 'Training',
                'description' => 'Kegiatan pelatihan dan workshop',
                'division_id' => null,
            ],
            [
                'name' => 'Bug Fixing',
                'description' => 'Perbaikan bug dan issue teknis',
                'division_id' => $itDivision ? $itDivision->id : null,
            ],
            [
                'name' => 'Development',
                'description' => 'Pengembangan fitur dan sistem baru',
                'division_id' => $itDivision ? $itDivision->id : null,
            ],
            [
                'name' => 'Social Media Campaign',
                'description' => 'Kampanye dan konten media sosial',
                'division_id' => $marketingDivision ? $marketingDivision->id : null,
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
