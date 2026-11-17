<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produksi project:
 *  - Material Request (MR): kebutuhan material project di satu branch, 1 approver.
 *  - Serah Terima (MH): gudang menyerahkan baris stok ke produksi — stok langsung
 *    keluar (tanpa gudang produksi) dan menjadi "Material di Produksi" milik
 *    project + branch. Harga diambil dari stocks.unit_cost baris yang diserahkan.
 *  - Produksi (MO): memakai sebagian Material di Produksi menjadi barang jadi
 *    (baris stok baru, stocks.source_type = 'production'). Sisa material =
 *    qty serah terima − Σ production_items dari produksi berstatus posted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->date('request_date');
            $table->string('status', 20)->default('waiting_approval');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('material_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_request_id')->constrained('material_requests')->cascadeOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 50)->nullable();
            $table->string('note', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('material_handovers', function (Blueprint $table) {
            $table->id();
            $table->string('handover_number', 50)->unique();
            $table->foreignId('material_request_id')->constrained('material_requests')->restrictOnDelete();
            $table->date('handover_date');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('material_handover_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_handover_id')->constrained('material_handovers')->cascadeOnDelete();
            $table->foreignId('material_request_item_id')->constrained('material_request_items')->restrictOnDelete();
            // Baris stok (sisi project) yang keluar; harga modal dibaca dari sini.
            $table->foreignId('stock_id')->constrained('stocks')->restrictOnDelete();
            $table->decimal('qty', 15, 4);
            $table->timestamps();
        });

        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->string('production_number', 50)->unique();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('output_product_id')->constrained('master_products')->restrictOnDelete();
            $table->decimal('output_qty', 15, 4);
            // Branch produksi = branch gudang hasil.
            $table->foreignId('output_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->date('production_date');
            $table->string('status', 20)->default('posted');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('production_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained('productions')->cascadeOnDelete();
            $table->foreignId('material_handover_item_id')->constrained('material_handover_items')->restrictOnDelete();
            $table->decimal('qty', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_items');
        Schema::dropIfExists('productions');
        Schema::dropIfExists('material_handover_items');
        Schema::dropIfExists('material_handovers');
        Schema::dropIfExists('material_request_items');
        Schema::dropIfExists('material_requests');
    }
};
