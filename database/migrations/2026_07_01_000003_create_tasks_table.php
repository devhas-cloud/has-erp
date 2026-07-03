<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('task_categories')->restrictOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('due_date');
            $table->enum('status', ['todo', 'in_progress', 'waiting_approval', 'done'])
                ->default('todo');
            $table->boolean('requires_approval')->default(false);
            $table->enum('alert_type', ['none', 'email', 'whatsapp', 'both'])
                ->default('none');
            $table->enum('alert_target', ['personal', 'group', 'both'])
                ->default('personal');
            $table->dateTime('alert_time')->nullable();
            $table->boolean('is_alert_sent')->default(false);
            $table->timestamps();

            $table->index('status');
            $table->index('due_date');
            $table->index(['is_alert_sent', 'alert_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
