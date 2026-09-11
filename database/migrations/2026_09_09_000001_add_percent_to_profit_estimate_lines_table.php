<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_estimate_lines', function (Blueprint $table) {
            // Baris biaya operasional: bila diisi, nominal = percent% x total HPP
            // (Rp) untuk vendor bermata uang selain IDR; kosong = nominal manual.
            $table->decimal('percent', 7, 2)->nullable()->after('is_manual');
        });
    }

    public function down(): void
    {
        Schema::table('profit_estimate_lines', function (Blueprint $table) {
            $table->dropColumn('percent');
        });
    }
};
