<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    protected $fillable = [
        'stage_name',
        'description',
        'status',
        'probability',
    ];

    protected $casts = [
        'probability' => 'integer',
    ];
}
