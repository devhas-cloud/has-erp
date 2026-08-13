<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->nullable()->after('grand_total');
            $table->decimal('discount_amount', 15, 2)->nullable()->after('discount_percent');
            $table->decimal('ppn_percent', 5, 2)->nullable()->after('discount_amount');
            $table->decimal('ppn_amount', 15, 2)->nullable()->after('ppn_percent');
        });

        // Backfill data lama: tanpa diskon, PPN 11% dari subtotal, DPP = subtotal.
        DB::table('quotations')
            ->whereNull('ppn_percent')
            ->where('subtotal', '>', 0)
            ->update([
                'ppn_percent' => 11,
                'ppn_amount' => DB::raw('ROUND(subtotal * 0.11, 2)'),
                'discount_percent' => null,
                'discount_amount' => 0,
                'dpp' => DB::raw('subtotal'),
                'grand_total' => DB::raw('ROUND(subtotal + (subtotal * 0.11), 2)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_amount', 'ppn_percent', 'ppn_amount']);
        });
    }
};
