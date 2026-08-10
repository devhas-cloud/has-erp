<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_categories', function (Blueprint $table) {
            $table->boolean('use_division_handler')->default(false)->after('division_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_categories', function (Blueprint $table) {
            $table->dropColumn('use_division_handler');
        });
    }
};
