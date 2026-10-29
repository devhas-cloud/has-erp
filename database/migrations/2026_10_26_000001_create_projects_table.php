<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_number', 50)->unique();
            $table->string('project_name');
            $table->text('project_description')->nullable();
            // Satu quotation boleh menjadi lebih dari satu project.
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('company_id')->constrained('account_companies');
            $table->string('po_number_company', 100)->unique();
            $table->date('po_date');
            $table->date('po_date_received');
            $table->date('po_due_date');
            // Read-only saat quotation dipilih: total = quotation.grand_total,
            // net = profit_estimates.real_project_value.
            $table->decimal('total_sale_amount', 15, 2)->default(0);
            $table->decimal('net_sale_amount', 15, 2)->default(0);
            $table->boolean('is_retention')->default(false);
            $table->string('type_of_achievement', 20)->default('project');
            $table->string('status', 20)->default('active');
            // Dokumen PO milik project (bukan quotation), karena satu quotation
            // bisa punya beberapa project dengan PO masing-masing.
            $table->string('po_document_path')->nullable();
            $table->string('po_document_name')->nullable();
            $table->foreignId('po_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('po_uploaded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
