<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\ModuleGroup;
use Illuminate\Database\Seeder;

class ModulesTableSeeder extends Seeder
{
    /**
     * Urutan grup sidebar. Grup di luar daftar ini tampil paling akhir.
     */
    private const GROUP_ORDER = [
        'Dashboard',
        'CRM',
        'Master Data',
        'Admin',
        'Purchasing',
        'Inventory',
        'Finance',
        'Employee Relations',
        'Water',
        'IMS',
        'Enviro',
        'IH',
        'Gas',
    ];

    /**
     * Urutan modul mengikuti alur bisnis (bukan urutan daftar di bawah):
     * - Admin: quotation -> PL -> project -> approval PO supplier.
     * - Purchasing: dokumen dengan supplier — permintaan -> PO / pembelian langsung -> retur.
     * - Inventory: pergerakan stok oleh gudang — terima -> keluar / kirim -> pindah ->
     *   masuk lain-lain -> opname -> laporan.
     * - Finance: piutang (invoice -> penerimaan -> laporan) lalu hutang (tagihan ->
     *   pembayaran -> refund -> laporan).
     */
    private const GROUP_MODULE_ORDER = [
        'Admin' => [
            'MOD_QUOTATION',
            'MOD_PROFIT_ESTIMATE',
            'MOD_PROJECT',
            'MOD_PO_SUPPLIER_APPROVAL',
        ],
        'Purchasing' => [
            'MOD_GOODS_REQUEST',
            'MOD_PURCHASE_ORDER',
            'MOD_DIRECT_PURCHASE',
            'MOD_SUPPLIER_RETURN',
        ],
        'Inventory' => [
            'MOD_GOODS_RECEIPT',
            'MOD_STOCK_OUT',
            'MOD_DELIVERY_ORDER',
            'MOD_STOCK_TRANSFER',
            'MOD_STOCK_ENTRY',
            'MOD_STOCK_OPNAME',
            'MOD_STOCK_REPORT',
        ],
        'Finance' => [
            'MOD_INVOICE',
            'MOD_CUSTOMER_RECEIPT',
            'MOD_RECEIVABLE_REPORT',
            'MOD_SUPPLIER_BILL',
            'MOD_SUPPLIER_PAYMENT',
            'MOD_SUPPLIER_REFUND',
            'MOD_PAYABLE_REPORT',
        ],
    ];

