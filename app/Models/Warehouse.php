<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Gudang. TYPE_STOCK = gudang utama (stok bebas); TYPE_TRANSIT = gudang
 * sementara barang project (teralokasi) sebelum Delivery Order.
 */
class Warehouse extends Model
{
    public const STATUS_ACTIVE = 'Active';

    public const STATUS_INACTIVE = 'Inactive';

    public const TYPE_STOCK = 'stock';

    public const TYPE_TRANSIT = 'transit';

    public const TYPE_LABELS = [
        self::TYPE_STOCK => 'Gudang Stok',
        self::TYPE_TRANSIT => 'Gudang Transit (Project)',
    ];

    protected $fillable = [
        'code',
        'name',
        'type',
        'status',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isTransit(): bool
    {
        return $this->type === self::TYPE_TRANSIT;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst((string) $this->type);
    }
}
