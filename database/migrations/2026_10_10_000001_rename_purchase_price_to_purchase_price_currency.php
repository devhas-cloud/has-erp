<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename purchase_price -> purchase_price_currency pada tabel item snapshot
     * (config, template, quotation snapshot, PO). Migration 2026_10_09 sudah
     * menyediakan purchase_price_currency untuk fresh install; migration ini
     * hanya memperbaiki DB yang sudah terlanjur punya nama purchase_price.
     */
    public function up(): void
    {
        foreach (['quote_configuration_items', 'configuration_template_items', 'quotation_config_items', 'purchase_order_items'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'purchase_price')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('purchase_price', 'purchase_price_currency');
            });
        }
    }

    public function down(): void
    {
        foreach (['quote_configuration_items', 'configuration_template_items', 'quotation_config_items', 'purchase_order_items'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'purchase_price_currency')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('purchase_price_currency', 'purchase_price');
            });
        }
    }
};