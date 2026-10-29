<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu approval Permintaan Barang oleh satu user can_approve. Permintaan
 * menjadi Approved (boleh diproses PO) setelah GoodsRequest::REQUIRED_APPROVALS
 * user berbeda menyetujui.
 */
class GoodsRequestApproval extends Model
{
    protected $fillable = [
        'goods_request_id',
        'user_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function goodsRequest(): BelongsTo
    {
        return $this->belongsTo(GoodsRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
