<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionItem extends Model
{
    protected $fillable = [
        'production_id',
        'material_handover_item_id',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
        ];
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function handoverItem(): BelongsTo
    {
        return $this->belongsTo(MaterialHandoverItem::class, 'material_handover_item_id');
    }

    public function value(): float
    {
        return round($this->qty * $this->handoverItem?->unitCost(), 2);
    }
}
