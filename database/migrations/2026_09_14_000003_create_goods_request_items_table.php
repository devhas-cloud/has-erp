<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permintaan Barang tidak perlu tahu harga — harga ditentukan belakangan
     * saat Purchase Order ke supplier, bukan di tahap pengajuan ini.
     */
    public function up(): void
    {
        Schema::create('goods_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_request_id')->constrained('goods_requests')->cascadeOnDelete();
            // Jejak asal item bila diambil dari configuration (live master data,
            // difilter berdasarkan opportunity_id) — opsional, boleh diisi manual.
            $table->foreignId('quote_configuration_item_id')->nullable()->constrained('quote_configuration_items')->nullOnDelete();
            // Jejak asal item bila diambil dari katalog Master Product — opsional.
            $table->foreignId('master_product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->string('part_number', 100)->nullable();
            $table->text('description')->nullable();
            $table->integer('qty')->nullable();
            $table->string('unit', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_request_items');
    }
};
