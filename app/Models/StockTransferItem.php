<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    protected $fillable = [
        'stock_transfer_id',
        'stock_id',
        'qty',
        'unit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
        ];
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /**
     * Baris stok yang dipindah (produk, SN, batch dibaca dari sini).
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
