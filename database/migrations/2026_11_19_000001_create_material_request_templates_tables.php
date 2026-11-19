<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template Material Request: daftar material bawaan (qty per 1 set) yang bisa
 * diambil ke form MR — dipakai bersama semua user. Satuan tidak disimpan,
 * selalu mengikuti master produk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_request_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('Active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('material_request_template_items', function (Blueprint $table) {
            $table->id();
            // Nama constraint dipendekkan: nama otomatis melebihi batas 64 karakter MySQL.
            $table->foreignId('material_request_template_id')->constrained('material_request_templates', 'id', 'mr_tpl_items_template_fk')->cascadeOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products', 'id', 'mr_tpl_items_product_fk')->restrictOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['material_request_template_id', 'master_product_id'], 'mr_template_items_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_request_template_items');
        Schema::dropIfExists('material_request_templates');
    }
};
