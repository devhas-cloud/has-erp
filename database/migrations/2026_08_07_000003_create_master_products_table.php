<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->nullable();
            $table->string('code', 50)->unique();
            $table->string('brand', 100)->nullable();
            $table->string('category', 100)->nullable();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 15, 2)->default(0.00);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->index('status');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_products');
    }
};
