<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_configuration_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('water_configuration_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('water_configuration_templates')->cascadeOnDelete();
            $table->string('item_no', 50)->nullable();
            $table->foreignId('parent_id')->nullable()
                ->constrained('water_configuration_template_items')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->string('category', 100)->nullable();
            $table->string('part_number', 100)->nullable();
            $table->text('description')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('price_currency', 15, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('unit', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_configuration_template_items');
        Schema::dropIfExists('water_configuration_templates');
    }
};