<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('device_name');
            $table->decimal('qty', 15, 2)->default(0);
            $table->string('unit', 50)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->foreignId('division_id')->constrained('divisions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_achievements');
    }
};
