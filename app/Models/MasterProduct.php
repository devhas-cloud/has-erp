<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MasterProduct extends Model
{
    protected $table = 'master_products';

    protected $fillable = [
        'name',
        'code',
        'brand',
        'category',
        'division_id',
        'description',
        'image',
        'price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::url($this->image) : null;
    }

    public function getImageThumbAttribute(): ?string
    {
        return $this->image ? Storage::url($this->image) : null;
    }
}
