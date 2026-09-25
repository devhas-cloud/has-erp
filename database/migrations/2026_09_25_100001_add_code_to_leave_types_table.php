<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leave_types', 'code')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->string('code', 10)->unique()->after('id');
            });
        }

        // kode berdasarkan nama default seeder
        DB::table('leave_types')->where('name', 'Cuti Tahunan')->update(['code' => 'ANN']);
        DB::table('leave_types')->whereNull('code')->where('name', 'Izin')->update(['code' => 'IZN']);
        DB::table('leave_types')->whereNull('code')->where('name', 'Sakit')->update(['code' => 'SCK']);
        DB::table('leave_types')->whereNull('code')->where('name', 'Cuti Unpaid')->update(['code' => 'UNP']);
        DB::table('leave_types')->whereNull('code')->where('name', 'Cuti Special')->update(['code' => 'SPL']);
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
