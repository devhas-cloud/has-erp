<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Serah Terima Material — gudang menyerahkan baris stok ke produksi untuk satu
 * Material Request. Diposting saat disimpan (stok langsung keluar) dan tidak
 * bisa dibatalkan. Project & branch ikut Material Request.
 */
class MaterialHandover extends Model
{
    protected $fillable = [
        'handover_number',
        'material_request_id',
        'handover_date',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'handover_date' => 'date',
        ];
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('material_handovers', 'handover_number', $date);
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialHandoverItem::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->branch_id
            ? $query->whereHas('materialRequest', fn (Builder $q) => $q->where('branch_id', $user->branch_id))
            : $query;
    }

    public function totalValue(): float
    {
        return round($this->items->sum(fn (MaterialHandoverItem $item) => $item->value()), 2);
    }
}
