<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_status', 50)->default('New');
            $table->string('lead_title', 500);
            $table->foreignId('account_contacts_id')->constrained('account_contacts')->cascadeOnDelete();
            $table->foreignId('account_companies_id')->nullable()->constrained('account_companies')->nullOnDelete();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('unqualified_reason')->nullable();
            $table->date('closed_date')->nullable();
            $table->boolean('all_filed_completed')->default(false);
            $table->foreignId('lead_owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('lead_can_be_contacted')->default(false);
            $table->date('lead_follow_up_date')->nullable();
            $table->boolean('lead_appoinment')->default(false);
            $table->boolean('identification')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
