<?php

namespace App\Models;

use App\Models\Concerns\HasPurchaseDocuments;
use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penerimaan Customer — dana masuk untuk satu invoice Approved. Customer
 * selalu membayar pas sesuai invoice, jadi nominal = total invoice dan satu
 * invoice hanya punya satu penerimaan aktif (draft/posted). Posting menandai
 * invoice Paid; void membuka kembali invoice menjadi Unpaid.
 */
class CustomerReceipt extends Model
{
    use HasPurchaseDocuments;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_VOID = 'void';

    public const ACTIVE_STATUSES = [self::STATUS_DRAFT, self::STATUS_POSTED];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_POSTED => 'Posted',
        self::STATUS_VOID => 'Void',
    ];

    public const METHOD_LABELS = SupplierPayment::METHOD_LABELS;

    protected $fillable = [
        'receipt_number',
        'invoice_id',
        'company_id',
        'receipt_date',
        'method',
        'deposit_account',
        'reference_number',
        'amount',
        'notes',
        'status',
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
            'receipt_date' => 'date',
            'amount' => 'float',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(AccountCompany::class, 'company_id');
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
        return NumberSequence::make('customer_receipts', 'receipt_number', $date);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHOD_LABELS[$this->method] ?? ucfirst((string) $this->method);
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

    /**
     * Bukti transfer menjadi arsip setelah diposting — hanya bisa ditambah.
     */
    public function canDeleteDocument(Document $document, User $user): bool
    {
        return $this->isDraft();
    }
}
