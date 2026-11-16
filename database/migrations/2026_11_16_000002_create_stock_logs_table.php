<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat perubahan baris stok. Hanya ditambah, tidak pernah diubah/dihapus.
 * Tidak dipakai untuk menghitung saldo sekarang (saldo = stocks.qty); dipakai
 * untuk kartu stok, saldo per tanggal (dihitung mundur), dan biaya project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('stocks')->restrictOnDelete();
            // Posisi baris saat kejadian (disalin), karena baris bisa berpindah.
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->boolean('is_material')->default(false);
            $table->date('log_date');
            $table->string('type', 20);
            // Plus = masuk, minus = keluar; 0 untuk perubahan data (detail_update).
            $table->decimal('qty', 15, 4);
            $table->decimal('qty_after', 15, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            // Item dokumen penyebab.
            $table->string('ref_type', 40)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->foreignId('goods_request_item_id')->nullable()->constrained('goods_request_items')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['master_product_id', 'warehouse_id', 'log_date'], 'stock_logs_card_index');
            $table->index(['project_id', 'log_date'], 'stock_logs_project_index');
            $table->index(['stock_id', 'id'], 'stock_logs_stock_index');
            $table->index(['ref_type', 'ref_id'], 'stock_logs_ref_index');
            $table->index('goods_request_item_id', 'stock_logs_gri_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_logs');
    }
};
