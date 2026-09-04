<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Currency extends Model
{
    protected $fillable = [
        'name',
        'symbol',
        'rate',
        'is_base',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_base' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function isBase(): bool
    {
        return (bool) $this->is_base;
    }

    public function getSymbolOrDefaultAttribute(): string
    {
        return $this->symbol ?: $this->name;
    }
}
