<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor Permintaan Barang dengan format dokumen yang sama (YYMM + 4 digit,
 * reset tiap tahun — App\Support\NumberSequence). Data lama diberi nomor
 * berurutan menurut tanggal dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_requests', function (Blueprint $table) {
            $table->string('request_number', 50)->nullable()->unique()->after('id');
        });

        $sequence = [];
        foreach (DB::table('goods_requests')->orderBy('created_at')->orderBy('id')->get(['id', 'created_at']) as $row) {
            $timestamp = $row->created_at ? strtotime($row->created_at) : time();
            $year = date('y', $timestamp);
            $sequence[$year] = ($sequence[$year] ?? 0) + 1;

            DB::table('goods_requests')->where('id', $row->id)->update([
                'request_number' => date('ym', $timestamp).str_pad((string) $sequence[$year], 4, '0', STR_PAD_LEFT),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('goods_requests', function (Blueprint $table) {
            $table->dropUnique(['request_number']);
            $table->dropColumn('request_number');
        });
    }
};
