<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number', 50)->nullable()->unique();
            $table->foreignId('quote_configuration_id')->nullable()
                ->constrained('quote_configurations')
                ->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()
                ->constrained('opportunities')
                ->nullOnDelete();
            $table->foreignId('task_id')->nullable()
                ->constrained('tasks')
                ->nullOnDelete();
            $table->date('date')->nullable();
            $table->string('currency', 30)->default('Rupiah');
            $table->string('your_ref', 100)->nullable();
            $table->integer('no_of_pages')->default(1);
            $table->string('to_name', 200)->nullable();
            $table->text('address')->nullable();
            $table->string('attn_name', 150)->nullable();
            $table->string('attn_phone', 50)->nullable();
            $table->string('attn_email', 150)->nullable();
            $table->string('from_name', 150)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('parameter_note', 255)->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('dpp', 15, 2)->default(0);
            $table->decimal('ppn', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('status', 50)->default('draft');
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->string('category', 100)->nullable();
            $table->string('part_number', 100)->nullable();
            $table->text('description')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('price', 15, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
