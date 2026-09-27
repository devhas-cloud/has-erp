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

        User::firstOrCreate(
            ['username' => 'superadmin'],
            [
                'full_name' => 'Super Admin',
                'email' => 'superadmin@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 1,
                'role' => 'Admin',
                'task_role_id' => 1,
            ]
        );

        User::firstOrCreate(
            ['username' => 'husan'],
            [
                'full_name' => 'Husan',
                'email' => 'husan@erp.local',
                'password' => Hash::make('password'),
                'division_id' => null,
                'role' => 'User',
                'task_role_id' => 2,
            ]
        );

        User::firstOrCreate(
            ['username' => 'robi'],
            [
                'full_name' => 'Robi',
                'email' => 'robi@erp.local',
                'password' => Hash::make('password'),
                'division_id' => null,
                'role' => 'User',
                'task_role_id' => 3,
            ]
        );

        User::firstOrCreate(
            ['username' => 'abdul'],
            [
                'full_name' => 'Abdul',
                'email' => 'abdul@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 3,
                'role' => 'User',
                'task_role_id' => 4,
            ]
        );


        User::firstOrCreate(
            ['username' => 'cika'],
            [
                'full_name' => 'Cika',
                'email' => 'cika@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 3,
                'role' => 'User',
                'task_role_id' => 5,
            ]
        );

        User::firstOrCreate(
            ['username' => 'arlina'],
            [
                'full_name' => 'Arlina',
                'email' => 'arlina@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 3,
                'role' => 'User',
                'task_role_id' => 6,
            ]
        );

        User::firstOrCreate(
            ['username' => 'rizal'],
            [
                'full_name' => 'Rizal',
                'email' => 'rizal@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 3,
                'role' => 'User',
                'task_role_id' => 6,
            ]
        );


        User::firstOrCreate(
            ['username' => 'tio'],
            [
                'full_name' => 'Tio',
                'email' => 'tio@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 3,
                'role' => 'User',
                'task_role_id' => 7,
            ]
        );


        User::firstOrCreate(
            ['username' => 'riki'],
            [
                'full_name' => 'Riki',
                'email' => 'riki@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 2,
                'role' => 'User',
                'task_role_id' => 4,
            ]
        );

        User::firstOrCreate(
            ['username' => 'isandi'],
            [
                'full_name' => 'Isandi',
                'email' => 'isandi@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 2,
                'role' => 'User',
                'task_role_id' => 6,
            ]
        );


        User::firstOrCreate(
            ['username' => 'maidin'],
            [
                'full_name' => 'Maidin',
                'email' => 'maidin@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 2,
                'role' => 'User',
                'task_role_id' => 6,
            ]
        );


        User::firstOrCreate(
            ['username' => 'maya'],
            [
                'full_name' => 'Maya',
                'email' => 'maya@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 1,
                'role' => 'User',
                'task_role_id' => 4,
            ]
        );


        User::firstOrCreate(
            ['username' => 'frida'],
            [
                'full_name' => 'Frida',
                'email' => 'frida@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 2,
                'role' => 'User',
                'task_role_id' => 7,
            ]
        );


        User::firstOrCreate(
            ['username' => 'ichsan'],
            [
                'full_name' => 'Ichsan',
                'email' => 'ichsan@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 4,
                'role' => 'User',
                'task_role_id' => 4,
            ]
        );

        User::firstOrCreate(
            ['username' => 'abu'],
            [
                'full_name' => 'Abu',
                'email' => 'abu@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 4,
                'role' => 'User',
                'task_role_id' => 7,
            ]
        );









    }
}
