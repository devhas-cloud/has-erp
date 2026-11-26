<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kurs ditetapkan di PO (1 mata uang PO = x mata uang dasar). Penerimaan barang dari PO
 * memakai kurs ini untuk nilai stok. NULL = PO mata uang dasar / PO lama (fallback kurs master).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 6)->nullable()->after('ppn_percent');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
