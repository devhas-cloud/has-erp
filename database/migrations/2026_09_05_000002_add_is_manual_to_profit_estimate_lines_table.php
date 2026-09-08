<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_estimate_lines', function (Blueprint $table) {
            // Baris HPP: true bila nominal dikoreksi manual (bukan hasil Σ amount item per vendor).
            $table->boolean('is_manual')->default(false)->after('amount_idr');
        });
    }

    public function down(): void
    {
        Schema::table('profit_estimate_lines', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });
    }
};
