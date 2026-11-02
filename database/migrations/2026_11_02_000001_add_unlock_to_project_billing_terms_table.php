<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gerbang tagihan termin: termin baru Terkunci (unlocked_at kosong) dan baru
 * bisa dibuatkan invoice setelah dibuka oleh pemegang can_update Project.
 * Termin yang sudah punya invoice dianggap sudah dibuka.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_billing_terms', function (Blueprint $table) {
            $table->timestamp('unlocked_at')->nullable()->after('target_date');
            $table->foreignId('unlocked_by')->nullable()->after('unlocked_at')
                ->constrained('users')->nullOnDelete();
        });

        DB::table('project_billing_terms')
            ->whereIn('id', DB::table('invoices')->whereNotNull('billing_term_id')->select('billing_term_id'))
            ->update(['unlocked_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('project_billing_terms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unlocked_by');
            $table->dropColumn('unlocked_at');
        });
    }
};
