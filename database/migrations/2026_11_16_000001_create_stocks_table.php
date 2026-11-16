<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Posisi stok per baris. Satu baris = satu penerimaan barang (material, dihitung
 * per jumlah) atau satu unit barang ber-SN (qty 1). Saldo SELALU dibaca dari
 * stocks.qty, bukan dari riwayat; riwayat ada di stock_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            // Kosong = stok bebas; terisi = milik project.
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            // Salinan master_products.is_material saat baris dibuat (belum dipakai logika).
            $table->boolean('is_material')->default(false);
            // Kode lot pabrik atau nomor MFG supplier.
            $table->string('batch_number', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->date('received_date');
            $table->date('expiry_date')->nullable();
            // Garansi dari supplier (bukan garansi ke customer).
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('warranty_until')->nullable();
            $table->decimal('qty_in', 15, 4);
            $table->decimal('qty', 15, 4);
            $table->string('unit', 50)->nullable();
            // Harga modal per unit dalam mata uang base (Rupiah).
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->boolean('is_open')->storedAs('qty > 0');
            $table->foreignId('parent_id')->nullable()->constrained('stocks')->nullOnDelete();
            // Item dokumen asal: goods_receipt_item, stock_entry_item, stock_opname_item.
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['master_product_id', 'serial_number'], 'stocks_product_serial_unique');
            $table->index(['master_product_id', 'warehouse_id', 'is_open', 'project_id'], 'stocks_pick_index');
            $table->index(['source_type', 'source_id'], 'stocks_source_index');
            $table->index('batch_number');
            $table->index('expiry_date');
            $table->index('warranty_until');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_qty_range CHECK (qty >= 0 AND qty <= qty_in)');
            DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_serial_single CHECK (serial_number IS NULL OR qty_in = 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
