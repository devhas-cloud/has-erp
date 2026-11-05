<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian nilai project manual (tanpa quotation), mengikuti skema quotation:
 * subtotal − diskon = nilai sesudah diskon (dasar penagihan); + PPN = total sale;
 * − referen = net sale. Null = project lama (dasar penagihan = net sale).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('manual_subtotal', 18, 2)->nullable()->after('net_sale_amount');
            $table->decimal('manual_discount_percent', 7, 4)->nullable()->after('manual_subtotal');
            $table->decimal('manual_discount_amount', 18, 2)->nullable()->after('manual_discount_percent');
            $table->decimal('manual_ppn_percent', 7, 4)->nullable()->after('manual_discount_amount');
            $table->decimal('manual_ppn_amount', 18, 2)->nullable()->after('manual_ppn_percent');
            $table->decimal('manual_referral_percent', 7, 4)->nullable()->after('manual_ppn_amount');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'manual_subtotal', 'manual_discount_percent', 'manual_discount_amount',
                'manual_ppn_percent', 'manual_ppn_amount', 'manual_referral_percent',
            ]);
        });
    }
};
