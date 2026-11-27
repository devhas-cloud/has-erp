<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peminjaman barang (Item Loan): dicatat petugas inventory, tanpa pengajuan /
 * approval peminjam. Hanya unit ber-SN. Stok TIDAK berkurang saat dipinjam —
 * unit hanya ditahan (tidak tersedia untuk dokumen lain); stok berkurang hanya
 * bila dinyatakan hilang. Peminjam: user internal atau pihak luar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number', 30)->unique();
            // internal = user ERP; external = pihak luar (nama/instansi/telepon bebas).
            $table->string('borrower_type', 10)->default('internal');
            $table->foreignId('borrower_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('borrower_name', 150)->nullable();
            $table->string('borrower_company', 150)->nullable();
            $table->string('borrower_phone', 30)->nullable();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('purpose', 255)->nullable();
            $table->date('loan_date');
            $table->date('due_date');
            // on_loan | returned (semua unit selesai) | cancelled
            $table->string('status', 20)->default('on_loan');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamp('closed_at')->nullable();
            // Pengingat WA terakhir ke peminjam (sekali per hari).
            $table->date('last_reminded_on')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });

        Schema::create('item_loan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_loan_id')->constrained('item_loans')->cascadeOnDelete();
            $table->foreignId('stock_id')->constrained('stocks')->restrictOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products')->restrictOnDelete();
            $table->string('condition_out', 255)->nullable();
            // null = masih dipinjam; good | damaged | lost
            $table->string('return_status', 20)->nullable();
            $table->date('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('return_note', 255)->nullable();
            $table->timestamps();

            $table->index(['stock_id', 'return_status']);
        });

        Schema::create('item_loan_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_loan_id')->constrained('item_loans')->cascadeOnDelete();
            $table->date('old_due_date');
            $table->date('new_due_date');
            $table->string('reason', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_loan_extensions');
        Schema::dropIfExists('item_loan_items');
        Schema::dropIfExists('item_loans');
    }
};
