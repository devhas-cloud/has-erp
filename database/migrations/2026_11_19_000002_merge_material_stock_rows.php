<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Material (master_products.is_material = true) kini SATU baris saldo per
 * produk + gudang + kepemilikan (stok bebas / project). Baris material lama yang
 * masih bersisa di posisi yang sama digabung ke baris tertua:
 *   - qty dipindah lewat stock_logs bertipe 'merge' (keluar di baris lama, masuk
 *     di saldo) dengan harga baris asal; harga saldo = rata-rata tertimbang;
 *   - dokumen yang belum diposting dan menunjuk baris lama (Packing List
 *     Draft/Siap Kirim, DO Draft, Branch Transfer Draft, Stock Opname Draft)
 *     diarahkan ke baris saldo;
 *   - baris lama tetap ada (qty 0) sebagai riwayat dokumen yang sudah diposting.
 * Barang ber-SN tidak disentuh. BACKUP DATABASE sebelum menjalankan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $groups = DB::table('stocks as s')
            ->join('master_products as p', 'p.id', '=', 's.master_product_id')
            ->where('p.is_material', true)
            ->whereNull('s.serial_number')
            ->groupBy('s.master_product_id', 's.warehouse_id', 's.project_id')
            ->havingRaw('COUNT(*) > 1')
            ->select('s.master_product_id', 's.warehouse_id', 's.project_id')
            ->get();

        $today = now()->toDateString();
        $now = now();

        foreach ($groups as $group) {
            DB::transaction(function () use ($group, $today, $now) {
                $rows = DB::table('stocks')
                    ->where('master_product_id', $group->master_product_id)
                    ->where('warehouse_id', $group->warehouse_id)
                    ->when($group->project_id, fn ($q) => $q->where('project_id', $group->project_id), fn ($q) => $q->whereNull('project_id'))
                    ->whereNull('serial_number')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $survivor = $rows->first();
                $others = $rows->slice(1)->filter(fn ($row) => (float) $row->qty > 0.00005)->values();
                if ($others->isEmpty()) {
                    return;
                }

                $qty = (float) $survivor->qty;
                $value = $qty * (float) $survivor->unit_cost;
                $qtyIn = (float) $survivor->qty_in;

                foreach ($others as $other) {
                    $moved = round((float) $other->qty, 4);
                    $log = [
                        'master_product_id' => $other->master_product_id,
                        'warehouse_id' => $other->warehouse_id,
                        'project_id' => $other->project_id,
                        'is_material' => true,
                        'log_date' => $today,
                        'type' => 'merge',
                        'unit_cost' => $other->unit_cost,
                        'note' => 'Penggabungan saldo material ke baris #'.$survivor->id,
                        'created_at' => $now,
                    ];
                    DB::table('stock_logs')->insert($log + ['stock_id' => $other->id, 'qty' => -$moved, 'qty_after' => 0]);
                    DB::table('stocks')->where('id', $other->id)->update(['qty' => 0, 'updated_at' => $now]);

                    $qty = round($qty + $moved, 4);
                    $qtyIn = round($qtyIn + $moved, 4);
                    $value += $moved * (float) $other->unit_cost;
                    DB::table('stock_logs')->insert(array_merge($log, [
                        'stock_id' => $survivor->id,
                        'qty' => $moved,
                        'qty_after' => $qty,
                        'note' => 'Penggabungan saldo material dari baris #'.$other->id,
                    ]));
                }

                DB::table('stocks')->where('id', $survivor->id)->update([
                    'qty' => $qty,
                    'qty_in' => $qtyIn,
                    'unit_cost' => $qty > 0 ? round($value / $qty, 4) : $survivor->unit_cost,
                    'is_material' => true,
                    'updated_at' => $now,
                ]);

                $oldIds = $others->pluck('id')->all();
                $this->repoint($oldIds, (int) $survivor->id);
            });
        }
    }

    /** Arahkan dokumen yang belum diposting ke baris saldo. */
    private function repoint(array $oldIds, int $survivorId): void
    {
        DB::table('packing_list_items')->whereIn('stock_id', $oldIds)
            ->whereIn('packing_list_id', DB::table('packing_lists')->whereIn('status', ['draft', 'ready'])->select('id'))
            ->update(['stock_id' => $survivorId]);

        DB::table('delivery_order_item_stocks')->whereIn('stock_id', $oldIds)
            ->whereIn('delivery_order_item_id', DB::table('delivery_order_items')
                ->whereIn('delivery_order_id', DB::table('delivery_orders')->where('status', 'draft')->select('id'))
                ->select('id'))
            ->update(['stock_id' => $survivorId]);

        DB::table('branch_transfer_items')->whereIn('stock_id', $oldIds)
            ->whereIn('branch_transfer_id', DB::table('branch_transfers')->where('status', 'draft')->select('id'))
            ->update(['stock_id' => $survivorId]);

        // Opname draft: satu baris hitung per stok (unik). Bila saldo sudah ada di opname
        // yang sama, angka sistem & hitungan digabung ke barisnya.
        $draftOpnames = DB::table('stock_opnames')->where('status', 'draft')->pluck('id');
        $items = DB::table('stock_opname_items')->whereIn('stock_id', $oldIds)->whereIn('stock_opname_id', $draftOpnames)->orderBy('id')->get();
        foreach ($items as $item) {
            $target = DB::table('stock_opname_items')->where('stock_opname_id', $item->stock_opname_id)->where('stock_id', $survivorId)->first();
            if (! $target) {
                DB::table('stock_opname_items')->where('id', $item->id)->update(['stock_id' => $survivorId]);

                continue;
            }
            $counted = $target->counted_qty === null && $item->counted_qty === null
                ? null
                : round((float) $target->counted_qty + (float) $item->counted_qty, 4);
            DB::table('stock_opname_items')->where('id', $target->id)->update([
                'system_qty' => round((float) $target->system_qty + (float) $item->system_qty, 4),
                'counted_qty' => $counted,
            ]);
            DB::table('stock_opname_items')->where('id', $item->id)->delete();
        }
    }

    public function down(): void
    {
        // Tidak bisa dibalik otomatis: qty sudah digabung (riwayat ada di stock_logs bertipe 'merge').
        // Kembalikan dari backup database bila perlu.
    }
};
