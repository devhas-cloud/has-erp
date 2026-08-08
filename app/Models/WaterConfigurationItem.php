<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterConfigurationItem extends Model
{
    protected $fillable = [
        'water_configuration_id',
        'product_id',
        'category',
        'part_number',
        'description',
        'qty',
        'sort_order',
    ];

    public function waterConfiguration(): BelongsTo
    {
        return $this->belongsTo(WaterConfiguration::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }
}
