<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function returnItems(): HasMany
    {
        return $this->hasMany(MaterialReturnItem::class, 'material_handover_item_id');
    }

    /**
     * Material di Produksi milik project + branch, dengan kolom used_qty
     * (pemakaian produksi posted). Sisa dihitung di remainingQty().
     */
    public function scopePool(Builder $query, int $projectId, int $branchId): Builder
    {
        return $query
            ->whereHas('materialHandover.materialRequest', fn (Builder $q) => $q->where('project_id', $projectId)->where('branch_id', $branchId))
            ->withUsedQty()
            ->withReturnedQty();
    }

    public function scopeWithUsedQty(Builder $query): Builder
    {
        return $query->withSum(['productionItems as used_qty' => fn (Builder $q) => $q
            ->whereHas('production', fn (Builder $p) => $p->where('status', Production::STATUS_POSTED))], 'qty');
    }

    public function scopeWithReturnedQty(Builder $query): Builder
    {
        return $query->withSum(['returnItems as returned_qty' => fn (Builder $q) => $q
            ->whereHas('materialReturn', fn (Builder $r) => $r->where('status', MaterialReturn::STATUS_APPROVED))], 'qty');
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

    public function returnedQty(): float
    {
        if (array_key_exists('returned_qty', $this->attributes)) {
            return round((float) $this->attributes['returned_qty'], 4);
        }

        return round((float) $this->returnItems()
            ->whereHas('materialReturn', fn (Builder $r) => $r->where('status', MaterialReturn::STATUS_APPROVED))
            ->sum('qty'), 4);
    }

    public function remainingQty(): float
    {
        return max(0.0, round($this->qty - $this->usedQty() - $this->returnedQty(), 4));
    }

    /** Log stok saat diserahkan (keluar ke produksi) — menyimpan harga saat itu. */
    public function outLog(): HasOne
    {
        return $this->hasOne(StockLog::class, 'ref_id')
            ->where('ref_type', StockLog::REF_MATERIAL_HANDOVER_ITEM)
            ->where('type', StockLog::TYPE_OUT);
    }

    /**
     * Log alokasi stok bebas ke project saat diserahkan. Ada = material berasal
     * dari stok bebas (biaya dibebankan lewat allocate); tidak ada = material
     * sudah milik project (biaya pembelian sudah tercatat, tidak dialokasikan).
     */
    public function allocateLog(): HasOne
    {
        return $this->hasOne(StockLog::class, 'ref_id')
            ->where('ref_type', StockLog::REF_MATERIAL_HANDOVER_ITEM)
            ->where('type', StockLog::TYPE_ALLOCATE);
    }

    /**
     * Harga per unit saat diserahkan. Saldo material memakai harga rata-rata yang
     * berubah setiap ada barang masuk, jadi harga diambil dari log serah terima.
     */
    public function unitCost(): float
    {
        $log = $this->relationLoaded('outLog') ? $this->outLog : $this->outLog()->first();

        return (float) ($log?->unit_cost ?? $this->stock?->unit_cost ?? 0);
    }

    public function value(): float
    {
        return round($this->qty * $this->unitCost(), 2);
    }
}
