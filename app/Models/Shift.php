<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'name',
        'clock_in_time',
        'clock_out_time',
        'tolerance_minutes',
    ];

    protected $casts = [
        'clock_in_time' => 'datetime:H:i:s',
        'clock_out_time' => 'datetime:H:i:s',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
