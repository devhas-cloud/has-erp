<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\MasterProduct;

/**
 * Harga modal stok dalam mata uang base. Tidak ada harga rata-rata: setiap
 * baris stok membawa unit_cost-nya sendiri (lihat App\Models\Stock).
 */
class StockCost
{
    /**
     * Harga beli master produk dalam mata uang base (default isian Stock Entry).
     */
    public static function standardCost(int $masterProductId): float
    {
        $product = MasterProduct::with('currency')->find($masterProductId);
        if (! $product) {
            return 0.0;
        }

        return round((float) $product->purchase_price * Currency::rateOf($product->currency?->name), 4);
    }

    /**
     * Harga satuan dokumen (mata uang dokumen, setelah diskon) -> base.
     * $rate = kurs yang sudah ditetapkan (mis. kurs penerimaan); null = kurs master saat ini.
     */
    public static function toBase(?float $price, ?string $currency, float $discountPercent = 0, ?float $rate = null): float
    {
        return round((float) $price * (1 - $discountPercent / 100) * ($rate ?? Currency::rateOf($currency)), 4);
    }

    /**
     * Format nilai uang: tanpa desimal kecuali ada sen.
     */
    public static function format(?float $value): string
    {
        if ($value === null) {
            return '—';
        }
        $decimals = abs($value - round($value)) > 0.004 ? 2 : 0;

        return Number::format($value, $decimals);
    }

    /**
     * Format qty desimal: buang nol di belakang (10.5000 -> 10.5, 3.0000 -> 3).
     */
    public static function qty(?float $value): string
    {
        if ($value === null) {
            return '—';
        }
        $value = round($value, 4);
        $decimals = 0;
        while ($decimals < 4 && abs($value - round($value, $decimals)) > 0.00001) {
            $decimals++;
        }

        return Number::format($value, $decimals);
    }
}
