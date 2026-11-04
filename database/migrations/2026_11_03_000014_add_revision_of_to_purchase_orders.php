<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi PO Rejected: PO baru (draft) disalin dari PO yang ditolak dengan
 * nomor dasar yang sama + "REV" (26110005REV, 26110005REV2-L, ...).
 * revision_of_id menunjuk PO Rejected yang direvisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('revision_of_id')->nullable()->after('po_type')->constrained('purchase_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revision_of_id');
        });
    }
};
