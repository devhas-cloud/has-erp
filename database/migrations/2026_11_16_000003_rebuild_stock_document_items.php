<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item dokumen stok disesuaikan dengan skema stocks + stock_logs.
 * Semua tabel ini masih kosong saat migration dibuat (16 Nov 2026), jadi tabel
 * dibuat ulang dengan struktur akhir (portable untuk MariaDB & SQLite).
 *
 * - Item masuk (GRN, Stock Entry): + batch/no. MFG, kedaluwarsa, garansi, daftar SN.
 * - Item opname: per baris stok (stock_id; kosong = barang temuan).
 * - Item keluar (Transfer, DO, Retur): menunjuk baris stok yang dipilih user.
 * - Semua qty desimal (arti mengikuti satuan).
 */
return new class extends Migration
{
    private const TABLES = [
        'goods_receipt_items', 'stock_entry_items', 'stock_opname_items',
        'stock_transfer_items', 'delivery_order_items', 'supplier_return_items',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::dropIfExists($name);
        }

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('direct_purchase_item_id')->nullable()->constrained('direct_purchase_items')->nullOnDelete();
            $table->foreignId('master_product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 50)->nullable();
            $table->string('currency', 10)->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->string('destination', 20)->default('warehouse');
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('warranty_until')->nullable();
            // Daftar SN saat draft; saat posting menjadi baris stocks (boleh sebagian/kosong).
            $table->json('serial_numbers')->nullable();
            $table->string('note', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('stock_entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_entry_id')->constrained('stock_entries')->cascadeOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 50)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('warranty_until')->nullable();
            $table->json('serial_numbers')->nullable();
            $table->string('note', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            // Baris stok yang dihitung; kosong = barang temuan (belum ada di sistem).
            $table->foreignId('stock_id')->nullable()->constrained('stocks')->restrictOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            // Identitas barang temuan.
            $table->string('batch_number', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            // Angka sistem saat qty fisik diisi (acuan selisih).
            $table->decimal('system_qty', 15, 4)->default(0);
            $table->decimal('counted_qty', 15, 4)->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->string('remark')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['stock_opname_id', 'stock_id']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('stock_id')->constrained('stocks')->restrictOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('delivery_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->foreignId('stock_id')->constrained('stocks')->restrictOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 50)->nullable();
            $table->string('remark')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('supplier_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_return_id')->constrained('supplier_returns')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            // Wajib untuk barang dari gudang; kosong untuk barang tujuan langsung (tanpa stok).
            $table->foreignId('stock_id')->nullable()->constrained('stocks')->restrictOnDelete();
            $table->foreignId('master_product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 50)->nullable();
            $table->string('destination', 20)->default('warehouse');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::dropIfExists($name);
        }

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

        Schema::create('stock_entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_entry_id')->constrained('stock_entries')->cascadeOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->integer('qty');
            $table->string('unit', 50)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->integer('system_qty');
            $table->integer('counted_qty');
            $table->string('unit', 50)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->string('remark')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->integer('qty');
            $table->string('unit', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('delivery_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->integer('qty');
            $table->string('unit', 50)->nullable();
            $table->string('remark')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('supplier_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_return_id')->constrained('supplier_returns')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('master_product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->integer('qty');
            $table->string('unit', 50)->nullable();
            $table->string('destination', 20)->default('warehouse');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
