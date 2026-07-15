<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('stages')->nullOnDelete();
            $table->unsignedInteger('probability')->default(0);
            $table->foreignId('forecast_id')->nullable()->constrained('forecasts')->nullOnDelete();
            $table->string('opportunity_name', 150);
            $table->foreignId('loss_reasons_id')->nullable()->constrained('loss_reasons')->nullOnDelete();
            $table->boolean('quote_ready')->default(false);
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->foreignId('account_companies_id')->constrained('account_companies');
            $table->foreignId('account_contacts_id')->nullable()->constrained('account_contacts')->nullOnDelete();
            $table->string('type')->nullable();
            $table->date('close_date')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->text('next_step')->nullable();
            $table->foreignId('end_user_id')->nullable()->constrained('account_companies', 'id')->nullOnDelete();
            $table->boolean('budget')->default(false);
            $table->boolean('authorize')->default(false);
            $table->boolean('timeline')->default(false);
            $table->date('close_won_date')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
