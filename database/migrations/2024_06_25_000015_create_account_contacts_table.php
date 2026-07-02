<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_companies_id')->constrained('account_companies')->cascadeOnDelete();
            $table->string('full_name', 150);
            $table->string('icon')->nullable();
            $table->enum('salutation', ['Ibu', 'Bapak'])->nullable();
            $table->string('email', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->foreignId('job_titles_id')->nullable()->constrained('job_titles')->nullOnDelete();
            $table->foreignId('sources_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('role_in_projects_id')->nullable()->constrained('role_in_projects')->nullOnDelete();
            $table->foreignId('contact_methods_id')->nullable()->constrained('contact_methods')->nullOnDelete();
            $table->foreignId('divisions_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->foreignId('contact_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('address_street')->nullable();
            $table->string('address_city', 100)->nullable();
            $table->string('address_province', 100)->nullable();
            $table->string('address_postal_code', 10)->nullable();
            $table->string('address_country', 100)->nullable();
            $table->enum('lead_status', ['New', 'Contacted', 'Qualified', 'Unqualified'])->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_contacts');
    }
};
