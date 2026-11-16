<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul "Lengkapi Data Stok" (SN, batch / No. MFG, kedaluwarsa, garansi yang
 * menyusul). Akses awal: user yang boleh membuat Penerimaan Barang atau Masuk
 * Stok Lain-lain (pihak yang menerima barang), plus yang boleh baca Laporan Stok
 * mendapat akses baca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modules') || DB::table('modules')->where('module_code', 'MOD_STOCK_DETAIL')->exists()) {
            return;
        }

        // Hanya instalasi yang sudah punya modul Inventory; instalasi baru diisi ModulesTableSeeder.
        $report = DB::table('modules')->where('module_code', 'MOD_STOCK_REPORT')->first();
        if (! $report) {
            return;
        }

        $now = now();
        $moduleId = DB::table('modules')->insertGetId([
            'module_code' => 'MOD_STOCK_DETAIL',
            'module_name' => 'Lengkapi Data Stok',
            'description' => 'Lengkapi SN, batch / No. MFG, kedaluwarsa, dan garansi barang yang sudah masuk stok; riwayat per SN',
            'route_name' => 'stock-detail',
            'icon' => 'fa fa-barcode',
            'group' => 'Inventory',
            'sort_order' => max(10, (int) $report->sort_order - 5),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sourceModules = DB::table('modules')->whereIn('module_code', ['MOD_GOODS_RECEIPT', 'MOD_STOCK_ENTRY', 'MOD_STOCK_REPORT'])->pluck('id', 'module_code');
        $grants = DB::table('user_access_controls')->whereIn('module_id', $sourceModules->values())->get()
            ->groupBy('user_id')
            ->map(function ($rows) use ($sourceModules) {
                $canEdit = $rows->contains(fn ($r) => $r->can_create && in_array($r->module_id, [$sourceModules['MOD_GOODS_RECEIPT'] ?? 0, $sourceModules['MOD_STOCK_ENTRY'] ?? 0]));

                return ['read' => $canEdit || $rows->contains(fn ($r) => $r->can_read), 'edit' => $canEdit];
            })
            ->filter(fn ($g) => $g['read']);

        foreach ($grants as $userId => $g) {
            DB::table('user_access_controls')->insert([
                'user_id' => $userId,
                'module_id' => $moduleId,
                'can_create' => $g['edit'],
                'can_read' => true,
                'can_update' => $g['edit'],
                'can_delete' => false,
                'can_approve' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $id = DB::table('modules')->where('module_code', 'MOD_STOCK_DETAIL')->value('id');
        if ($id) {
            DB::table('user_access_controls')->where('module_id', $id)->delete();
            DB::table('modules')->where('id', $id)->delete();
        }
    }
};
