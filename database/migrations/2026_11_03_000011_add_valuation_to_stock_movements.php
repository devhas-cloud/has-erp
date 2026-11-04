<?php

use App\Models\StockMovement;
use App\Support\StockValuation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Valuasi stok (moving average, mata uang base): unit_cost per pergerakan,
 * dan harga satuan opsional pada Masuk Stok Lain-lain. Pergerakan lama diisi
 * ulang berurutan: penerimaan dari harga dokumen (setelah diskon PO, x kurs),
 * masuk lain-lain dari harga beli master, sisanya rata-rata berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 18, 4)->nullable()->after('unit');
        });

        Schema::table('stock_entry_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 18, 4)->nullable()->after('unit');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('stock_entry_items', fn (Blueprint $table) => $table->dropColumn('unit_cost'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropColumn('unit_cost'));
    }

    private function backfill(): void
    {
        $running = [];

        foreach (DB::table('stock_movements')->orderBy('moved_at')->orderBy('id')->get() as $m) {
            $productId = (int) $m->master_product_id;
            $state = $running[$productId] ??= ['qty' => 0, 'value' => 0.0, 'last' => null];
            $isIn = in_array($m->type, StockMovement::IN_TYPES, true);

            $cost = match (true) {
                $m->type === StockMovement::TYPE_RECEIPT => $this->receiptCost($m) ?? StockValuation::standardCost($productId),
                $m->type === StockMovement::TYPE_MISC_IN => StockValuation::standardCost($productId),
                $state['qty'] > 0 => round($state['value'] / $state['qty'], 4),
                default => $state['last'] ?? StockValuation::standardCost($productId),
            };

            DB::table('stock_movements')->where('id', $m->id)->update(['unit_cost' => $cost]);

            $sign = $isIn ? 1 : -1;
            $state['qty'] += $sign * $m->qty;
            $state['value'] += $sign * $m->qty * $cost;
            $state['last'] = $cost;
            $running[$productId] = $state;
        }
    }

    private function receiptCost(object $movement): ?float
    {
        $item = DB::table('goods_receipt_items')->where('id', $movement->source_line_id)->first();
        if (! $item || $item->price === null) {
            return null;
        }

        $discount = 0.0;
        if ($item->purchase_order_item_id) {
            $discount = (float) DB::table('purchase_order_items as i')
                ->join('purchase_orders as po', 'po.id', '=', 'i.purchase_order_id')
                ->where('i.id', $item->purchase_order_item_id)
                ->value('po.discount_percent');
        }

        return StockValuation::toBase((float) $item->price, $item->currency, $discount);
    }
};
