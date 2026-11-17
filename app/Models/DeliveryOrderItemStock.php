<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isi baris DO jalur Langsung: satu baris stok milik project yang keluar saat
 * DO diposting.
 */
class DeliveryOrderItemStock extends Model
{
    protected $fillable = [
        'delivery_order_item_id',
        'stock_id',
        'qty',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
        ];
    }

    public function deliveryOrderItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrderItem::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
