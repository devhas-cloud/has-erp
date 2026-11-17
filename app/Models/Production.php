<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Produksi — memakai Material di Produksi satu project + branch menjadi barang
 * jadi (tidak ber-SN). Barang jadi = baris stok baru milik project di gudang
 * hasil (stocks.source_type = 'production'), harga modal = total material ÷ qty.
 * Branch produksi = branch gudang hasil.
 *
 * Batal hanya selama baris barang jadi belum tersentuh; material kembali
 * menjadi sisa karena pemakaian hanya dihitung dari produksi berstatus posted.
 */
class Production extends Model
{
    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_POSTED => 'Selesai',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    protected $fillable = [
        'production_number',
        'project_id',
        'output_product_id',
        'output_qty',
        'output_warehouse_id',
        'production_date',
        'status',
        'note',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'output_qty' => 'float',
            'production_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('productions', 'production_number', $date);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function outputProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class, 'output_product_id');
    }

    public function outputWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'output_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionItem::class)->orderBy('id');
    }

    /** Baris stok barang jadi. */
    public function outputStock(): HasOne
    {
        return $this->hasOne(Stock::class, 'source_id')->where('source_type', Stock::SOURCE_PRODUCTION)->whereNull('parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->branch_id
            ? $query->whereHas('outputWarehouse', fn (Builder $q) => $q->where('branch_id', $user->branch_id))
            : $query;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function totalCost(): float
    {
        return round($this->items->sum(fn (ProductionItem $item) => $item->value()), 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadgeHtml(): string
    {
        [$bg, $fg] = $this->isPosted() ? ['var(--accent-soft)', 'var(--accent)'] : ['var(--danger-soft)', '#7f1d1d'];

        return '<span class="status-badge" style="background:'.$bg.';color:'.$fg.';">'.e($this->status_label).'</span>';
    }
}
