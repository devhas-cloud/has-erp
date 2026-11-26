<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor invoice supplier hanya unik di antara tagihan non-void per supplier.
 * Kolom active_invoice = 1 untuk non-void, NULL untuk void; unique index
 * (supplier_id, supplier_invoice_number, active_invoice) tetap mengizinkan
 * nomor yang sama dipakai lagi setelah tagihan lama di-void.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('supplier_bills')
            ->select('supplier_id', 'supplier_invoice_number')
            ->where('status', '!=', 'void')
            ->groupBy('supplier_id', 'supplier_invoice_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $pairs = $duplicates
                ->map(fn ($row) => $row->supplier_id.':'.$row->supplier_invoice_number)
                ->implode(', ');

            throw new RuntimeException(
                'Tidak bisa menambah unique invoice supplier: kombinasi supplier:invoice berikut duplikat non-void: '.$pairs
            );
        }

        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->unsignedTinyInteger('active_invoice')->nullable()->after('supplier_invoice_number');
        });

        DB::table('supplier_bills')
            ->where('status', '!=', 'void')
            ->update(['active_invoice' => 1]);

        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->unique(
                ['supplier_id', 'supplier_invoice_number', 'active_invoice'],
                'supplier_bills_supplier_invoice_active_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->dropUnique('supplier_bills_supplier_invoice_active_unique');
            $table->dropColumn('active_invoice');
        });
    }
};
