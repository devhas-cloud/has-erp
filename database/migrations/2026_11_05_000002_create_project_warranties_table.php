<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Masa garansi project (sederhana): kapan garansi mulai dihitung dan kapan berakhir.
 * Satu project satu masa garansi; ikut terhapus bila project dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_warranties');
    }
};
