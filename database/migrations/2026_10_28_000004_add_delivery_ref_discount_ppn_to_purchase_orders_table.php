<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field cetakan PO sesuai dokumen PT. HAS ENVIRONMENTAL: DELIVERY, YOUR REF
 * (nomor penawaran supplier), serta rincian Sub Total -> Diskon -> Sub Total 1
 * -> PPN -> Total. Semua opsional; baris kosong tidak dicetak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('delivery', 150)->nullable()->after('terms');
            $table->string('your_ref', 100)->nullable()->after('delivery');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('your_ref');
            $table->decimal('ppn_percent', 5, 2)->nullable()->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['delivery', 'your_ref', 'discount_percent', 'ppn_percent']);
        });
    }
};
