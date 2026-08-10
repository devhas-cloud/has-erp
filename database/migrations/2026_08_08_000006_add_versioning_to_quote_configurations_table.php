<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_configurations', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('id');
            $table->unsignedInteger('version')->default(1)->after('group_id');
            $table->foreignId('parent_id')->nullable()->after('version')
                ->constrained('quote_configurations')
                ->nullOnDelete();
            $table->boolean('is_current')->default(true)->after('parent_id');
            $table->foreignId('unlocked_by')->nullable()->after('is_current')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('unlocked_at')->nullable()->after('unlocked_by');

            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::table('quote_configurations', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
            $table->dropConstrainedForeignId('unlocked_by');
            $table->dropColumn(['group_id', 'version', 'parent_id', 'is_current', 'unlocked_at']);
        });
    }
};
