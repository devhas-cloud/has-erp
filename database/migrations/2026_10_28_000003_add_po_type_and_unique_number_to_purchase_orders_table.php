<?php

use App\Support\NumberSequence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor PO dibuat otomatis (YYMM + 4 digit, urutan bersama lintas jenis) dengan
 * suffix sesuai jenis: manufacture tanpa suffix, local "-L", calibration "-C".
 * PO lama tanpa nomor diberi nomor dari tanggal dibuatnya; nomor manual lama
 * dipertahankan. Migration berhenti bila ada nomor dobel agar unique index
 * tidak gagal diam-diam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('po_type', 20)->default('manufacture')->after('po_number');
        });

        DB::table('purchase_orders')->where('po_number', '')->update(['po_number' => null]);

        DB::table('purchase_orders')->whereNull('po_number')->orderBy('created_at')->orderBy('id')
            ->get(['id', 'created_at'])
            ->each(function ($po) {
                DB::table('purchase_orders')->where('id', $po->id)->update([
                    'po_number' => NumberSequence::make('purchase_orders', 'po_number', $po->created_at, true),
                ]);
            });

        $duplicates = DB::table('purchase_orders')->select('po_number')
            ->groupBy('po_number')->havingRaw('COUNT(*) > 1')->pluck('po_number');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Nomor PO dobel, perbaiki manual dulu sebelum migrate: '.$duplicates->implode(', '));
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unique('po_number');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique(['po_number']);
            $table->dropColumn('po_type');
        });
    }
};
