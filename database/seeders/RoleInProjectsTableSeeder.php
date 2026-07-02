<?php

namespace Database\Seeders;

use App\Models\RoleInProject;
use Illuminate\Database\Seeder;

class RoleInProjectsTableSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['role_name' => 'Project Owner', 'description' => 'Pemilik proyek', 'status' => 'Active'],
            ['role_name' => 'Decision Maker', 'description' => 'Pengambil keputusan', 'status' => 'Active'],
            ['role_name' => 'Influencer', 'description' => 'Pemberi pengaruh', 'status' => 'Active'],
            ['role_name' => 'Champion', 'description' => 'Pendukung utama', 'status' => 'Active'],
            ['role_name' => 'End User', 'description' => 'Pengguna akhir', 'status' => 'Active'],
        ];

        foreach ($roles as $role) {
            RoleInProject::firstOrCreate(
                ['role_name' => $role['role_name']],
                $role
            );
        }
    }
}
