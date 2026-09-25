<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('present_days')->default(0);
            $table->unsignedInteger('late_count')->default(0);
            $table->unsignedInteger('sick_days')->default(0);
            $table->unsignedInteger('leave_days')->default(0);
            $table->unsignedInteger('absent_days')->default(0);
            $table->decimal('base_salary_prorata', 15, 2)->default(0);
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->decimal('total_deduction', 15, 2)->default(0);
            $table->decimal('total_bpjs_company', 15, 2)->default(0);
            $table->decimal('total_pph21', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account_no', 30)->nullable();
            $table->string('bank_account_name', 100)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
