<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            // Nomor internal: YYMM + 4 digit urut, mulai 0001 dan reset tiap tahun.
            $table->string('invoice_number', 50)->unique();
            $table->date('invoice_date');
            $table->text('ship_to')->nullable();
            $table->date('date_shipped')->nullable();
            $table->string('shipping_method', 100)->nullable();
            $table->string('do_number', 100)->nullable();
            $table->string('terms', 100)->nullable();
            $table->date('due_date')->nullable();
            $table->date('date_payment')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('noted', 255)->nullable();
            $table->decimal('percent_payment', 5, 2)->default(100);
            $table->decimal('amount_payment', 15, 2)->default(0);
            // PPN disimpan sebagai persen, bukan nominal.
            $table->decimal('ppn', 5, 2)->default(0);
            $table->decimal('total_payment', 15, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
