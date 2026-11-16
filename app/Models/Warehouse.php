<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gudang milik satu branch. Tidak ada lagi gudang "transit": barang milik
 * project cukup ditandai project_id di baris stok, dan barang antar branch
 * tercatat "dalam pengiriman" di dokumen Branch Transfer.
 */
class Warehouse extends Model
{
    public const STATUS_ACTIVE = 'Active';

    public const STATUS_INACTIVE = 'Inactive';

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'status',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOfBranch($query, ?int $branchId)
    {
        return $branchId ? $query->where('branch_id', $branchId) : $query;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Nama branch pemilik (untuk label form & laporan).
     */
    public function getBranchLabelAttribute(): string
    {
        return $this->branch?->name ?? 'Tanpa branch';
    }

    public function getFullLabelAttribute(): string
    {
        return $this->code.' — '.$this->name.' ('.$this->branch_label.')';
    }
}
