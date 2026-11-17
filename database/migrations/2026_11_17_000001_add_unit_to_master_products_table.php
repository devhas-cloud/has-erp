<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satuan bawaan produk (Unit, Pcs, Meter, ...). Hanya dipakai sebagai nilai awal
 * kolom unit saat produk dipilih di Configuration / Purchase Request / PO /
 * Direct Purchase — satuan yang sudah tersimpan di dokumen tidak ikut berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->string('unit', 50)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
