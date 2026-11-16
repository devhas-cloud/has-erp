<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - warehouses: + branch_id; konsep gudang stok/transit (type) dihapus — barang
 *   antar branch tercatat "dalam pengiriman" di dokumen Branch Transfer.
 *   branch_id nullable di database agar gudang lama tidak hilang; wajib di form.
 * - users: + branch_id (kosong = melihat semua branch; hak tetap lewat akses modul).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->restrictOnDelete();
        });
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('division_id')->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('type', 20)->default('stock')->after('name');
        });
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
