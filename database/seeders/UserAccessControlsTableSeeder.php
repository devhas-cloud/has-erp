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
        $modules = Module::all();

        $users = User::all();

        foreach ($users as $user) {
            foreach ($modules as $module) {
                UserAccessControl::firstOrCreate(
                    ['user_id' => $user->id, 'module_id' => $module->id],
                    [
                        'can_create' => true,
                        'can_read' => true,
                        'can_update' => true,
                        'can_delete' => true,
                        'can_approve' => true,
                    ]
                );
            }
        }
    }
}
