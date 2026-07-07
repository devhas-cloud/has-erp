<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->after('creator_id')->constrained()->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropForeign(['activity_id']);
            $table->dropColumn(['lead_id', 'activity_id']);
        });
    }
};
