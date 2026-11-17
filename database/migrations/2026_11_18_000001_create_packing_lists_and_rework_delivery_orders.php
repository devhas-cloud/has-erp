<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Packing List & Delivery Order dua jalur.
 *
 *  - Packing List (PKL): gudang mengemas barang milik project per koli. Satu
 *    baris = satu koli (deskripsi dari item Quotation / bebas, qty order, qty
 *    kirim, dimensi, berat); isi koli = baris stok (packing_list_items).
 *    draft -> ready (Siap Kirim, stok dipesan) -> shipped (saat DO diposting).
 *  - Delivery Order: delivery_order_items menjadi baris dokumen. Jalur
 *    Langsung: isi stok per baris di delivery_order_item_stocks. Jalur Dengan
 *    Packing List: memuat PKL (packing_lists.delivery_order_id).
 *
 * Belum ada data DO di produksi pada skema stok baru, jadi tanpa konversi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_lists', function (Blueprint $table) {
            $table->id();
            $table->string('packing_number', 50)->unique();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            // Tanggal pengiriman (Date di bagian bawah dokumen).
            $table->date('packing_date');
            $table->string('status', 20)->default('draft');
            $table->foreignId('delivery_order_id')->nullable()->constrained('delivery_orders')->nullOnDelete();
            $table->string('recipient_name', 150);
            $table->text('recipient_address')->nullable();
            $table->string('recipient_phone', 100)->nullable();
            $table->string('recipient_attn', 150)->nullable();
            // PO/SPK customer + tanggalnya (Date di bagian atas dokumen).
            $table->string('customer_ref', 100)->nullable();
            $table->date('customer_ref_date')->nullable();
            $table->string('marking', 150)->nullable();
            $table->string('signer_name', 150)->nullable();
            $table->string('signer_position', 150)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ready_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('packing_list_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packing_list_id')->constrained('packing_lists')->cascadeOnDelete();
            $table->foreignId('quotation_item_id')->nullable()->constrained('quotation_items')->nullOnDelete();
            $table->text('description');
            $table->decimal('qty_order', 15, 4)->nullable();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 50)->nullable();
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();
            $table->decimal('weight_kg', 10, 2)->nullable();
            // Urutan = nomor koli.
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('packing_list_items', function (Blueprint $table) {
            $table->id();
            // packing_list_id ikut disimpan agar reservasi cukup satu join.
            $table->foreignId('packing_list_id')->constrained('packing_lists')->cascadeOnDelete();
            $table->foreignId('packing_list_box_id')->constrained('packing_list_boxes')->cascadeOnDelete();
            $table->foreignId('stock_id')->constrained('stocks')->restrictOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 50)->nullable();
            $table->string('remark', 255)->nullable();
            $table->timestamps();

            $table->index(['stock_id', 'packing_list_id']);
        });

        // Baris DO menjadi baris dokumen (tanpa konversi data lama).
        DB::table('delivery_order_items')->delete();
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_id');
        });
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->foreignId('quotation_item_id')->nullable()->after('delivery_order_id')->constrained('quotation_items')->nullOnDelete();
            $table->text('description')->after('quotation_item_id');
            // Kolom Serial Number / Part Number jalur Packing List (mis. "Box 1-2 · PKL-26110001").
            $table->string('reference', 255)->nullable()->after('unit');
        });

        Schema::create('delivery_order_item_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_item_id')->constrained('delivery_order_items')->cascadeOnDelete();
            $table->foreignId('stock_id')->constrained('stocks')->restrictOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 50)->nullable();
            $table->timestamps();
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->boolean('uses_packing_list')->default(false)->after('warehouse_id');
            $table->string('recipient_attn', 150)->nullable()->after('recipient_phone');
            $table->string('customer_ref', 100)->nullable()->after('shipping_address');
            $table->date('customer_ref_date')->nullable()->after('customer_ref');
            $table->string('checked_by_name', 150)->nullable()->after('note');
            $table->string('acknowledged_by_name', 255)->nullable()->after('checked_by_name');
            $table->string('sent_by_name', 150)->nullable()->after('acknowledged_by_name');
        });

        $this->addModule();
    }

    public function down(): void
    {
        $id = DB::table('modules')->where('module_code', 'MOD_PACKING_LIST')->value('id');
        if ($id) {
            DB::table('user_access_controls')->where('module_id', $id)->delete();
            DB::table('modules')->where('id', $id)->delete();
        }

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn(['uses_packing_list', 'recipient_attn', 'customer_ref', 'customer_ref_date', 'checked_by_name', 'acknowledged_by_name', 'sent_by_name']);
        });
        Schema::dropIfExists('delivery_order_item_stocks');
        DB::table('delivery_order_items')->delete();
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_item_id');
            $table->dropColumn(['description', 'reference']);
        });
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->foreignId('stock_id')->after('delivery_order_id')->constrained('stocks')->restrictOnDelete();
        });
        Schema::dropIfExists('packing_list_items');
        Schema::dropIfExists('packing_list_boxes');
        Schema::dropIfExists('packing_lists');
    }

    /** Modul Packing List (grup Inventory, sebelum Delivery Order); akses disalin dari Delivery Order. */
    private function addModule(): void
    {
        if (! Schema::hasTable('modules') || DB::table('modules')->where('module_code', 'MOD_PACKING_LIST')->exists()) {
            return;
        }
        $source = DB::table('modules')->where('module_code', 'MOD_DELIVERY_ORDER')->first();
        if (! $source) {
            return;
        }

        $now = now();
        $moduleId = DB::table('modules')->insertGetId([
            'module_code' => 'MOD_PACKING_LIST',
            'module_name' => 'Packing List',
            'description' => 'Gudang mengemas barang milik project per koli (dimensi, berat) — stok dipesan saat Siap Kirim, keluar saat Delivery Order diposting',
            'route_name' => 'packing-list',
            'icon' => 'fa fa-box',
            'group' => $source->group,
            'sort_order' => max(0, (int) $source->sort_order - 5),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (DB::table('user_access_controls')->where('module_id', $source->id)->get() as $row) {
            DB::table('user_access_controls')->insert([
                'user_id' => $row->user_id,
                'module_id' => $moduleId,
                'can_create' => $row->can_create,
                'can_read' => $row->can_read,
                'can_update' => $row->can_update,
                'can_delete' => $row->can_delete,
                'can_approve' => $row->can_approve,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
