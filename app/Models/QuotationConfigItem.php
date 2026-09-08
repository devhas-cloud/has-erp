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
        'price_currency',
        'currency',
        'unit',
        'formula',
        'sort_order',
    ];

    /**
     * price          = harga satuan IDR sesudah kurs (price_currency x rate)
     * price_currency = harga satuan sebelum kurs, dalam mata uang `currency`
     * currency       = kode mata uang (currencies.name)
     */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price' => 'float',
            'price_currency' => 'float',
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
