<?php

namespace Database\Seeders;

use App\Models\RoleInProject;
use Illuminate\Database\Seeder;

class RoleInProjectsTableSeeder extends Seeder
{
    public function run(): void
    {
        // Nilai dari export Salesforce (Contact.Role_in_Project__c).
        $roles = [
            ['role_name' => 'Project Owner', 'status' => 'Active'],
            ['role_name' => 'Decision Maker', 'status' => 'Active'],
            ['role_name' => 'Influencer', 'status' => 'Active'],
            ['role_name' => 'Champion', 'status' => 'Active'],
            ['role_name' => 'End User', 'status' => 'Active'],
            ['role_name' => 'Procurement', 'status' => 'Active'],
        ];

        foreach ($roles as $role) {
            RoleInProject::firstOrCreate(
                ['role_name' => $role['role_name']],
                $role + ['description' => $role['role_name']]
            );
        }
    }
}
