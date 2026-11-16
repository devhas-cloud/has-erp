<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Branch Transfer — pengiriman barang antar branch dengan surat jalan.
 * draft -> in_transit (stok keluar dari gudang asal, "dalam pengiriman")
 * -> received (penerima memilih gudang tujuan; hanya bila semua barang sesuai).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 50)->unique();
            $table->date('transfer_date');
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            // Dipilih penerima saat menerima (gudang milik branch tujuan).
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('courier', 150)->nullable();
            $table->string('vehicle_no', 50)->nullable();
            $table->string('driver_name', 150)->nullable();
            $table->text('note')->nullable();
            $table->text('receive_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('branch_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_transfer_id')->constrained('branch_transfers')->cascadeOnDelete();
            // Baris stok asal yang dikirim (produk, SN, batch dibaca dari sini).
            $table->foreignId('stock_id')->constrained('stocks')->restrictOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 50)->nullable();
            // Hasil pemeriksaan penerima.
            $table->decimal('received_qty', 15, 4)->nullable();
            $table->boolean('condition_ok')->nullable();
            $table->string('receive_note', 255)->nullable();
            $table->timestamp('checked_at')->nullable();
            // Baris stok di gudang tujuan setelah diterima.
            $table->foreignId('received_stock_id')->nullable()->constrained('stocks')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_transfer_items');
        Schema::dropIfExists('branch_transfers');
    }
};
