<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Format nomor dokumen: PREFIX-YYMM + 4 digit urut, reset tiap tahun.
 * Prefix per tabel diatur di config/document_numbers.php.
 * Contoh: PO-26100001 = Purchase Order pertama Oktober 2026.
 */
class NumberSequence
{
    /**
     * $allowSuffix: ikut menghitung nomor bersuffix huruf (mis. PO-26100002-L)
     * agar urutan dipakai bersama lintas suffix — dipakai nomor PO per jenis.
     */
    public static function make(string $table, string $column, ?string $date = null, bool $allowSuffix = false): string
    {
        $timestamp = $date ? strtotime($date) : now()->getTimestamp();
        $prefix = static::prefix($table);
        $yearPrefix = $prefix.date('y', $timestamp);

        $maxSeq = 0;
        $quoted = preg_quote($prefix, '/');
        $pattern = $allowSuffix ? '/^'.$quoted.'(\d{8})(-[A-Z]+)?$/' : '/^'.$quoted.'(\d{8})$/';

        DB::table($table)
            ->where($column, 'like', $yearPrefix.'%')
            ->whereNotNull($column)
            ->pluck($column)
            ->each(function ($number) use (&$maxSeq, $pattern) {
                if (! preg_match($pattern, (string) $number, $match)) {
                    return;
                }

                $maxSeq = max($maxSeq, (int) substr($match[1], 4));
            });

        return $prefix.date('ym', $timestamp).str_pad((string) ($maxSeq + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * "PO-" untuk tabel purchase_orders; tabel tanpa prefix -> "" (format lama).
     */
    public static function prefix(string $table): string
    {
        $prefix = (string) config('document_numbers.'.$table, '');

        return $prefix === '' ? '' : $prefix.'-';
    }
}
