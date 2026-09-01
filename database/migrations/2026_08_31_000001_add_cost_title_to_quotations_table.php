<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->text('cost_title')->nullable()->after('cost_notes');
        });

        // Migrasi data lama: judul biaya (title) dipindah dari baris cost_items
        // menjadi satu kolom quotations.cost_title. Ambil root-level title
        // terakhir yang pernah dibuat per quotation.
        $titles = DB::table('quotation_cost_items')
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->whereNull('parent_id')
            ->orderBy('id')
            ->get(['id', 'quotation_id', 'title']);

        foreach ($titles as $title) {
            DB::table('quotations')
                ->where('id', $title->quotation_id)
                ->whereNull('cost_title')
                ->update(['cost_title' => $title->title]);
        }

        // Hapus semua baris cost_items yang masih berperan sebagai judul
        // (root-level title) agar tidak tampil ganda di tabel item.
        $titleIds = DB::table('quotation_cost_items')
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->whereNull('parent_id')
            ->pluck('id');

        DB::table('quotation_cost_items')
            ->whereIn('parent_id', $titleIds)
            ->update(['parent_id' => null]);

        DB::table('quotation_cost_items')
            ->whereIn('id', $titleIds)
            ->delete();
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('cost_title');
        });
    }
};
