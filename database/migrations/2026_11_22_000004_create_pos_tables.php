<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POS (Point of Sale = Sales Order = penjualan): dokumen penjualan datar
 * yang menjembatani Quotation -> Project. POS wajib berbasis quotation
 * (quotation_id NOT NULL); customer & syarat DP (requires_dp) ikut quotation.
 * Item datar (tanpa hierarki), deskripsi = penamaan customer (landasan item
 * hilir). Setelah DP terpenuhi, Project dibuat dari POS (pos.project_id).
 * Invoice DP/lunas mengacu ke pos (invoices.pos_id), termin ke Project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos', function (Blueprint $table) {
            $table->id();
            $table->string('pos_number', 50)->unique();
            $table->foreignId('quotation_id')->constrained('quotations'); // wajib, restrict on delete
            $table->foreignId('company_id')->nullable()->constrained('account_companies')->nullOnDelete();
            $table->date('date');
            $table->boolean('requires_dp')->default(false); // snapshot dari quotation.requires_dp
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('ppn_percent', 5, 2)->nullable();
            $table->decimal('ppn_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft | waiting_approval | approved | rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('pos_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_id')->constrained('pos')->cascadeOnDelete();
            $table->foreignId('quotation_item_id')->nullable()->constrained('quotation_items')->nullOnDelete();
            $table->foreignId('master_product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->text('description')->nullable();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 50)->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('pos_id')->nullable()->constrained('pos')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // project_id kembali wajib: hentikan rollback sebelum ada yang berubah bila masih ada
        // invoice tanpa project (invoice dari POS). Tangani / pindahkan invoice tsb lebih dulu.
        $orphans = DB::table('invoices')->whereNull('project_id')->pluck('id');
        if ($orphans->isNotEmpty()) {
            throw new RuntimeException('Rollback POS dibatalkan: invoice tanpa project masih ada (id: '.$orphans->implode(', ').'). Hubungkan ke project atau hapus dulu.');
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['pos_id']);
            $table->dropColumn('pos_id');
            $table->foreignId('project_id')->nullable(false)->change();
        });

        Schema::dropIfExists('pos_items');
        Schema::dropIfExists('pos');
    }
};
