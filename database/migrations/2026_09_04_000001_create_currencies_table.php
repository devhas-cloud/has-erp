<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 10)->unique();
            $table->string('symbol', 10)->nullable();
            $table->decimal('rate', 15, 4)->default(1.0000);
            $table->boolean('is_base')->default(false);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
