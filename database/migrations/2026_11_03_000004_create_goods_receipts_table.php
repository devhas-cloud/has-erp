<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penerimaan Barang (GRN) dari Purchase Order ATAU Direct Purchase. Diterima
 * bertahap (parsial), tidak boleh melebihi sisa. Posting mencatat stok masuk
 * untuk baris bertujuan 'warehouse'; baris 'direct' (langsung pakai) hanya
 * konfirmasi. warehouse_id kosong bila seluruh baris langsung pakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 50)->nullable()->unique();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('direct_purchase_id')->nullable()->constrained('direct_purchases')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->date('received_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('direct_purchase_item_id')->nullable()->constrained('direct_purchase_items')->nullOnDelete();
            $table->foreignId('master_product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->integer('qty');
            $table->string('unit', 50)->nullable();
            $table->string('currency', 10)->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->string('destination', 20)->default('warehouse');
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
    }
};
