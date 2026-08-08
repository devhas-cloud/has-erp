<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number', 50)->unique();
            $table->string('to_name', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('location', 150)->nullable();
            $table->string('pic_name', 100)->nullable();
            $table->string('pic_phone', 50)->nullable();
            $table->string('pic_email', 100)->nullable();
            $table->string('sales_name', 100)->nullable();
            $table->date('quotation_date')->nullable();
            $table->string('parameter_note', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('final_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('quotation_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_configurations');
    }
};
