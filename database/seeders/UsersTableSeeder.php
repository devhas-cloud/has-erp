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
                'email' => 'abu@erp.local',
                'password' => Hash::make('password'),
                'division_id' => 4,
                'role' => 'User',
                'task_role_id' => 7,
            ]
        );









    }
}
