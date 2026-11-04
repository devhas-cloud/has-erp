<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direct Purchase: pembelian langsung tanpa Permintaan Barang / PO formal.
 * purpose: 'stock' (masuk gudang stok), 'project' (masuk transit, dialokasikan
 * ke project_id) atau 'direct' (langsung pakai, tidak masuk stok). Tanpa
 * terms/delivery/your_ref/diskon — hanya Subtotal -> PPN -> Total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('dp_number', 50)->nullable()->unique();
            $table->date('date')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name', 200);
            $table->string('supplier_phone', 50)->nullable();
            $table->string('purpose', 20)->default('stock');
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->decimal('ppn_percent', 5, 2)->nullable();
            $table->string('status', 50)->default('draft');
            $table->text('notes')->nullable();
            $table->string('request_by_name', 150)->nullable();
            $table->string('finance_name', 150)->nullable();
            $table->string('accounting_name', 150)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('final_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_purchases');
    }
};
