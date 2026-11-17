<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baris dokumen DO (yang dicetak di surat jalan): deskripsi dari item
 * Quotation atau bebas, qty, satuan. Jalur Langsung: isi stoknya di stocks().
 * Jalur Dengan Packing List: tanpa stok; reference = rujukan koli
 * (mis. "Box 1-2 · PKL-26110001").
 */
class DeliveryOrderItem extends Model
{
    protected $fillable = [
        'delivery_order_id',
        'quotation_item_id',
        'description',
        'qty',
        'unit',
        'reference',
        'remark',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
        ];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(DeliveryOrderItemStock::class)->orderBy('id');
    }

    /**
     * Isi kolom Serial Number / Part Number: SN baris stok (kode produk bila
     * tidak ber-SN) untuk jalur Langsung, atau rujukan koli.
     */
    public function serialLabel(): string
    {
        if ($this->relationLoaded('stocks') && $this->stocks->isNotEmpty()) {
            return $this->stocks
                ->map(fn (DeliveryOrderItemStock $s) => $s->stock?->serial_number ?: $s->stock?->masterProduct?->code)
                ->filter()->unique()->implode(', ');
        }

        return (string) $this->reference;
    }
}
