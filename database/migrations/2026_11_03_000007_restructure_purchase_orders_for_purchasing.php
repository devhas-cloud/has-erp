<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase Order mengikuti prinsip purchasing:
 * - baris hanya dari item Permintaan Barang (tanpa baris manual / group), satu
 *   item permintaan boleh dipecah ke beberapa PO (unique dihapus);
 * - harga satuan cukup satu kolom `price` (mata uang PO, tanpa kurs);
 * - status bertambah partially_received / received / cancelled, dengan data
 *   pembatalan (sisa yang belum diterima dilepas kembali ke permintaan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->text('cancel_reason')->nullable()->after('rejected_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancel_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });

        // Unique goods_request_item_id juga dipakai FK: lepas FK dulu, ganti unique
        // dengan index biasa, lalu pasang FK lagi.
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['goods_request_item_id']);
        });
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropUnique(['goods_request_item_id']);
            $table->index('goods_request_item_id');
            $table->foreign('goods_request_item_id')->references('id')->on('goods_request_items')->nullOnDelete();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('goods_request_id');
            $table->dropColumn(['category', 'selling_price', 'purchase_price_currency']);
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->renameColumn('selling_price_currency', 'price');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->renameColumn('price', 'selling_price_currency');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('goods_request_id')->nullable()->constrained('goods_requests')->nullOnDelete();
            $table->string('category', 200)->nullable();
            $table->decimal('selling_price', 15, 2)->nullable();
            $table->decimal('purchase_price_currency', 15, 2)->nullable()->default(0);
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['goods_request_item_id']);
            $table->dropIndex(['goods_request_item_id']);
            $table->unique('goods_request_item_id');
            $table->foreign('goods_request_item_id')->references('id')->on('goods_request_items')->nullOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancel_reason', 'cancelled_at']);
        });
    }
};
