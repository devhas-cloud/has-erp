<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Urutan sidebar dibuat fleksibel: urutan grup disimpan di module_groups,
 * urutan modul di dalam grup disimpan di modules.sort_order. Tidak ada lagi
 * urutan yang ditentukan alfabet nama grup/nama modul.
 */
return new class extends Migration
{
    /** Urutan grup sidebar (kecil tampil lebih dulu). */
    private const GROUP_ORDER = [
        'Dashboard',
        'CRM',
        'Master Data',
        'Admin',
        'Purchasing',
        'Employee Relations',
        'Water',
        'IMS',
        'Enviro',
        'IH',
        'Gas',
    ];

    /** Urutan modul di dalam grup Admin. */
    private const ADMIN_ORDER = [
        'MOD_QUOTATION',
        'MOD_PROFIT_ESTIMATE',
        'MOD_PROJECT',
        'MOD_INVOICE',
        'MOD_PO_SUPPLIER_APPROVAL',
    ];

    public function up(): void
    {
        Schema::create('module_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_name', 50)->unique();
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(100)->after('group')->index();
        });

        $now = now();
        $groups = DB::table('modules')->whereNotNull('group')->distinct()->orderBy('group')->pluck('group');
        $rows = [];
        foreach ($groups as $name) {
            $position = array_search($name, self::GROUP_ORDER, true);
            $rows[] = [
                'group_name' => $name,
                'sort_order' => $position === false ? 100 : ($position + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows !== []) {
            DB::table('module_groups')->insertOrIgnore($rows);
        }

        // Admin: urutan bisnis eksplisit; grup lain: ikut urutan id (urutan seed).
        $seq = 0;
        foreach (self::ADMIN_ORDER as $code) {
            DB::table('modules')->where('module_code', $code)->update(['sort_order' => ++$seq * 10]);
        }

        DB::table('modules')
            ->where('group', 'Admin')
            ->whereNotIn('module_code', self::ADMIN_ORDER)
            ->orderBy('id')
            ->each(function ($module) use (&$seq) {
                DB::table('modules')->where('id', $module->id)->update(['sort_order' => ++$seq * 10]);
            });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });

        Schema::dropIfExists('module_groups');
    }
};
