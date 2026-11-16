<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Branch Transfer (BT-) — pengiriman barang antar branch dengan surat jalan.
 *
 *   draft      : disusun branch asal, bisa diubah/dihapus.
 *   in_transit : dikirim — baris stok keluar dari gudang asal (log ship_out) dan
 *                tercatat "dalam pengiriman"; surat jalan bisa dicetak.
 *   received   : branch tujuan memeriksa SEMUA barang (qty & kondisi harus
 *                sesuai), memilih gudang tujuan; barang masuk (log ship_in).
 *                Bila ada yang tidak sesuai, penerimaan tidak bisa diselesaikan.
 */
class BranchTransfer extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_IN_TRANSIT => 'Dalam Pengiriman',
        self::STATUS_RECEIVED => 'Diterima',
    ];

    protected $fillable = [
        'transfer_number',
        'transfer_date',
        'from_branch_id',
        'from_warehouse_id',
        'to_branch_id',
        'to_warehouse_id',
        'status',
        'courier',
        'vehicle_no',
        'driver_name',
        'note',
        'receive_note',
        'created_by',
        'shipped_by',
        'shipped_at',
        'received_by',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BranchTransferItem::class)->orderBy('sort_order');
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('branch_transfers', 'transfer_number', $date);
    }

    /**
     * Dokumen yang boleh dilihat user: user ber-branch hanya asal/tujuan branch-nya.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->branch_id
            ? $query->where(fn (Builder $q) => $q->where('from_branch_id', $user->branch_id)->orWhere('to_branch_id', $user->branch_id))
            : $query;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isInTransit(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    /** Pengirim: user branch asal atau user tanpa branch. */
    public function canBeSentBy(User $user): bool
    {
        return $user->canAccessBranch($this->from_branch_id);
    }

    /** Penerima: user branch tujuan atau user tanpa branch. */
    public function canBeReceivedBy(User $user): bool
    {
        return $user->canAccessBranch($this->to_branch_id);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadgeHtml(): string
    {
        [$bg, $fg] = match ($this->status) {
            self::STATUS_IN_TRANSIT => ['#fef3c7', '#92400e'],
            self::STATUS_RECEIVED => ['var(--accent-soft)', 'var(--accent)'],
            default => ['#e5e7eb', '#374151'],
        };

        return '<span class="status-badge" style="background:'.$bg.';color:'.$fg.';">'.e($this->status_label).'</span>';
    }
}
