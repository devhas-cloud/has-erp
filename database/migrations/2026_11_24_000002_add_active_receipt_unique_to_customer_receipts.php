<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu invoice hanya boleh punya satu penerimaan aktif (draft/posted).
 * Kolom active_receipt = 1 untuk baris aktif, NULL untuk void; unique index
 * (invoice_id, active_receipt) memanfaatkan fakta bahwa NULL tidak saling
 * bentrok di MySQL maupun SQLite, sehingga riwayat void tetap boleh banyak.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('customer_receipts')
            ->select('invoice_id')
            ->where('status', '!=', 'void')
            ->groupBy('invoice_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('invoice_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Tidak bisa menambah unique receipt aktif: invoice berikut punya lebih dari satu receipt non-void: '
                .$duplicates->implode(', ')
            );
        }

        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->unsignedTinyInteger('active_receipt')->nullable()->after('status');
        });

        DB::table('customer_receipts')
            ->where('status', '!=', 'void')
            ->update(['active_receipt' => 1]);

        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->unique(['invoice_id', 'active_receipt'], 'customer_receipts_invoice_active_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->dropUnique('customer_receipts_invoice_active_unique');
            $table->dropColumn('active_receipt');
        });
    }
};
