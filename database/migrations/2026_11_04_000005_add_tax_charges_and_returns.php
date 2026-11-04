<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance Tahap 4 — Pajak & selisih.
 *  - PPh dipotong per tagihan dengan tarif bebas (pph_label/percent/base/amount);
 *    yang ditransfer = total - potongan uang muka - PPh. Bukti potong dicatat
 *    belakangan (pph_slip_number/date). suppliers.default_pph_percent = saran tarif.
 *  - Biaya tambahan tagihan (ongkos kirim dll.) = baris line_type 'charge'.
 *  - Retur ke Supplier: barang yang sudah diterima dikembalikan (stok keluar),
 *    mengurangi qty diterima & qty yang bisa ditagih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('default_pph_percent', 5, 2)->nullable()->after('is_pkp');
        });

        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->decimal('other_charges', 18, 2)->default(0)->after('discount_amount');
            $table->string('pph_label', 50)->nullable()->after('ppn_amount');
            $table->decimal('pph_percent', 5, 2)->nullable()->after('pph_label');
            $table->decimal('pph_base', 18, 2)->default(0)->after('pph_percent');
            $table->decimal('pph_amount', 18, 2)->default(0)->after('pph_base');
            $table->string('pph_slip_number', 100)->nullable()->after('advance_deduction');
            $table->date('pph_slip_date')->nullable()->after('pph_slip_number');
        });

        Schema::table('supplier_bill_items', function (Blueprint $table) {
            $table->string('line_type', 20)->default('item')->after('supplier_bill_id');
        });

        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->date('return_date');
            $table->text('reason');
            $table->string('status', 20)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('supplier_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_return_id')->constrained('supplier_returns')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('master_product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->integer('qty');
            $table->string('unit', 50)->nullable();
            $table->string('destination', 20)->default('warehouse');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
        Schema::dropIfExists('supplier_returns');
        Schema::table('supplier_bill_items', fn (Blueprint $table) => $table->dropColumn('line_type'));
        Schema::table('supplier_bills', fn (Blueprint $table) => $table->dropColumn(['other_charges', 'pph_label', 'pph_percent', 'pph_base', 'pph_amount', 'pph_slip_number', 'pph_slip_date']));
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropColumn('default_pph_percent'));
    }
};
