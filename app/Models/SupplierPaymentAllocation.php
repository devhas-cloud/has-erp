<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPaymentAllocation extends Model
{
    protected $fillable = [
        'supplier_payment_id',
        'supplier_bill_id',
        'amount',
        'amount_base_book',
        'amount_base_paid',
        'fx_difference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'amount_base_book' => 'float',
            'amount_base_paid' => 'float',
            'fx_difference' => 'float',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }
}
