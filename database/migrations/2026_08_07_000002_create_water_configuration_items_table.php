<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_configuration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_configuration_id')->constrained()->cascadeOnDelete();
            $table->string('category', 100)->nullable();
            $table->string('part_number', 100)->nullable();
            $table->text('description')->nullable();
            $table->integer('qty')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['water_configuration_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_configuration_items');
    }
};
