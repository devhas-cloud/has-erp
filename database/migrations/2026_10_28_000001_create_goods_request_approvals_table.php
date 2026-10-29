<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approval Permintaan Barang: kuorum 2 user can_approve berbeda (pola sama
 * dengan project_po_supplier_approvals). Unique (goods_request_id, user_id)
 * menjadi backstop agar satu user tidak terhitung dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_request_id')->constrained('goods_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['goods_request_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_request_approvals');
    }
};
