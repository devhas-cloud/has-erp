<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kurs yang dipakai saat penerimaan disimpan per baris (base = 1), supaya
 * nilai stok bisa diaudit. Baris lama dibiarkan NULL (kurs tidak tercatat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->decimal('exchange_rate', 15, 4)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
