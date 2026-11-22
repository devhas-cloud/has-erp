<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris material yang dikembalikan dari produksi ke gudang asal. Biaya
 * mengikuti harga modal saat diserahkan (unit_cost frozen dari log serah terima).
 */
class MaterialReturnItem extends Model
{
    protected $fillable = [
        'material_return_id',
        'material_handover_item_id',
        'stock_id',
        'master_product_id',
        'warehouse_id',
        'project_id',
        'qty',
        'unit',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'unit_cost' => 'float',
        ];
    }

    public function materialReturn(): BelongsTo
    {
        return $this->belongsTo(MaterialReturn::class);
    }

    public function handoverItem(): BelongsTo
    {
        return $this->belongsTo(MaterialHandoverItem::class, 'material_handover_item_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
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

    public function value(): float
    {
        return round($this->qty * $this->unit_cost, 2);
    }
}
