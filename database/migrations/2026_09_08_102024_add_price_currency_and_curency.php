<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quote_configuration_items', function (Blueprint $table) {
            // Presisi mengikuti kolom price (15,2): harga IDR bisa > 99 juta.
            $table->decimal('price_currency', 15, 2)->nullable()->after('price');
            // Kode mengikuti currencies.name (string 10).
            $table->string('currency', 10)->nullable()->after('price_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_configuration_items', function (Blueprint $table) {
            $table->dropColumn(['price_currency', 'currency']);
        });
    }
};
