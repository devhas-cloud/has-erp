<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_config_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('quote_configuration_id')->nullable()
                ->constrained('quote_configurations')
                ->nullOnDelete();
            $table->string('category', 100)->nullable();
            $table->string('part_number', 100)->nullable();
            $table->text('description')->nullable();
            $table->integer('qty')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_config_items');
    }
};
