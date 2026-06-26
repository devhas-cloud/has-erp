<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Database\Seeder;

class UserAccessControlsTableSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::where('username', 'superadmin')->first();
        $modules = Module::all();

        if ($superadmin) {
            foreach ($modules as $module) {
                UserAccessControl::create([
                    'user_id' => $superadmin->id,
                    'module_id' => $module->id,
                    'can_create' => true,
                    'can_read' => true,
                    'can_update' => true,
                    'can_delete' => true,
                    'can_approve' => true,
                ]);
            }
        }
    }
}
