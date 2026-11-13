<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master barang:
 *  - type: enum(Acc, Main, Service) -> teks bebas (nilai lama tetap).
 *  - is_material: true = Material (stok dihitung per qty), false = barang ber-SN
 *    (stok dihitung per Serial Number — penanganan SN menyusul di tahap stok).
 *    Default false; produk yang sudah ada ikut false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->string('type', 50)->nullable()->change();
            $table->boolean('is_material')->default(false)->after('type');
            $table->index('is_material');
        });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropIndex(['is_material']);
            $table->dropColumn('is_material');
        });

        // Nilai di luar enum lama dikosongkan agar kolom bisa kembali menjadi enum.
        \Illuminate\Support\Facades\DB::table('master_products')->whereNotIn('type', ['Acc', 'Main', 'Service'])->update(['type' => null]);
        Schema::table('master_products', function (Blueprint $table) {
            $table->enum('type', ['Acc', 'Main', 'Service'])->nullable()->change();
        });
    }
};
