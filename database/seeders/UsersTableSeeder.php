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
            ['username' => 'Water'],
            [
                'full_name' => 'HAS Admin PM Water',
                'email' => 'riki@has-environmental.com',
                'password' => Hash::make('password'),
                'division_id' => 6,
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

        // ===== Owner CRM (import dari Salesforce) — divisi Sales =====
        // Username = Owner Alias, Email = Owner Email, Full Name = Owner Name.
        $crmOwners = [
            ['username' => 'Enviro', 'full_name' => 'HAS Admin PM Enviro', 'email' => 'wisnu@has-environmental.com'],
            ['username' => 'IH', 'full_name' => 'HAS Admin PM IH', 'email' => 'ihdivision@has-environmental.com'],
            ['username' => 'Rima', 'full_name' => 'Rima Lubis', 'email' => 'rimalubis@has-environmental.com'],
            ['username' => 'Marketing', 'full_name' => 'HAS Marketing', 'email' => 'marketing@has-environmental.com'],
            ['username' => 'Devi', 'full_name' => 'Devi Wahyuningsih', 'email' => 'devi@has-environmental.com'],
            ['username' => 'Nisa', 'full_name' => 'Anasiah Khaairunnisa', 'email' => 'nisa@has-environmental.com'],
            ['username' => 'Nina', 'full_name' => 'Nina Parlina', 'email' => 'nina@has-environmental.com'],
            ['username' => 'Nabila', 'full_name' => 'Nabila Agustin', 'email' => 'nabila@has-environmental.com'],
            ['username' => 'Ardi', 'full_name' => 'Primadian Ardiyasa', 'email' => 'ardi@has-environmental.com'],
            ['username' => 'Tania', 'full_name' => 'Tania Varera', 'email' => 'tania@has-environmental.com'],
        ];

        foreach ($crmOwners as $owner) {
            User::firstOrCreate(
                ['username' => $owner['username']],
                [
                    'full_name' => $owner['full_name'],
                    'email' => $owner['email'],
                    'password' => Hash::make('password'),
                    'division_id' => 6,
                    'role' => 'User',
                    'task_role_id' => 7,
                ]
            );
        }









    }
}
