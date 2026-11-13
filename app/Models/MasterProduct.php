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
        'is_material',
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
            'is_material' => 'boolean',
        ];
    }

    /**
     * Material = stok dihitung per qty; bukan Material = barang ber-SN
     * (stok dihitung per Serial Number).
     */
    public function isMaterial(): bool
    {
        return (bool) $this->is_material;
    }

    /** Nilai type yang sudah dipakai — saran isian di form & template import. */
    public static function typeOptions(): array
    {
        return static::query()->whereNotNull('type')->where('type', '!=', '')
            ->distinct()->orderBy('type')->pluck('type')->all();
    }

    /**
     * Type = teks bebas. Penulisan disamakan dengan nilai yang sudah ada
     * ("main" -> "Main") agar filter tidak terpecah karena beda huruf besar/kecil.
     */
    public static function normalizeType(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = mb_substr($value, 0, 50);
        $existing = static::query()->whereRaw('LOWER(type) = ?', [mb_strtolower($value)])->value('type');

        return $existing ?? $value;
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
