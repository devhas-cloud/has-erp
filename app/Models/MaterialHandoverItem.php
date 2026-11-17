<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu baris stok yang diserahkan ke produksi = satu baris "Material di
 * Produksi". Sisa = qty − Σ pemakaian produksi yang masih berstatus posted.
 */
class MaterialHandoverItem extends Model
{
    protected $fillable = [
        'material_handover_id',
        'material_request_item_id',
        'stock_id',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
        ];
    }

    public function materialHandover(): BelongsTo
    {
        return $this->belongsTo(MaterialHandover::class);
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(MaterialRequestItem::class, 'material_request_item_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function productionItems(): HasMany
    {
        return $this->hasMany(ProductionItem::class);
    }

    /**
     * Material di Produksi milik project + branch, dengan kolom used_qty
     * (pemakaian produksi posted). Sisa dihitung di remainingQty().
     */
    public function scopePool(Builder $query, int $projectId, int $branchId): Builder
    {
        return $query
            ->whereHas('materialHandover.materialRequest', fn (Builder $q) => $q->where('project_id', $projectId)->where('branch_id', $branchId))
            ->withUsedQty();
    }

    public function scopeWithUsedQty(Builder $query): Builder
    {
        return $query->withSum(['productionItems as used_qty' => fn (Builder $q) => $q
            ->whereHas('production', fn (Builder $p) => $p->where('status', Production::STATUS_POSTED))], 'qty');
    }

    public function usedQty(): float
    {
        if (array_key_exists('used_qty', $this->attributes)) {
            return round((float) $this->attributes['used_qty'], 4);
        }

        return round((float) $this->productionItems()
            ->whereHas('production', fn (Builder $p) => $p->where('status', Production::STATUS_POSTED))
            ->sum('qty'), 4);
    }

    public function remainingQty(): float
    {
        return max(0.0, round($this->qty - $this->usedQty(), 4));
    }

    public function unitCost(): float
    {
        return (float) ($this->stock?->unit_cost ?? 0);
    }

    public function value(): float
    {
        return round($this->qty * $this->unitCost(), 2);
    }
}
