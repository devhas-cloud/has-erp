<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hirarki parent-child + relasi master product pada item PO:
     *   - parent_id : baris header/kategori (mis. nama project) — baris tanpa
     *     part_number/qty/harga; anak-anaknya item di bawah group tersebut.
     *   - category  : label group (hanya dimiliki baris parent).
     *   - master_product_id : relasi part number ke master produk — dipakai
     *     picker produk untuk mengisi currency & harga dari master.
     * Baris lama (flat, tanpa parent/category) tetap valid — semua kolom nullable.
     */
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('purchase_order_id')
                ->constrained('purchase_order_items')->cascadeOnDelete();
            $table->string('category', 200)->nullable()->after('parent_id');
            $table->foreignId('master_product_id')->nullable()->after('goods_request_id')
                ->constrained('master_products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('master_product_id');
            $table->dropColumn('category');
        });
    }
};