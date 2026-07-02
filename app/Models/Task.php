<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'creator_id',
        'category_id',
        'division_id',
        'title',
        'description',
        'start_date',
        'due_date',
        'status',
        'requires_approval',
        'alert_type',
        'alert_target',
        'alert_time',
        'is_alert_sent',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'due_date' => 'datetime',
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
}
