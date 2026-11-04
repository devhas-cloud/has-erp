<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBillItem extends Model
{
    /** Baris barang dari PO (3-way match). */
    public const TYPE_ITEM = 'item';

    /** Biaya tambahan (ongkos kirim, dll.) — keterangan wajib, ikut DPP. */
    public const TYPE_CHARGE = 'charge';

    protected $fillable = [
        'supplier_bill_id',
        'line_type',
        'purchase_order_item_id',
        'direct_purchase_item_id',
        'master_product_id',
        'project_id',
        'part_number',
        'description',
        'qty',
        'unit',
        'price',
        'amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price' => 'float',
            'amount' => 'float',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
