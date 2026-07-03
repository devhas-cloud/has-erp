<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_activities', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_type', 'attachment_name']);
        });
    }

    public function down(): void
    {
        Schema::table('task_activities', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('content');
            $table->string('attachment_type')->nullable()->after('attachment_path');
            $table->string('attachment_name')->nullable()->after('attachment_type');
        });
    }
};
