<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * goods_request_item_id UNIK (bila diisi) — satu item Permintaan Barang
     * hanya boleh masuk ke SATU PurchaseOrder di manapun, mencegah item yang
     * sama diorder dobel. NULL dibolehkan untuk baris yang diisi manual di
     * luar Permintaan Barang (unique constraint tidak berlaku pada NULL).
     */
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('goods_request_item_id')->nullable()->unique()->constrained('goods_request_items')->nullOnDelete();
            $table->foreignId('goods_request_id')->nullable()->constrained('goods_requests')->nullOnDelete();
            $table->string('part_number', 100)->nullable();
            $table->text('description')->nullable();
            $table->integer('qty')->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('price_currency', 15, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
