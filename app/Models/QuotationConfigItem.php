<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationConfigItem extends Model
{
    protected $table = 'quotation_config_items';

    protected $fillable = [
        'quotation_id',
        'quote_configuration_id',
        'item_no',
        'parent_id',
        'category',
        'part_number',
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

    public function quoteConfiguration(): BelongsTo
    {
        return $this->belongsTo(QuoteConfiguration::class);
    }
}
