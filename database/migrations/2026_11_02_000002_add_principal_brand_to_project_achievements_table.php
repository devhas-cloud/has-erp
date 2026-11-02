<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian achievement per device dilengkapi principal (pabrikan) dan brand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_achievements', function (Blueprint $table) {
            $table->string('principal', 150)->nullable()->after('project_id');
            $table->string('brand', 150)->nullable()->after('principal');
        });
    }

    public function down(): void
    {
        Schema::table('project_achievements', function (Blueprint $table) {
            $table->dropColumn(['principal', 'brand']);
        });
    }
};
