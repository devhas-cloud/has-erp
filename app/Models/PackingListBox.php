<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu koli Packing List = satu baris dokumen (deskripsi, qty order, qty
 * kirim, dimensi, berat). Isinya baris stok (items) — wajib minimal satu.
 */
class PackingListBox extends Model
{
    protected $fillable = [
        'packing_list_id',
        'quotation_item_id',
        'description',
        'qty_order',
        'qty',
        'unit',
        'length_cm',
        'width_cm',
        'height_cm',
        'weight_kg',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty_order' => 'float',
            'qty' => 'float',
            'length_cm' => 'float',
            'width_cm' => 'float',
            'height_cm' => 'float',
            'weight_kg' => 'float',
        ];
    }

    public function packingList(): BelongsTo
    {
        return $this->belongsTo(PackingList::class);
    }

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackingListItem::class)->orderBy('id');
    }

    /** "66x30x41" atau null bila belum diisi. */
    public function dimensionLabel(): ?string
    {
        $parts = [$this->length_cm, $this->width_cm, $this->height_cm];
        if (count(array_filter($parts, fn ($v) => $v !== null)) === 0) {
            return null;
        }

        return implode('x', array_map(fn ($v) => $v === null ? '—' : \App\Support\StockCost::qty($v), $parts));
    }
}
