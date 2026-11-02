<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timeline penagihan project (mis. DP -> Termin 1 -> Termin 2), direncanakan saat
 * project dibuat atau menyusul. Total persen <= 100% dari nilai penawaran sebelum
 * PPN. Invoice dibuat dari termin (invoices.billing_term_id) dan persennya
 * terkunci mengikuti termin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_billing_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('name', 100);
            $table->decimal('percent', 5, 2);
            $table->string('description', 255)->nullable();
            $table->date('target_date')->nullable();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('billing_term_id')->nullable()->after('project_id')
                ->constrained('project_billing_terms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_term_id');
        });
        Schema::dropIfExists('project_billing_terms');
    }
};
