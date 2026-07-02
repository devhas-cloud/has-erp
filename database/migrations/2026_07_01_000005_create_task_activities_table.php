<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_type')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();

            $table->index('task_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
    }
};
