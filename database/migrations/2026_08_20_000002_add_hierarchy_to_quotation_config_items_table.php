<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_config_items', function (Blueprint $table) {
            $table->string('item_no', 50)->nullable()->after('quote_configuration_id');
            $table->foreignId('parent_id')->nullable()
                ->after('item_no')
                ->constrained('quotation_config_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotation_config_items', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'item_no']);
        });
    }
};
