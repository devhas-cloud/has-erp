<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * POS (Point of Sale = Sales Order = penjualan): dokumen penjualan datar yang
 * menjembatani Quotation -> Project. POS wajib berbasis quotation; customer &
 * syarat DP (requires_dp) ikut quotation. Item datar, deskripsi = penamaan customer.
 * Disimpan = Draft, lalu diajukan (Waiting Approval), disetujui/ditolak.
 * Project dibuat dari POS setelah syarat DP terpenuhi.
 */
class Pos extends Model
{
    public const MODULE_CODE = 'MOD_POS';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_WAITING_APPROVAL = 'waiting_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_WAITING_APPROVAL => 'Waiting Approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
    ];

    protected $table = 'pos';

    protected $fillable = [
        'pos_number',
        'quotation_id',
        'company_id',
        'date',
        'requires_dp',
        'subtotal',
        'discount_percent',
        'discount_amount',
        'ppn_percent',
        'ppn_amount',
        'grand_total',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'reject_reason',
        'project_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'requires_dp' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'subtotal' => 'float',
            'discount_percent' => 'float',
            'discount_amount' => 'float',
            'ppn_percent' => 'float',
            'ppn_amount' => 'float',
            'grand_total' => 'float',
        ];
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('pos', 'pos_number', $date);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(AccountCompany::class, 'company_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isWaitingApproval(): bool
    {
        return $this->status === self::STATUS_WAITING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadgeHtml(): string
    {
        [$bg, $fg] = match ($this->status) {
            self::STATUS_DRAFT => ['var(--info-soft)', '#1e40af'],
            self::STATUS_WAITING_APPROVAL => ['#fef3c7', '#92400e'],
            self::STATUS_APPROVED => ['var(--accent-soft)', 'var(--accent)'],
            self::STATUS_REJECTED => ['var(--danger-soft)', '#7f1d1d'],
            default => ['#e5e7eb', '#374151'],
        };

        return '<span class="status-badge" style="background:'.$bg.';color:'.$fg.';">'.e($this->status_label).'</span>';
    }
}
