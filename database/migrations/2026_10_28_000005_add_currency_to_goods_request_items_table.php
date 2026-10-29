<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mata uang item Permintaan Barang, dikunci sesuai sumbernya: item configuration
 * -> mata uang item configuration, item katalog -> mata uang master product,
 * selain itu / kosong -> mata uang dasar (IDR). PO mengikuti mata uang ini tanpa
 * konversi kurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_request_items', function (Blueprint $table) {
            $table->string('currency', 10)->nullable()->after('unit');
        });

        $base = strtoupper((string) (DB::table('currencies')->where('is_base', true)->value('name') ?: 'IDR'));
        $configCurrencies = DB::table('quote_configuration_items')->pluck('currency', 'id');
        $productCurrencies = DB::table('master_products')
            ->leftJoin('currencies', 'currencies.id', '=', 'master_products.currency_id')
            ->pluck('currencies.name', 'master_products.id');

        DB::table('goods_request_items')->orderBy('id')
            ->get(['id', 'quote_configuration_item_id', 'master_product_id'])
            ->each(function ($item) use ($base, $configCurrencies, $productCurrencies) {
                $currency = $item->quote_configuration_item_id
                    ? ($configCurrencies[$item->quote_configuration_item_id] ?? null)
                    : ($item->master_product_id ? ($productCurrencies[$item->master_product_id] ?? null) : null);

                DB::table('goods_request_items')->where('id', $item->id)->update([
                    'currency' => strtoupper(trim((string) $currency)) ?: $base,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('goods_request_items', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
