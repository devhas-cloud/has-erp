<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retur Material Produksi -> Gudang: produksi mengembalikan material yang
 * masih tersisa (belum terpakai) ke gudang asal sebagai stok bebas. Sisa
 * Material di Produksi = qty serah terima - pemakaian produksi posted - retur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->date('return_date');
            $table->string('status', 20)->default('waiting_approval');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('material_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_return_id')->constrained('material_returns')->cascadeOnDelete();
            $table->foreignId('material_handover_item_id')->constrained('material_handover_items')->restrictOnDelete();
            // Baris stok asal saat diserahkan; gudang asal = warehouse_id baris itu.
            $table->foreignId('stock_id')->constrained('stocks')->restrictOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            // Project asal (MR) — untuk info & lepas alokasi biaya.
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 50)->nullable();
            $table->decimal('unit_cost', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_return_items');
        Schema::dropIfExists('material_returns');
    }
};
