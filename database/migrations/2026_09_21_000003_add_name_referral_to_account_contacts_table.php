<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nama pemberi referensi saat Contact Source = Referral (wajib diisi).
     * Nullable di DB default null; kewajiban hanya diberlakukan saat source
     * Referral (divalidasi & dinormalisasi di controller).
     */
    public function up(): void
    {
        Schema::table('account_contacts', function (Blueprint $table) {
            $table->string('name_referral', 150)->nullable()->default(null)->after('sources_id');
        });
    }

    public function down(): void
    {
        Schema::table('account_contacts', function (Blueprint $table) {
            $table->dropColumn('name_referral');
        });
    }
};
