<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LossReason extends Model
{
    protected $fillable = [
        'reason_name',
        'description',
        'status',
    ];
}
