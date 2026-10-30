<?php

namespace App\Support;

use App\Models\Currency;

/**
 * Formulasi harga jual dari harga beli master product (1 sumber rumus,
 * dipakai Product Management & Quotation):
 *   base (is_base / tanpa currency): selling_currency = purchase x 2 ; IDR = nilai itu
 *   non-base:                        selling_currency = purchase / 0.43 ;
 *                                    IDR = x rate + ceil kelipatan 500rb (< 10 jt) / 1 jt
 */
class ProductPricing
{
    /**
     * @param  Currency|int|null  $currency
     * @return array{price_currency: float, price_idr: float}
     */
    public static function price($currency, $purchasePrice): array
    {
        if (! $currency instanceof Currency) {
            $currency = $currency ? Currency::find($currency) : null;
        }

        $isBase    = ! $currency || $currency->is_base;   // tanpa currency = base
        $purchase  = (float) $purchasePrice;

        if ($purchase <= 0) {
            return ['price_currency' => 0.0, 'price_idr' => 0.0];
        }

        $priceCurrency = $isBase ? $purchase * 2 : $purchase / 0.43;
        $priceIdr      = $priceCurrency * ($isBase ? 1.0 : (float) $currency->rate);

        if (! $isBase) {                                  // ceil hanya non-base
            $step     = $priceIdr < 10000000 ? 500000 : 1000000;
            $priceIdr = ceil($priceIdr / $step) * $step;
        }

        return ['price_currency' => round($priceCurrency, 2), 'price_idr' => round($priceIdr, 2)];
    }
}
