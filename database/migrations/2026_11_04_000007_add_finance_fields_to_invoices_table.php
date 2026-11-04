<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // No. faktur pajak keluaran; boleh menyusul setelah invoice Approved.
            $table->string('tax_invoice_number', 50)->nullable()->after('reference_number');
            // Wajib untuk invoice tanpa termin (tagihan di luar timeline project).
            $table->text('non_term_reason')->nullable()->after('noted');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['tax_invoice_number', 'non_term_reason']);
        });
    }
};
