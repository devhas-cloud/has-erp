<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_categories', function (Blueprint $table) {
            $table->boolean('visit')->default(false)->after('use_division_handler');
        });
    }

    public function down(): void
    {
        Schema::table('task_categories', function (Blueprint $table) {
            $table->dropColumn('visit');
        });
    }
};
