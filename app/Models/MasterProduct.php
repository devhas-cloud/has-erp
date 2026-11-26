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
        'unit',
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

    /**
     * Masih punya stok (baris stok terbuka). Selama ini is_material tidak boleh
     * diubah, karena perilaku stok (per jumlah vs per SN) sudah melekat di barisnya.
     */
    public function hasStock(): bool
    {
        return Stock::where('master_product_id', $this->id)->where('is_open', true)->exists();
    }

    /** Satuan yang umum + yang sudah dipakai di master — saran isian di form. */
    public static function unitOptions(): array
    {
        $used = static::query()->whereNotNull('unit')->where('unit', '!=', '')
            ->distinct()->orderBy('unit')->pluck('unit')->all();

        return collect(['Unit', 'Pcs', 'Set', 'Lot', 'Package', 'Box', 'Meter', 'Roll'])
            ->merge($used)->unique(fn ($u) => mb_strtolower($u))->values()->all();
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

    /** Panjang maksimum nama yang diambil dari deskripsi. */
    public const DISPLAY_NAME_LIMIT = 120;

    /**
     * Nama tampilan: kolom name; bila kosong diambil dari baris pertama deskripsi
     * (dipotong), agar produk impor tanpa nama tetap mudah dikenali.
     */
    public function getDisplayNameAttribute(): ?string
    {
        $name = trim((string) $this->name);
        if ($name !== '') {
            return $name;
        }

        $firstLine = trim(strtok(str_replace("\r", '', (string) $this->description), "\n") ?: '');

        return $firstLine !== '' ? \Illuminate\Support\Str::limit($firstLine, self::DISPLAY_NAME_LIMIT) : null;
    }

    /** True bila nama tampilan berasal dari deskripsi (kolom name kosong). */
    public function nameFromDescription(): bool
    {
        return trim((string) $this->name) === '' && $this->display_name !== null;
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
