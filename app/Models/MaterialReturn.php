<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Retur Material Produksi — produksi mengajukan pengembalian material sisa
 * (belum terpakai) ke gudang asal sebagai stok bebas. Disimpan = Menunggu
 * Approval (stok belum bergerak); approver (can_approve) menyetujui -> stok
 * langsung masuk, atau menolak. Branch = branch material.
 */
class MaterialReturn extends Model
{
    public const MODULE_CODE = 'MOD_MATERIAL_RETURN';

    public const STATUS_WAITING_APPROVAL = 'waiting_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_WAITING_APPROVAL => 'Menunggu Approval',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_REJECTED => 'Ditolak',
    ];

    protected $fillable = [
        'return_number',
        'branch_id',
        'return_date',
        'status',
        'note',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('material_returns', 'return_number', $date);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialReturnItem::class)->orderBy('id');
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

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->branch_id ? $query->where('branch_id', $user->branch_id) : $query;
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
            self::STATUS_WAITING_APPROVAL => ['#fef3c7', '#92400e'],
            self::STATUS_APPROVED => ['var(--accent-soft)', 'var(--accent)'],
            self::STATUS_REJECTED => ['var(--danger-soft)', '#7f1d1d'],
            default => ['#e5e7eb', '#374151'],
        };

        return '<span class="status-badge" style="background:'.$bg.';color:'.$fg.';">'.e($this->status_label).'</span>';
    }

    public function totalValue(): float
    {
        return round($this->items->sum(fn (MaterialReturnItem $item) => $item->value()), 2);
    }
}
