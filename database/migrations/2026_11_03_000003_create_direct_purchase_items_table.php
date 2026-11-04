<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item Direct Purchase — wajib master product (dijaga aplikasi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direct_purchase_id')->constrained('direct_purchases')->cascadeOnDelete();
            $table->foreignId('master_product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->string('part_number', 100)->nullable();
            $table->text('description')->nullable();
            $table->integer('qty')->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('currency', 10)->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_purchase_items');
    }
};
