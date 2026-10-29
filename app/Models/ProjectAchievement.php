<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAchievement extends Model
{
    protected $fillable = [
        'project_id',
        'device_name',
        'qty',
        'unit',
        'amount',
        'division_id',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'amount' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