    public function run(): void
    {
        foreach (self::GROUP_ORDER as $i => $name) {
            ModuleGroup::updateOrCreate(
                ['group_name' => $name],
                ['sort_order' => ($i + 1) * 10]
            );
        }

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
                'module_code' => 'MOD_DASHBOARD_ACHIEVEMENT',
                'module_name' => 'Dashboard Achievement',
                'description' => 'Dashboard total achievement & brand terjual per divisi (quotation Closed Won / Finish)',
                'route_name' => 'dashboard-achievement',
                'icon' => 'fa fa-trophy',
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
                'module_code' => 'MOD_WAREHOUSE',
                'module_name' => 'Gudang',
                'description' => 'Data master gudang/lokasi penyimpanan stok',
                'route_name' => 'warehouse',
                'icon' => 'fa fa-warehouse',
                'group' => 'Master Data',
            ],
            [
                'module_code' => 'MOD_FINANCE_NOTIFICATION',
                'module_name' => 'Notifikasi Finance',
                'description' => 'Pengaturan pengingat WhatsApp harian hutang supplier & piutang customer — aktif/nonaktif, jam kirim, tujuan grup / WA pribadi, pratinjau, kirim sekarang',
                'route_name' => 'finance-notification',
                'icon' => 'fa fa-bell',
                'group' => 'Master Data',
            ],
            [
                'module_code' => 'MOD_Water_CONFIGURATION',
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
                // Modul sementara: input project hybrid yang sudah berjalan (PO, dokumen,
                // garansi) untuk pemetaan maintenance. Nilai & sales dilengkapi di modul Project.
                'module_code' => 'MOD_IMS_PROJECT',
                'module_name' => 'IMS Project',
                'description' => 'Input project hybrid IMS: data PO customer, dokumen, dan masa garansi untuk pemetaan maintenance',
                'route_name' => 'ims-project',
                'icon' => 'fa fa-shield-halved',
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
                'module_code' => 'MOD_PROJECT',
                'module_name' => 'Project',
                'description' => 'Menu pengelolaan project dari quotation yang sudah deal — PO customer, rincian achievement per device, dan pembagian achievement sales',
                'route_name' => 'project',
                'icon' => 'fa fa-diagram-project',
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
                'module_code' => 'MOD_DIRECT_PURCHASE',
                'module_name' => 'Direct Purchase',
                'description' => 'Menu pembelian langsung di luar PO formal, tidak terikat Permintaan Barang — beli untuk stok, untuk project, atau langsung pakai; diterima lewat Penerimaan Barang',
                'route_name' => 'direct-purchase',
                'icon' => 'fa fa-basket-shopping',
                'group' => 'Purchasing',
            ],
            [
                'module_code' => 'MOD_GOODS_RECEIPT',
                'module_name' => 'Penerimaan Barang',
                'description' => 'Penerimaan barang dari PO / Direct Purchase — masuk gudang (stok bebas / milik project) atau langsung pakai',
                'route_name' => 'goods-receipt',
                'icon' => 'fa fa-boxes-stacked',
                'group' => 'Inventory',
            ],
            [
                'module_code' => 'MOD_STOCK_OUT',
                'module_name' => 'Pemenuhan dari Stok',
                'description' => 'Penuhi item Permintaan Barang dari stok bebas — internal keluar stok, project dialokasikan ke gudang transit',
                'route_name' => 'stock-out',
                'icon' => 'fa fa-right-from-bracket',
                'group' => 'Inventory',
            ],
            [
                'module_code' => 'MOD_STOCK_TRANSFER',
                'module_name' => 'Transfer Stok',
                'description' => 'Pindah stok antar gudang dan/atau kepemilikan — termasuk alokasi stok bebas ke project dan lepas alokasi',
                'route_name' => 'stock-transfer',
                'icon' => 'fa fa-arrow-right-arrow-left',
                'group' => 'Inventory',
            ],
            [
                'module_code' => 'MOD_STOCK_ENTRY',
                'module_name' => 'Masuk Stok Lain-lain',
                'description' => 'Barang masuk tanpa dokumen pembelian — saldo awal, retur, hibah/sampel',
                'route_name' => 'stock-entry',
                'icon' => 'fa fa-square-plus',
                'group' => 'Inventory',
            ],
            [
                'module_code' => 'MOD_STOCK_REPORT',
                'module_name' => 'Laporan Stok',
                'description' => 'Saldo stok per produk, gudang, dan kepemilikan (bebas / project) beserta kartu stok',
                'route_name' => 'stock-report',
                'icon' => 'fa fa-chart-column',
                'group' => 'Inventory',
            ],
            [
                'module_code' => 'MOD_DELIVERY_ORDER',
                'module_name' => 'Delivery Order',
                'description' => 'Surat jalan pengiriman barang milik project dari gudang (umumnya transit) ke customer — draft lalu diposting oleh gudang',
                'route_name' => 'delivery-order',
                'icon' => 'fa fa-truck',
                'group' => 'Inventory',
            ],
            [
                'module_code' => 'MOD_STOCK_OPNAME',
                'module_name' => 'Stock Opname',
                'description' => 'Hitung fisik stok per gudang — draft oleh gudang, posting penyesuaian selisih oleh approver (can_approve)',
                'route_name' => 'stock-opname',
                'icon' => 'fa fa-clipboard-list',
                'group' => 'Inventory',
            ],
            [
                'module_code' => 'MOD_SUPPLIER_RETURN',
                'module_name' => 'Retur ke Supplier',
                'description' => 'Pengembalian barang yang sudah diterima ke supplier (rusak / salah kirim) — stok keluar, barang retur tidak bisa ditagih',
                'route_name' => 'supplier-return',
                'icon' => 'fa fa-truck-arrow-right',
                'group' => 'Purchasing',
            ],
            [
                'module_code' => 'MOD_INVOICE',
                'module_name' => 'Invoice',
                'description' => 'Invoice ke customer per termin timeline project (piutang) — item, PPN, faktur pajak keluaran, approval bukan oleh pembuat, dan status pembayaran',
                'route_name' => 'invoice',
                'icon' => 'fa fa-file-invoice-dollar',
                'group' => 'Finance',
            ],
            [
                'module_code' => 'MOD_CUSTOMER_RECEIPT',
                'module_name' => 'Penerimaan Customer',
                'description' => 'Pencatatan dana masuk dari customer untuk invoice Approved (nominal = total invoice), diposting approver Finance — invoice menjadi Lunas',
                'route_name' => 'customer-receipt',
                'icon' => 'fa fa-hand-holding-dollar',
                'group' => 'Finance',
            ],
            [
                'module_code' => 'MOD_RECEIVABLE_REPORT',
                'module_name' => 'Laporan Piutang',
                'description' => 'Monitoring piutang customer: per customer, umur piutang, proyeksi penerimaan, dan piutang per project mengikuti timeline termin & PO due date',
                'route_name' => 'receivable-report',
                'icon' => 'fa fa-chart-pie',
                'group' => 'Finance',
            ],
            [
                'module_code' => 'MOD_SUPPLIER_BILL',
                'module_name' => 'Tagihan Supplier',
                'description' => 'Pencatatan tagihan/invoice supplier dari PO dengan 3-way match (PO x Penerimaan x Tagihan), diverifikasi Finance sebagai hutang',
                'route_name' => 'supplier-bill',
                'icon' => 'fa fa-file-invoice',
                'group' => 'Finance',
            ],
            [
                'module_code' => 'MOD_SUPPLIER_PAYMENT',
                'module_name' => 'Pembayaran Supplier',
                'description' => 'Pencatatan pembayaran ke supplier untuk tagihan terverifikasi (sebagian / banyak tagihan sekaligus), diposting approver Finance',
                'route_name' => 'supplier-payment',
                'icon' => 'fa fa-money-bill-transfer',
                'group' => 'Finance',
            ],
            [
                'module_code' => 'MOD_SUPPLIER_REFUND',
                'module_name' => 'Refund Supplier',
                'description' => 'Pengembalian uang muka PO dari supplier (PO batal / barang kurang) — daftar Menunggu Refund dan penerimaan refund',
                'route_name' => 'supplier-refund',
                'icon' => 'fa fa-rotate-left',
                'group' => 'Finance',
            ],
            [
                'module_code' => 'MOD_PAYABLE_REPORT',
                'module_name' => 'Laporan Hutang',
                'description' => 'Monitoring hutang supplier: per supplier, umur hutang, jadwal pembayaran, dan biaya pembelian per project vs Estimasi PL',
                'route_name' => 'payable-report',
                'icon' => 'fa fa-chart-line',
                'group' => 'Finance',
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

        $seq = 0;
        foreach ($modules as $module) {
            $module['sort_order'] = ++$seq * 10;
            Module::updateOrCreate(
                ['module_code' => $module['module_code']],
                $module
            );
        }

        foreach (self::GROUP_MODULE_ORDER as $codes) {
            foreach ($codes as $i => $code) {
                Module::where('module_code', $code)->update(['sort_order' => ($i + 1) * 10]);
            }
        }
    }
}
