<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kartu stok — satu-satunya sumber kebenaran stok. Saldo per (produk, gudang,
 * project) = SUM(masuk) - SUM(keluar), tidak disimpan agar bebas drift.
 * project_id kosong = stok bebas; terisi = stok milik project (teralokasi).
 * source_type/source_id/source_line_id menunjuk dokumen asal pergerakan.
 * goods_request_item_id mencatat pemenuhan item Permintaan Barang dari stok
 * (tidak unique: boleh beberapa batch / parsial).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('type', 20);
            $table->integer('qty');
            $table->string('unit', 50)->nullable();
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

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
