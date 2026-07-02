<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $itDivision = Division::where('division_name', 'IT')->first();

        User::firstOrCreate(
            ['username' => 'superadmin'],
            [
                'email' => 'superadmin@erp.local',
                'password' => Hash::make('password'),
                'division_id' => $itDivision ? $itDivision->id : null,
                'role' => 'Admin',
                'task_role_id' => 1,
            ]
        );

        User::firstOrCreate(
            ['username' => 'abu'],
            [
                'email' => 'abu@erp.local',
                'password' => Hash::make('password'),
                'division_id' => $itDivision ? $itDivision->id : null,
                'role' => 'Staff',
                'task_role_id' => 3,
            ]
        );

        User::firstOrCreate(
            ['username' => 'ichsan'],
            [
                'email' => 'ichsan@erp.local',
                'password' => Hash::make('password'),
                'division_id' => $itDivision ? $itDivision->id : null,
                'role' => 'Manager',
                'task_role_id' => 2,
            ]
        );

    }
}
