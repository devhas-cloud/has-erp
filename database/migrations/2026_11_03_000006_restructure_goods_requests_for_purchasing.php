<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan Barang: tujuan 'project' (terikat project) atau 'internal'
 * (stok/kebutuhan internal, tanpa project). opportunity/quotation dihapus —
 * diturunkan lewat project. Item bisa "Tutup Sisa" (closed_qty) agar sisa
 * kebutuhan yang tidak diperlukan lagi tidak bisa dipenuhi / di-PO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_requests', function (Blueprint $table) {
            $table->string('purpose', 20)->default('project')->after('id');
        });

        DB::table('goods_requests')->whereNull('project_id')->update(['purpose' => 'internal']);

        Schema::table('goods_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opportunity_id');
            $table->dropConstrainedForeignId('quotation_id');
        });

        Schema::table('goods_request_items', function (Blueprint $table) {
            $table->integer('closed_qty')->default(0)->after('qty');
            $table->string('closed_reason', 255)->nullable()->after('closed_qty');
            $table->foreignId('closed_by')->nullable()->after('closed_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable()->after('closed_by');
        });
    }

    public function down(): void
    {
        Schema::table('goods_request_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['closed_qty', 'closed_reason', 'closed_at']);
        });

        Schema::table('goods_requests', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->nullable()->after('id')->constrained('opportunities')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->after('opportunity_id')->constrained('quotations')->nullOnDelete();
            $table->dropColumn('purpose');
        });
    }
};
