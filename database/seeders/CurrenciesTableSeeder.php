<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrenciesTableSeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            [
                'name' => 'IDR',
                'symbol' => 'Rp',
                'rate' => 1.0000,
                'is_base' => true,
                'status' => 'Active',
                'description' => 'Indonesian Rupiah (mata uang base / acuan)',
            ],
            [
                'name' => 'USD',
                'symbol' => '$',
                'rate' => 20000.0000,
                'is_base' => false,
                'status' => 'Active',
                'description' => 'US Dollar. Contoh kurs: 1 USD = 20.000 IDR.',
            ],
            [
                'name' => 'EUR',
                'symbol' => '€',
                'rate' => 18000.0000,
                'is_base' => false,
                'status' => 'Active',
                'description' => 'Euro. Contoh kurs: 1 EUR = 18.000 IDR.',
            ],
            [
                'name' => 'GBP',
                'symbol' => '£',
                'rate' => 22000.0000,
                'is_base' => false,
                'status' => 'Active',
                'description' => 'British Pound Sterling.',
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::firstOrCreate(
                ['name' => $currency['name']],
                $currency
            );
        }
    }
}
