<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModulesTableSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'module_code' => 'MOD_USER_MANAGEMENT',
                'module_name' => 'User Management',
                'description' => 'Menu untuk mengelola data user',
                'route_name' => 'user-management',
                'icon' => 'fa fa-users',
                'group' => 'Master Data',
            ],
            [
                'module_code' => 'MOD_CONFIGURATION',
                'module_name' => 'Configuration',
                'description' => 'Menu untuk konfigurasi data master',
                'route_name' => 'configuration',
                'icon' => 'fa fa-cogs',
                'group' => 'Master Data',
            ],
            [
                'module_code' => 'MOD_LEADS_MANAGEMENT',
                'module_name' => 'Leads Management',
                'description' => 'Menu untuk mengelola data leads',
                'route_name' => 'leads-management',
                'icon' => 'fa fa-bullhorn',
                'group' => 'CRM',
            ],
        ];

        foreach ($modules as $module) {
            Module::create($module);
        }
    }
}
