<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'parent_id',
        'category',
        'goods_request_item_id',
        'goods_request_id',
        'master_product_id',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function goodsRequestItem(): BelongsTo
    {
        return $this->belongsTo(GoodsRequestItem::class);
    }

    public function goodsRequest(): BelongsTo
    {
        return $this->belongsTo(GoodsRequest::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }

    /**
     * Baris header/kategori (parent) — label group yang memisahkan item per
     * project. Tidak punya data item (part_number/qty/harga), hanya `category`.
     */
    public function isHeader(): bool
    {
        return $this->category !== null && $this->category !== '';
    }

    public function amount(): float
    {
        return (float) ($this->qty ?? 0) * (float) ($this->price ?? 0);
    }
}
