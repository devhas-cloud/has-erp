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
                'module_code' => 'MOD_CONTACT_MANAGEMENT',
                'module_name' => 'Contact Management',
                'description' => 'Menu untuk mengelola data kontak',
                'route_name' => 'contact-management',
                'icon' => 'fa fa-address-book',
                'group' => 'CRM',
            ],
            [
                'module_code' => 'MOD_ACCOUNT_MANAGEMENT',
                'module_name' => 'Account Management',
                'description' => 'Menu untuk mengelola akun perusahaan',
                'route_name' => 'accounts-management',
                'icon' => 'fa fa-building',
                'group' => 'CRM',
            ],

            [
                'module_code' => 'MOD_LEADS_MANAGEMENT',
                'module_name' => 'Leads Management',
                'description' => 'Menu untuk mengelola data leads',
                'route_name' => 'leads-management',
                'icon' => 'fa fa-bullhorn',
                'group' => 'CRM',
            ],
            [
                'module_code' => 'MOD_TASK_PLANNER',
                'module_name' => 'Task Planner',
                'description' => 'Menu untuk mengelola tugas dan pendelegasian lintas divisi',
                'route_name' => 'task-planner',
                'icon' => 'fa fa-tasks',
                'group' => 'CRM',
            ],
        ];

        foreach ($modules as $module) {
            Module::firstOrCreate(
                ['module_code' => $module['module_code']],
                $module
            );
        }
    }
}
