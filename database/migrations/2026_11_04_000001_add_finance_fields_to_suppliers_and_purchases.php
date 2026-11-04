<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fondasi modul Finance (Tahap 0): data keuangan supplier dan termin bayar
 * terstruktur di PO / Direct Purchase.
 *  - payment_term_days     : termin bayar default supplier (hari setelah tagihan).
 *  - max_advance_percent   : batas uang muka per PO (% nilai PO, 0 = tidak boleh).
 *  - is_pkp + npwp         : syarat faktur pajak.
 *  - rekening bank         : tujuan transfer pembayaran.
 * PO & DP menyalin termin dari supplier (boleh diubah) sebagai dasar jatuh tempo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_term_days')->default(30)->after('attn_name');
            $table->decimal('max_advance_percent', 5, 2)->default(0)->after('payment_term_days');
            $table->string('npwp', 30)->nullable()->after('max_advance_percent');
            $table->boolean('is_pkp')->default(false)->after('npwp');
            $table->string('bank_name', 100)->nullable()->after('is_pkp');
            $table->string('bank_account_no', 50)->nullable()->after('bank_name');
            $table->string('bank_account_name', 150)->nullable()->after('bank_account_no');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_term_days')->nullable()->after('terms');
        });

        Schema::table('direct_purchases', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_term_days')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('direct_purchases', fn (Blueprint $table) => $table->dropColumn('payment_term_days'));
        Schema::table('purchase_orders', fn (Blueprint $table) => $table->dropColumn('payment_term_days'));
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['payment_term_days', 'max_advance_percent', 'npwp', 'is_pkp', 'bank_name', 'bank_account_no', 'bank_account_name']);
        });
    }
};
