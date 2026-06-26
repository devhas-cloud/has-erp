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

        User::create([
            'username' => 'superadmin',
            'email' => 'superadmin@erp.local',
            'password' => Hash::make('password'),
            'division_id' => $itDivision ? $itDivision->id : null,
            'role' => 'Admin',
        ]);
    }
}
