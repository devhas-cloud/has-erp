<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris opname = satu baris stok yang dihitung (stock_id), atau barang temuan
 * yang belum ada di sistem (stock_id kosong; identitas di batch/serial/expiry).
 * system_qty = sisa baris stok SAAT qty fisik diisi (counted_at); saat posting
 * selisih (counted - system) diterapkan ke qty sekarang.
 */
class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id',
        'stock_id',
        'master_product_id',
        'project_id',
        'batch_number',
        'serial_number',
        'expiry_date',
        'system_qty',
        'counted_qty',
        'counted_at',
        'unit',
        'unit_cost',
        'remark',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'float',
            'counted_qty' => 'float',
            'counted_at' => 'datetime',
            'expiry_date' => 'date',
            'unit_cost' => 'float',
        ];
    }

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isFound(): bool
    {
        return $this->stock_id === null;
    }

    public function difference(): float
    {
        return round((float) $this->counted_qty - (float) $this->system_qty, 4);
    }
}
