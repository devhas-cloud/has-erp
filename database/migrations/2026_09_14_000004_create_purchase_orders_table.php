<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PO ke supplier. supplier_name teks bebas (tidak ada master Supplier di
     * aplikasi ini — mengikuti pola field `vendor` pada ProfitEstimateLine).
     * Satu PO bisa berisi item dari beberapa Permintaan Barang berbeda
     * (lintas divisi) — lihat purchase_order_items.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name', 200);
            $table->string('po_number', 50)->nullable();
            $table->date('date')->nullable();
            $table->string('status', 50)->default('draft');
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('purchase_orders');
    }
};
