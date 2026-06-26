<?php

namespace Database\Seeders;

use App\Models\AccountType;
use Illuminate\Database\Seeder;

class AccountTypesTableSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['type_name' => 'New', 'description' => 'Akun baru', 'status' => 'Active'],
            ['type_name' => 'Existing', 'description' => 'Akun existing', 'status' => 'Active'],
            ['type_name' => 'Prospect', 'description' => 'Akun prospek', 'status' => 'Active'],
            ['type_name' => 'Dormant', 'description' => 'Akun dormant/tidak aktif', 'status' => 'Active'],
        ];

        foreach ($types as $type) {
            AccountType::create($type);
        }
    }
}
