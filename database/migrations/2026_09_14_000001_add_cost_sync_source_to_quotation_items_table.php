<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sumber total yang disinkronkan ke harga item ini: null (tidak
     * disinkronkan), 'biaya' (Total Price Biaya), atau
     * 'config:<quote_configuration_id>:<kategori>' (Subtotal satu kategori
     * pada tab List Configuration). Satu item hanya boleh mengikuti satu
     * sumber pada satu waktu.
     */
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->string('cost_sync_source', 191)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn('cost_sync_source');
        });
    }
};
