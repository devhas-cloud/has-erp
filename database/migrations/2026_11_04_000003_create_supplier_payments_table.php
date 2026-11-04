<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran Supplier (Finance Tahap 2) — dana keluar ke supplier. Satu
 * pembayaran dialokasikan ke satu/banyak tagihan terverifikasi supplier yang
 * sama (boleh sebagian). Kurs saat bayar disimpan agar nilai Rupiah konsisten.
 * Rekening tujuan = snapshot master supplier saat pembayaran dibuat.
 * Status: draft -> posted (approver Finance) -> void beralasan (membuka sisa tagihan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('payment_date');
            $table->string('method', 20);
            $table->string('source_account', 150)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_no', 50)->nullable();
            $table->string('bank_account_name', 150)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->string('currency', 10);
            $table->decimal('amount', 18, 2)->default(0);
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

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained('supplier_payments')->cascadeOnDelete();
            $table->foreignId('supplier_bill_id')->constrained('supplier_bills')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->unique(['supplier_payment_id', 'supplier_bill_id'], 'spa_payment_bill_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
    }
};
