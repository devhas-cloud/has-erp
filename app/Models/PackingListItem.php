<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isi fisik koli: satu baris stok milik project. Stok keluar dari sini saat
 * Delivery Order yang memuat Packing List-nya diposting.
 */
class PackingListItem extends Model
{
    protected $fillable = [
        'packing_list_id',
        'packing_list_box_id',
        'stock_id',
        'qty',
        'unit',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
        ];
    }

    public function packingList(): BelongsTo
    {
        return $this->belongsTo(PackingList::class);
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(PackingListBox::class, 'packing_list_box_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * SQL qty baris stok yang sedang dipesan Packing List Siap Kirim
     * (dipakai sebagai subquery berkorelasi ke stocks.id).
     */
    public static function reservedSql(string $stockColumn = 'stocks.id'): string
    {
        return '(SELECT COALESCE(SUM(pli.qty), 0) FROM packing_list_items pli'
            .' INNER JOIN packing_lists pl ON pl.id = pli.packing_list_id'
            ." WHERE pli.stock_id = {$stockColumn} AND pl.status = '".PackingList::STATUS_READY."')";
    }

    /** Qty baris stok yang dipesan PKL Siap Kirim (selain PKL tertentu). */
    public static function reservedQty(int $stockId, ?int $exceptPackingListId = null): float
    {
        return round((float) static::query()
            ->where('stock_id', $stockId)
            ->whereHas('packingList', fn ($q) => $q->where('status', PackingList::STATUS_READY))
            ->when($exceptPackingListId, fn ($q, $id) => $q->where('packing_list_id', '!=', $id))
            ->sum('qty'), 4);
    }
}
