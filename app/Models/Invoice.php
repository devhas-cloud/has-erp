<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const APPROVAL_DRAFT = 'draft';

    public const APPROVAL_WAITING_APPROVAL = 'waiting_approval';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    public const APPROVAL_STATUS_LABELS = [
        self::APPROVAL_DRAFT => 'Draft',
        self::APPROVAL_WAITING_APPROVAL => 'Waiting Approval',
        self::APPROVAL_APPROVED => 'Approved',
        self::APPROVAL_REJECTED => 'Rejected',
    ];

    public const DEFAULT_TERMS = 14;

    public const DEFAULT_PERCENT_PAYMENT = 5;

    public const DEFAULT_SIGNER_NAME = 'Limaran Madu Ratri';

    public const DEFAULT_SIGNER_POSITION = 'Finance Director';

    public const STATUS_LABELS = [
        self::STATUS_UNPAID => 'Unpaid',
        self::STATUS_PAID => 'Paid',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'project_id',
        'invoice_number',
        'invoice_date',
        'ship_to',
        'date_shipped',
        'shipping_method',
        'do_number',
        'terms',
        'due_date',
        'date_payment',
        'reference_number',
        'subtotal',
        'discount_percent',
        'discount_amount',
        'noted',
        'signer_name',
        'signer_position',
        'percent_payment',
        'amount_payment',
        'ppn',
        'total_payment',
        'status',
        'approval_status',
        'final_checked_by',
        'approval_note',
        'approved_at',
        'rejected_at',
        'paid_by',
        'cancel_reason',
        'cancelled_at',
        'cancelled_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'date_shipped' => 'date',
            'due_date' => 'date',
            'date_payment' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'float',
            'discount_percent' => 'float',
            'discount_amount' => 'float',
            'percent_payment' => 'float',
            'amount_payment' => 'float',
            'ppn' => 'float',
            'total_payment' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function finalChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_checked_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Pembayaran & pembatalan hanya untuk invoice yang sudah Approved dan belum dibayar.
     */
    public function canRecordPayment(): bool
    {
        return $this->isApproved() && $this->status === self::STATUS_UNPAID;
    }

    public function canBeCancelled(): bool
    {
        return $this->isApproved() && $this->status === self::STATUS_UNPAID;
    }

    /**
     * Nilai setelah diskon dan sebelum PPN (DPP), menjadi dasar persentase pembayaran.
     */
    public function getNetSubtotalAttribute(): float
    {
        return round((float) $this->subtotal - (float) $this->discount_amount, 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function getApprovalStatusLabelAttribute(): string
    {
        return self::APPROVAL_STATUS_LABELS[$this->approval_status] ?? ucfirst($this->approval_status);
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }

    public function canBeModified(): bool
    {
        return in_array($this->approval_status, [self::APPROVAL_DRAFT, self::APPROVAL_REJECTED], true);
    }

    public function statusBadgeHtml(): string
    {
        return match ($this->status) {
            self::STATUS_UNPAID => '<span class="status-badge" style="background:#fef3c7;color:#92400e;">Unpaid</span>',
            self::STATUS_PAID => '<span class="status-badge status-active">Paid</span>',
            self::STATUS_CANCELLED => '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Cancelled</span>',
            default => '<span class="status-badge">'.ucfirst($this->status).'</span>',
        };
    }

    public function approvalBadgeHtml(): string
    {
        return match ($this->approval_status) {
            self::APPROVAL_DRAFT => '<span class="status-badge" style="background:var(--info-soft);color:#1e40af;">Draft</span>',
            self::APPROVAL_WAITING_APPROVAL => '<span class="status-badge" style="background:#fef3c7;color:#92400e;">Waiting Approval</span>',
            self::APPROVAL_APPROVED => '<span class="status-badge status-active">Approved</span>',
            self::APPROVAL_REJECTED => '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Rejected</span>',
            default => '<span class="status-badge">'.ucfirst($this->approval_status).'</span>',
        };
    }

    /**
     * Nomor invoice: YYMM + 4 digit urut.
     * Urutan dimulai dari 0001 dan reset setiap tahun.
     * Contoh: 26100001 untuk invoice pertama Oktober 2026.
     */
    public static function generateInvoiceNumber(?string $date = null): string
    {
        return NumberSequence::make('invoices', 'invoice_number', $date);
    }

    public static function formatNumber($value, int $decimals = 2): string
    {
        $formatted = number_format((float) $value, $decimals, '.', ',');

        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted;
    }

    public static function formatMoney($value, int $decimals = 2): string
    {
        return 'Rp '.self::formatNumber($value, $decimals);
    }
}
