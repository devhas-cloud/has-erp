<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Status tagihan disederhanakan: unpaid, paid, cancelled.
        DB::table('invoices')
            ->whereIn('status', ['draft', 'sent', 'overdue'])
            ->update(['status' => 'unpaid']);

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('status', 20)->default('unpaid')->change();
        });
    }

    public function down(): void
    {
        DB::table('invoices')
            ->where('status', 'unpaid')
            ->update(['status' => 'draft']);

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->change();
        });
    }
};
