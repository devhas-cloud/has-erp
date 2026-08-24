<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('handling_group_id')->nullable()
                ->after('handling_division_id')
                ->constrained('handling_groups')
                ->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['handling_division_id']);
            $table->dropColumn('handling_division_id');
        });

        Schema::dropIfExists('division_handlers');
    }

    public function down(): void
    {
        Schema::create('division_handlers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['division_id', 'user_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('handling_division_id')->nullable()
                ->after('category_id')
                ->constrained('divisions')
                ->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['handling_group_id']);
            $table->dropColumn('handling_group_id');
        });
    }
};
