<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stock_movements digantikan stocks (posisi) + stock_logs (riwayat).
 * Tabel kosong saat migration dibuat (16 Nov 2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('stock_movements');
    }

    public function down(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('type', 20);
            $table->integer('qty');
            $table->string('unit', 50)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('source_line_id')->nullable();
            $table->foreignId('goods_request_item_id')->nullable()->constrained('goods_request_items')->nullOnDelete();
            $table->date('moved_at');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['master_product_id', 'warehouse_id', 'project_id'], 'stock_movements_balance_index');
            $table->index(['source_type', 'source_id']);
            $table->index('goods_request_item_id');
        });
    }
};
