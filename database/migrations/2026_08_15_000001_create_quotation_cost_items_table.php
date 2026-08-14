<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_cost_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->string('item_no', 50)->nullable();
            $table->foreignId('parent_id')->nullable()
                ->constrained('quotation_cost_items')
                ->cascadeOnDelete();
            $table->string('title', 200)->nullable();
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
        Schema::dropIfExists('quotation_cost_items');
    }
};
