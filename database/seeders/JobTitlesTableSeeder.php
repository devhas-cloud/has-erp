<?php

namespace Database\Seeders;

use App\Models\JobTitle;
use Illuminate\Database\Seeder;

class JobTitlesTableSeeder extends Seeder
{
    public function run(): void
    {
        $titles = [
            ['title_name' => 'CEO', 'description' => 'Chief Executive Officer', 'status' => 'Active'],
            ['title_name' => 'Manager', 'description' => 'Manager', 'status' => 'Active'],
            ['title_name' => 'Supervisor', 'description' => 'Supervisor', 'status' => 'Active'],
            ['title_name' => 'Staff', 'description' => 'Staff', 'status' => 'Active'],
            ['title_name' => 'Intern', 'description' => 'Intern / Magang', 'status' => 'Active'],
        ];

        foreach ($titles as $title) {
            JobTitle::firstOrCreate(
                ['title_name' => $title['title_name']],
                $title
            );
        }
    }
}
