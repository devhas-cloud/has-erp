<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationCostItem extends Model
{
    protected $table = 'quotation_cost_items';

    protected $fillable = [
        'quotation_id',
        'item_no',
        'parent_id',
        'title',
        'description',
        'qty',
        'price',
        'unit',
        'formula',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price' => 'float',
            'formula' => 'array',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
