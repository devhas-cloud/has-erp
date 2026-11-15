<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('visits')->default(0);
            $table->string('note', 150)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'start_date', 'end_date'], 'visit_baselines_unique_periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_baselines');
    }
};
