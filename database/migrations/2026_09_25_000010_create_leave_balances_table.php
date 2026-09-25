<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedInteger('entitlement')->default(0);
            $table->unsignedInteger('used')->default(0);
            $table->unsignedInteger('carried_over')->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'leave_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
