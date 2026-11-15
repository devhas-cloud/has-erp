<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitBaseline extends Model
{
    protected $fillable = ['user_id', 'start_date', 'end_date', 'visits', 'note'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'visits' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
