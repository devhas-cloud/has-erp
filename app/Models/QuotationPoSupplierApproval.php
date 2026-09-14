<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Approval "boleh lanjut PO ke supplier" (kuorum 2 user berbeda), terpisah
 * dari approval quotation itu sendiri (Quotation::STATUS_WAITING_APPROVAL ->
 * STATUS_APPROVED, lihat Quotation::finalChecker()).
 */
class QuotationPoSupplierApproval extends Model
{
    protected $fillable = [
        'quotation_id',
        'user_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
