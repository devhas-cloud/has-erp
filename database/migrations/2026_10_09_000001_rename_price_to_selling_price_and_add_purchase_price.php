<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename kolom harga jual -> selling_price / selling_price_currency dan
     * tambah kolom purchase_price_currency (snapshot harga beli pada tabel item).
     * Dipecah per tabel agar renameColumn tidak
     * mengabaikan perubahan lain. Tanpa ->after() supaya portabel (SQLite).
     */
    public function up(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->renameColumn('price', 'selling_price');
        });
        Schema::table('master_products', function (Blueprint $table) {
            $table->decimal('purchase_price', 15, 2)->nullable()->default(0);
        });

        foreach (['quote_configuration_items', 'configuration_template_items', 'quotation_config_items', 'purchase_order_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('price', 'selling_price');
            });
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('price_currency', 'selling_price_currency');
            });
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('purchase_price_currency', 15, 2)->nullable()->default(0);
            });
        }

        foreach (['quotation_items', 'quotation_cost_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('price', 'selling_price');
            });
        }
    }

    public function down(): void
    {
        foreach (['quotation_items', 'quotation_cost_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('selling_price', 'price');
            });
        }

        foreach (['quote_configuration_items', 'configuration_template_items', 'quotation_config_items', 'purchase_order_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('purchase_price_currency');
            });
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('selling_price_currency', 'price_currency');
            });
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('selling_price', 'price');
            });
        }

        Schema::table('master_products', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });
        Schema::table('master_products', function (Blueprint $table) {
            $table->renameColumn('selling_price', 'price');
        });
    }
};
