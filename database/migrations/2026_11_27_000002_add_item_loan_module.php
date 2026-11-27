<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul "Item Loan" (peminjaman barang ber-SN). Akses awal disalin dari
 * Transfer Stok. Instalasi baru diisi ModulesTableSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modules') || DB::table('modules')->where('module_code', 'MOD_ITEM_LOAN')->exists()) {
            return;
        }

        $source = DB::table('modules')->where('module_code', 'MOD_STOCK_TRANSFER')->first();
        if (! $source) {
            return;
        }

        $now = now();
        $moduleId = DB::table('modules')->insertGetId([
            'module_code' => 'MOD_ITEM_LOAN',
            'module_name' => 'Item Loan',
            'description' => 'Peminjaman unit ber-SN ke user internal atau pihak luar — unit tetap tercatat milik inventory sampai dikembalikan',
            'route_name' => 'item-loan',
            'icon' => 'fa fa-hand-holding',
            'group' => 'Inventory',
            'sort_order' => (int) $source->sort_order + 6,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (DB::table('user_access_controls')->where('module_id', $source->id)->get() as $row) {
            DB::table('user_access_controls')->insert([
                'user_id' => $row->user_id,
                'module_id' => $moduleId,
                'can_create' => $row->can_create,
                'can_read' => $row->can_read,
                'can_update' => $row->can_update,
                'can_delete' => $row->can_delete,
                'can_approve' => $row->can_approve,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $id = DB::table('modules')->where('module_code', 'MOD_ITEM_LOAN')->value('id');
        if ($id) {
            DB::table('user_access_controls')->where('module_id', $id)->delete();
            DB::table('modules')->where('id', $id)->delete();
        }
    }
};
