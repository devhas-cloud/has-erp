<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteConfigurationItem extends Model
{
    protected $table = 'quote_configuration_items';

    protected $fillable = [
        'quote_configuration_id',
        'item_no',
        'parent_id',
        'product_id',
        'category',
        'part_number',
        'description',
        'qty',
        'price',
        'unit',
        'sort_order',
    ];

    public function quoteConfiguration(): BelongsTo
    {
        return $this->belongsTo(QuoteConfiguration::class);
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
