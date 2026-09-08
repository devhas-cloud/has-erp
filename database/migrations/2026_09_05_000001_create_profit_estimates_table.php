<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->unique()
                ->constrained('quotations')
                ->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()
                ->constrained('tasks')
                ->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()
                ->constrained('opportunities')
                ->nullOnDelete();
            $table->date('date')->nullable();
            $table->string('project_name', 255)->nullable();

            // Snapshot kurs saat PL dibuat: {"USD": 17500, "EUR": 20000, ...}
            $table->json('rates')->nullable();

            // Blok nilai project (input)
            $table->decimal('nilai_awal', 18, 2)->default(0);
            $table->decimal('ppn_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('referral_percent', 7, 2)->default(0);
            $table->decimal('investment_percent', 7, 2)->default(0);
            $table->decimal('marketing_fee_percent', 7, 2)->default(0);
            $table->decimal('pm_fee_percent', 7, 2)->default(0);

            // Nilai turunan (hasil hitung, disimpan agar list & laporan tidak menghitung ulang)
            $table->decimal('subtotal_1', 18, 2)->default(0);
            $table->decimal('subtotal_2', 18, 2)->default(0);
            $table->decimal('referral_amount', 18, 2)->default(0);
            $table->decimal('real_project_value', 18, 2)->default(0);
            $table->decimal('investment_amount', 18, 2)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->decimal('estimated_profit', 18, 2)->default(0);
            $table->decimal('profit_percent', 7, 2)->default(0);
            $table->decimal('marketing_fee_amount', 18, 2)->default(0);
            $table->decimal('pm_fee_amount', 18, 2)->default(0);
            $table->decimal('real_profit', 18, 2)->default(0);
            $table->decimal('real_profit_percent', 7, 2)->default(0);

            $table->text('notes')->nullable();
            $table->string('sales_person_name', 150)->nullable();
            $table->string('finance_name', 150)->nullable();
            $table->string('accounting_name', 150)->nullable();

            // Ditandai outdated bila quotation sumber berubah/direvisi.
            $table->boolean('is_outdated')->default(false);
            $table->timestamp('outdated_at')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('profit_estimate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profit_estimate_id')
                ->constrained('profit_estimates')
                ->cascadeOnDelete();
            // product | hpp | cost_spent | cost_planned
            $table->string('section', 20);
            $table->string('label', 255)->nullable();
            $table->decimal('qty', 12, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('vendor', 150)->nullable();
            $table->string('currency', 10)->nullable();
            // 4 desimal: nilai FOB asing bisa pecahan (mis. 4.715,9375 USD)
            $table->decimal('amount', 18, 4)->nullable();
            $table->decimal('amount_idr', 18, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['profit_estimate_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_estimate_lines');
        Schema::dropIfExists('profit_estimates');
    }
};
