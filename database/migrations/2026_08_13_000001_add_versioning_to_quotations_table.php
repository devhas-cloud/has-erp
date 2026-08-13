<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('id');
            $table->integer('version')->default(1)->after('group_id');
            $table->foreignId('parent_id')->nullable()
                ->after('version')
                ->constrained('quotations')
                ->nullOnDelete();
            $table->boolean('is_current')->default(true)->after('parent_id');
            $table->foreignId('unlocked_by')->nullable()
                ->after('is_current')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('unlocked_at')->nullable()->after('unlocked_by');
            $table->foreignId('final_checked_by')->nullable()
                ->after('unlocked_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('approval_note')->nullable()->after('final_checked_by');
            $table->timestamp('approved_at')->nullable()->after('approval_note');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
        });

        // Backward-compat: status lama 'issued' -> 'approved'.
        DB::table('quotations')
            ->where('status', 'issued')
            ->update(['status' => 'approved', 'approved_at' => now()]);

        // Backfill group_id (grup = dokumen pertama di grup tsb, biasanya id terkecil).
        DB::table('quotations')
            ->whereNull('group_id')
            ->update([
                'group_id' => DB::raw('id'),
                'is_current' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['unlocked_by']);
            $table->dropForeign(['final_checked_by']);
            $table->dropColumn([
                'group_id',
                'version',
                'parent_id',
                'is_current',
                'unlocked_by',
                'unlocked_at',
                'final_checked_by',
                'approval_note',
                'approved_at',
                'rejected_at',
            ]);
        });
    }
};
