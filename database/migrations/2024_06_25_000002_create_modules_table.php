<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('module_code', 50)->unique();
            $table->string('module_name', 100);
            $table->text('description')->nullable();
            $table->string('route_name', 100)->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('group', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
