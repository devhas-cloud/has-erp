<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master data Supplier — dipakai Purchase Order sebagai sumber pilihan
     * supplier (menggantikan input teks bebas). Field alamat/telepon/fax/attn
     * dicetak pada dokumen PO; nilainya di-snapshot ke purchase_orders saat
     * supplier dipilih supaya PO yang sudah terbit tidak berubah kalau data
     * master Supplier diedit belakangan.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('attn_name', 150)->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
