<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 25)->nullable();
            $table->string('email')->nullable();
            $table->string('nik', 30)->nullable()->index();
            $table->string('npwp_no', 30)->nullable();
            $table->string('ptkp_status', 10)->default('TK0');
            $table->string('bpjs_kesehatan_no', 30)->nullable();
            $table->string('bpjs_tk_no', 30)->nullable();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained('job_titles')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('join_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account_no', 30)->nullable();
            $table->string('bank_account_name', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
