<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'goods_request_item_id',
        'goods_request_id',
        'part_number',
        'description',
        'qty',
        'unit',
        'price',
        'price_currency',
        'currency',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price' => 'float',
            'price_currency' => 'float',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsRequestItem(): BelongsTo
    {
        return $this->belongsTo(GoodsRequestItem::class);
    }

    public function goodsRequest(): BelongsTo
    {
        return $this->belongsTo(GoodsRequest::class);
    }

    public function amount(): float
    {
        return (float) ($this->qty ?? 0) * (float) ($this->price ?? 0);
    }
}
