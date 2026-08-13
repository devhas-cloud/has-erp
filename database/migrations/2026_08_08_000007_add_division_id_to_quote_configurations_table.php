<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_configurations', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->after('id')
                ->constrained('divisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quote_configurations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('division_id');
        });
    }
};
