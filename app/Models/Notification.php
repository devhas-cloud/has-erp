<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'body',
        'notifiable_type', 'notifiable_id', 'data', 'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getGroupKeyAttribute(): string
    {
        if ($taskId = $this->data['task_id'] ?? null) {
            return 'task_'.$taskId;
        }
        if ($leadId = $this->data['lead_id'] ?? null) {
            return 'lead_'.$leadId;
        }

        return str_replace('\\', '_', $this->notifiable_type).'_'.$this->notifiable_id;
    }

    public function getGroupTypeAttribute(): string
    {
        if ($this->data['task_id'] ?? null) {
            return 'task';
        }
        if ($this->data['lead_id'] ?? null) {
            return 'lead';
        }

        return 'other';
    }

    public function getGroupIdAttribute(): ?int
    {
        return $this->data['task_id'] ?? $this->data['lead_id'] ?? null;
    }

    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }
}
