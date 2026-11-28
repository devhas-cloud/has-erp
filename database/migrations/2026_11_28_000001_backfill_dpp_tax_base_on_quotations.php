<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill kolom quotations.dpp agar mengikuti tarif pajak.
 *
 * Sebelumnya dpp = netto (subtotal - diskon). Semenjak PPN dipungut 11% dari
 * netto dengan dasar pengenaan tarif 12%, DPP yang benar adalah netto x 11/12.
 * Nilai ppn dan grand_total tidak diubah (sudah benar).
 */
return new class extends Migration
{
    private const DPP_TAX_BASE_RATE = 0.12;

    public function up(): void
    {
        $rows = DB::table('quotations')
            ->select('id', 'subtotal', 'discount_amount', 'ppn_percent')
            ->whereNotNull('ppn_percent')
            ->where('ppn_percent', '>', 0)
            ->get();

        foreach ($rows as $row) {
            $netto = round((float) $row->subtotal - (float) $row->discount_amount, 2);
            // Bulat ke bawah, tanpa desimal.
            $dpp = floor($netto * (float) $row->ppn_percent / (self::DPP_TAX_BASE_RATE * 100));

            DB::table('quotations')->where('id', $row->id)->update(['dpp' => $dpp]);
        }

        // Quotation tanpa PPN: DPP tetap sama dengan netto.
        $rows = DB::table('quotations')
            ->select('id', 'subtotal', 'discount_amount')
            ->where(function ($q) {
                $q->whereNull('ppn_percent')->orWhere('ppn_percent', 0);
            })
            ->get();

        foreach ($rows as $row) {
            $netto = round((float) $row->subtotal - (float) $row->discount_amount, 2);

            DB::table('quotations')->where('id', $row->id)->update(['dpp' => $netto]);
        }
    }

    public function down(): void
    {
        // Tidak ada rollback nilai: dpp lama tidak bisa direkonstruksi dengan aman
        // karena sebelumnya selalu sama dengan netto.
        DB::table('quotations')->update([
            'dpp' => DB::raw('ROUND(subtotal - COALESCE(discount_amount, 0), 2)'),
        ]);
    }
};
