<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\MasterProduct;
use App\Models\StockMovement;

/**
 * Valuasi stok rata-rata bergerak (moving average) per produk, lintas gudang &
 * kepemilikan, dalam mata uang base. Barang masuk dari pembelian / masuk
 * lain-lain membawa harga sendiri; semua pergerakan lain (keluar, transfer,
 * alokasi, penyesuaian) dinilai dengan rata-rata saat itu sehingga pasangan
 * transfer/alokasi tidak mengubah nilai persediaan.
 */
class StockValuation
{
    /**
     * Harga rata-rata saat ini. Saldo nol/negatif -> harga terakhir yang
     * tercatat, lalu harga beli master (dikonversi kurs).
     */
    public static function averageCost(int $masterProductId): float
    {
        $inTypes = StockMovement::IN_TYPES;
        $totals = StockMovement::where('master_product_id', $masterProductId)
            ->get(['type', 'qty', 'unit_cost'])
            ->reduce(function (array $carry, StockMovement $m) use ($inTypes) {
                $sign = in_array($m->type, $inTypes, true) ? 1 : -1;
                $carry['qty'] += $sign * $m->qty;
                $carry['value'] += $sign * $m->qty * (float) $m->unit_cost;

                return $carry;
            }, ['qty' => 0, 'value' => 0.0]);

        if ($totals['qty'] > 0) {
            return round($totals['value'] / $totals['qty'], 4);
        }

        $last = StockMovement::where('master_product_id', $masterProductId)->whereNotNull('unit_cost')->latest('id')->value('unit_cost');

        return $last !== null ? (float) $last : self::standardCost($masterProductId);
    }

    /**
     * Harga beli master produk dalam mata uang base.
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
     */
    public static function toBase(?float $price, ?string $currency, float $discountPercent = 0): float
    {
        return round((float) $price * (1 - $discountPercent / 100) * Currency::rateOf($currency), 4);
    }

    public static function format(?float $value): string
    {
        if ($value === null) {
            return '—';
        }
        $decimals = abs($value - round($value)) > 0.004 ? 2 : 0;

        return number_format($value, $decimals, ',', '.');
    }
}
