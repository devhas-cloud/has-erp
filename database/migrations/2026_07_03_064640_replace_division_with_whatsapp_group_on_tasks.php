<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropColumn('division_id');
            $table->foreignId('whatsapp_group_id')->nullable()->after('category_id')->constrained('whatsapp_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_group_id']);
            $table->dropColumn('whatsapp_group_id');
            $table->foreignId('division_id')->nullable()->after('category_id')->constrained('divisions')->nullOnDelete();
        });
    }
};
