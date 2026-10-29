<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan Barang kini terikat project; project retail (tanpa quotation)
 * tidak punya quotation/opportunity, jadi keduanya harus boleh kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_requests', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->nullable()->change();
            $table->foreignId('quotation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('goods_requests', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable(false)->change();
            $table->foreignId('opportunity_id')->nullable(false)->change();
        });
    }
};
