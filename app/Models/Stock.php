<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Posisi stok per baris — satu-satunya sumber saldo stok (saldo = SUM(qty)).
 *
 * - Material (master_products.is_material = true): satu baris per penerimaan,
 *   boleh diambil sebagian.
 * - Barang ber-SN (is_material = false): satu baris per unit (qty 1). SN boleh
 *   menyusul; unit tanpa SN tidak bisa keluar ke customer/supplier/pemakaian.
 *
 * Semua perubahan qty WAJIB lewat App\Services\StockService (mencatat stock_logs).
 */
class Stock extends Model
{
    public const SOURCE_GOODS_RECEIPT_ITEM = 'goods_receipt_item';

    public const SOURCE_STOCK_ENTRY_ITEM = 'stock_entry_item';

    public const SOURCE_STOCK_OPNAME_ITEM = 'stock_opname_item';

    /** Barang jadi hasil produksi (source_id = productions.id). */
    public const SOURCE_PRODUCTION = 'production';

    /** Material kembali dari produksi (source_id = material_returns.id). */
    public const SOURCE_PRODUCTION_RETURN = 'production_return';

    /** Selisih qty di bawah ini dianggap 0 (sisa pecahan desimal). */
    public const EPSILON = 0.00005;

    protected $fillable = [
        'master_product_id',
        'warehouse_id',
        'project_id',
        'is_material',
        'batch_number',
        'serial_number',
        'received_date',
        'expiry_date',
        'supplier_id',
        'warranty_until',
        'qty_in',
        'qty',
        'unit',
        'unit_cost',
        'parent_id',
        'source_type',
        'source_id',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_material' => 'boolean',
            'is_open' => 'boolean',
            'received_date' => 'date',
            'expiry_date' => 'date',
            'warranty_until' => 'date',
            'qty_in' => 'float',
            'qty' => 'float',
            'unit_cost' => 'float',
        ];
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(StockLog::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_open', true);
    }

    /**
     * Baris milik satu posisi stok. $projectId null = stok bebas.
     */
    public function scopePool(Builder $query, int $masterProductId, int $warehouseId, ?int $projectId): Builder
    {
        return $query->where('master_product_id', $masterProductId)
            ->where('warehouse_id', $warehouseId)
            ->when($projectId, fn (Builder $q) => $q->where('project_id', $projectId), fn (Builder $q) => $q->whereNull('project_id'));
    }

    /**
     * Unit barang ber-SN yang SN-nya belum diisi (masih di stok).
     */
    public function scopeMissingSerial(Builder $query): Builder
    {
        return $query->open()->whereNull('serial_number')
            ->whereHas('masterProduct', fn (Builder $q) => $q->where('is_material', false));
    }

    /**
     * Perilaku stok mengikuti master produk (bukan salinan is_material di baris).
     */
    public function isSerialized(): bool
    {
        $product = $this->relationLoaded('masterProduct') ? $this->masterProduct : $this->masterProduct()->first();

        return $product ? ! $product->isMaterial() : true;
    }

    public function hasSerial(): bool
    {
        return trim((string) $this->serial_number) !== '';
    }

    /**
     * Unit ber-SN tanpa SN belum boleh keluar (DO, retur, pemakaian).
     */
    public function canLeave(): bool
    {
        return ! $this->isSerialized() || $this->hasSerial();
    }

    public function getIdentityLabelAttribute(): string
    {
        if ($this->hasSerial()) {
            return 'SN '.$this->serial_number;
        }
        if ($this->isSerialized()) {
            return 'SN belum diisi';
        }

        return $this->batch_number ? 'Batch '.$this->batch_number : 'Tanpa batch';
    }

    public function getValueAttribute(): float
    {
        return round($this->qty * $this->unit_cost, 2);
    }
}
