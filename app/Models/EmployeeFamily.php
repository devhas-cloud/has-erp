<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFamily extends Model
{
    protected $fillable = [
        'employee_id',
        'name',
        'relation',
        'birth_date',
        'occupation',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
