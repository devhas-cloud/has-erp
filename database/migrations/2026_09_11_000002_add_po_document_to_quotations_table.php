<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('po_document_path')->nullable()->after('rejected_at');
            $table->string('po_document_name')->nullable()->after('po_document_path');
            $table->foreignId('po_uploaded_by')->nullable()->after('po_document_name')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('po_uploaded_at')->nullable()->after('po_uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['po_uploaded_by']);
            $table->dropColumn(['po_document_path', 'po_document_name', 'po_uploaded_by', 'po_uploaded_at']);
        });
    }
};
