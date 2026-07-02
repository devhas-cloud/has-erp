<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('division_name', 100);
            $table->text('description')->nullable();
            $table->enum('type', ['Internal', 'External'])->default('Internal');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->string('whatsapp_group_id', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
