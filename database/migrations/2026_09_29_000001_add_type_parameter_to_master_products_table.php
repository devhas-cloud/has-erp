<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->enum('type', ['Acc', 'Main', 'Service'])->nullable();
            $table->string('parameter', 255)->nullable();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'parameter']);
        });
    }
};
