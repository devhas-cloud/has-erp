<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_estimate_lines', function (Blueprint $table) {
            // Baris HPP (mata uang non-IDR): true bila nominal dinaikkan otomatis
            // sebesar ProfitEstimate::HPP_UP_AMOUNT (biaya TT). Disimpan terpisah
            // dari is_manual/amount agar toggle tetap dikenali saat edit meskipun
            // nominal otomatis (HPP hasil Σ item) berubah di antara dua simpan.
            $table->boolean('is_up')->default(false)->after('percent');
        });
    }

    public function down(): void
    {
        Schema::table('profit_estimate_lines', function (Blueprint $table) {
            $table->dropColumn('is_up');
        });
    }
};
