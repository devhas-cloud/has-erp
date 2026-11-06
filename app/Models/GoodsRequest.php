<?php

namespace App\Models;

use App\Support\ModuleAccess;
use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Approval cukup oleh 1 user can_approve (REQUIRED_APPROVALS): permintaan Waiting
 * Approval langsung Approved (boleh diproses PO). Pembuat yang punya can_approve
 * boleh menyetujui permintaannya sendiri (keputusan 2026-11-06). Satu reject dari
 * approver mana pun langsung menolak permintaan.
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

    /**
     * Tujuan permintaan: 'project' (terikat project, barang dialokasikan ke project)
     * atau 'internal' (stok / kebutuhan internal, tanpa project).
     */
    public const PURPOSE_PROJECT = 'project';

    public const PURPOSE_INTERNAL = 'internal';

    public const PURPOSE_LABELS = [
        self::PURPOSE_PROJECT => 'Project',
        self::PURPOSE_INTERNAL => 'Stok / Internal',
    ];

    /** Jumlah approver yang dibutuhkan (dulu 2, kini 1). */
    public const REQUIRED_APPROVALS = 1;

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
        'request_number',
        'purpose',
        'project_id',
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

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('goods_requests', 'request_number', $date);
    }

    /**
     * Nomor untuk ditampilkan (fallback #id bila belum bernomor).
     */
    public function getNumberLabelAttribute(): string
    {
        return $this->request_number ?: '#'.$this->id;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Quotation & opportunity diturunkan lewat project (tidak disimpan di permintaan).
     */
    public function quotation(): ?Quotation
    {
        return $this->project?->quotation;
    }

    public function opportunity(): ?Opportunity
    {
        return $this->project?->quotation?->opportunity;
    }

    public function isForProject(): bool
    {
        return $this->purpose === self::PURPOSE_PROJECT && $this->project_id;
    }

    public function getPurposeLabelAttribute(): string
    {
        return self::PURPOSE_LABELS[$this->purpose] ?? ucfirst((string) $this->purpose);
    }

    /**
     * Label tujuan untuk tampilan: nomor & nama project, atau "Stok / Internal".
     */
    public function destinationLabel(): string
    {
        return $this->isForProject() && $this->project
            ? $this->project->project_number.' — '.$this->project->project_name
            : self::PURPOSE_LABELS[self::PURPOSE_INTERNAL];
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
     * Status pemenuhan dokumen: none (belum diproses) | partial | done.
     * Hanya bermakna untuk permintaan Approved; butuh items + FULFILLMENT_RELATIONS.
     */
    public function fulfillmentStatus(): string
    {
        if ($this->items->isEmpty()) {
            return 'none';
        }

        $statuses = $this->items->map(fn (GoodsRequestItem $item) => $item->fulfillmentStatus())->unique();

        return match (true) {
            $statuses->every(fn ($s) => $s === 'done') => 'done',
            $statuses->every(fn ($s) => $s === 'none') => 'none',
            default => 'partial',
        };
    }

    public const FULFILLMENT_LABELS = [
        'none' => 'Belum diproses',
        'partial' => 'Sebagian',
        'done' => 'Terpenuhi',
    ];
}
