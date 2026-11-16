<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Branch (kantor cabang). Pemilik gudang; user boleh terikat ke satu branch
 * (kosong = melihat semua branch). Dikelola di halaman Configuration.
 */
class Branch extends Model
{
    public const STATUS_ACTIVE = 'Active';

    protected $fillable = ['code', 'name', 'status'];

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function getLabelAttribute(): string
    {
        return $this->code.' — '.$this->name;
    }

    /**
     * Alasan branch tidak bisa dihapus (masih dipakai), atau null.
     */
    public function usageBlocker(): ?string
    {
        return match (true) {
            $this->warehouses()->exists() => 'masih punya gudang',
            BranchTransfer::where('from_branch_id', $this->id)->orWhere('to_branch_id', $this->id)->exists() => 'sudah dipakai di Branch Transfer',
            default => null,
        };
    }
}
