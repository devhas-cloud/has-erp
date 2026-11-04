<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsRequestItem extends Model
{
    protected $fillable = [
        'goods_request_id',
        'quote_configuration_item_id',
        'master_product_id',
        'part_number',
        'description',
        'qty',
        'closed_qty',
        'closed_reason',
        'closed_by',
        'closed_at',
        'unit',
        'currency',
        'sort_order',
    ];

    /**
     * Kode mata uang item (huruf besar); kosong -> mata uang dasar (IDR).
     */
    public static function normalizeCurrency(?string $currency): string
    {
        $currency = strtoupper(trim((string) $currency));

        return $currency !== '' ? $currency : once(fn () => Currency::baseName());
    }

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'closed_qty' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    public function goodsRequest(): BelongsTo
    {
        return $this->belongsTo(GoodsRequest::class);
    }

    public function quoteConfigurationItem(): BelongsTo
    {
        return $this->belongsTo(QuoteConfigurationItem::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Baris PO yang memakai item ini — satu item boleh dipecah ke beberapa PO.
     */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Pergerakan stok yang memenuhi item ini dari stok bebas (keluar stok
     * internal / alokasi ke project).
     */
    public function stockFulfillmentMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'goods_request_item_id')
            ->whereIn('type', StockMovement::FULFILLMENT_TYPES);
    }

    /**
     * Qty yang dipesan lewat PO yang masih berlaku (lihat PurchaseOrderItem::committedQty()).
     */
    public function orderedQty(): int
    {
        $lines = $this->relationLoaded('purchaseOrderItems')
            ? $this->purchaseOrderItems
            : $this->purchaseOrderItems()->with(['purchaseOrder', 'goodsReceiptItems.goodsReceipt'])->get();

        return (int) $lines->sum(fn (PurchaseOrderItem $line) => $line->committedQty());
    }

    /**
     * Qty yang dipenuhi dari stok (keluar stok / alokasi).
     */
    public function stockQty(): int
    {
        $movements = $this->relationLoaded('stockFulfillmentMovements')
            ? $this->stockFulfillmentMovements
            : $this->stockFulfillmentMovements()->get();

        return (int) $movements->sum('qty');
    }

    /**
     * Total terpenuhi: dari stok + PO berlaku + qty yang ditutup.
     */
    public function fulfilledQty(): int
    {
        return $this->stockQty() + $this->orderedQty() + (int) $this->closed_qty;
    }

    /**
     * Sisa kebutuhan yang masih bisa dipenuhi (stok atau PO).
     */
    public function remainingQty(): int
    {
        return max(0, (int) ($this->qty ?? 0) - $this->fulfilledQty());
    }

    public function isFulfilled(): bool
    {
        return $this->remainingQty() <= 0;
    }

    /**
     * Status pemenuhan: none | partial | done.
     */
    public function fulfillmentStatus(): string
    {
        $remaining = $this->remainingQty();

        return match (true) {
            $remaining <= 0 => 'done',
            $remaining < (int) $this->qty => 'partial',
            default => 'none',
        };
    }

    /**
     * Relasi yang perlu di-eager-load agar perhitungan pemenuhan tanpa N+1.
     */
    public const FULFILLMENT_RELATIONS = [
        'purchaseOrderItems.purchaseOrder',
        'purchaseOrderItems.goodsReceiptItems.goodsReceipt',
        'stockFulfillmentMovements',
    ];
}
