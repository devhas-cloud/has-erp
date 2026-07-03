<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TaskAssignee extends Pivot
{
    protected $table = 'task_assignees';

    protected $casts = [
        'assigned_at' => 'datetime',
    ];
}
