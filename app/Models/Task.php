<?php

namespace App\Models;

use App\Traits\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use Notifiable;

    protected $fillable = [
        'creator_id',
        'lead_id',
        'activity_id',
        'category_id',
        'whatsapp_group_id',
        'title',
        'description',
        'due_date',
        'time',
        'status',
        'requires_approval',
        'alert_type',
        'alert_target',
        'alert_time',
        'is_alert_sent',
    ];

    protected $casts = [
        'due_date' => 'date',
        'alert_time' => 'datetime',
        'requires_approval' => 'boolean',
        'is_alert_sent' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'category_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function whatsappGroup(): BelongsTo
    {
        return $this->belongsTo(WhatsAppGroup::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function sourceActivity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
