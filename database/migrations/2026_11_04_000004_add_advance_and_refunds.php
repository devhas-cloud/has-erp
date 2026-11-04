<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance Tahap 3 — Uang Muka & Refund.
 *  - supplier_bills.advance_deduction: potongan uang muka (sudah dibayar) pada
 *    tagihan reguler; yang harus dibayar = total - advance_deduction.
 *  - supplier_refunds: uang muka yang dikembalikan supplier (PO batal / barang
 *    kurang). Draft -> posted (approver Finance) -> void beralasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->decimal('advance_deduction', 18, 2)->default(0)->after('total');
        });

        Schema::create('supplier_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->date('refund_date');
            $table->string('method', 20);
            $table->string('destination_account', 150)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->string('currency', 10);
            $table->decimal('amount', 18, 2);
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('amount_base', 18, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_refunds');
        Schema::table('supplier_bills', fn (Blueprint $table) => $table->dropColumn('advance_deduction'));
    }
};
