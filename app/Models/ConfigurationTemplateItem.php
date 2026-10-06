<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigurationTemplateItem extends Model
{
    protected $table = 'configuration_template_items';

    protected $fillable = [
        'template_id',
        'item_no',
        'parent_id',
        'product_id',
        'category',
        'part_number',
        'description',
        'qty',
        'price',
        'price_currency',
        'currency',
        'unit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price' => 'float',
            'price_currency' => 'float',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ConfigurationTemplate::class, 'template_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
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