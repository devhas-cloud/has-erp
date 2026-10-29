<?php

namespace App\Models;

use App\Support\ModuleAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Approval memakai kuorum: permintaan Waiting Approval menjadi Approved (boleh
 * diproses PO) setelah REQUIRED_APPROVALS user can_approve BERBEDA menyetujui
 * (pola sama dengan PO Supplier Approval). Satu reject dari approver mana pun
 * langsung menolak permintaan.
 *
 * Visibilitas (scopeVisibleTo):
 *   - Admin, atau user divisi Finance yang punya can_approve -> semua permintaan;
 *   - user lain (dengan/tanpa can_approve) -> permintaan divisinya, ditambah
 *     buatannya sendiri (untuk user tanpa divisi).
 * Karena approve memakai data yang terlihat, approver non-Finance hanya bisa
 * menyetujui permintaan divisinya.
 */
class GoodsRequest extends Model
{
    public const MODULE_CODE = 'MOD_GOODS_REQUEST';

    public const REQUIRED_APPROVALS = 2;

    /**
     * Nama divisi yang approver-nya boleh melihat semua permintaan. Dicocokkan
     * lewat nama (case-insensitive) karena master divisi memiliki lebih dari
     * satu baris "Finance".
     */
    public const FINANCE_DIVISION_NAME = 'Finance';

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

    protected $fillable = [
        'project_id',
        'opportunity_id',
        'quotation_id',
        'division_id',
        'status',
        'notes',
        'created_by',
        'final_checked_by',
        'approval_note',
        'approved_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_checked_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(GoodsRequestApproval::class)->orderBy('approved_at')->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsRequestItem::class)->orderBy('sort_order');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (self::canSeeAll($user)) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id);
            if ($user->division_id) {
                $q->orWhere('division_id', $user->division_id);
            }
        });
    }

    public static function canSeeAll(User $user): bool
    {
        if ($user->role === 'Admin') {
            return true;
        }

        $divisionName = strtolower(trim((string) $user->division?->division_name));

        return $divisionName === strtolower(self::FINANCE_DIVISION_NAME) && self::isApprover($user);
    }

    public static function isApprover(User $user): bool
    {
        return ModuleAccess::for($user)->module(self::MODULE_CODE)->canApprove();
    }

    public function hasApprovalFrom(User $user): bool
    {
        return $this->relationLoaded('approvals')
            ? $this->approvals->contains('user_id', $user->id)
            : $this->approvals()->where('user_id', $user->id)->exists();
    }

    /**
     * Tombol Approve/Reject tampil untuk approver yang belum ikut menyetujui
     * permintaan yang sedang menunggu approval.
     */
    public function canBeApprovedBy(User $user): bool
    {
        return $this->status === self::STATUS_WAITING_APPROVAL
            && self::isApprover($user)
            && ! $this->hasApprovalFrom($user);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function statusBadgeHtml(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => '<span class="status-badge" style="background:var(--info-soft);color:#1e40af;">Draft</span>',
            self::STATUS_WAITING_APPROVAL => '<span class="status-badge" style="background:#fef3c7;color:#92400e;">Waiting Approval</span>',
            self::STATUS_APPROVED => '<span class="status-badge status-active">Approved</span>',
            self::STATUS_REJECTED => '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Rejected</span>',
            default => '<span class="status-badge">'.ucfirst($this->status).'</span>',
        };
    }

    /**
     * Sudah semua item permintaan ini masuk ke suatu Purchase Order (lintas
     * PO manapun, lihat GoodsRequestItem::isOrdered()).
     */
    public function isFullyOrdered(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }

        return $this->items->every(fn (GoodsRequestItem $item) => $item->isOrdered());
    }
}
