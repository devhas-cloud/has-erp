<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nama produk boleh kosong (NULL), tapi jika terisi tidak boleh ada yang sama.
     * Unique index di MySQL/SQLite memperlakukan NULL sebagai nilai berbeda,
     * sehingga beberapa produk bernama kosong tetap diperbolehkan.
     */
    public function up(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->string('name', 150)->nullable()->change();
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropUnique('master_products_name_unique');
            $table->string('name', 150)->nullable(false)->change();
        });
    }
};
