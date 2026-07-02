<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskActivity extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'content',
        'reply_to_id',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskActivityAttachment::class, 'task_activity_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(TaskActivity::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TaskActivity::class, 'reply_to_id');
    }
}
