<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesAchievement extends Model
{
    protected $fillable = [
        'project_id',
        'sales_person_id',
        'amount_achieved',
    ];

    protected function casts(): array
    {
        return [
            'amount_achieved' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_person_id');
    }
}
