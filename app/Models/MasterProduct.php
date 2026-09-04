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
        'division_id',
        'description',
        'image',
        'price',
        'currency_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
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
     * Nilai harga setara base (IDR): price × rate mata uang produk.
     * Produk tanpa currency atau ber-currency base dikonversi dgn rate 1.
     */
    public function priceInBase(): float
    {
        $currency = $this->relationLoaded('currency') ? $this->currency : $this->currency()->first();

        if ($currency && ! $currency->isBase()) {
            return round((float) $this->price * (float) $currency->rate, 2);
        }

        return round((float) $this->price, 2);
    }
}
