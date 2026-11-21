<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kurs ditetapkan di Direct Purchase (1 mata uang DP = x mata uang dasar), sama seperti
 * PO. Penerimaan barang dari DP memakai kurs ini (terkunci). NULL = DP mata uang dasar /
 * DP lama (fallback: isian di form penerimaan, default kurs master).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direct_purchases', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 6)->nullable()->after('ppn_percent');
        });
    }

    public function down(): void
    {
        Schema::table('direct_purchases', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
