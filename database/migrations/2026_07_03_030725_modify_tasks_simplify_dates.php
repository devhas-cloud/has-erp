<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('start_date');
            $table->date('due_date')->change();
            $table->time('time')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('time');
            $table->dateTime('due_date')->change();
            $table->dateTime('start_date')->after('division_id');
        });
    }
};
