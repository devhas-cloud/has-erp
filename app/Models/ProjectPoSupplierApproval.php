<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Approval "boleh lanjut PO ke supplier" (kuorum 2 user berbeda), terpisah
 * dari approval quotation itu sendiri dan dari approval project manapun.
 * Menempel ke project karena PO customer diupload saat project dibuat.
 */
class ProjectPoSupplierApproval extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
