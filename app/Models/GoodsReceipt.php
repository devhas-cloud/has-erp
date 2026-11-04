<?php

namespace App\Models;

use App\Models\Concerns\HasPurchaseDocuments;
use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Penerimaan Barang (GRN) dari Purchase Order atau Direct Purchase. Satu
 * dokumen sumber boleh diterima bertahap. Posting mencatat stok masuk
 * (stock_movements 'receipt') untuk baris bertujuan gudang — beralokasi
 * project bila barang untuk project; baris langsung pakai hanya konfirmasi.
 * Dokumen yang sudah posted tidak bisa diubah/dihapus.
 */
class GoodsReceipt extends Model
{
    use HasPurchaseDocuments;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_POSTED => 'Posted',
    ];

    protected $fillable = [
        'receipt_number',
        'purchase_order_id',
        'direct_purchase_id',
        'warehouse_id',
        'received_date',
        'status',
        'note',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function directPurchase(): BelongsTo
    {
        return $this->belongsTo(DirectPurchase::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function documentTypes(): array
    {
        return self::purchaseDocumentTypes([
            Document::TYPE_DELIVERY_NOTE, Document::TYPE_SUPPLIER_INVOICE, Document::TYPE_OTHER,
        ]);
    }

    /**
     * Penerimaan langsung posted (tanpa tahap draft): koreksi salah unggah
     * hanya oleh pengunggahnya sendiri atau Admin.
     */
    public function canDeleteDocument(Document $document, User $user): bool
    {
        return $user->role === 'Admin' || (int) $document->uploaded_by === (int) $user->id;
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('goods_receipts', 'receipt_number', $date);
    }

    public function sourceNumber(): string
    {
        return $this->purchase_order_id
            ? ($this->purchaseOrder?->po_number ?: 'PO #'.$this->purchase_order_id)
            : ($this->directPurchase?->dp_number ?: 'DPO #'.$this->direct_purchase_id);
    }

    public function supplierName(): ?string
    {
        return $this->purchaseOrder?->supplier_name ?? $this->directPurchase?->supplier_name;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function statusBadgeHtml(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => '<span class="status-badge" style="background:var(--info-soft);color:#1e40af;">Draft</span>',
            self::STATUS_POSTED => '<span class="status-badge status-active">Posted</span>',
            default => '<span class="status-badge">'.ucfirst($this->status).'</span>',
        };
    }
}
