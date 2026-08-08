<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('water_configuration_items')) {
            Schema::drop('water_configuration_items');
        }
        if (Schema::hasTable('water_configurations')) {
            Schema::drop('water_configurations');
        }

        Schema::create('quote_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->date('date')->nullable();
            $table->string('parameter_note', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 50)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('final_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quote_configuration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_configuration_id')->constrained('quote_configurations')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->string('category', 100)->nullable();
            $table->string('part_number', 100)->nullable();
            $table->text('description')->nullable();
            $table->integer('qty')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_configuration_items');
        Schema::dropIfExists('quote_configurations');

        Schema::create('water_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number', 50)->nullable();
            $table->string('to_name', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('location', 150)->nullable();
            $table->string('pic_name', 100)->nullable();
            $table->string('pic_phone', 50)->nullable();
            $table->string('pic_email', 100)->nullable();
            $table->string('sales_name', 100)->nullable();
            $table->date('quotation_date')->nullable();
            $table->string('parameter_note', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 50)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('final_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('water_configuration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_configuration_id')->constrained('water_configurations')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->string('category', 100)->nullable();
            $table->string('part_number', 100)->nullable();
            $table->text('description')->nullable();
            $table->integer('qty')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
