<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialRequestItem extends Model
{
    protected $fillable = [
        'material_request_id',
        'master_product_id',
        'qty',
        'unit',
        'note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
        ];
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function handoverItems(): HasMany
    {
        return $this->hasMany(MaterialHandoverItem::class);
    }

    public function handedQty(): float
    {
        return round((float) $this->handoverItems->sum('qty'), 4);
    }

    public function remainingQty(): float
    {
        return max(0.0, round($this->qty - $this->handedQty(), 4));
    }
}
