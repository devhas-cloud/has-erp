<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
            $table->decimal('override_amount', 15, 2)->nullable();
            $table->date('active_since')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['employee_id', 'salary_component_id'], 'esc_employee_component_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
    }
};
