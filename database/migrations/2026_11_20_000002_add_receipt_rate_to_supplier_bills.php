<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kurs rata-rata penerimaan barang yang ditagih (dari goods_receipt_items.exchange_rate),
 * pembanding kurs tagihan: selisihnya = selisih kurs terima vs tagihan (info, stok tidak diubah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->decimal('receipt_rate', 18, 6)->nullable()->after('rate_estimated');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->dropColumn('receipt_rate');
        });
    }
};
