<?php

namespace App\Models;

use App\Support\NumberSequence;
use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use Loggable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const TYPE_PROJECT = 'project';

    public const TYPE_RETAIL = 'retail';

    public const TYPE_LABELS = [
        self::TYPE_PROJECT => 'Project',
        self::TYPE_RETAIL => 'Retail',
    ];

    protected $fillable = [
        'project_number',
        'project_name',
        'project_description',
        'quotation_id',
        'company_id',
        'po_number_company',
        'po_date',
        'po_date_received',
        'po_due_date',
        'total_sale_amount',
        'net_sale_amount',
        'is_retention',
        'type_of_achievement',
        'status',
        'po_document_path',
        'po_document_name',
        'po_uploaded_by',
        'po_uploaded_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'po_date' => 'date',
            'po_date_received' => 'date',
            'po_due_date' => 'date',
            'is_retention' => 'boolean',
            'total_sale_amount' => 'float',
            'net_sale_amount' => 'float',
            'po_uploaded_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(AccountCompany::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function poUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'po_uploaded_by');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(ProjectAchievement::class)->orderBy('id');
    }

    public function salesAchievements(): HasMany
    {
        return $this->hasMany(SalesAchievement::class)->orderBy('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderBy('invoice_date')->orderBy('id');
    }

    /**
     * Approval "boleh lanjut PO ke supplier" (modul PO Supplier Approval),
     * BUKAN approval quotation (lihat Quotation::finalChecker()).
     */
    public function poSupplierApprovals(): HasMany
    {
        return $this->hasMany(ProjectPoSupplierApproval::class);
    }

    /**
     * Purchasing boleh lanjut PO barang ke supplier = kuorum 2 approver
     * berbeda di modul PO Supplier Approval sudah terpenuhi.
     */
    public function isReadyForSupplierPo(): bool
    {
        return $this->poSupplierApprovals()->count() >= 2;
    }

    public function opportunity(): ?Opportunity
    {
        return $this->quotation?->opportunity;
    }

    public function hasPoDocument(): bool
    {
        return ! empty($this->po_document_path);
    }

    /**
     * Format angka project: 1000000 -> "1,000,000"; 1000000.5 -> "1,000,000.5".
     */
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

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type_of_achievement] ?? ucfirst($this->type_of_achievement);
    }

    public function statusBadgeHtml(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => '<span class="status-badge status-active">Active</span>',
            self::STATUS_COMPLETED => '<span class="status-badge" style="background:var(--info-soft);color:#1e40af;">Completed</span>',
            self::STATUS_CANCELLED => '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Cancelled</span>',
            default => '<span class="status-badge">'.ucfirst($this->status).'</span>',
        };
    }

    /**
     * Nomor project: YYMM + 4 digit urut (format sama dengan invoice).
     * Urutan dimulai dari 0001 dan reset setiap tahun.
     * Contoh: 26100001 untuk project pertama Oktober 2026.
     */
    public function generateProjectNumber(): string
    {
        return NumberSequence::make('projects', 'project_number');
    }
}
