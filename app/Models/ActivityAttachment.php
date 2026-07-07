<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ActivityAttachment extends Model
{
    protected $fillable = [
        'activity_id',
        'attachment_path',
        'attachment_type',
        'attachment_name',
    ];

    protected $appends = ['attachment_url'];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? Storage::url($this->attachment_path) : null;
    }
}
