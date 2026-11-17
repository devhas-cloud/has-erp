<?php

namespace App\Models;

use App\Support\ModuleAccess;
use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Material Request — kebutuhan material project untuk produksi di satu branch.
 *
 * Tanpa draft: disimpan = Menunggu Approval (masih boleh diubah / dihapus) ->
 * Disetujui (1 approver can_approve) atau Ditolak (boleh diperbaiki lalu
 * otomatis diajukan ulang) -> Selesai (otomatis saat semua item diserahkan,
 * atau ditutup manual bila sisanya tidak dilanjutkan).
 *
 * User ber-branch hanya melihat MR branch-nya.
 */
class MaterialRequest extends Model
{
    public const MODULE_CODE = 'MOD_MATERIAL_REQUEST';

    public const STATUS_WAITING_APPROVAL = 'waiting_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_LABELS = [
        self::STATUS_WAITING_APPROVAL => 'Menunggu Approval',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_COMPLETED => 'Selesai',
    ];

    protected $fillable = [
        'request_number',
        'project_id',
        'branch_id',
        'request_date',
        'status',
        'note',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'reject_reason',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('material_requests', 'request_number', $date);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequestItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(MaterialHandover::class)->orderBy('id');
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

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->branch_id ? $query->where('branch_id', $user->branch_id) : $query;
    }

    public static function isApprover(User $user): bool
    {
        return ModuleAccess::for($user)->module(self::MODULE_CODE)->canApprove();
    }

    /** Masih boleh diubah / dihapus (belum disetujui). */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_WAITING_APPROVAL, self::STATUS_REJECTED], true);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** Sisa permintaan seluruh item (0 = semua sudah diserahkan). */
    public function remainingQty(): float
    {
        return round($this->items->sum(fn (MaterialRequestItem $item) => $item->remainingQty()), 4);
    }

    /** Disetujui & semua item sudah diserahkan -> Selesai otomatis. */
    public function completeIfFulfilled(): void
    {
        $this->load('items.handoverItems');
        if ($this->isApproved() && $this->remainingQty() <= 0) {
            $this->update(['status' => self::STATUS_COMPLETED]);
        }
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadgeHtml(): string
    {
        [$bg, $fg] = match ($this->status) {
            self::STATUS_WAITING_APPROVAL => ['#fef3c7', '#92400e'],
            self::STATUS_APPROVED => ['var(--accent-soft)', 'var(--accent)'],
            self::STATUS_REJECTED => ['var(--danger-soft)', '#7f1d1d'],
            default => ['#e5e7eb', '#374151'],
        };

        return '<span class="status-badge" style="background:'.$bg.';color:'.$fg.';">'.e($this->status_label).'</span>';
    }
}
