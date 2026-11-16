<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris DO = satu baris stok milik project yang dikirim (produk, SN, batch
 * dibaca dari stock). Material boleh dikirim sebagian dari baris stoknya.
 */
class DeliveryOrderItem extends Model
{
    protected $fillable = [
        'delivery_order_id',
        'stock_id',
        'qty',
        'unit',
        'remark',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
        ];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
