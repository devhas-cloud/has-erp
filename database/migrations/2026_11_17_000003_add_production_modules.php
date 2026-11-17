<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grup sidebar "Produksi" + modul Material Request, Serah Terima Material,
 * Produksi. Akses awal disalin dari modul yang paling dekat perannya.
 * Instalasi baru diisi ModulesTableSeeder.
 */
return new class extends Migration
{
    private const MODULES = [
        [
            'module_code' => 'MOD_MATERIAL_REQUEST',
            'module_name' => 'Material Request',
            'description' => 'Permintaan material project untuk produksi di satu branch — cukup 1 approver',
            'route_name' => 'material-request',
            'icon' => 'fa fa-clipboard-list',
            'copy_from' => 'MOD_GOODS_REQUEST',
            'sort_order' => 10,
        ],
        [
            'module_code' => 'MOD_MATERIAL_HANDOVER',
            'module_name' => 'Serah Terima Material',
            'description' => 'Gudang menyerahkan material Material Request ke produksi — stok langsung keluar menjadi Material di Produksi',
            'route_name' => 'material-handover',
            'icon' => 'fa fa-people-carry-box',
            'copy_from' => 'MOD_STOCK_OUT',
            'sort_order' => 20,
        ],
        [
            'module_code' => 'MOD_PRODUCTION',
            'module_name' => 'Produksi',
            'description' => 'Merakit Material di Produksi project menjadi barang jadi yang masuk ke gudang branch yang sama',
            'route_name' => 'production',
            'icon' => 'fa fa-industry',
            'copy_from' => 'MOD_STOCK_OUT',
            'sort_order' => 30,
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('modules') || ! DB::table('modules')->where('module_code', 'MOD_STOCK_OUT')->exists()) {
            return;
        }

        $now = now();
        if (Schema::hasTable('module_groups') && ! DB::table('module_groups')->where('group_name', 'Produksi')->exists()) {
            $inventory = (int) DB::table('module_groups')->where('group_name', 'Inventory')->value('sort_order');
            DB::table('module_groups')->insert([
                'group_name' => 'Produksi',
                'sort_order' => $inventory ? $inventory + 5 : 100,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::MODULES as $module) {
            if (DB::table('modules')->where('module_code', $module['module_code'])->exists()) {
                continue;
            }
            $source = DB::table('modules')->where('module_code', $module['copy_from'])->first();

            $moduleId = DB::table('modules')->insertGetId([
                'module_code' => $module['module_code'],
                'module_name' => $module['module_name'],
                'description' => $module['description'],
                'route_name' => $module['route_name'],
                'icon' => $module['icon'],
                'group' => 'Produksi',
                'sort_order' => $module['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! $source) {
                continue;
            }
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
    }

    public function down(): void
    {
        foreach (self::MODULES as $module) {
            $id = DB::table('modules')->where('module_code', $module['module_code'])->value('id');
            if ($id) {
                DB::table('user_access_controls')->where('module_id', $id)->delete();
                DB::table('modules')->where('id', $id)->delete();
            }
        }
        if (Schema::hasTable('module_groups')) {
            DB::table('module_groups')->where('group_name', 'Produksi')->delete();
        }
    }
};
