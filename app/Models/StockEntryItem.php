<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baris Masuk Stok Lain-lain. serial_numbers = SN yang diisi saat posting
 * (barang ber-SN; boleh sebagian/kosong). Baris stok hasilnya ada di stocks();
 * SN yang kembali (retur customer) mengaktifkan baris stok lamanya.
 */
class StockEntryItem extends Model
{
    protected $fillable = [
        'stock_entry_id',
        'master_product_id',
        'qty',
        'unit',
        'unit_cost',
        'batch_number',
        'expiry_date',
        'supplier_id',
        'warranty_until',
        'serial_numbers',
        'note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'unit_cost' => 'float',
            'expiry_date' => 'date',
            'warranty_until' => 'date',
            'serial_numbers' => 'array',
        ];
    }

    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'source_id')->where('source_type', Stock::SOURCE_STOCK_ENTRY_ITEM)->whereNull('parent_id')->orderBy('id');
    }
}
