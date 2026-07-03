<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone_number', 20)->nullable();
            $table->foreignId('division_id')->nullable()
                ->constrained('divisions')->nullOnDelete();
            $table->foreignId('task_role_id')->nullable()
                ->constrained('task_roles')->nullOnDelete();
            $table->enum('role', ['Admin', 'Manager', 'Staff'])->default('Staff');
            $table->string('icon')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
