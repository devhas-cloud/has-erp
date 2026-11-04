<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DirectPurchaseItem extends Model
{
    protected $fillable = [
        'direct_purchase_id',
        'master_product_id',
        'part_number',
        'description',
        'qty',
        'unit',
        'currency',
        'price',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price' => 'float',
        ];
    }

    public function directPurchase(): BelongsTo
    {
        return $this->belongsTo(DirectPurchase::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function amount(): float
    {
        return (float) ($this->qty ?? 0) * (float) ($this->price ?? 0);
    }

    /**
     * Qty yang sudah diterima (hanya receipt berstatus posted yang dihitung).
     */
    public function receivedQty(): int
    {
        $items = $this->relationLoaded('goodsReceiptItems')
            ? $this->goodsReceiptItems
            : $this->goodsReceiptItems()->with('goodsReceipt')->get();

        return (int) $items->filter(fn (GoodsReceiptItem $item) => $item->goodsReceipt?->status === GoodsReceipt::STATUS_POSTED)->sum('qty');
    }

    /**
     * Sisa qty yang belum diterima (boleh diterima bertahap).
     */
    public function remainingReceiptQty(): int
    {
        return max(0, (int) ($this->qty ?? 0) - $this->receivedQty());
    }
}
