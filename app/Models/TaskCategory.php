<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskCategory extends Model
{
    protected $table = 'task_categories';

    protected $fillable = [
        'name',
        'description',
        'division_id',
        'use_division_handler',
    ];

    protected $casts = [
        'use_division_handler' => 'boolean',
    ];

    public function setUseDivisionHandlerAttribute($value): void
    {
        $this->attributes['use_division_handler'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'category_id');
    }
}
