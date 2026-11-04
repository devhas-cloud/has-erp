<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagihan Supplier (Finance Tahap 1) — pengakuan hutang ke supplier.
 * Tagihan reguler dicocokkan 3-way: baris PO (harga) x Penerimaan Barang
 * (qty diterima) x tagihan ini (qty ditagih <= diterima - sudah ditagih).
 * bill_type 'advance' (uang muka) disiapkan untuk Tahap 3.
 * project_id baris = snapshot project dari baris PO, dasar biaya per project.
 * Status: draft -> verified (Finance) -> partially_paid -> paid; void beralasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number', 50)->unique();
            $table->string('bill_type', 20)->default('regular');
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('direct_purchase_id')->nullable()->constrained('direct_purchases')->restrictOnDelete();
            $table->string('supplier_invoice_number', 100);
            $table->string('tax_invoice_number', 100)->nullable();
            $table->date('bill_date');
            $table->date('due_date');
            $table->string('currency', 10);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('ppn_percent', 5, 2)->default(0);
            $table->decimal('ppn_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['supplier_id', 'supplier_invoice_number']);
            $table->index('due_date');
        });

        Schema::create('supplier_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_bill_id')->constrained('supplier_bills')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('direct_purchase_item_id')->nullable()->constrained('direct_purchase_items')->restrictOnDelete();
            $table->foreignId('master_product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('part_number', 100)->nullable();
            $table->text('description')->nullable();
            $table->integer('qty');
            $table->string('unit', 50)->nullable();
            $table->decimal('price', 18, 2);
            $table->decimal('amount', 18, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bill_items');
        Schema::dropIfExists('supplier_bills');
    }
};
