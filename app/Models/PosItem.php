<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item POS datar (tanpa hierarki). Sumber boleh dari quotation_item_id atau
 * manual. description = penamaan customer (landasan item hilir). master_product_id
 * = penjodoh internal opsional (untuk produksi/fulfillment).
 */
class PosItem extends Model
{
    protected $fillable = [
        'pos_id',
        'quotation_item_id',
        'master_product_id',
        'description',
        'qty',
        'unit',
        'unit_price',
        'subtotal',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'unit_price' => 'float',
            'subtotal' => 'float',
        ];
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }
}
