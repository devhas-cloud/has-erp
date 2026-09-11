<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitEstimateLine extends Model
{
    protected $table = 'profit_estimate_lines';

    protected $fillable = [
        'profit_estimate_id',
        'section',
        'label',
        'qty',
        'unit',
        'vendor',
        'currency',
        'amount',
        'amount_idr',
        'is_manual',
        'percent',
        'is_up',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'amount' => 'float',
            'amount_idr' => 'float',
            'is_manual' => 'boolean',
            'percent' => 'float',
            'is_up' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function profitEstimate(): BelongsTo
    {
        return $this->belongsTo(ProfitEstimate::class);
    }
}
