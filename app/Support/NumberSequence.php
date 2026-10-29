<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Format nomor dokumen: YYMM + 4 digit urut, reset tiap tahun.
 * Contoh: 26100001 = dokumen pertama Oktober 2026.
 */
class NumberSequence
{
    /**
     * $allowSuffix: ikut menghitung nomor bersuffix huruf (mis. 26100002-L)
     * agar urutan dipakai bersama lintas suffix — dipakai nomor PO per jenis.
     */
    public static function make(string $table, string $column, ?string $date = null, bool $allowSuffix = false): string
    {
        $timestamp = $date ? strtotime($date) : now()->getTimestamp();
        $yearPrefix = date('y', $timestamp);

        $maxSeq = 0;

        DB::table($table)
            ->where($column, 'like', $yearPrefix.'%')
            ->whereNotNull($column)
            ->pluck($column)
            ->each(function ($number) use (&$maxSeq, $allowSuffix) {
                $pattern = $allowSuffix ? '/^(\d{8})(-[A-Z]+)?$/' : '/^(\d{8})$/';

                if (! preg_match($pattern, (string) $number, $match)) {
                    return;
                }

                $maxSeq = max($maxSeq, (int) substr($match[1], 4));
            });

        return date('ym', $timestamp).str_pad((string) ($maxSeq + 1), 4, '0', STR_PAD_LEFT);
    }
}
