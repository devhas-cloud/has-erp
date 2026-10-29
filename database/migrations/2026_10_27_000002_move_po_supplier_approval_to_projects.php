<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PO Supplier Approval pindah dari Quotation ke Project: approval baru
 * menempel ke project (dibuat setelah PO customer), bukan ke quotation.
 * Approval quotation lama disalin ke semua project dari quotation itu, lalu
 * tabel lama dihapus. goods_requests juga diikat ke project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_po_supplier_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        $rows = DB::table('quotation_po_supplier_approvals as a')
            ->join('projects as p', 'p.quotation_id', '=', 'a.quotation_id')
            ->select('p.id as project_id', 'a.user_id', 'a.approved_at')
            ->get()
            ->map(fn ($row) => [
                'project_id' => $row->project_id,
                'user_id' => $row->user_id,
                'approved_at' => $row->approved_at,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('project_po_supplier_approvals')->insertOrIgnore($chunk);
        }

        Schema::dropIfExists('quotation_po_supplier_approvals');

        Schema::table('goods_requests', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('quotation_id')
                ->constrained('projects')->nullOnDelete();
        });

        // Backfill: permintaan barang lama diikat ke project pertama dari quotation-nya.
        DB::table('goods_requests')
            ->whereNull('project_id')
            ->whereNotNull('quotation_id')
            ->orderBy('id')
            ->each(function ($row) {
                $projectId = DB::table('projects')
                    ->where('quotation_id', $row->quotation_id)
                    ->orderBy('id')
                    ->value('id');

                if ($projectId) {
                    DB::table('goods_requests')->where('id', $row->id)->update(['project_id' => $projectId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('goods_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::create('quotation_po_supplier_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();

            $table->unique(['quotation_id', 'user_id']);
        });

        $rows = DB::table('project_po_supplier_approvals as a')
            ->join('projects as p', 'p.id', '=', 'a.project_id')
            ->whereNotNull('p.quotation_id')
            ->select('p.quotation_id', 'a.user_id', 'a.approved_at')
            ->get()
            ->map(fn ($row) => [
                'quotation_id' => $row->quotation_id,
                'user_id' => $row->user_id,
                'approved_at' => $row->approved_at,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('quotation_po_supplier_approvals')->insertOrIgnore($chunk);
        }

        Schema::dropIfExists('project_po_supplier_approvals');
    }
};
