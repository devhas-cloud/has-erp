<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menautkan PO ke master Supplier (supplier_id, opsional — form lama
     * berbasis teks bebas tetap didukung lewat supplier_name yang sudah ada)
     * + field snapshot alamat/telepon/fax/attn saat supplier dipilih, dan
     * field tambahan (terms, request/finance/accounting signer) yang muncul
     * pada dokumen cetak PO ke supplier.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('supplier_name')
                ->constrained('suppliers')->nullOnDelete();
            $table->text('supplier_address')->nullable()->after('supplier_id');
            $table->string('supplier_phone', 50)->nullable()->after('supplier_address');
            $table->string('supplier_fax', 50)->nullable()->after('supplier_phone');
            $table->string('supplier_attn', 150)->nullable()->after('supplier_fax');
            $table->string('terms', 100)->nullable()->after('date');
            $table->string('request_by_name', 150)->nullable()->after('notes');
            $table->string('finance_name', 150)->nullable()->after('request_by_name');
            $table->string('accounting_name', 150)->nullable()->after('finance_name');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn([
                'supplier_id',
                'supplier_address',
                'supplier_phone',
                'supplier_fax',
                'supplier_attn',
                'terms',
                'request_by_name',
                'finance_name',
                'accounting_name',
            ]);
        });
    }
};
