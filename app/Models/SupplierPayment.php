<?php

namespace App\Models;

use App\Models\Concerns\HasPurchaseDocuments;
use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pembayaran Supplier — dana keluar, dialokasikan ke tagihan terverifikasi
 * supplier yang sama (boleh sebagian / banyak tagihan). Hanya pembayaran
 * Posted yang mengurangi hutang; void membuka kembali sisa tagihan.
 * amount_base = amount x exchange_rate (kurs saat bayar) untuk laporan Rupiah.
 */
class SupplierPayment extends Model
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

    public const METHOD_LABELS = [
        'transfer' => 'Transfer Bank',
        'cash' => 'Tunai / Kas',
        'giro' => 'Giro / Cek',
    ];

    protected $fillable = [
        'payment_number',
        'supplier_id',
        'payment_date',
        'method',
        'source_account',
        'bank_name',
        'bank_account_no',
        'bank_account_name',
        'reference_number',
        'currency',
        'amount',
        'exchange_rate',
        'amount_base',
        'bank_charge',
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
            'payment_date' => 'date',
            'amount' => 'float',
            'exchange_rate' => 'float',
            'amount_base' => 'float',
            'bank_charge' => 'float',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
        return NumberSequence::make('supplier_payments', 'payment_number', $date);
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
