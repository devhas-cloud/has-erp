<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->json('formula')->nullable()->after('unit');
        });

        Schema::table('quotation_config_items', function (Blueprint $table) {
            $table->json('formula')->nullable()->after('unit');
        });

        Schema::table('quotation_cost_items', function (Blueprint $table) {
            $table->json('formula')->nullable()->after('unit');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->json('formula')->nullable()->after('ppn_amount');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn('formula');
        });

        Schema::table('quotation_config_items', function (Blueprint $table) {
            $table->dropColumn('formula');
        });

        Schema::table('quotation_cost_items', function (Blueprint $table) {
            $table->dropColumn('formula');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('formula');
        });
    }
};
