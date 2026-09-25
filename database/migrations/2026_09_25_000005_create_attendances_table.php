<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date')->index();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->string('check_in_method', 30)->default('manual')->index();
            $table->string('source', 20)->default('web');
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('check_in_lat', 10, 8)->nullable();
            $table->decimal('check_in_lng', 11, 8)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('face_matched')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
