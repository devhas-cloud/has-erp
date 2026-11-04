<?php

namespace App\Models;

use App\Models\Concerns\HasPurchaseDocuments;
use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penerimaan Refund dari supplier — uang muka PO yang tidak terpakai (PO batal
 * atau barang kurang) dikembalikan. Hanya refund Posted yang mengurangi saldo
 * uang muka PO; nominal tidak boleh melebihi refund yang jatuh tempo.
 */
class SupplierRefund extends Model
{
    use HasPurchaseDocuments;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_VOID = 'void';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_POSTED => 'Posted',
        self::STATUS_VOID => 'Void',
    ];

    protected $fillable = [
        'refund_number',
        'supplier_id',
        'purchase_order_id',
        'refund_date',
        'method',
        'destination_account',
        'reference_number',
        'currency',
        'amount',
        'exchange_rate',
        'amount_base',
        'amount_base_book',
        'fx_difference',
        'status',
        'notes',
        'created_by',
        'posted_by',
        'posted_at',
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'refund_date' => 'date',
            'amount' => 'float',
            'exchange_rate' => 'float',
            'amount_base' => 'float',
            'amount_base_book' => 'float',
            'fx_difference' => 'float',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('supplier_refunds', 'refund_number', $date);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function getMethodLabelAttribute(): string
    {
        return SupplierPayment::METHOD_LABELS[$this->method] ?? ucfirst((string) $this->method);
    }

    public function statusBadgeHtml(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => '<span class="status-badge" style="background:var(--info-soft);color:#1e40af;">Draft</span>',
            self::STATUS_POSTED => '<span class="status-badge status-active">Posted</span>',
            self::STATUS_VOID => '<span class="status-badge" style="background:#f3f4f6;color:#4b5563;">Void</span>',
            default => '<span class="status-badge">'.e($this->status).'</span>',
        };
    }

    public function documentTypes(): array
    {
        return self::purchaseDocumentTypes([Document::TYPE_PAYMENT_PROOF, Document::TYPE_OTHER]);
    }

    public function canDeleteDocument(Document $document, User $user): bool
    {
        return $this->isDraft();
    }
}
