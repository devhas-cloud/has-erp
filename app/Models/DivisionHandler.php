<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DivisionHandler extends Model
{
    protected $table = 'division_handlers';

    protected $fillable = [
        'division_id',
        'user_id',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
