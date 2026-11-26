<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nama penerima barang dari pihak produksi pada Serah Terima Material (wajib di form,
 * dicetak di bukti serah terima). Data lama boleh kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_handovers', function (Blueprint $table) {
            $table->string('received_by_name', 150)->nullable()->after('handover_date');
        });
    }

    public function down(): void
    {
        Schema::table('material_handovers', function (Blueprint $table) {
            $table->dropColumn('received_by_name');
        });
    }
};
