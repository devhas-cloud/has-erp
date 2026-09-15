<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GoodsRequestItem extends Model
{
    protected $fillable = [
        'goods_request_id',
        'quote_configuration_item_id',
        'master_product_id',
        'part_number',
        'description',
        'qty',
        'unit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
        ];
    }

    public function goodsRequest(): BelongsTo
    {
        return $this->belongsTo(GoodsRequest::class);
    }

    public function quoteConfigurationItem(): BelongsTo
    {
        return $this->belongsTo(QuoteConfigurationItem::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }

    /**
     * PurchaseOrderItem yang memakai item ini (unique di level DB — satu item
     * Permintaan Barang hanya boleh diorder di SATU PO manapun).
     */
    public function purchaseOrderItem(): HasOne
    {
        return $this->hasOne(PurchaseOrderItem::class);
    }

    public function isOrdered(): bool
    {
        return $this->purchaseOrderItem()->exists();
    }
}
