<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul "Project Tracking" (grup Admin, setelah Project). Akses awal disalin
 * dari Project. Instalasi baru diisi ModulesTableSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modules') || DB::table('modules')->where('module_code', 'MOD_PROJECT_TRACKING')->exists()) {
            return;
        }
        $source = DB::table('modules')->where('module_code', 'MOD_PROJECT')->first();
        if (! $source) {
            return;
        }

        $now = now();
        $moduleId = DB::table('modules')->insertGetId([
            'module_code' => 'MOD_PROJECT_TRACKING',
            'module_name' => 'Project Tracking',
            'description' => 'Monitoring proses setiap project dalam 7 tahap — dari opportunity sampai penagihan — beserta peringatan',
            'route_name' => 'project-tracking',
            'icon' => 'fa fa-route',
            'group' => $source->group,
            'sort_order' => (int) $source->sort_order + 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (DB::table('user_access_controls')->where('module_id', $source->id)->get() as $row) {
            DB::table('user_access_controls')->insert([
                'user_id' => $row->user_id,
                'module_id' => $moduleId,
                'can_create' => false,
                'can_read' => $row->can_read,
                'can_update' => false,
                'can_delete' => false,
                'can_approve' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $id = DB::table('modules')->where('module_code', 'MOD_PROJECT_TRACKING')->value('id');
        if ($id) {
            DB::table('user_access_controls')->where('module_id', $id)->delete();
            DB::table('modules')->where('id', $id)->delete();
        }
    }
};
