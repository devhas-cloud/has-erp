<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('type', 20)->index();
            $table->string('calculation', 40)->default('fixed')->index();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('percent', 6, 3)->default(0);
            $table->decimal('amount_cap', 15, 2)->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('prorate_on_absence')->default(false);
            $table->boolean('is_globally_assigned')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
