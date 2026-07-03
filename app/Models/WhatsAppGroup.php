<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppGroup extends Model
{
    protected $table = 'whatsapp_groups';

    protected $fillable = [
        'division_id',
        'group_name',
        'group_id',
        'description',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
