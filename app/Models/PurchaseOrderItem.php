<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baris PO — selalu berasal dari satu item Permintaan Barang (satu item boleh
 * dipecah ke beberapa PO). price = harga satuan dalam mata uang PO (tanpa kurs).
 */
class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'goods_request_item_id',
        'master_product_id',
        'part_number',
        'description',
        'qty',
        'unit',
        'price',
        'currency',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price' => 'float',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsRequestItem(): BelongsTo
    {
        return $this->belongsTo(GoodsRequestItem::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class);
    }

    /**
     * Qty yang sudah diretur ke supplier (retur selalu langsung terposting).
     */
    public function returnedQty(): int
    {
        $items = $this->relationLoaded('returnItems') ? $this->returnItems : $this->returnItems()->get();

        return (int) $items->sum('qty');
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(SupplierBillItem::class);
    }

    /**
     * Qty yang sudah ditagih supplier (tagihan selain void, draft ikut dihitung
     * agar dua draft tidak menagih barang yang sama).
     */
    public function billedQty(): int
    {
        $items = $this->relationLoaded('billItems')
            ? $this->billItems
            : $this->billItems()->with('bill')->get();

        return (int) $items->filter(fn (SupplierBillItem $item) => $item->bill && $item->bill->status !== SupplierBill::STATUS_VOID
            && $item->bill->bill_type === SupplierBill::TYPE_REGULAR && $item->line_type === SupplierBillItem::TYPE_ITEM)->sum('qty');
    }

    /**
     * 3-way match: yang boleh ditagih = sudah diterima - sudah ditagih.
     */
    public function billableQty(): int
    {
        return max(0, $this->receivedQty() - $this->billedQty());
    }

    public function goodsRequest(): ?GoodsRequest
    {
        return $this->goodsRequestItem?->goodsRequest;
    }

    /**
     * Project tujuan barang (alokasi saat diterima) — dari Permintaan Barang bertujuan project.
     */
    public function projectId(): ?int
    {
        $request = $this->goodsRequest();

        return $request && $request->isForProject() ? $request->project_id : null;
    }

    public function amount(): float
    {
        return (float) ($this->qty ?? 0) * (float) ($this->price ?? 0);
    }

    /**
     * Qty diterima bersih = penerimaan posted - retur ke supplier. Barang yang
     * diretur dianggap belum diterima (PO kembali menunggu barang pengganti).
     */
    public function receivedQty(): int
    {
        $items = $this->relationLoaded('goodsReceiptItems')
            ? $this->goodsReceiptItems
            : $this->goodsReceiptItems()->with('goodsReceipt')->get();

        $received = (int) $items->filter(fn (GoodsReceiptItem $item) => $item->goodsReceipt?->status === GoodsReceipt::STATUS_POSTED)->sum('qty');

        return max(0, $received - $this->returnedQty());
    }

    public function remainingReceiptQty(): int
    {
        return max(0, (int) ($this->qty ?? 0) - $this->receivedQty());
    }

    /**
     * Qty yang memakan kebutuhan Permintaan Barang: PO aktif = seluruh qty;
     * PO Cancelled = hanya yang sudah diterima; PO Rejected = 0.
     */
    public function committedQty(): int
    {
        $status = $this->purchaseOrder?->status;

        return match (true) {
            $status === PurchaseOrder::STATUS_REJECTED => 0,
            $status === PurchaseOrder::STATUS_CANCELLED => $this->receivedQty(),
            default => (int) ($this->qty ?? 0),
        };
    }
}
