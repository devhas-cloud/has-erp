<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris retur. destination 'warehouse' = dari baris stok (stock_id) hasil
 * penerimaan PO; 'direct' = barang langsung pakai (tanpa stok).
 */
class SupplierReturnItem extends Model
{
    protected $fillable = [
        'supplier_return_id',
        'purchase_order_item_id',
        'stock_id',
        'master_product_id',
        'project_id',
        'qty',
        'unit',
        'destination',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
        ];
    }

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
