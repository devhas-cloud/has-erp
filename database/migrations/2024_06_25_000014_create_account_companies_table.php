<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_companies', function (Blueprint $table) {
            $table->id();
            $table->string('account_name', 150);
            $table->string('icon')->nullable();
            $table->foreignId('sources_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('types_accounts_companies_id')->nullable()->constrained('types_accounts_companies')->nullOnDelete();
            $table->string('website', 200)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('segmentation_id')->nullable()->constrained('segmentations')->nullOnDelete();
            $table->foreignId('business_entities_id')->nullable()->constrained('business_entities')->nullOnDelete();
            $table->foreignId('business_values_id')->nullable()->constrained('business_values')->nullOnDelete();
            $table->foreignId('account_types_id')->nullable()->constrained('account_types')->nullOnDelete();
            $table->integer('end_user')->nullable();
            $table->foreignId('parent_account_id')->nullable()->constrained('account_companies')->nullOnDelete();
            $table->string('phone', 30)->nullable();
            $table->foreignId('interaction_levels_id')->nullable()->constrained('interaction_levels')->nullOnDelete();
            $table->foreignId('account_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('address_billing_street')->nullable();
            $table->string('address_billing_city', 100)->nullable();
            $table->string('address_billing_province', 100)->nullable();
            $table->string('address_billing_postal_code', 10)->nullable();
            $table->string('address_billing_country', 100)->nullable();
            $table->string('address_shipping_street')->nullable();
            $table->string('address_shipping_city', 100)->nullable();
            $table->string('address_shipping_province', 100)->nullable();
            $table->string('address_shipping_postal_code', 10)->nullable();
            $table->string('address_shipping_country', 100)->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_companies');
    }
};
