<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TaskActivityAttachment extends Model
{
    protected $fillable = [
        'task_activity_id',
        'attachment_path',
        'attachment_type',
        'attachment_name',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(TaskActivity::class, 'task_activity_id');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? Storage::url($this->attachment_path) : null;
    }
}
