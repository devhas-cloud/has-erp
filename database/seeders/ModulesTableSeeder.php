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
            [
                'module_code' => 'MOD_OPPORTUNITY_MANAGEMENT',
                'module_name' => 'Opportunity Management',
                'description' => 'Menu untuk mengelola opportunity',
                'route_name' => 'opportunity-management',
                'icon' => 'fa fa-chart-line',
                'group' => 'CRM',
            ],
            [
                'module_code' => 'MOD_DASHBOARD_TASK_PLANNER',
                'module_name' => 'Dashboard Task Planner',
                'description' => 'Dashboard monitoring tugas dan statistik',
                'route_name' => 'dashboard-task-planner',
                'icon' => 'fa fa-chart-pie',
                'group' => 'Dashboard',
            ],
            [
                'module_code' => 'MOD_PRODUCT_MANAGEMENT',
                'module_name' => 'Product Management',
                'description' => 'Menu untuk mengelola data master produk',
                'route_name' => 'product-management',
                'icon' => 'fa fa-box',
                'group' => 'Master Data',
            ],
            [
                'module_code' => 'MOD_WATER_CONFIGURATION',
                'module_name' => 'Water Configuration',
                'description' => 'Menu quotation water configuration (parameter pH, Ammonia, COD, TSS dan Debit)',
                'route_name' => 'water-configuration',
                'icon' => 'fa fa-droplet',
                'group' => 'Water',
            ],
            [
                'module_code' => 'MOD_IMS_CONFIGURATION',
                'module_name' => 'IMS Configuration',
                'description' => 'Menu konfigurasi IMS yang berkaitan dengan material',
                'route_name' => 'ims-configuration',
                'icon' => 'fa fa-industry',
                'group' => 'IMS',
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
