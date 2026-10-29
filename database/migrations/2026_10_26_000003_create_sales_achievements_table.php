<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('sales_person_id')->constrained('users');
            // Pembagian sama rata dari projects.net_sale_amount.
            $table->decimal('amount_achieved', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'sales_person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_achievements');
    }
};
