<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_activity_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_activity_id')->constrained('task_activities')->cascadeOnDelete();
            $table->string('attachment_path');
            $table->string('attachment_type');
            $table->string('attachment_name')->nullable();
            $table->timestamps();

            $table->index('task_activity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activity_attachments');
    }
};
