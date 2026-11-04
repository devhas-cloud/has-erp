<?php

namespace App\Support;

use App\Models\Currency;

/**
 * Format angka satu standar untuk seluruh aplikasi (gaya US): koma = pemisah
 * ribuan, titik = desimal. Pasangan JavaScript-nya: window.Num
 * (resources/views/partials/number-input.blade.php).
 *
 *  - money : Rupiah tanpa desimal kecuali memang ada sen (10,000 / 17,112,643.56);
 *            valas selalu 2 desimal (3,579.75).
 *  - qty   : 1,250 (desimal hanya bila ada).
 *  - percent: 11.5 (maks. 2 desimal, tanpa pemisah ribuan).
 *  - rate  : kurs, 16,250.5 (maks. 6 desimal).
 */
class Number
{
    /**
     * Angka dengan pemisah ribuan; desimal di belakang yang nol dibuang.
     */
    public static function format($value, int $maxDecimals = 2, int $minDecimals = 0): string
    {
        $value = round((float) $value, $maxDecimals);
        $formatted = number_format($value, $maxDecimals, '.', ',');

        if ($maxDecimals > $minDecimals && str_contains($formatted, '.')) {
            [$int, $dec] = explode('.', $formatted);
            $dec = rtrim($dec, '0');
            $dec = str_pad($dec, $minDecimals, '0');
            $formatted = $dec === '' ? $int : $int.'.'.$dec;
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    /**
     * Nilai uang. Mata uang dasar (Rupiah): tanpa desimal, kecuali ada sen.
     * Valas: selalu 2 desimal.
     */
    public static function money($value, ?string $currency = null): string
    {
        $isBase = $currency === null || strtoupper($currency) === Currency::baseName();
        if (! $isBase) {
            return number_format(round((float) $value, 2), 2, '.', ',');
        }

        $cents = round((float) $value, 2);

        return abs($cents - round($cents)) >= 0.005
            ? number_format($cents, 2, '.', ',')
            : number_format(round($cents), 0, '.', ',');
    }

    /** Nilai uang dengan kode mata uang di depan, mis. "IDR 10,000" / "USD 3,579.75". */
    public static function moneyWithCode($value, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?: Currency::baseName());

        return $currency.' '.static::money($value, $currency);
    }

    public static function qty($value): string
    {
        return static::format($value, 2);
    }

    public static function percent($value): string
    {
        return str_replace(',', '', static::format($value, 2));
    }

    public static function rate($value): string
    {
        return static::format($value, 6);
    }
}
