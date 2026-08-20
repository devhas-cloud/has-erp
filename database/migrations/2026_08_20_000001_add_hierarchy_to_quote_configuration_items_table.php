<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_configuration_items', function (Blueprint $table) {
            $table->string('item_no', 50)->nullable()->after('quote_configuration_id');
            $table->foreignId('parent_id')->nullable()
                ->after('item_no')
                ->constrained('quote_configuration_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quote_configuration_items', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'item_no']);
        });
    }
};
