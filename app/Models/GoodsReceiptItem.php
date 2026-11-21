<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baris Penerimaan Barang dari baris PO atau baris Direct Purchase.
 * destination: 'warehouse' (masuk gudang -> stok bertambah saat posting) atau
 * 'direct' (langsung pakai -> hanya konfirmasi, tidak masuk kartu stok).
 * project_id: alokasi project (dari Permintaan Barang project / DP project).
 * Qty dari PO/DP selalu bilangan bulat. serial_numbers = SN yang diisi saat
 * terima (boleh sebagian/kosong); baris stok yang terbentuk ada di stocks().
 */
class GoodsReceiptItem extends Model
{
    public const DESTINATION_WAREHOUSE = 'warehouse';

    public const DESTINATION_DIRECT = 'direct';

    public const DESTINATION_LABELS = [
        self::DESTINATION_WAREHOUSE => 'Masuk Gudang',
        self::DESTINATION_DIRECT => 'Langsung Pakai',
    ];

    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'direct_purchase_item_id',
        'master_product_id',
        'qty',
        'unit',
        'currency',
        'price',
        'exchange_rate',
        'destination',
        'project_id',
        'batch_number',
        'expiry_date',
        'warranty_until',
        'serial_numbers',
        'note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price' => 'float',
            'exchange_rate' => 'float',
            'expiry_date' => 'date',
            'warranty_until' => 'date',
            'serial_numbers' => 'array',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function directPurchaseItem(): BelongsTo
    {
        return $this->belongsTo(DirectPurchaseItem::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Baris stok asli hasil penerimaan ini (tanpa pecahan transfer/alokasi, lihat parent_id).
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'source_id')->where('source_type', Stock::SOURCE_GOODS_RECEIPT_ITEM)->whereNull('parent_id')->orderBy('id');
    }

    public function sourceLine(): PurchaseOrderItem|DirectPurchaseItem|null
    {
        return $this->purchaseOrderItem ?? $this->directPurchaseItem;
    }

    public function getDestinationLabelAttribute(): string
    {
        return self::DESTINATION_LABELS[$this->destination] ?? ucfirst((string) $this->destination);
    }
}
