<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MasterProduct extends Model
{
    protected $table = 'master_products';

    protected $fillable = [
        'name',
        'code',
        'brand',
        'category',
        'type',
        'parameter',
        'division_id',
        'description',
        'image',
        'selling_price',
        'purchase_price',
        'currency_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::url($this->image) : null;
    }

    public function getImageThumbAttribute(): ?string
    {
        return $this->image ? Storage::url($this->image) : null;
    }

    /**
     * Nilai harga jual setara base (IDR): selling_price × rate mata uang produk.
     * Produk tanpa currency atau ber-currency base dikonversi dgn rate 1.
     */
    public function sellingPriceInBase(): float
    {
        $currency = $this->relationLoaded('currency') ? $this->currency : $this->currency()->first();

        if ($currency && ! $currency->isBase()) {
            return round((float) $this->selling_price * (float) $currency->rate, 2);
        }

        return round((float) $this->selling_price, 2);
    }
}
