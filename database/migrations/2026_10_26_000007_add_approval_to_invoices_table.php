<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('approval_status', 30)->default('draft')->after('status')->index();
            $table->foreignId('final_checked_by')->nullable()->after('approval_status')
                ->constrained('users')->nullOnDelete();
            $table->text('approval_note')->nullable()->after('final_checked_by');
            $table->timestamp('approved_at')->nullable()->after('approval_note');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
        });

        // Invoice lama yang sudah dikirim/dibayar dianggap sudah melewati approval.
        DB::table('invoices')
            ->whereIn('status', ['sent', 'paid', 'overdue', 'cancelled'])
            ->update([
                'approval_status' => 'approved',
                'approved_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['final_checked_by']);
            $table->dropIndex(['approval_status']);
            $table->dropColumn([
                'approval_status',
                'final_checked_by',
                'approval_note',
                'approved_at',
                'rejected_at',
            ]);
        });
    }
};
