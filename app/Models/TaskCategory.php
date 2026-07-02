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
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'category_id');
    }
}
