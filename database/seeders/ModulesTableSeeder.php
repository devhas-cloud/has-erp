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
                'module_code' => 'MOD_CURRENCY_MANAGEMENT',
                'module_name' => 'Currency Management',
                'description' => 'Menu untuk mengelola mata uang dan nilai kurs konversi',
                'route_name' => 'currency',
                'icon' => 'fa fa-money-bill',
                'group' => 'Master Data',
            ],
            [
                'module_code' => 'MOD_SUPPLIER',
                'module_name' => 'Supplier',
                'description' => 'Menu untuk mengelola data master supplier — dipakai sebagai sumber pilihan supplier pada Purchase Order',
                'route_name' => 'supplier',
                'icon' => 'fa fa-truck',
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
            [
                'module_code' => 'MOD_ENVIRO_CONFIGURATION',
                'module_name' => 'Enviro Configuration',
                'description' => 'Menu quotation Enviro configuration',
                'route_name' => 'enviro-configuration',
                'icon' => 'fa fa-leaf',
                'group' => 'Enviro',
            ],
            [
                'module_code' => 'MOD_IH_CONFIGURATION',
                'module_name' => 'IH Configuration',
                'description' => 'Menu quotation IH configuration',
                'route_name' => 'ih-configuration',
                'icon' => 'fa fa-heart-pulse',
                'group' => 'IH',
            ],
            [
                'module_code' => 'MOD_GAS_CONFIGURATION',
                'module_name' => 'Gas Configuration',
                'description' => 'Menu quotation Gas configuration',
                'route_name' => 'gas-configuration',
                'icon' => 'fa fa-fire',
                'group' => 'Gas',
            ],
            [
                'module_code' => 'MOD_QUOTATION',
                'module_name' => 'Quotation',
                'description' => 'Menu pembuatan dokumen quotation dari quote configuration yang sudah disetujui',
                'route_name' => 'quotation',
                'icon' => 'fa fa-file-invoice',
                'group' => 'Admin',
            ],
            [
                'module_code' => 'MOD_PROFIT_ESTIMATE',
                'module_name' => 'Estimasi PL',
                'description' => 'Menu Estimasi Perhitungan Pendapatan (profit & loss) per quotation',
                'route_name' => 'profit-estimate',
                'icon' => 'fa fa-chart-line',
                'group' => 'Admin',
            ],
            [
                'module_code' => 'MOD_PO_SUPPLIER_APPROVAL',
                'module_name' => 'PO Supplier Approval',
                'description' => 'Menu approval quotation status Finish (PO customer terupload) agar purchasing boleh melanjutkan PO barang ke supplier — memerlukan 2 approver berbeda',
                'route_name' => 'po-supplier-approval',
                'icon' => 'fa fa-truck-fast',
                'group' => 'Admin',
            ],
            [
                'module_code' => 'MOD_GOODS_REQUEST',
                'module_name' => 'Permintaan Barang',
                'description' => 'Menu pengajuan permintaan barang ke purchasing, terikat pada quotation yang PO Supplier Approval-nya sudah disetujui 2 approver',
                'route_name' => 'goods-request',
                'icon' => 'fa fa-dolly',
                'group' => 'Purchasing',
            ],
            [
                'module_code' => 'MOD_PURCHASE_ORDER',
                'module_name' => 'Purchase Order',
                'description' => 'Menu pembuatan PO ke supplier dari item Permintaan Barang yang sudah Approved — satu PO boleh menggabungkan item dari beberapa Permintaan Barang lintas divisi',
                'route_name' => 'purchase-order',
                'icon' => 'fa fa-file-invoice-dollar',
                'group' => 'Purchasing',
            ],
            [
                'module_code' => 'MOD_ER_EMPLOYEE',
                'module_name' => 'Data Karyawan',
                'description' => 'Menu pengelolaan data karyawan berserta keluarga, masa kerja, dan data gaji',
                'route_name' => 'employee-management',
                'icon' => 'fa fa-users',
                'group' => 'Employee Relations',
            ],
            [
                'module_code' => 'MOD_ER_ATTENDANCE',
                'module_name' => 'Absensi Karyawan',
                'description' => 'Menu absensi karyawan: manual, import device fingerprint, approval, dan koreksi',
                'route_name' => 'attendance',
                'icon' => 'fa fa-fingerprint',
                'group' => 'Employee Relations',
            ],
            [
                'module_code' => 'MOD_ER_LEAVE',
                'module_name' => 'Pengajuan (Cuti/Izin)',
                'description' => 'Menu pengajuan cuti, izin, dan sakit berserta saldo cuti dan approval',
                'route_name' => 'leave-request',
                'icon' => 'fa fa-calendar-check',
                'group' => 'Employee Relations',
            ],
            [
                'module_code' => 'MOD_ER_OVERTIME',
                'module_name' => 'Pengajuan Lembur',
                'description' => 'Menu pengajuan lembur: jam terhitung otomatis dari absensi (shift end s/d jam pulang) berserta approval dan integrasi payroll',
                'route_name' => 'overtime-request',
                'icon' => 'fa fa-user-clock',
                'group' => 'Employee Relations',
            ],
            [
                'module_code' => 'MOD_ER_LOAN',
                'module_name' => 'Peminjaman',
                'description' => 'Menu kas bon / pinjaman karyawan berserta jadwal angsuran dan pelunasan',
                'route_name' => 'loan',
                'icon' => 'fa fa-hand-holding-usd',
                'group' => 'Employee Relations',
            ],
            [
                'module_code' => 'MOD_ER_PAYROLL',
                'module_name' => 'Penggajian/Payrol',
                'description' => 'Menu payroll periode 25-24: slip gaji, BPJS, PPH21, potongan angsuran, export Excel/PDF',
                'route_name' => 'payroll',
                'icon' => 'fa fa-file-invoice-dollar',
                'group' => 'Employee Relations',
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
