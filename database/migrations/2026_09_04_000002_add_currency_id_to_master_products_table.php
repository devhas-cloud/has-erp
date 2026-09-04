<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->foreignId('currency_id')
                ->nullable()
                ->after('price')
                ->constrained('currencies')
                ->nullOnDelete();
        });

        // Produk existing diarahkan ke mata uang base (IDR) bila tersedia.
        $baseId = DB::table('currencies')->where('is_base', true)->value('id');
        if ($baseId) {
            DB::table('master_products')->whereNull('currency_id')->update(['currency_id' => $baseId]);
        }
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });
    }
};
