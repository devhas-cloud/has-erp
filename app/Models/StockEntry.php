<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Masuk Stok Lain-lain — barang masuk tanpa dokumen pembelian. Langsung
 * terposting (misc_in) saat disimpan; project kosong = stok bebas.
 */
class StockEntry extends Model
{
    public const REASON_OPENING = 'opening';

    public const REASON_RETURN = 'return';

    public const REASON_SAMPLE = 'sample';

    public const REASON_OTHER = 'other';

    public const REASON_LABELS = [
        self::REASON_OPENING => 'Saldo Awal',
        self::REASON_RETURN => 'Retur dari Project / Customer',
        self::REASON_SAMPLE => 'Hibah / Sampel',
        self::REASON_OTHER => 'Lain-lain',
    ];

    protected $fillable = [
        'entry_number',
        'entry_date',
        'warehouse_id',
        'project_id',
        'reason',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockEntryItem::class)->orderBy('sort_order');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('stock_entries', 'entry_number', $date);
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASON_LABELS[$this->reason] ?? ucfirst((string) $this->reason);
    }
}
