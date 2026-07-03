<?php

namespace Database\Seeders;

use App\Models\TaskRole;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['role_name' => 'Admin',      'hierarchy_level' => 10, 'is_global_delegator' => true],
            ['role_name' => 'Manager',    'hierarchy_level' => 20, 'is_global_delegator' => false],
            ['role_name' => 'Supervisor', 'hierarchy_level' => 30, 'is_global_delegator' => false],
            ['role_name' => 'Staff',      'hierarchy_level' => 40, 'is_global_delegator' => false],
        ];

        foreach ($roles as $role) {
            TaskRole::firstOrCreate(
                ['role_name' => $role['role_name']],
                $role
            );
        }
    }
}
